<?php

namespace Tests\Feature;

use App\Models\DailyRoutine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FourHourReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_a_report_and_receive_daily_statistics(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tehran']);
        DailyRoutine::create(['user_id' => $user->id, 'routine_date' => '2026-11-02', 'wake_time' => '07:30']);

        $response = $this->actingAs($user)->getJson('/api/four-hour-reports?date=2026-11-02')
            ->assertOk()
            ->assertJsonCount(5, 'categories')
            ->assertJsonCount(4, 'slots')
            ->assertJsonPath('wake_time', '07:30')
            ->assertJsonPath('wake_time_source', 'routine')
            ->assertJsonPath('slots.0.start_time', '07:30')
            ->assertJsonPath('slots.0.end_time', '11:30')
            ->assertJsonPath('slots.3.start_time', '19:30')
            ->assertJsonPath('slots.3.end_time', '23:30');
        $categories = $response->json('categories');

        $workCategory = collect($categories)->firstWhere('name', 'کاری');
        $healthCategory = collect($categories)->firstWhere('name', 'سلامتی');

        $this->actingAs($user)->postJson('/api/four-hour-reports', [
            'report_date' => '2026-11-02',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'items' => [
                ['category_id' => $workCategory['id'], 'description' => 'جلسه پروژه و تکمیل گزارش', 'duration_minutes' => 180],
                ['category_id' => $healthCategory['id'], 'description' => 'پیاده‌روی', 'duration_minutes' => 60],
            ],
        ])->assertOk()
            ->assertJsonPath('duration_minutes', 240)
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.category.name', 'کاری')
            ->assertJsonPath('items.1.category.name', 'سلامتی');

        $this->actingAs($user)->getJson('/api/four-hour-reports?date=2026-11-02')
            ->assertOk()
            ->assertJsonCount(1, 'reports')
            ->assertJsonPath('summary.total_minutes', 240)
            ->assertJsonPath('summary.report_count', 1)
            ->assertJsonPath('summary.categories.0.minutes', 60)
            ->assertJsonPath('summary.categories.1.minutes', 180);
    }

    public function test_reports_cannot_overlap_or_exceed_four_hours(): void
    {
        $user = User::factory()->create();
        $initial = $this->actingAs($user)->getJson('/api/four-hour-reports?date=2026-11-02')
            ->assertJsonPath('wake_time', '06:00')
            ->assertJsonPath('wake_time_source', 'default')
            ->assertJsonPath('slots.3.end_time', '22:00');
        $categoryId = $initial->json('categories.0.id');
        $payload = [
            'report_date' => '2026-11-02',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'items' => [['category_id' => $categoryId, 'description' => 'گزارش اول', 'duration_minutes' => 60]],
        ];

        $this->actingAs($user)->postJson('/api/four-hour-reports', $payload)->assertOk();
        $this->actingAs($user)->postJson('/api/four-hour-reports', [
            ...$payload, 'start_time' => '09:00', 'end_time' => '11:00',
        ])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/four-hour-reports', [
            ...$payload, 'start_time' => '12:00', 'end_time' => '17:00',
        ])->assertUnprocessable();
    }

    public function test_user_can_edit_their_report_and_its_activities(): void
    {
        $user = User::factory()->create();
        $categories = $this->actingAs($user)->getJson('/api/four-hour-reports?date=2026-11-02')->json('categories');
        $workCategory = collect($categories)->firstWhere('name', 'کاری');
        $healthCategory = collect($categories)->firstWhere('name', 'سلامتی');

        $reportId = $this->actingAs($user)->postJson('/api/four-hour-reports', [
            'report_date' => '2026-11-02',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'items' => [
                ['category_id' => $workCategory['id'], 'description' => 'نسخه قدیمی', 'duration_minutes' => 60],
            ],
        ])->assertOk()->json('id');

        $this->actingAs($user)->putJson("/api/four-hour-reports/{$reportId}", [
            'report_date' => '2026-11-02',
            'start_time' => '08:30',
            'end_time' => '11:30',
            'items' => [
                ['category_id' => $workCategory['id'], 'description' => 'نسخه ویرایش‌شده', 'duration_minutes' => 120],
                ['category_id' => $healthCategory['id'], 'description' => 'پیاده‌روی', 'duration_minutes' => 30],
            ],
        ])->assertOk()
            ->assertJsonPath('start_time', '08:30')
            ->assertJsonPath('duration_minutes', 180)
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.description', 'نسخه ویرایش‌شده')
            ->assertJsonPath('items.1.category.name', 'سلامتی');

        $this->assertDatabaseMissing('four_hour_report_items', ['description' => 'نسخه قدیمی']);
        $this->actingAs($user)->getJson('/api/four-hour-reports?date=2026-11-02')
            ->assertJsonPath('summary.total_minutes', 180)
            ->assertJsonPath('summary.categories.0.minutes', 30)
            ->assertJsonPath('summary.categories.1.minutes', 120);

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->putJson("/api/four-hour-reports/{$reportId}", [])->assertNotFound();
    }

    public function test_user_can_add_a_personal_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/four-hour-report-categories', [
            'name' => 'خلاقیت',
            'color' => '#14B8A6',
        ])->assertOk()
            ->assertJsonPath('name', 'خلاقیت')
            ->assertJsonPath('is_default', false);

        $this->assertDatabaseHas('four_hour_report_categories', ['user_id' => $user->id, 'name' => 'خلاقیت']);
    }
}
