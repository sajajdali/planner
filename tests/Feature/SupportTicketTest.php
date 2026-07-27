<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_ticket_and_management_can_reply(): void
    {
        $user = User::factory()->create();

        $ticket = $this->actingAs($user)
            ->postJson('/api/support-tickets', [
                'subject' => 'مشکل ثبت تراکنش',
                'body' => 'بعد از ثبت، مبلغ را در لیست نمی‌بینم.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'open')
            ->json();

        $this->actingAs($user)
            ->getJson('/api/support-tickets')
            ->assertOk()
            ->assertJsonPath('0.subject', 'مشکل ثبت تراکنش');

        $this->actingAs($user)
            ->putJson("/api/admin/support-tickets/{$ticket['id']}/reply", [
                'admin_reply' => 'بررسی شد؛ صفحه را یک بار تازه‌سازی کن.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('admin_reply', 'بررسی شد؛ صفحه را یک بار تازه‌سازی کن.');

        $this->actingAs($user)
            ->deleteJson("/api/support-tickets/{$ticket['id']}")
            ->assertNoContent();

        $this->actingAs($user)
            ->getJson('/api/support-tickets')
            ->assertOk()
            ->assertJsonCount(0);
    }
}
