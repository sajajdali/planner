<?php

namespace Tests\Feature;

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
}
