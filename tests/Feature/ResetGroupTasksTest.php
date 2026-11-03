<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GroupTaskItem;
use App\Models\GroupTaskArchive;
use App\Models\GroupTaskProject;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetGroupTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_clear_only_their_group_tasks_with_the_confirmation_phrase(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $userProject = $this->projectFor($user, 'پروژه من');
        $otherProject = $this->projectFor($otherUser, 'پروژه دیگران');
        GroupTaskItem::create(['group_task_project_id' => $userProject->id, 'title' => 'کار من', 'period_type' => 'weekly']);
        $otherItem = GroupTaskItem::create(['group_task_project_id' => $otherProject->id, 'title' => 'کار دیگران', 'period_type' => 'weekly']);

        $archiveId = $this->actingAs($user)->postJson('/api/group-tasks/reset', [
            'confirmation' => 'حذف برنامه قبلی',
            'archive_name' => 'برنامه هفته اول',
        ])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1)
            ->assertJsonPath('archive.name', 'برنامه هفته اول')
            ->json('archive.id');

        $this->assertDatabaseMissing('group_task_items', ['group_task_project_id' => $userProject->id]);
        $this->assertDatabaseHas('group_task_items', ['id' => $otherItem->id]);
        $this->assertDatabaseHas('group_task_projects', ['id' => $userProject->id]);
        $this->assertDatabaseHas('group_task_archives', ['id' => $archiveId, 'user_id' => $user->id, 'task_count' => 1]);

        $this->actingAs($user)->getJson("/api/group-task-archives/{$archiveId}")
            ->assertOk()
            ->assertJsonPath('name', 'برنامه هفته اول')
            ->assertJsonPath('sections.0.name', 'پروژه من')
            ->assertJsonPath('sections.0.projects.0.name', 'پروژه من')
            ->assertJsonPath('sections.0.projects.0.total_count', 1)
            ->assertJsonPath('sections.0.projects.0.items.0.title', 'کار من')
            ->assertJsonPath('sections.0.projects.0.items.0.period_type', 'weekly');

        $this->actingAs($otherUser)->getJson("/api/group-task-archives/{$archiveId}")
            ->assertNotFound();
    }

    public function test_reset_rejects_an_incorrect_confirmation_phrase(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user, 'پروژه من');
        $item = GroupTaskItem::create(['group_task_project_id' => $project->id, 'title' => 'کار من', 'period_type' => 'general']);

        $this->actingAs($user)->postJson('/api/group-tasks/reset', ['confirmation' => 'مطمئن هستم'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('group_task_items', ['id' => $item->id]);
        $this->assertSame(0, GroupTaskArchive::count());
    }

    private function projectFor(User $user, string $name): GroupTaskProject
    {
        $category = Category::create(['user_id' => $user->id, 'name' => $name, 'color' => '#2563EB', 'is_active' => true]);
        $group = TaskGroup::create(['user_id' => $user->id, 'category_id' => $category->id, 'name' => $name, 'color' => '#2563EB', 'is_active' => true]);

        return GroupTaskProject::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'task_group_id' => $group->id,
        ]);
    }
}
