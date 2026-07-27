<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'آرمین رضایی',
            'email' => 'armin@example.com',
            'password' => 'password',
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa',
        ]);

        $categories = collect([
            ['name' => 'کاری', 'color' => '#2563EB', 'soft_color' => '#EAF1FF', 'icon' => 'briefcase'],
            ['name' => 'ورزش', 'color' => '#F97316', 'soft_color' => '#FFF1E6', 'icon' => 'activity'],
            ['name' => 'تغذیه', 'color' => '#10B981', 'soft_color' => '#E7F9F1', 'icon' => 'leaf'],
            ['name' => 'آموزش', 'color' => '#8B5CF6', 'soft_color' => '#F1ECFE', 'icon' => 'book'],
            ['name' => 'زندگی', 'color' => '#06B6D4', 'soft_color' => '#E4F8FB', 'icon' => 'home'],
        ])->map(fn ($category, $index) => Category::create([
            ...$category,
            'user_id' => $user->id,
            'sort_order' => $index + 1,
            'is_default' => true,
        ]));

        collect([
            ['name' => 'خرید روزانه', 'color' => '#14B8A6', 'soft_color' => '#DDFCF7'],
            ['name' => 'خرید عمومی', 'color' => '#A855F7', 'soft_color' => '#F3E8FF'],
            ['name' => 'خرید های شرکت', 'color' => '#0EA5E9', 'soft_color' => '#E0F2FE'],
            ['name' => 'خرید های ضروری', 'color' => '#F43F5E', 'soft_color' => '#FFE4E6'],
        ])->each(fn ($category, $index) => ExpenseCategory::create([
            ...$category,
            'user_id' => $user->id,
            'sort_order' => $index + 1,
            'is_default' => true,
        ]));

        $work = $categories->firstWhere('name', 'کاری');
        $sport = $categories->firstWhere('name', 'ورزش');
        $nutrition = $categories->firstWhere('name', 'تغذیه');
        $education = $categories->firstWhere('name', 'آموزش');
        $life = $categories->firstWhere('name', 'زندگی');
        $today = now('Asia/Tehran')->toDateString();

        $task = Task::create(['user_id' => $user->id, 'category_id' => $work->id, 'title' => 'طراحی صفحه ورود', 'task_date' => $today, 'planned_start_time' => '09:00', 'planned_end_time' => '10:30', 'estimated_minutes' => 90, 'priority' => 'urgent', 'status' => 'in_progress', 'sort_order' => 1]);
        Task::create(['user_id' => $user->id, 'category_id' => $work->id, 'parent_id' => $task->id, 'title' => 'فرم ورود', 'task_date' => $today, 'status' => 'done', 'completed_at' => now(), 'sort_order' => 1]);
        Task::create(['user_id' => $user->id, 'category_id' => $work->id, 'parent_id' => $task->id, 'title' => 'اتصال به API', 'task_date' => $today, 'sort_order' => 2]);

        Task::create(['user_id' => $user->id, 'category_id' => $work->id, 'title' => 'بررسی لیست وظایف امروز', 'task_date' => $today, 'planned_start_time' => '11:00', 'planned_end_time' => '11:35', 'estimated_minutes' => 35, 'priority' => 'high', 'status' => 'done', 'completed_at' => now(), 'manual_actual_minutes' => 35, 'sort_order' => 2]);
        Task::create(['user_id' => $user->id, 'category_id' => $sport->id, 'title' => 'تمرین هوازی', 'task_date' => $today, 'planned_start_time' => '18:00', 'planned_end_time' => '18:45', 'estimated_minutes' => 45, 'priority' => 'medium', 'sort_order' => 1]);
        Task::create(['user_id' => $user->id, 'category_id' => $nutrition->id, 'title' => 'ثبت وعده‌های غذایی', 'task_date' => $today, 'priority' => 'low', 'sort_order' => 1]);
        Task::create(['user_id' => $user->id, 'category_id' => $education->id, 'title' => 'مطالعه کتاب برنامه‌نویسی', 'task_date' => $today, 'planned_start_time' => '20:00', 'planned_end_time' => '20:45', 'estimated_minutes' => 45, 'priority' => 'medium', 'status' => 'done', 'completed_at' => now(), 'manual_actual_minutes' => 45, 'sort_order' => 1]);
        Task::create(['user_id' => $user->id, 'category_id' => $life->id, 'title' => 'خرید روزانه', 'task_date' => $today, 'priority' => 'medium', 'sort_order' => 1]);

        FollowUp::create(['user_id' => $user->id, 'title' => 'پیگیری پرداخت مشتری', 'follow_up_date' => $today, 'follow_up_time' => '12:30', 'person_name' => 'آقای کاظمی', 'priority' => 'high']);
        FollowUp::create(['user_id' => $user->id, 'title' => 'تماس با پزشک', 'follow_up_date' => $today, 'follow_up_time' => '16:00', 'person_name' => 'کلینیک', 'priority' => 'medium']);
    }
}
