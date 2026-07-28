<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\FollowUp;
use App\Models\Goal;
use App\Models\GoalMilestone;
use App\Models\GoalPlanItem;
use App\Models\GoalProgressLog;
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

        collect([
            [
                'title' => 'کاهش وزن به ۸۵ کیلوگرم',
                'type' => 'numeric',
                'category' => 'سلامتی',
                'color' => '#2563EB',
                'icon' => 'weight',
                'status' => 'onTrack',
                'start_value' => 95,
                'current_value' => 91,
                'target_value' => 85,
                'unit' => 'کیلوگرم',
                'direction' => 'decrease',
                'deadline' => now('Asia/Tehran')->addDays(52)->toDateString(),
                'why' => 'برای سلامتی بهتر و داشتن انرژی بیشتر در طول روز.',
                'next_action' => 'ثبت وزن امروز',
                'last_activity_label' => '۲ روز پیش',
                'milestones' => [['رسیدن به ۹۰ کیلو', true, '۱ ماه پیش'], ['رسیدن به ۸۸ کیلو', false, 'هدف بعدی'], ['رسیدن به ۸۵ کیلو', false, 'نهایی']],
                'logs' => [['value' => 91, 'note' => 'کاهش خوب این هفته', 'days' => 2], ['value' => 92, 'note' => 'ثابت مانده', 'days' => 9]],
                'plan' => [['وزن‌کشی روزانه صبح', 'هر روز'], ['پیاده‌روی ۳۰ دقیقه‌ای', '۴ روز در هفته']],
            ],
            [
                'title' => 'انتشار نسخه اول اپلیکیشن',
                'type' => 'doable',
                'category' => 'کاری',
                'color' => '#8B5CF6',
                'icon' => 'product',
                'status' => 'attention',
                'current_value' => 60,
                'target_value' => 100,
                'unit' => '٪',
                'deadline' => now('Asia/Tehran')->addDays(8)->toDateString(),
                'why' => 'اولین محصول مستقل من — فرصتی برای اثبات توانایی‌ام.',
                'next_action' => 'رفع باگ‌های نهایی',
                'last_activity_label' => '۵ روز پیش',
                'milestones' => [['طراحی و توسعه', true, 'تکمیل‌شده'], ['تست داخلی', true, 'تکمیل‌شده'], ['رفع باگ‌ها', false, 'در حال انجام'], ['انتشار نهایی', false, 'هدف نهایی']],
                'logs' => [['value' => 60, 'note' => 'تست‌های اولیه انجام شد', 'days' => 5]],
                'plan' => [['رفع باگ صفحه پرداخت', 'این هفته'], ['آماده‌سازی استور', 'هفته بعد']],
            ],
            [
                'title' => 'هفته‌ای سه جلسه ورزش',
                'type' => 'habit',
                'category' => 'سلامتی',
                'color' => '#16A34A',
                'icon' => 'habit',
                'status' => 'onTrack',
                'current_value' => 2,
                'target_value' => 3,
                'unit' => 'جلسه در هفته',
                'why' => 'حفظ سلامتی و تناسب اندام در بلندمدت.',
                'next_action' => 'جلسه سوم این هفته',
                'last_activity_label' => 'دیروز',
                'milestones' => [['یک ماه پیوسته', true, 'تکمیل‌شده'], ['سه ماه پیوسته', false, 'در حال انجام']],
                'logs' => [['value' => 2, 'note' => 'جلسه سوم عقب افتاد', 'days' => 1]],
                'plan' => [['باشگاه', 'شنبه، دوشنبه، چهارشنبه']],
            ],
            [
                'title' => 'راه‌اندازی کسب‌وکار آنلاین',
                'type' => 'milestone',
                'category' => 'مالی',
                'color' => '#F59E0B',
                'icon' => 'business',
                'status' => 'atRisk',
                'current_value' => 25,
                'target_value' => 100,
                'unit' => '٪',
                'deadline' => now('Asia/Tehran')->subDays(6)->toDateString(),
                'why' => 'ایجاد منبع درآمد مستقل و پایدار.',
                'next_action' => 'تکمیل صفحه فروشگاه',
                'last_activity_label' => '۱۲ روز پیش',
                'milestones' => [['تحقیق بازار', true, 'تکمیل‌شده'], ['ساخت فروشگاه', false, 'عقب‌افتاده'], ['راه‌اندازی رسمی', false, 'هدف نهایی']],
                'logs' => [['value' => 25, 'note' => 'کندی در پیشرفت به دلیل مشغله کاری', 'days' => 12]],
                'plan' => [['انتخاب پلتفرم فروشگاه', 'عقب‌افتاده']],
            ],
        ])->each(function (array $item) use ($user) {
            $goal = Goal::create([
                'user_id' => $user->id,
                ...collect($item)->except(['milestones', 'logs', 'plan'])->all(),
            ]);

            collect($item['milestones'])->each(fn (array $milestone, int $index) => GoalMilestone::create([
                'goal_id' => $goal->id,
                'title' => $milestone[0],
                'is_done' => $milestone[1],
                'date_label' => $milestone[2],
                'sort_order' => $index + 1,
            ]));

            collect($item['logs'])->each(fn (array $log) => GoalProgressLog::create([
                'goal_id' => $goal->id,
                'value' => $log['value'],
                'energy' => 3,
                'note' => $log['note'],
                'logged_at' => now()->subDays($log['days']),
            ]));

            collect($item['plan'])->each(fn (array $plan, int $index) => GoalPlanItem::create([
                'goal_id' => $goal->id,
                'title' => $plan[0],
                'when_label' => $plan[1],
                'sort_order' => $index + 1,
            ]));
        });
    }
}
