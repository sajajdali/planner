<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_code_recreates_a_missing_phone_user_and_logs_them_in(): void
    {
        $response = $this->withSession(['_token' => 'test-csrf-token'])
            ->postJson('/api/phone-login', [
                'phone' => '09120000000',
                'code' => '9990',
            ], ['X-CSRF-TOKEN' => 'test-csrf-token']);

        $response->assertOk()
            ->assertJsonPath('user.phone', '09120000000');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['phone' => '09120000000']);
    }

    public function test_any_new_phone_can_register_then_log_back_in_after_logout(): void
    {
        $phone = '09121112233';

        $this->postJson('/api/phone-code', [
            'phone' => $phone,
            'mode' => 'register',
        ])->assertOk();

        $this->postJson('/api/phone-code/verify', [
            'phone' => $phone,
            'code' => '9990',
            'mode' => 'register',
        ])->assertOk()->assertJsonPath('verified', true);

        $this->postJson('/api/phone-register', [
            'phone' => $phone,
            'code' => '9990',
            'name' => 'کاربر آزمایشی',
        ])->assertOk()->assertJsonPath('user.phone', $phone);

        $this->assertAuthenticated();
        $userId = User::query()->where('phone', $phone)->value('id');

        $logout = $this->postJson('/api/logout');
        $logout->assertOk()->assertJsonStructure(['csrf_token']);
        $this->assertGuest();

        $this->postJson('/api/phone-login', [
            'phone' => $phone,
            'code' => '9990',
        ])->assertOk()
            ->assertJsonPath('user.id', $userId)
            ->assertJsonPath('user.phone', $phone);

        $this->assertAuthenticated();
    }
}
