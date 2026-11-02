<?php

namespace Tests\Feature;

use App\Models\TeamDailyChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeamDailyChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_returns_the_current_days_items(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $user = User::factory()->create(['timezone' => 'Asia/Tehran']);

        TeamDailyChecklistItem::create(['user_id' => $user->id, 'title' => 'کار دیروز', 'checklist_date' => '2026-09-14']);
        TeamDailyChecklistItem::create(['user_id' => $user->id, 'title' => 'کار امروز', 'checklist_date' => '2026-09-15']);

        $this->actingAs($user)->getJson('/api/group-tasks')
            ->assertOk()
            ->assertJsonCount(1, 'daily_checklist')
            ->assertJsonPath('daily_checklist.0.title', 'کار امروز');
    }

    public function test_user_can_add_and_complete_a_daily_item(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $user = User::factory()->create(['timezone' => 'Asia/Tehran']);

        $itemId = $this->actingAs($user)->postJson('/api/team-daily-checklist', ['title' => '  تماس با مشتری  '])
            ->assertCreated()
            ->assertJsonPath('title', 'تماس با مشتری')
            ->assertJsonPath('is_done', false)
            ->json('id');

        $this->actingAs($user)->putJson("/api/team-daily-checklist/{$itemId}", ['is_done' => true])
            ->assertOk()
            ->assertJsonPath('is_done', true);
    }
}
