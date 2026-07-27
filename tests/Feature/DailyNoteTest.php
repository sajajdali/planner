<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_note_preserves_spacing_and_line_breaks(): void
    {
        $user = User::factory()->create();
        $body = "خط اول\n\n  خط سوم با فاصله  \nآخر";

        $this->actingAs($user)
            ->putJson('/api/daily-note', [
                'note_date' => '2026-07-27',
                'body' => $body,
            ])
            ->assertOk()
            ->assertJsonPath('body', $body);

        $this->actingAs($user)
            ->getJson('/api/daily-planner?date=2026-07-27')
            ->assertOk()
            ->assertJsonPath('note.body', $body);
    }
}
