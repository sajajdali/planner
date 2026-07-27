<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_obligation_payment_creates_monthly_finance_transaction(): void
    {
        $user = User::factory()->create();

        $obligation = $this->actingAs($user)
            ->postJson('/api/finance-obligations', [
                'type' => 'debt',
                'title' => 'بدهی تست',
                'party_name' => 'علی',
                'total_amount' => 500000,
                'due_date' => '2026-07-27',
            ])
            ->assertOk()
            ->json();

        $this->actingAs($user)
            ->postJson("/api/finance-obligations/{$obligation['id']}/pay", [
                'amount' => 200000,
                'paid_date' => '2026-07-27',
            ])
            ->assertOk()
            ->assertJsonPath('remaining_amount', 300000);

        $this->actingAs($user)
            ->getJson('/api/finance-dashboard?start=2026-07-01&end=2026-07-31')
            ->assertOk()
            ->assertJsonPath('totals.expense', 200000)
            ->assertJsonPath('totals.debt', 300000);
    }
}
