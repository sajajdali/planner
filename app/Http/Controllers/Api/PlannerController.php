<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyNote;
use App\Models\DailyRoutine;
use App\Models\DailyRoutineCheck;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinanceObligation;
use App\Models\FinanceObligationPayment;
use App\Models\FinancialAccount;
use App\Models\FollowUp;
use App\Models\Goal;
use App\Models\GoalMilestone;
use App\Models\GoalPlanItem;
use App\Models\GoalProgressLog;
use App\Models\GroupTaskItem;
use App\Models\GroupTaskProject;
use App\Models\MealEntry;
use App\Models\NotebookNote;
use App\Models\NotebookNoteGroup;
use App\Models\PrioritySetting;
use App\Models\RoutineItem;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\TaskTimeSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlannerController extends Controller
{
    public function daily(Request $request)
    {
        $date = $request->query('date', now($request->user()->timezone)->toDateString());
        $user = $request->user();

        $this->ensureCategories($user->id);
        $this->ensurePrioritySettings($user->id);
        $this->ensureFinancialAccounts($user->id);
        $this->ensureExpenseCategories($user->id);
        $this->ensureRoutineItems($user->id);

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $tasks = Task::query()
            ->with(['subtasks.group', 'timeSessions', 'group'])
            ->where('user_id', $user->id)
            ->whereNull('parent_id')
            ->whereDate('task_date', $date)
            ->orderBy('sort_order')
            ->get();

        $followUps = FollowUp::query()
            ->where('user_id', $user->id)
            ->whereDate('follow_up_date', $date)
            ->orderBy('follow_up_time')
            ->get();

        $expenseCategories = ExpenseCategory::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $expenses = Expense::query()
            ->with(['category', 'account'])
            ->where('user_id', $user->id)
            ->whereDate('expense_date', $date)
            ->latest()
            ->get();

        $meals = MealEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('meal_date', $date)
            ->orderBy('sort_order')
            ->orderBy('meal_time')
            ->get();

        $activeTimer = TaskTimeSession::query()
            ->with('task.category')
            ->where('user_id', $user->id)
            ->whereIn('status', ['running', 'paused'])
            ->latest()
            ->first();

        return [
            'date' => $date,
            'categories' => $categories,
            'taskGroups' => $this->taskGroupsForUser($user->id),
            'priorities' => $this->prioritySettings($user->id),
            'tasks' => $tasks->map(fn (Task $task) => $this->taskPayload($task)),
            'followUps' => $followUps,
            'expenseCategories' => $expenseCategories,
            'financialAccounts' => $this->financialAccountList($user->id),
            'expenses' => $expenses->map(fn (Expense $expense) => $this->expensePayload($expense)),
            'expenseTotal' => $expenses->sum('amount'),
            'meals' => $meals,
            'activeTimer' => $activeTimer ? [
                'id' => $activeTimer->id,
                'task_id' => $activeTimer->task_id,
                'task_title' => $activeTimer->task?->title,
                'task_date' => $activeTimer->task?->task_date ? $this->dateKey($activeTimer->task->task_date) : null,
                'category_id' => $activeTimer->task?->category_id,
                'category_name' => $activeTimer->task?->category?->name,
                'category_color' => $activeTimer->task?->category?->color,
                'started_at' => $activeTimer->started_at,
                'duration_seconds' => $activeTimer->duration_seconds,
                'status' => $activeTimer->status,
            ] : null,
            'review' => DB::table('daily_reviews')->where('user_id', $user->id)->whereDate('review_date', $date)->first(),
            'note' => $this->dailyNotePayload(
                DailyNote::query()
                    ->where('user_id', $user->id)
                    ->whereDate('note_date', $date)
                    ->first()
            ),
            'routine' => $this->routinePayload($user->id, $date),
        ];
    }

    public function updateDailyNote(Request $request)
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'body' => ['nullable', 'string', 'max:30000'],
        ]);

        $note = DailyNote::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'note_date' => Carbon::parse($data['note_date'])->toDateString(),
            ],
            [
                'body' => $data['body'] ?? '',
            ],
        );

        return $this->dailyNotePayload($note);
    }

    public function goals(Request $request)
    {
        $query = Goal::query()
            ->with(['milestones', 'planItems', 'progressLogs'])
            ->where('user_id', $request->user()->id);

        $filter = $request->query('filter', 'all');
        if ($filter && $filter !== 'all') {
            $query->where('status', $filter);
        }

        $goals = $query->get();
        $sort = $request->query('sort', 'priority');
        $priority = ['atRisk' => 0, 'attention' => 1, 'onTrack' => 2, 'planned' => 3, 'paused' => 4, 'done' => 5, 'archived' => 6];

        $goals = match ($sort) {
            'deadline' => $goals->sortBy(fn (Goal $goal) => $goal->deadline ? $goal->deadline->timestamp : PHP_INT_MAX),
            'progress' => $goals->sortByDesc(fn (Goal $goal) => $this->goalPercent($goal)),
            'created' => $goals->sortByDesc('created_at'),
            'activity' => $goals->sortByDesc(fn (Goal $goal) => $goal->progressLogs->first()?->logged_at?->timestamp ?? $goal->updated_at?->timestamp ?? 0),
            default => $goals->sortBy(fn (Goal $goal) => $priority[$goal->status] ?? 9),
        };

        $allGoals = Goal::query()
            ->where('user_id', $request->user()->id)
            ->get();

        return [
            'stats' => [
                'activeCount' => $allGoals->whereIn('status', ['onTrack', 'attention', 'atRisk', 'planned'])->count(),
                'avgProgress' => (int) round($allGoals->avg(fn (Goal $goal) => $this->goalPercent($goal)) ?? 0),
                'needsAttention' => $allGoals->whereIn('status', ['attention', 'atRisk'])->count(),
                'completedCount' => $allGoals->where('status', 'done')->count(),
            ],
            'goals' => $goals->values()->map(fn (Goal $goal) => $this->goalPayload($goal))->values(),
        ];
    }

    public function storeGoal(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['numeric', 'doable', 'habit', 'milestone', 'ongoing'])],
            'category' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'why' => ['nullable', 'string', 'max:4000'],
            'deadline' => ['nullable', 'date'],
            'start_value' => ['nullable', 'numeric'],
            'current_value' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', Rule::in(['increase', 'decrease'])],
            'next_action' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.weight' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'milestones.*.starts_on' => ['nullable', 'date'],
            'milestones.*.ends_on' => ['nullable', 'date'],
            'milestones.*.dependency' => ['nullable', 'string', 'max:255'],
        ]);

        $milestones = collect($data['milestones'] ?? []);
        $targetValue = $data['type'] === 'milestone' && $milestones->isNotEmpty()
            ? $milestones->sum(fn (array $milestone) => (float) ($milestone['weight'] ?? 1))
            : max(1, (float) ($data['target_value'] ?? 100));

        $goal = Goal::create([
            'user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'type' => $data['type'],
            'category' => $data['category'],
            'color' => $data['color'],
            'icon' => $this->goalIconForType($data['type']),
            'status' => 'planned',
            'start_value' => $data['start_value'] ?? 0,
            'current_value' => $data['current_value'] ?? ($data['start_value'] ?? 0),
            'target_value' => max(1, $targetValue),
            'unit' => $data['unit'] ?? '٪',
            'direction' => $data['direction'] ?? 'increase',
            'deadline' => $data['deadline'] ?? null,
            'why' => $data['why'] ?? null,
            'next_action' => $data['next_action'] ?? 'شروع اولین اقدام',
            'last_activity_label' => 'همین الان',
            'metadata' => $data['metadata'] ?? [],
        ]);

        foreach ($milestones as $index => $milestone) {
            if (trim($milestone['title'] ?? '') === '') {
                continue;
            }

            GoalMilestone::create([
                'goal_id' => $goal->id,
                'title' => trim($milestone['title']),
                'weight' => (float) ($milestone['weight'] ?? 1),
                'starts_on' => $milestone['starts_on'] ?? null,
                'ends_on' => $milestone['ends_on'] ?? null,
                'dependency' => $milestone['dependency'] ?? null,
                'status' => 'pending',
                'progress' => 0,
                'date_label' => 'در انتظار',
                'sort_order' => $index + 1,
            ]);
        }

        GoalPlanItem::create([
            'goal_id' => $goal->id,
            'title' => $goal->next_action ?: 'شروع اولین اقدام',
            'when_label' => 'این هفته',
            'sort_order' => 1,
        ]);

        return $this->goalPayload($goal->load(['milestones', 'planItems', 'progressLogs']));
    }

    public function showGoal(Request $request, Goal $goal)
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        return $this->goalPayload($goal->load(['milestones', 'planItems', 'progressLogs']));
    }

    public function logGoalProgress(Request $request, Goal $goal)
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'value' => [Rule::requiredIf($goal->type !== 'milestone'), 'nullable', 'numeric'],
            'milestone_id' => [Rule::requiredIf($goal->type === 'milestone'), 'nullable', 'integer'],
            'milestone_progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'energy' => ['required', 'integer', 'min:1', 'max:5'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($goal->type === 'milestone') {
            $milestone = $goal->milestones()->findOrFail($data['milestone_id']);
            $progress = (int) ($data['milestone_progress'] ?? 100);
            $done = $progress >= 100;

            $milestone->update([
                'is_done' => $done,
                'status' => $done ? 'done' : ($progress > 0 ? 'in_progress' : 'pending'),
                'progress' => $progress,
                'date_label' => $done ? 'تکمیل‌شده' : ($progress > 0 ? 'در حال انجام' : 'در انتظار'),
            ]);

            $freshGoal = $goal->fresh('milestones');
            $this->syncMilestoneGoalProgress($freshGoal);
            $freshGoal = $freshGoal->fresh(['milestones', 'planItems', 'progressLogs']);

            GoalProgressLog::create([
                'goal_id' => $goal->id,
                'value' => $freshGoal->current_value,
                'energy' => $data['energy'],
                'note' => $data['note'] ?: $milestone->title,
                'logged_at' => now(),
            ]);

            return $this->goalPayload($goal->fresh()->load(['milestones', 'planItems', 'progressLogs']));
        }

        $value = (float) $data['value'];
        GoalProgressLog::create([
            'goal_id' => $goal->id,
            'value' => $value,
            'energy' => $data['energy'],
            'note' => $data['note'] ?? null,
            'logged_at' => now(),
        ]);

        if ($goal->type === 'numeric' && $goal->direction === 'decrease' && (float) $goal->start_value <= (float) $goal->target_value && $value > (float) $goal->target_value) {
            $goal->start_value = $value;
        }

        $updates = [
            'start_value' => $goal->start_value,
            'current_value' => $value,
            'last_activity_label' => 'همین الان',
            'status' => $this->statusAfterProgress($goal, $value),
        ];

        $goal->update($updates);

        return $this->goalPayload($goal->fresh()->load(['milestones', 'planItems', 'progressLogs']));
    }

    public function updateGoalStatus(Request $request, Goal $goal)
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['planned', 'onTrack', 'attention', 'atRisk', 'paused', 'done', 'archived'])],
        ]);

        $goal->update([
            'status' => $data['status'],
            'last_activity_label' => 'همین الان',
        ]);

        return $this->goalPayload($goal->fresh()->load(['milestones', 'planItems', 'progressLogs']));
    }

    public function toggleGoalMilestone(Request $request, Goal $goal, GoalMilestone $milestone)
    {
        abort_unless($goal->user_id === $request->user()->id && $milestone->goal_id === $goal->id, 404);

        $done = $request->boolean('done', ! $milestone->is_done);
        $milestone->update([
            'is_done' => $done,
            'status' => $done ? 'done' : 'pending',
            'progress' => $done ? 100 : 0,
            'date_label' => $done ? 'تکمیل‌شده' : 'در انتظار',
        ]);

        if ($goal->type === 'milestone') {
            $this->syncMilestoneGoalProgress($goal->fresh('milestones'));
        }

        return $this->goalPayload($goal->fresh()->load(['milestones', 'planItems', 'progressLogs']));
    }

    public function destroyGoal(Request $request, Goal $goal)
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $goal->delete();

        return response()->noContent();
    }

    public function monthlyReport(Request $request)
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $user = $request->user();
        $start = Carbon::parse($data['start'])->startOfDay();
        $end = Carbon::parse($data['end'])->endOfDay();

        $this->ensureCategories($user->id);
        $this->ensurePrioritySettings($user->id);
        $this->ensureFinancialAccounts($user->id);
        $this->ensureExpenseCategories($user->id);
        $this->ensureRoutineItems($user->id);

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $tasks = Task::query()
            ->with(['subtasks.timeSessions', 'subtasks.group', 'timeSessions', 'category', 'group'])
            ->where('user_id', $user->id)
            ->whereNull('parent_id')
            ->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('task_date')
            ->orderBy('sort_order')
            ->get();

        $followUps = FollowUp::query()
            ->where('user_id', $user->id)
            ->whereBetween('follow_up_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $meals = MealEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('meal_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('meal_date')
            ->orderBy('sort_order')
            ->get();

        $expenses = Expense::query()
            ->with(['category', 'account'])
            ->where('user_id', $user->id)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('expense_date')
            ->latest()
            ->get();

        $routines = DailyRoutine::query()
            ->with('checks')
            ->where('user_id', $user->id)
            ->whereBetween('routine_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (DailyRoutine $routine) => $routine->routine_date->format('Y-m-d'));

        $routineItems = RoutineItem::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $dayTasks = $tasks->filter(fn (Task $task) => $this->dateKey($task->task_date) === $date);
            $flatTasks = $dayTasks->flatMap(fn (Task $task) => collect([$task])->merge($task->subtasks));
            $routine = $routines->get($date);
            $routineDone = $routine?->checks->where('is_done', true)->count() ?? 0;
            $dayMeals = $meals->filter(fn (MealEntry $meal) => $this->dateKey($meal->meal_date) === $date);
            $dayExpenses = $expenses->filter(fn (Expense $expense) => $this->dateKey($expense->expense_date) === $date);

            $days[] = [
                'date' => $date,
                'tasks_total' => $flatTasks->count(),
                'tasks_done' => $flatTasks->where('status', 'done')->count(),
                'actual_seconds' => $flatTasks->sum(fn (Task $task) => $this->taskActualSeconds($task)),
                'meals_total' => $dayMeals->count(),
                'meals_done' => $dayMeals->where('status', 'eaten')->count(),
                'routine_total' => $routineItems->count(),
                'routine_done' => $routineDone,
                'wake_time' => $routine?->wake_time ? substr($routine->wake_time, 0, 5) : null,
                'sleep_time' => $routine?->sleep_time ? substr($routine->sleep_time, 0, 5) : null,
                'income' => $dayExpenses->where('type', 'income')->sum('amount'),
                'expense' => $dayExpenses->where('type', 'expense')->sum('amount'),
            ];
        }

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'categories' => $categories,
            'taskGroups' => $this->taskGroupsForUser($user->id),
            'priorities' => $this->prioritySettings($user->id),
            'category_stats' => $categories->map(function (Category $category) use ($tasks) {
                $categoryTasks = $tasks->where('category_id', $category->id);
                $flatTasks = $categoryTasks->flatMap(fn (Task $task) => collect([$task])->merge($task->subtasks));

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'soft_color' => $category->soft_color,
                    'icon' => $category->icon,
                    'total' => $flatTasks->count(),
                    'done' => $flatTasks->where('status', 'done')->count(),
                    'actual_seconds' => $flatTasks->sum(fn (Task $task) => $this->taskActualSeconds($task)),
                ];
            })->values(),
            'overview' => [
                'tasks_total' => collect($days)->sum('tasks_total'),
                'tasks_done' => collect($days)->sum('tasks_done'),
                'follow_total' => $followUps->count(),
                'follow_done' => $followUps->where('status', 'done')->count(),
                'actual_seconds' => collect($days)->sum('actual_seconds'),
            ],
            'days' => $days,
            'finance' => [
                'income' => $expenses->where('type', 'income')->sum('amount'),
                'expense' => $expenses->where('type', 'expense')->sum('amount'),
                'entries' => $expenses->map(fn (Expense $expense) => $this->expensePayload($expense))->values(),
            ],
            'routine_items' => $routineItems->map(function (RoutineItem $item) use ($routines) {
                $doneDays = $routines->filter(fn (DailyRoutine $routine) => $routine->checks->firstWhere('routine_item_id', $item->id)?->is_done)->count();

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'color' => $item->color,
                    'done_days' => $doneDays,
                ];
            })->values(),
            'meals' => [
                'total' => $meals->count(),
                'done' => $meals->where('status', 'eaten')->count(),
            ],
        ];
    }

    public function financeDashboard(Request $request)
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $user = $request->user();
        $this->ensureFinancialAccounts($user->id);
        $this->ensureExpenseCategories($user->id);

        $start = Carbon::parse($data['start'])->toDateString();
        $end = Carbon::parse($data['end'])->toDateString();

        $expenses = Expense::query()
            ->with(['category', 'account'])
            ->where('user_id', $user->id)
            ->whereBetween('expense_date', [$start, $end])
            ->latest()
            ->get();

        $obligations = FinanceObligation::query()
            ->with(['account', 'payments.expense.account'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return [
            'accounts' => $this->financialAccountList($user->id),
            'expenseCategories' => $this->expenseCategories($request),
            'transactions' => $expenses->map(fn (Expense $expense) => $this->expensePayload($expense))->values(),
            'totals' => [
                'income' => (int) $expenses->where('type', 'income')->sum('amount'),
                'expense' => (int) $expenses->where('type', 'expense')->sum('amount'),
                'debt' => (int) $obligations->where('type', 'debt')->sum(fn (FinanceObligation $item) => $this->obligationRemaining($item)),
                'due_this_month' => (int) $obligations->where('type', 'installment')->sum(fn (FinanceObligation $item) => $this->obligationCurrentDue($item)),
            ],
            'installments' => $obligations->where('type', 'installment')->map(fn (FinanceObligation $item) => $this->obligationPayload($item))->values(),
            'debts' => $obligations->where('type', 'debt')->map(fn (FinanceObligation $item) => $this->obligationPayload($item))->values(),
        ];
    }

    public function storeFinanceObligation(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:installment,debt'],
            'financial_account_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'integer', 'min:1'],
            'installment_amount' => ['nullable', 'integer', 'min:1'],
            'installments_total' => ['nullable', 'integer', 'min:1', 'max:240'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! empty($data['financial_account_id'])) {
            FinancialAccount::where('user_id', $request->user()->id)->findOrFail($data['financial_account_id']);
        }

        $obligation = FinanceObligation::create([
            ...$data,
            'user_id' => $request->user()->id,
            'installment_amount' => $data['type'] === 'installment' ? ($data['installment_amount'] ?? $data['total_amount']) : null,
            'installments_total' => $data['type'] === 'installment' ? ($data['installments_total'] ?? 1) : null,
            'due_day' => $data['type'] === 'installment' ? ($data['due_day'] ?? 1) : null,
            'status' => 'active',
            'color' => $data['color'] ?? ($data['type'] === 'installment' ? '#7C3AED' : '#DC2626'),
        ]);

        return $this->obligationPayload($obligation->load(['account', 'payments']));
    }

    public function payFinanceObligation(Request $request, FinanceObligation $obligation)
    {
        abort_unless($obligation->user_id === $request->user()->id, 404);
        $this->ensureExpenseCategories($request->user()->id);

        $data = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1'],
            'paid_date' => ['required', 'date'],
            'financial_account_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = min($data['amount'] ?? $this->obligationCurrentDue($obligation), $this->obligationRemaining($obligation->loadMissing('payments')));
        abort_if($amount <= 0, 422, 'این مورد قبلاً کامل پرداخت شده است.');

        $account = FinancialAccount::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->find($data['financial_account_id'] ?? $obligation->financial_account_id);

        $category = ExpenseCategory::where('user_id', $request->user()->id)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->firstOrFail();

        $obligation = DB::transaction(function () use ($request, $obligation, $category, $account, $amount, $data) {
            $paidDate = Carbon::parse($data['paid_date'])->toDateString();

            $expense = Expense::create([
                'user_id' => $request->user()->id,
                'expense_category_id' => $category->id,
                'financial_account_id' => $account?->id,
                'title' => 'پرداخت '.$obligation->title,
                'amount' => $amount,
                'type' => 'expense',
                'expense_date' => $paidDate,
                'note' => $data['note'] ?? ($obligation->type === 'installment' ? 'پرداخت قسط' : 'پرداخت بدهی'),
            ]);

            FinanceObligationPayment::create([
                'finance_obligation_id' => $obligation->id,
                'expense_id' => $expense->id,
                'paid_date' => $paidDate,
                'amount' => $amount,
                'note' => $data['note'] ?? null,
            ]);

            $fresh = $obligation->fresh(['account', 'payments']);
            if ($this->obligationRemaining($fresh) <= 0) {
                $fresh->update(['status' => 'paid']);
                $fresh = $fresh->fresh(['account', 'payments']);
            }

            return $fresh;
        });

        return $this->obligationPayload($obligation);
    }

    public function destroyFinanceObligationPayment(Request $request, FinanceObligationPayment $payment)
    {
        $payment->loadMissing(['obligation', 'expense']);
        abort_unless($payment->obligation?->user_id === $request->user()->id, 404);

        $obligation = DB::transaction(function () use ($payment) {
            $obligation = $payment->obligation;
            $expense = $payment->expense;

            $payment->delete();
            $expense?->delete();
            $obligation->update(['status' => 'active']);

            return $obligation;
        });

        return $this->obligationPayload($obligation->fresh(['account', 'payments.expense.account']));
    }

    public function supportTickets(Request $request)
    {
        return SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->supportTicketPayload($ticket))
            ->values();
    }

    public function storeSupportTicket(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $ticket = SupportTicket::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return $this->supportTicketPayload($ticket);
    }

    public function destroySupportTicket(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        $ticket->delete();

        return response()->noContent();
    }

    public function adminSupportTickets()
    {
        return SupportTicket::query()
            ->with('user')
            ->latest()
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->supportTicketPayload($ticket, true))
            ->values();
    }

    public function replySupportTicket(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'admin_reply' => ['required', 'string', 'max:4000'],
        ]);

        $ticket->update([
            'admin_reply' => $data['admin_reply'],
            'status' => 'answered',
            'replied_at' => now(),
        ]);

        return $this->supportTicketPayload($ticket->fresh('user'), true);
    }

    public function categories(Request $request)
    {
        $this->ensureCategories($request->user()->id);

        $query = Category::query()
            ->where('user_id', $request->user()->id);

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->get();
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
        ]);

        $sortOrder = Category::where('user_id', $request->user()->id)->max('sort_order') + 1;

        return Category::create([
            ...$data,
            'soft_color' => $this->softColor($data['color']),
            'user_id' => $request->user()->id,
            'sort_order' => $sortOrder,
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    public function updateCategory(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'color' => $data['color'],
            'icon' => $data['icon'],
            'soft_color' => $this->softColor($data['color']),
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        return $category->fresh();
    }

    public function reorderCategories(Request $request)
    {
        $data = $request->validate([
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['integer'],
        ]);

        $ownedIds = Category::where('user_id', $request->user()->id)
            ->whereIn('id', $data['category_ids'])
            ->pluck('id')
            ->all();

        foreach ($data['category_ids'] as $index => $categoryId) {
            if (! in_array($categoryId, $ownedIds, true)) {
                continue;
            }

            Category::where('user_id', $request->user()->id)
                ->where('id', $categoryId)
                ->update(['sort_order' => $index + 1]);
        }

        return $this->categories($request->merge(['include_inactive' => true]));
    }

    public function destroyCategory(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id, 404);

        $category->update(['is_active' => false]);

        return response()->noContent();
    }

    public function taskGroups(Request $request)
    {
        return $this->taskGroupsForUser($request->user()->id, $request->boolean('include_inactive'));
    }

    public function storeTaskGroup(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $category = Category::where('user_id', $request->user()->id)->findOrFail($data['category_id']);
        $sortOrder = TaskGroup::where('user_id', $request->user()->id)
            ->where('category_id', $category->id)
            ->max('sort_order') + 1;

        return TaskGroup::create([
            'user_id' => $request->user()->id,
            'category_id' => $category->id,
            'name' => trim($data['name']),
            'color' => $data['color'],
            'soft_color' => $this->softColor($data['color']),
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    public function updateTaskGroup(Request $request, TaskGroup $taskGroup)
    {
        abort_unless($taskGroup->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $taskGroup->update([
            'name' => trim($data['name']),
            'color' => $data['color'],
            'soft_color' => $this->softColor($data['color']),
            'is_active' => $data['is_active'] ?? $taskGroup->is_active,
        ]);

        if (! $taskGroup->is_active) {
            Task::where('user_id', $request->user()->id)
                ->where('task_group_id', $taskGroup->id)
                ->update(['task_group_id' => null]);
        }

        return $taskGroup->fresh();
    }

    public function reorderTaskGroups(Request $request)
    {
        $data = $request->validate([
            'task_group_ids' => ['required', 'array'],
            'task_group_ids.*' => ['integer'],
        ]);

        $groups = TaskGroup::where('user_id', $request->user()->id)
            ->whereIn('id', $data['task_group_ids'])
            ->get()
            ->keyBy('id');

        foreach ($data['task_group_ids'] as $index => $groupId) {
            $groups->get((int) $groupId)?->update(['sort_order' => $index + 1]);
        }

        return $this->taskGroupsForUser($request->user()->id, true);
    }

    public function destroyTaskGroup(Request $request, TaskGroup $taskGroup)
    {
        abort_unless($taskGroup->user_id === $request->user()->id, 404);

        Task::where('user_id', $request->user()->id)
            ->where('task_group_id', $taskGroup->id)
            ->update(['task_group_id' => null]);

        $taskGroup->update(['is_active' => false]);

        return response()->noContent();
    }

    public function groupTasks(Request $request)
    {
        $userId = $request->user()->id;
        $categories = Category::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $taskGroups = TaskGroup::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get();

        $projects = GroupTaskProject::query()
            ->with(['taskGroup', 'items'])
            ->where('user_id', $userId)
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get();

        return [
            'sections' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'soft_color' => $category->soft_color,
                'groups' => $taskGroups
                    ->where('category_id', $category->id)
                    ->map(fn (TaskGroup $group) => $this->groupTaskCatalogPayload($group, $projects->contains('task_group_id', $group->id)))
                    ->values(),
                'projects' => $projects
                    ->where('category_id', $category->id)
                    ->map(fn (GroupTaskProject $project) => $this->groupTaskProjectPayload($project))
                    ->values(),
            ])->values(),
        ];
    }

    public function storeGroupTaskProject(Request $request)
    {
        $data = $request->validate([
            'task_group_id' => ['required', 'integer'],
        ]);

        $group = TaskGroup::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->findOrFail($data['task_group_id']);

        $sortOrder = GroupTaskProject::where('user_id', $request->user()->id)
            ->where('category_id', $group->category_id)
            ->max('sort_order') + 1;

        $project = GroupTaskProject::firstOrCreate(
            ['user_id' => $request->user()->id, 'task_group_id' => $group->id],
            ['category_id' => $group->category_id, 'sort_order' => $sortOrder],
        );

        return $this->groupTaskProjectPayload($project->load(['taskGroup', 'items']));
    }

    public function destroyGroupTaskProject(Request $request, GroupTaskProject $project)
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $project->delete();

        return response()->noContent();
    }

    public function storeGroupTaskItem(Request $request, GroupTaskProject $project)
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $sortOrder = $project->items()->max('sort_order') + 1;
        $item = $project->items()->create([
            'title' => trim($data['title']),
            'sort_order' => $sortOrder,
            'is_done' => false,
        ]);

        return $item;
    }

    public function updateGroupTaskItem(Request $request, GroupTaskItem $item)
    {
        $item->load('project');
        abort_unless($item->project?->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'is_done' => ['nullable', 'boolean'],
        ]);

        $item->update([
            'title' => isset($data['title']) ? trim($data['title']) : $item->title,
            'is_done' => $data['is_done'] ?? $item->is_done,
        ]);

        return $item->fresh();
    }

    public function reorderGroupTaskItems(Request $request, GroupTaskProject $project)
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        $items = $project->items()->whereIn('id', $data['item_ids'])->get()->keyBy('id');
        foreach ($data['item_ids'] as $index => $itemId) {
            $items->get((int) $itemId)?->update(['sort_order' => $index + 1]);
        }

        return $this->groupTaskProjectPayload($project->fresh(['taskGroup', 'items']));
    }

    public function destroyGroupTaskItem(Request $request, GroupTaskItem $item)
    {
        $item->load('project');
        abort_unless($item->project?->user_id === $request->user()->id, 404);

        $item->delete();

        return response()->noContent();
    }

    public function priorities(Request $request)
    {
        $this->ensurePrioritySettings($request->user()->id);

        return $this->prioritySettings($request->user()->id);
    }

    public function storePriority(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $sortOrder = PrioritySetting::where('user_id', $request->user()->id)->max('sort_order') + 1;
        $key = 'custom_'.$request->user()->id.'_'.now()->format('YmdHis').'_'.random_int(100, 999);

        return PrioritySetting::create([
            'user_id' => $request->user()->id,
            'key' => $key,
            'label' => $data['label'],
            'color' => $data['color'],
            'soft_color' => $this->softColor($data['color']),
            'sort_order' => $sortOrder,
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    public function updatePriority(Request $request, PrioritySetting $priority)
    {
        abort_unless($priority->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $priority->update([
            'label' => $data['label'],
            'color' => $data['color'],
            'soft_color' => $this->softColor($data['color']),
        ]);

        return $priority->fresh();
    }

    public function destroyPriority(Request $request, PrioritySetting $priority)
    {
        abort_unless($priority->user_id === $request->user()->id, 404);

        $fallback = PrioritySetting::where('user_id', $request->user()->id)
            ->where('key', 'medium')
            ->first();

        if ($fallback && $priority->key !== $fallback->key) {
            Task::where('user_id', $request->user()->id)
                ->where('priority', $priority->key)
                ->update(['priority' => $fallback->key]);
        }

        $priority->update(['is_active' => false]);

        return response()->noContent();
    }

    public function storeTask(Request $request)
    {
        $priorityKeys = $this->priorityKeys($request->user()->id);

        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'task_group_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'task_date' => ['nullable', 'date'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_end_time' => ['nullable', 'date_format:H:i'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'priority' => ['required', Rule::in($priorityKeys)],
        ]);

        $category = Category::where('user_id', $request->user()->id)->findOrFail($data['category_id']);
        $taskGroupId = $this->validatedTaskGroupId($request->user()->id, $category->id, $data['task_group_id'] ?? null);
        $sortOrder = Task::where('user_id', $request->user()->id)->where('category_id', $category->id)->max('sort_order') + 1;

        $task = Task::create([
            ...$data,
            'user_id' => $request->user()->id,
            'category_id' => $category->id,
            'task_group_id' => $taskGroupId,
            'task_date' => $data['task_date'] ?? now($request->user()->timezone)->toDateString(),
            'sort_order' => $sortOrder,
        ]);

        foreach ($request->input('subtasks', []) as $index => $subtask) {
            $title = is_array($subtask) ? ($subtask['title'] ?? '') : $subtask;

            if (trim((string) $title) === '') {
                continue;
            }

            Task::create([
                'user_id' => $request->user()->id,
                'category_id' => $category->id,
                'task_group_id' => $taskGroupId,
                'parent_id' => $task->id,
                'title' => trim($title),
                'task_date' => $task->task_date,
                'planned_start_time' => is_array($subtask) ? ($subtask['planned_start_time'] ?? null) : null,
                'planned_end_time' => is_array($subtask) ? ($subtask['planned_end_time'] ?? null) : null,
                'priority' => is_array($subtask) ? ($subtask['priority'] ?? 'medium') : 'medium',
                'sort_order' => $index + 1,
            ]);
        }

        return $this->taskPayload($task->load(['subtasks', 'timeSessions']));
    }

    public function updateTask(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        abort_if($task->parent_id, 422, 'زیروظیفه از فرم وظیفه اصلی ویرایش می‌شود.');

        $priorityKeys = $this->priorityKeys($request->user()->id);

        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'task_group_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_end_time' => ['nullable', 'date_format:H:i'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'priority' => ['required', Rule::in($priorityKeys)],
            'subtasks' => ['nullable', 'array'],
            'subtasks.*.id' => ['nullable', 'integer'],
            'subtasks.*.title' => ['required_with:subtasks', 'string', 'max:255'],
            'subtasks.*.planned_start_time' => ['nullable', 'date_format:H:i'],
            'subtasks.*.planned_end_time' => ['nullable', 'date_format:H:i'],
            'subtasks.*.priority' => ['required_with:subtasks', Rule::in($priorityKeys)],
        ]);

        $category = Category::where('user_id', $request->user()->id)->findOrFail($data['category_id']);
        $taskGroupId = $this->validatedTaskGroupId($request->user()->id, $category->id, $data['task_group_id'] ?? null);
        $task->update([
            'category_id' => $category->id,
            'task_group_id' => $taskGroupId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'planned_start_time' => $data['planned_start_time'] ?? null,
            'planned_end_time' => $data['planned_end_time'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'priority' => $data['priority'],
        ]);

        $keptIds = [];
        foreach ($data['subtasks'] ?? [] as $index => $subtaskData) {
            $subtaskId = $subtaskData['id'] ?? null;
            $payload = [
                'user_id' => $request->user()->id,
                'category_id' => $category->id,
                'task_group_id' => $taskGroupId,
                'parent_id' => $task->id,
                'task_date' => $task->task_date,
                'title' => trim($subtaskData['title']),
                'planned_start_time' => $subtaskData['planned_start_time'] ?? null,
                'planned_end_time' => $subtaskData['planned_end_time'] ?? null,
                'priority' => $subtaskData['priority'],
                'sort_order' => $index + 1,
            ];

            if ($subtaskId) {
                $subtask = Task::where('user_id', $request->user()->id)
                    ->where('parent_id', $task->id)
                    ->findOrFail($subtaskId);
                $subtask->update($payload);
                $keptIds[] = $subtask->id;
                continue;
            }

            $created = Task::create($payload);
            $keptIds[] = $created->id;
        }

        Task::where('user_id', $request->user()->id)
            ->where('parent_id', $task->id)
            ->when($keptIds, fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();

        return $this->taskPayload($task->fresh()->load(['subtasks', 'timeSessions']));
    }

    public function complete(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $done = $request->boolean('done', true);

        $task->update([
            'status' => $done ? 'done' : 'pending',
            'completed_at' => $done ? now() : null,
        ]);

        if (! $task->parent_id) {
            $task->subtasks()->update([
                'status' => $done ? 'done' : 'pending',
                'completed_at' => $done ? now() : null,
            ]);
        }

        return $this->taskPayload($task->fresh()->load(['subtasks', 'timeSessions']));
    }

    public function referTask(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        abort_if($task->parent_id, 422, 'فقط وظیفه اصلی قابل ارجاع است.');

        $data = $request->validate([
            'task_date' => ['required', 'date'],
        ]);

        $targetDate = Carbon::parse($data['task_date'])->toDateString();
        $sortOrder = Task::where('user_id', $request->user()->id)
            ->where('category_id', $task->category_id)
            ->whereDate('task_date', $targetDate)
            ->whereNull('parent_id')
            ->max('sort_order') + 1;

        $copy = Task::create([
            'user_id' => $task->user_id,
            'category_id' => $task->category_id,
            'task_group_id' => $task->task_group_id,
            'title' => $task->title,
            'description' => $task->description,
            'task_date' => $targetDate,
            'planned_start_time' => $task->planned_start_time,
            'planned_end_time' => $task->planned_end_time,
            'estimated_minutes' => $task->estimated_minutes,
            'manual_actual_minutes' => null,
            'priority' => $task->priority,
            'status' => 'pending',
            'progress' => 0,
            'sort_order' => $sortOrder,
            'is_recurring' => false,
            'metadata' => [
                'referred_from_id' => $task->id,
                'referred_from_date' => $this->dateKey($task->task_date),
                'is_referred_copy' => true,
            ],
        ]);

        $task->subtasks()->get()->each(function (Task $subtask, int $index) use ($copy, $targetDate) {
            Task::create([
                'user_id' => $copy->user_id,
                'category_id' => $copy->category_id,
                'task_group_id' => $copy->task_group_id,
                'parent_id' => $copy->id,
                'title' => $subtask->title,
                'description' => $subtask->description,
                'task_date' => $targetDate,
                'planned_start_time' => $subtask->planned_start_time,
                'planned_end_time' => $subtask->planned_end_time,
                'estimated_minutes' => $subtask->estimated_minutes,
                'priority' => $subtask->priority,
                'status' => 'pending',
                'sort_order' => $index + 1,
                'metadata' => [
                    'referred_from_id' => $subtask->id,
                    'referred_from_date' => $this->dateKey($subtask->task_date),
                    'is_referred_copy' => true,
                ],
            ]);
        });

        $metadata = $task->metadata ?? [];
        $metadata['referred_to_date'] = $targetDate;
        $metadata['referred_copy_id'] = $copy->id;
        $metadata['was_referred'] = true;
        $task->update(['metadata' => $metadata]);

        return [
            'source' => $this->taskPayload($task->fresh()->load(['subtasks', 'timeSessions'])),
            'copy' => $this->taskPayload($copy->load(['subtasks', 'timeSessions'])),
        ];
    }

    public function destroyTask(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        Task::where('user_id', $request->user()->id)->where('parent_id', $task->id)->delete();
        $task->delete();

        return response()->noContent();
    }

    public function timer(Request $request, Task $task, string $action)
    {
        $this->authorizeTask($request, $task);
        $userId = $request->user()->id;

        if ($action === 'start') {
            $pausedSession = TaskTimeSession::where('user_id', $userId)
                ->where('task_id', $task->id)
                ->where('status', 'paused')
                ->latest()
                ->first();

            if ($pausedSession) {
                $pausedSession->update(['status' => 'running', 'started_at' => now(), 'paused_at' => null]);
                $task->update(['status' => 'in_progress']);

                return ['timer' => $pausedSession->fresh(), 'task' => $this->taskPayload($task->load(['subtasks', 'timeSessions']))];
            }

            TaskTimeSession::where('user_id', $userId)->where('status', 'running')->get()->each(function (TaskTimeSession $runningSession) {
                $elapsed = $runningSession->duration_seconds + (int) Carbon::parse($runningSession->started_at)->diffInSeconds(now(), true);
                $runningSession->update([
                    'status' => 'paused',
                    'paused_at' => now(),
                    'duration_seconds' => $elapsed,
                ]);
            });

            $session = TaskTimeSession::create([
                'user_id' => $userId,
                'task_id' => $task->id,
                'started_at' => now(),
                'status' => 'running',
            ]);

            $task->update(['status' => 'in_progress', 'started_at' => now()]);

            return ['timer' => $session, 'task' => $this->taskPayload($task->load(['subtasks', 'timeSessions']))];
        }

        $session = TaskTimeSession::where('user_id', $userId)->where('task_id', $task->id)->latest()->firstOrFail();
        $elapsed = $session->duration_seconds;

        if ($session->status === 'running') {
            $elapsed += (int) Carbon::parse($session->started_at)->diffInSeconds(now(), true);
        }

        if ($action === 'pause') {
            $session->update(['status' => 'paused', 'paused_at' => now(), 'duration_seconds' => $elapsed]);
        }

        if ($action === 'resume') {
            $session->update(['status' => 'running', 'started_at' => now(), 'paused_at' => null, 'duration_seconds' => $elapsed]);
            $task->update(['status' => 'in_progress']);
        }

        if ($action === 'stop') {
            $session->update(['status' => 'stopped', 'ended_at' => now(), 'duration_seconds' => $elapsed]);
            $shouldComplete = $request->boolean('complete', false);
            $task->update([
                'status' => $shouldComplete ? 'done' : ($task->status === 'done' ? 'done' : 'pending'),
                'completed_at' => $shouldComplete ? now() : $task->completed_at,
            ]);
        }

        return ['timer' => $session->fresh(), 'task' => $this->taskPayload($task->load(['subtasks', 'timeSessions']))];
    }

    public function storeTimeSession(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);

        $data = $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
        ]);

        $timezone = $request->user()->timezone ?? config('app.timezone');
        $startedAt = Carbon::parse($data['started_at'], $timezone);
        $endedAt = Carbon::parse($data['ended_at'], $timezone);

        TaskTimeSession::create([
            'user_id' => $request->user()->id,
            'task_id' => $task->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'paused_at' => null,
            'duration_seconds' => (int) $startedAt->diffInSeconds($endedAt),
            'status' => 'stopped',
        ]);

        return $this->taskPayload($task->load(['subtasks', 'timeSessions']));
    }

    public function updateTimeSession(Request $request, TaskTimeSession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_unless($session->status === 'stopped', 422, 'فقط کارکردهای کامل‌شده قابل ویرایش هستند.');

        $data = $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
        ]);

        $timezone = $request->user()->timezone ?? config('app.timezone');
        $startedAt = Carbon::parse($data['started_at'], $timezone);
        $endedAt = Carbon::parse($data['ended_at'], $timezone);

        $session->update([
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'paused_at' => null,
            'duration_seconds' => (int) $startedAt->diffInSeconds($endedAt),
        ]);

        return $this->taskPayload($session->task->load(['subtasks', 'timeSessions']));
    }

    public function destroyTimeSession(Request $request, TaskTimeSession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_unless($session->status === 'stopped', 422, 'فقط کارکردهای کامل‌شده قابل حذف هستند.');

        $task = $session->task;
        $session->delete();

        return $this->taskPayload($task->load(['subtasks', 'timeSessions']));
    }

    public function storeFollowUp(Request $request)
    {
        $priorityKeys = $this->priorityKeys($request->user()->id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'follow_up_date' => ['required', 'date'],
            'follow_up_time' => ['nullable', 'date_format:H:i'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', Rule::in($priorityKeys)],
        ]);

        return FollowUp::create([...$data, 'user_id' => $request->user()->id]);
    }

    public function storeExpense(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer'],
            'financial_account_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'in:expense,income'],
            'expense_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $data['type'] ?? 'expense';
        $category = ExpenseCategory::where('user_id', $request->user()->id)
            ->where('type', $type)
            ->where('is_active', true)
            ->findOrFail($data['expense_category_id']);
        $account = FinancialAccount::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->findOrFail($data['financial_account_id']);

        $expense = Expense::create([
            ...$data,
            'user_id' => $request->user()->id,
            'expense_category_id' => $category->id,
            'financial_account_id' => $account->id,
            'type' => $type,
        ]);

        return $this->expensePayload($expense->load(['category', 'account']));
    }

    public function destroyExpense(Request $request, Expense $expense)
    {
        abort_unless($expense->user_id === $request->user()->id, 404);
        $expense->delete();

        return response()->noContent();
    }

    public function storeExpenseCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:expense,income'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $palette = $data['type'] === 'income'
            ? [['#16A34A', '#DCFCE7'], ['#0F766E', '#CCFBF1'], ['#2563EB', '#DBEAFE']]
            : [['#F43F5E', '#FFE4E6'], ['#A855F7', '#F3E8FF'], ['#FF8A3D', '#FFEDD5'], ['#14B8A6', '#DDFCF7']];
        $sortOrder = ExpenseCategory::where('user_id', $request->user()->id)->where('type', $data['type'])->max('sort_order') + 1;
        [$color, $softColor] = $palette[$sortOrder % count($palette)];
        $color = $data['color'] ?? $color;

        $category = ExpenseCategory::create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'type' => $data['type'],
            'color' => $color,
            'soft_color' => isset($data['color']) ? $this->softColor($color) : $softColor,
            'sort_order' => $sortOrder,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $category;
    }

    public function expenseCategories(Request $request)
    {
        $this->ensureExpenseCategories($request->user()->id);

        $query = ExpenseCategory::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('type')
            ->orderBy('sort_order');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        abort_unless($expenseCategory->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $expenseCategory->update([
            'name' => trim($data['name']),
            'color' => $data['color'],
            'soft_color' => $this->softColor($data['color']),
            'is_active' => $data['is_active'] ?? $expenseCategory->is_active,
        ]);

        return $expenseCategory->fresh();
    }

    public function reorderExpenseCategories(Request $request)
    {
        $data = $request->validate([
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['integer'],
        ]);

        $categories = ExpenseCategory::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['category_ids'])
            ->get()
            ->keyBy('id');

        collect($data['category_ids'])->values()->each(function ($id, int $index) use ($categories) {
            $categories->get((int) $id)?->update(['sort_order' => $index + 1]);
        });

        return $this->expenseCategories($request);
    }

    public function destroyExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        abort_unless($expenseCategory->user_id === $request->user()->id, 404);
        $expenseCategory->update(['is_active' => false]);

        return response()->noContent();
    }

    public function financialAccounts(Request $request)
    {
        $this->ensureFinancialAccounts($request->user()->id);

        return $this->financialAccountList($request->user()->id, $request->boolean('include_inactive'));
    }

    public function storeFinancialAccount(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'initial_balance' => ['nullable', 'integer', 'min:0'],
            'card_number' => ['nullable', 'string', 'max:32'],
            'sheba_number' => ['nullable', 'string', 'max:34'],
        ]);

        $sortOrder = FinancialAccount::where('user_id', $request->user()->id)->max('sort_order') + 1;

        return $this->accountPayload(FinancialAccount::create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'color' => $data['color'],
            'initial_balance' => $data['initial_balance'] ?? 0,
            'card_number' => $this->cleanNullableNumber($data['card_number'] ?? null),
            'sheba_number' => $this->cleanNullableSheba($data['sheba_number'] ?? null),
            'sort_order' => $sortOrder,
            'is_default' => false,
            'is_active' => true,
        ]));
    }

    public function reorderFinancialAccounts(Request $request)
    {
        $data = $request->validate([
            'account_ids' => ['required', 'array'],
            'account_ids.*' => ['integer'],
        ]);

        $accounts = FinancialAccount::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['account_ids'])
            ->get()
            ->keyBy('id');

        collect($data['account_ids'])->values()->each(function ($id, int $index) use ($accounts) {
            $accounts->get((int) $id)?->update(['sort_order' => $index + 1]);
        });

        return $this->financialAccountList($request->user()->id, true);
    }

    public function updateFinancialAccount(Request $request, FinancialAccount $account)
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'initial_balance' => ['nullable', 'integer', 'min:0'],
            'card_number' => ['nullable', 'string', 'max:32'],
            'sheba_number' => ['nullable', 'string', 'max:34'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account->update([
            'name' => trim($data['name']),
            'color' => $data['color'],
            'initial_balance' => $data['initial_balance'] ?? 0,
            'card_number' => $this->cleanNullableNumber($data['card_number'] ?? null),
            'sheba_number' => $this->cleanNullableSheba($data['sheba_number'] ?? null),
            'is_active' => $account->is_default ? true : ($data['is_active'] ?? $account->is_active),
        ]);

        return $this->accountPayload($account->fresh());
    }

    public function destroyFinancialAccount(Request $request, FinancialAccount $account)
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        abort_if($account->is_default, 422, 'حساب پیش‌فرض قابل حذف نیست.');

        $wallet = FinancialAccount::where('user_id', $request->user()->id)->where('is_default', true)->first();
        if ($wallet) {
            Expense::where('user_id', $request->user()->id)
                ->where('financial_account_id', $account->id)
                ->update(['financial_account_id' => $wallet->id]);
        }

        $account->update(['is_active' => false]);

        return response()->noContent();
    }

    public function storeMeal(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meal_date' => ['required', 'date'],
            'meal_time' => ['nullable', 'date_format:H:i'],
            'meal_type' => ['required', 'in:breakfast,lunch,dinner,snack,water,meal'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $sortOrder = MealEntry::where('user_id', $request->user()->id)
            ->whereDate('meal_date', $data['meal_date'])
            ->max('sort_order') + 1;

        return MealEntry::create([
            ...$data,
            'user_id' => $request->user()->id,
            'sort_order' => $sortOrder,
        ]);
    }

    public function toggleMeal(Request $request, MealEntry $meal)
    {
        abort_unless($meal->user_id === $request->user()->id, 404);
        $done = $meal->status !== 'eaten';
        $meal->update([
            'status' => $done ? 'eaten' : 'planned',
            'completed_at' => $done ? now() : null,
        ]);

        return $meal;
    }

    public function updateMeal(Request $request, MealEntry $meal)
    {
        abort_unless($meal->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meal_time' => ['nullable', 'date_format:H:i'],
            'meal_type' => ['required', 'in:breakfast,lunch,dinner,snack,water,meal'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $meal->update($data);

        return $meal->fresh();
    }

    public function destroyMeal(Request $request, MealEntry $meal)
    {
        abort_unless($meal->user_id === $request->user()->id, 404);
        $meal->delete();

        return response()->noContent();
    }

    public function reorderMeals(Request $request)
    {
        $data = $request->validate([
            'meal_ids' => ['required', 'array'],
            'meal_ids.*' => ['integer'],
        ]);

        $meals = MealEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['meal_ids'])
            ->get()
            ->keyBy('id');

        foreach ($data['meal_ids'] as $index => $mealId) {
            if ($meals->has($mealId)) {
                $meals[$mealId]->update(['sort_order' => $index + 1]);
            }
        }

        return response()->noContent();
    }

    public function reorderTasks(Request $request)
    {
        $data = $request->validate([
            'task_ids' => ['required', 'array'],
            'task_ids.*' => ['integer'],
        ]);

        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['task_ids'])
            ->get()
            ->keyBy('id');

        foreach ($data['task_ids'] as $index => $taskId) {
            if ($tasks->has($taskId)) {
                $tasks[$taskId]->update(['sort_order' => $index + 1]);
            }
        }

        return response()->noContent();
    }

    public function updateRoutine(Request $request)
    {
        $data = $request->validate([
            'routine_date' => ['required', 'date'],
            'wake_time' => ['nullable', 'date_format:H:i'],
            'sleep_time' => ['nullable', 'date_format:H:i'],
        ]);

        $routine = DailyRoutine::updateOrCreate(
            ['user_id' => $request->user()->id, 'routine_date' => $data['routine_date']],
            [
                'wake_time' => $data['wake_time'] ?? null,
                'sleep_time' => $data['sleep_time'] ?? null,
            ]
        );

        return $this->routinePayload($request->user()->id, $routine->routine_date->format('Y-m-d'));
    }

    public function toggleRoutineItem(Request $request, RoutineItem $routineItem)
    {
        abort_unless($routineItem->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'routine_date' => ['required', 'date'],
            'done' => ['required', 'boolean'],
        ]);

        $routine = DailyRoutine::firstOrCreate([
            'user_id' => $request->user()->id,
            'routine_date' => $data['routine_date'],
        ]);

        DailyRoutineCheck::updateOrCreate(
            ['daily_routine_id' => $routine->id, 'routine_item_id' => $routineItem->id],
            ['is_done' => $data['done'], 'completed_at' => $data['done'] ? now() : null]
        );

        return $this->routinePayload($request->user()->id, $data['routine_date']);
    }

    public function storeRoutineItem(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $colors = ['#22D3D0', '#A855F7', '#FF8A3D', '#16A34A', '#2563EB', '#D63384'];
        $sortOrder = RoutineItem::where('user_id', $request->user()->id)->max('sort_order') + 1;

        RoutineItem::create([
            'user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'color' => $colors[$sortOrder % count($colors)],
            'sort_order' => $sortOrder,
        ]);

        return $this->routinePayload($request->user()->id, $request->input('routine_date', now($request->user()->timezone)->toDateString()));
    }

    public function destroyRoutineItem(Request $request, RoutineItem $routineItem)
    {
        abort_unless($routineItem->user_id === $request->user()->id, 404);
        $routineItem->update(['is_active' => false]);

        return $this->routinePayload($request->user()->id, $request->query('date', now($request->user()->timezone)->toDateString()));
    }

    public function toggleFollowUp(Request $request, FollowUp $followUp)
    {
        abort_unless($followUp->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'result_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $done = $followUp->status !== 'done';
        $followUp->update([
            'status' => $done ? 'done' : 'pending',
            'completed_at' => $done ? now() : null,
            ...(array_key_exists('result_note', $data) ? ['result_note' => $data['result_note'] ?? ''] : []),
        ]);

        return $followUp->fresh();
    }

    public function updateFollowUp(Request $request, FollowUp $followUp)
    {
        abort_unless($followUp->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'follow_up_time' => ['nullable', 'date_format:H:i'],
            'result_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $followUp->update($data);

        return $followUp->fresh();
    }

    public function destroyFollowUp(Request $request, FollowUp $followUp)
    {
        abort_unless($followUp->user_id === $request->user()->id, 404);
        $followUp->delete();

        return response()->noContent();
    }

    public function review(Request $request)
    {
        $data = $request->validate([
            'review_date' => ['required', 'date'],
            'achievement' => ['nullable', 'string'],
            'improvement_note' => ['nullable', 'string'],
            'satisfaction_score' => ['nullable', 'integer', 'min:1', 'max:10'],
            'energy_score' => ['nullable', 'integer', 'min:1', 'max:10'],
            'focus_score' => ['nullable', 'integer', 'min:1', 'max:10'],
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        DB::table('daily_reviews')->updateOrInsert(
            ['user_id' => $request->user()->id, 'review_date' => $data['review_date']],
            [...$data, 'updated_at' => now(), 'created_at' => now()]
        );

        return DB::table('daily_reviews')
            ->where('user_id', $request->user()->id)
            ->whereDate('review_date', $data['review_date'])
            ->first();
    }

    public function notebookNotes(Request $request)
    {
        $userId = $request->user()->id;

        $groups = NotebookNoteGroup::query()
            ->where('user_id', $userId)
            ->with(['notes' => fn ($query) => $query->where('user_id', $userId)->orderBy('sort_order')->latest()])
            ->orderBy('sort_order')
            ->latest()
            ->get();

        return [
            'groups' => $groups->map(fn (NotebookNoteGroup $group) => $this->notebookGroupPayload($group))->values(),
        ];
    }

    public function storeNotebookNoteGroup(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', Rule::in(['text', 'code', 'terminal'])],
        ]);

        $sortOrder = NotebookNoteGroup::where('user_id', $request->user()->id)->max('sort_order') + 1;

        $group = NotebookNoteGroup::create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? '#9B5DE5',
            'icon' => $data['icon'] ?? 'text',
            'sort_order' => $sortOrder,
        ]);

        return $this->notebookGroupPayload($group->load('notes'));
    }

    public function updateNotebookNoteGroup(Request $request, NotebookNoteGroup $group)
    {
        abort_unless($group->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', Rule::in(['text', 'code', 'terminal'])],
        ]);

        $group->update([
            'name' => trim($data['name']),
            'color' => $data['color'] ?? $group->color,
            'icon' => $data['icon'] ?? $group->icon,
        ]);

        return $this->notebookGroupPayload($group->fresh('notes'));
    }

    public function destroyNotebookNoteGroup(Request $request, NotebookNoteGroup $group)
    {
        abort_unless($group->user_id === $request->user()->id, 404);
        $group->delete();

        return response()->noContent();
    }

    public function storeNotebookNote(Request $request)
    {
        $data = $this->validateNotebookNote($request);
        $group = NotebookNoteGroup::where('user_id', $request->user()->id)->findOrFail($data['notebook_note_group_id']);
        $sortOrder = NotebookNote::where('user_id', $request->user()->id)->where('notebook_note_group_id', $group->id)->max('sort_order') + 1;

        $note = NotebookNote::create([
            ...$data,
            'user_id' => $request->user()->id,
            'sort_order' => $sortOrder,
            'share_token' => $this->newNotebookShareToken(),
        ]);

        return $this->notebookNotePayload($note);
    }

    public function updateNotebookNote(Request $request, NotebookNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 404);
        $data = $this->validateNotebookNote($request);
        NotebookNoteGroup::where('user_id', $request->user()->id)->findOrFail($data['notebook_note_group_id']);

        $note->update($data);

        return $this->notebookNotePayload($note->fresh());
    }

    public function destroyNotebookNote(Request $request, NotebookNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 404);
        $note->delete();

        return response()->noContent();
    }

    public function shareNotebookNote(Request $request, NotebookNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 404);

        if (! $note->share_token || strlen($note->share_token) > 6) {
            $note->forceFill(['share_token' => $this->newNotebookShareToken()])->save();
        }

        return $this->notebookNotePayload($note->fresh());
    }

    public function publicNotebookNote(string $token)
    {
        $note = NotebookNote::where('share_token', $token)->firstOrFail();

        return $this->notebookNotePayload($note);
    }

    private function validateNotebookNote(Request $request): array
    {
        $data = $request->validate([
            'notebook_note_group_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'content' => ['nullable', 'string'],
            'content_type' => ['required', Rule::in(['text', 'code'])],
            'language' => ['nullable', 'string', 'max:40'],
            'is_important' => ['nullable', 'boolean'],
        ]);

        $data['title'] = trim($data['title']);
        $data['content'] = $data['content'] ?? '';
        $data['language'] = $data['content_type'] === 'code' ? ($data['language'] ?? 'javascript') : null;
        $data['is_important'] = (bool) ($data['is_important'] ?? false);

        return $data;
    }

    private function notebookGroupPayload(NotebookNoteGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'color' => $group->color,
            'icon' => $group->icon,
            'sort_order' => $group->sort_order,
            'notes' => $group->relationLoaded('notes')
                ? $group->notes->map(fn (NotebookNote $note) => $this->notebookNotePayload($note))->values()
                : [],
        ];
    }

    private function notebookNotePayload(NotebookNote $note): array
    {
        return [
            'id' => $note->id,
            'notebook_note_group_id' => $note->notebook_note_group_id,
            'title' => $note->title,
            'content' => $note->content ?? '',
            'content_type' => $note->content_type,
            'language' => $note->language,
            'is_important' => (bool) $note->is_important,
            'sort_order' => $note->sort_order,
            'share_token' => $note->share_token,
            'updated_at' => $note->updated_at?->toISOString(),
        ];
    }

    private function newNotebookShareToken(): string
    {
        do {
            $token = Str::random(6);
        } while (NotebookNote::where('share_token', $token)->exists());

        return $token;
    }

    private function taskPayload(Task $task): array
    {
        $actual = $this->taskActualSeconds($task);

        return [
            'id' => $task->id,
            'category_id' => $task->category_id,
            'task_group_id' => $task->task_group_id,
            'group' => $task->group ? [
                'id' => $task->group->id,
                'category_id' => $task->group->category_id,
                'name' => $task->group->name,
                'color' => $task->group->color,
                'soft_color' => $task->group->soft_color,
            ] : null,
            'parent_id' => $task->parent_id,
            'title' => $task->title,
            'description' => $task->description,
            'task_date' => $task->task_date instanceof Carbon ? $task->task_date->format('Y-m-d') : $task->task_date,
            'planned_start_time' => $task->planned_start_time ? substr($task->planned_start_time, 0, 5) : null,
            'planned_end_time' => $task->planned_end_time ? substr($task->planned_end_time, 0, 5) : null,
            'estimated_minutes' => $task->estimated_minutes,
            'priority' => $task->priority,
            'status' => $task->status,
            'actual_seconds' => $actual,
            'completed_at' => $task->completed_at,
            'metadata' => $task->metadata,
            'time_sessions' => $task->timeSessions
                ->where('status', 'stopped')
                ->sortBy('started_at')
                ->map(fn (TaskTimeSession $session) => [
                    'id' => $session->id,
                    'started_at' => $session->started_at,
                    'ended_at' => $session->ended_at,
                    'duration_seconds' => (int) $session->duration_seconds,
                ])
                ->values(),
            'subtasks' => $task->subtasks->map(fn (Task $subtask) => $this->taskPayload($subtask))->values(),
        ];
    }

    private function goalPayload(Goal $goal): array
    {
        $percent = $this->goalPercent($goal);
        $status = $this->goalStatusMeta($goal->status);

        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'description' => $goal->description,
            'type' => $goal->type,
            'category' => $goal->category,
            'color' => $goal->color,
            'soft_color' => $this->softColor($goal->color),
            'icon' => $goal->icon,
            'status' => $goal->status,
            'status_label' => $status['label'],
            'status_bg' => $status['bg'],
            'status_color' => $status['color'],
            'start_value' => (float) $goal->start_value,
            'current_value' => (float) $goal->current_value,
            'target_value' => (float) $goal->target_value,
            'unit' => $goal->unit,
            'direction' => $goal->direction,
            'deadline' => $goal->deadline ? $this->dateKey($goal->deadline) : null,
            'days_left' => $goal->deadline ? now()->startOfDay()->diffInDays($goal->deadline, false) : null,
            'why' => $goal->why,
            'next_action' => $goal->next_action ?: '—',
            'last_activity' => $goal->last_activity_label ?: '—',
            'percent' => $percent,
            'metadata' => $goal->metadata ?? [],
            'milestones' => $goal->milestones->map(fn (GoalMilestone $milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'is_done' => $milestone->is_done,
                'weight' => (float) $milestone->weight,
                'starts_on' => $milestone->starts_on ? $this->dateKey($milestone->starts_on) : null,
                'ends_on' => $milestone->ends_on ? $this->dateKey($milestone->ends_on) : null,
                'status' => $milestone->status,
                'progress' => (int) $milestone->progress,
                'dependency' => $milestone->dependency,
                'date_label' => $milestone->date_label ?: '—',
            ])->values(),
            'plan_items' => $goal->planItems->map(fn (GoalPlanItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'when' => $item->when_label ?: '—',
            ])->values(),
            'logs' => $goal->progressLogs->map(fn (GoalProgressLog $log) => [
                'id' => $log->id,
                'value' => (float) $log->value,
                'value_label' => $this->persianNumber($this->plainNumber($log->value)).' '.$goal->unit,
                'energy' => $log->energy,
                'note' => $log->note ?: '—',
                'date_label' => $log->logged_at?->locale('fa')->diffForHumans() ?: '—',
            ])->values(),
        ];
    }

    private function goalPercent(Goal $goal): int
    {
        if ($goal->type === 'milestone' && $goal->relationLoaded('milestones') && $goal->milestones->isNotEmpty()) {
            $totalWeight = max(0.01, (float) $goal->milestones->sum('weight'));
            $doneWeight = (float) $goal->milestones->sum(fn (GoalMilestone $milestone) => ((int) $milestone->progress / 100) * (float) $milestone->weight);

            return (int) max(0, min(100, round(($doneWeight / $totalWeight) * 100)));
        }

        $start = (float) $goal->start_value;
        $current = (float) $goal->current_value;
        $target = (float) $goal->target_value;

        if ($goal->direction === 'decrease') {
            if ($start <= $target) {
                $start = max($target, $current);
                $target = min((float) $goal->start_value, $current);
            }

            $total = max(0.01, $start - $target);
            return (int) max(0, min(100, round((($start - $current) / $total) * 100)));
        }

        $total = max(0.01, $target - $start);
        return (int) max(0, min(100, round((($current - $start) / $total) * 100)));
    }

    private function goalStatusMeta(string $status): array
    {
        return [
            'planned' => ['label' => 'برنامه‌ریزی‌شده', 'bg' => '#DBEAFE', 'color' => '#1D4ED8'],
            'onTrack' => ['label' => 'در مسیر', 'bg' => '#DCFCE7', 'color' => '#15803D'],
            'attention' => ['label' => 'نیازمند توجه', 'bg' => '#FEF3C7', 'color' => '#B45309'],
            'atRisk' => ['label' => 'عقب‌افتاده', 'bg' => '#FEE2E2', 'color' => '#B91C1C'],
            'paused' => ['label' => 'متوقف', 'bg' => '#F3F4F6', 'color' => '#6B7280'],
            'done' => ['label' => 'تکمیل‌شده', 'bg' => '#DCFCE7', 'color' => '#15803D'],
            'archived' => ['label' => 'بایگانی‌شده', 'bg' => '#F3F4F6', 'color' => '#6B7280'],
        ][$status] ?? ['label' => $status, 'bg' => '#F3F4F6', 'color' => '#6B7280'];
    }

    private function goalIconForType(string $type): string
    {
        return [
            'numeric' => 'weight',
            'doable' => 'product',
            'habit' => 'habit',
            'milestone' => 'business',
            'ongoing' => 'reading',
        ][$type] ?? 'target';
    }

    private function statusAfterProgress(Goal $goal, float $value): string
    {
        $goal->current_value = $value;
        if ($this->goalPercent($goal) >= 100) {
            return 'done';
        }

        return $goal->status === 'planned' ? 'onTrack' : $goal->status;
    }

    private function syncMilestoneGoalProgress(Goal $goal): void
    {
        $totalWeight = max(1, (float) $goal->milestones->sum('weight'));
        $doneWeight = (float) $goal->milestones->sum(fn (GoalMilestone $milestone) => ((int) $milestone->progress / 100) * (float) $milestone->weight);
        $status = $doneWeight >= $totalWeight ? 'done' : ($goal->status === 'planned' ? 'onTrack' : $goal->status);

        $goal->update([
            'start_value' => 0,
            'current_value' => $doneWeight,
            'target_value' => $totalWeight,
            'unit' => 'امتیاز مرحله',
            'status' => $status,
            'last_activity_label' => 'همین الان',
        ]);
    }

    private function plainNumber(mixed $value): string
    {
        $number = (float) $value;

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function persianNumber(mixed $value): string
    {
        return strtr((string) $value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }

    private function taskActualSeconds(Task $task): int
    {
        return (int) $task->timeSessions->where('status', 'stopped')->sum('duration_seconds')
            + (int) (($task->manual_actual_minutes ?? 0) * 60);
    }

    private function dateKey(mixed $date): string
    {
        return $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->toDateString();
    }

    private function expensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_category_id' => $expense->expense_category_id,
            'financial_account_id' => $expense->financial_account_id,
            'title' => $expense->title,
            'amount' => $expense->amount,
            'type' => $expense->type ?? 'expense',
            'expense_date' => $expense->expense_date instanceof Carbon ? $expense->expense_date->format('Y-m-d') : $expense->expense_date,
            'note' => $expense->note,
            'category' => $expense->category ? [
                'id' => $expense->category->id,
                'name' => $expense->category->name,
                'color' => $expense->category->color,
                'soft_color' => $expense->category->soft_color,
                'type' => $expense->category->type ?? 'expense',
            ] : null,
            'account' => $expense->account ? [
                'id' => $expense->account->id,
                'name' => $expense->account->name,
                'color' => $expense->account->color,
            ] : null,
        ];
    }

    private function routinePayload(int $userId, string $date): array
    {
        $routine = DailyRoutine::firstOrCreate(['user_id' => $userId, 'routine_date' => $date]);
        $checks = DailyRoutineCheck::where('daily_routine_id', $routine->id)->get()->keyBy('routine_item_id');
        $items = RoutineItem::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return [
            'wake_time' => $routine->wake_time ? substr($routine->wake_time, 0, 5) : null,
            'sleep_time' => $routine->sleep_time ? substr($routine->sleep_time, 0, 5) : null,
            'items' => $items->map(fn (RoutineItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'color' => $item->color,
                'is_default' => $item->is_default,
                'done' => (bool) ($checks[$item->id]?->is_done ?? false),
            ])->values(),
        ];
    }

    private function ensureExpenseCategories(int $userId): void
    {
        $groups = [
            'expense' => [
                ['name' => 'خرید روزانه', 'color' => '#14B8A6', 'soft_color' => '#DDFCF7'],
                ['name' => 'خرید عمومی', 'color' => '#A855F7', 'soft_color' => '#F3E8FF'],
                ['name' => 'خرید های شرکت', 'color' => '#0EA5E9', 'soft_color' => '#E0F2FE'],
                ['name' => 'خرید های ضروری', 'color' => '#F43F5E', 'soft_color' => '#FFE4E6'],
            ],
            'income' => [
                ['name' => 'حقوق', 'color' => '#16A34A', 'soft_color' => '#DCFCE7'],
                ['name' => 'فروش', 'color' => '#0F766E', 'soft_color' => '#CCFBF1'],
                ['name' => 'سایر درآمدها', 'color' => '#2563EB', 'soft_color' => '#DBEAFE'],
            ],
        ];

        foreach ($groups as $type => $categories) {
            if (ExpenseCategory::where('user_id', $userId)->where('type', $type)->exists()) {
                continue;
            }

            collect($categories)->each(fn (array $category, int $index) => ExpenseCategory::create([
                ...$category,
                'type' => $type,
                'user_id' => $userId,
                'sort_order' => $index + 1,
                'is_default' => true,
            ]));
        }
    }

    private function ensureFinancialAccounts(int $userId): void
    {
        if (FinancialAccount::where('user_id', $userId)->exists()) {
            $wallet = FinancialAccount::where('user_id', $userId)->where('is_default', true)->first();
            if ($wallet) {
                Expense::where('user_id', $userId)->whereNull('financial_account_id')->update(['financial_account_id' => $wallet->id]);
            }
            return;
        }

        $wallet = FinancialAccount::create([
            'user_id' => $userId,
            'name' => 'کیف پول',
            'color' => '#22D3D0',
            'initial_balance' => 0,
            'sort_order' => 1,
            'is_default' => true,
            'is_active' => true,
        ]);

        Expense::where('user_id', $userId)->whereNull('financial_account_id')->update(['financial_account_id' => $wallet->id]);
    }

    private function financialAccountList(int $userId, bool $includeInactive = false)
    {
        $this->ensureFinancialAccounts($userId);

        $query = FinancialAccount::where('user_id', $userId)->orderBy('sort_order');
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->get()->map(fn (FinancialAccount $account) => $this->accountPayload($account))->values();
    }

    private function accountPayload(FinancialAccount $account): array
    {
        $income = Expense::where('user_id', $account->user_id)
            ->where('financial_account_id', $account->id)
            ->where('type', 'income')
            ->sum('amount');
        $expense = Expense::where('user_id', $account->user_id)
            ->where('financial_account_id', $account->id)
            ->where('type', 'expense')
            ->sum('amount');

        return [
            'id' => $account->id,
            'name' => $account->name,
            'color' => $account->color,
            'initial_balance' => $account->initial_balance,
            'card_number' => $account->card_number,
            'sheba_number' => $account->sheba_number,
            'income_total' => (int) $income,
            'expense_total' => (int) $expense,
            'current_balance' => (int) $account->initial_balance + (int) $income - (int) $expense,
            'is_default' => $account->is_default,
            'is_active' => $account->is_active,
        ];
    }

    private function dailyNotePayload(?DailyNote $note): ?array
    {
        if (! $note) {
            return null;
        }

        return [
            'id' => $note->id,
            'note_date' => $this->dateKey($note->note_date),
            'body' => $note->body ?? '',
            'updated_at' => $note->updated_at,
        ];
    }

    private function obligationPayload(FinanceObligation $obligation): array
    {
        $obligation->loadMissing(['account', 'payments.expense.account']);
        $paid = (int) $obligation->payments->sum('amount');
        $remaining = max(0, (int) $obligation->total_amount - $paid);
        $installmentAmount = (int) ($obligation->installment_amount ?: $obligation->total_amount);

        return [
            'id' => $obligation->id,
            'type' => $obligation->type,
            'title' => $obligation->title,
            'party_name' => $obligation->party_name,
            'total_amount' => (int) $obligation->total_amount,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'installment_amount' => $installmentAmount,
            'installments_total' => $obligation->installments_total,
            'paid_count' => $installmentAmount > 0 ? (int) floor($paid / $installmentAmount) : 0,
            'due_day' => $obligation->due_day,
            'start_date' => $obligation->start_date ? $this->dateKey($obligation->start_date) : null,
            'due_date' => $obligation->due_date ? $this->dateKey($obligation->due_date) : null,
            'status' => $obligation->status,
            'color' => $obligation->color,
            'note' => $obligation->note,
            'progress' => $obligation->total_amount > 0 ? min(100, round(($paid / $obligation->total_amount) * 100)) : 0,
            'current_due' => $this->obligationCurrentDue($obligation),
            'account' => $obligation->account ? $this->accountPayload($obligation->account) : null,
            'payments' => $obligation->payments
                ->sortByDesc('paid_date')
                ->map(fn (FinanceObligationPayment $payment) => [
                    'id' => $payment->id,
                    'amount' => (int) $payment->amount,
                    'paid_date' => $this->dateKey($payment->paid_date),
                    'note' => $payment->note,
                    'account' => $payment->expense?->account ? $this->accountPayload($payment->expense->account) : null,
                ])
                ->values(),
        ];
    }

    private function obligationRemaining(FinanceObligation $obligation): int
    {
        $obligation->loadMissing('payments');

        return max(0, (int) $obligation->total_amount - (int) $obligation->payments->sum('amount'));
    }

    private function obligationCurrentDue(FinanceObligation $obligation): int
    {
        $remaining = $this->obligationRemaining($obligation);
        if ($obligation->type === 'installment') {
            return min($remaining, (int) ($obligation->installment_amount ?: $remaining));
        }

        return $remaining;
    }

    private function supportTicketPayload(SupportTicket $ticket, bool $includeUser = false): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'body' => $ticket->body,
            'status' => $ticket->status,
            'admin_reply' => $ticket->admin_reply,
            'created_at' => $ticket->created_at,
            'replied_at' => $ticket->replied_at,
            'user' => $includeUser && $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->name,
                'email' => $ticket->user->email,
                'phone' => $ticket->user->phone,
            ] : null,
        ];
    }

    private function cleanNullableNumber(?string $value): ?string
    {
        $clean = preg_replace('/\D+/', '', $value ?? '');

        return $clean !== '' ? $clean : null;
    }

    private function cleanNullableSheba(?string $value): ?string
    {
        $clean = strtoupper(preg_replace('/\s+/', '', $value ?? ''));

        return $clean !== '' ? $clean : null;
    }

    private function ensureCategories(int $userId): void
    {
        if (Category::where('user_id', $userId)->exists()) {
            return;
        }

        collect([
            ['name' => 'کاری', 'color' => '#2563EB', 'icon' => 'briefcase'],
            ['name' => 'ورزش', 'color' => '#F97316', 'icon' => 'activity'],
            ['name' => 'تغذیه', 'color' => '#16A34A', 'icon' => 'leaf'],
            ['name' => 'آموزش', 'color' => '#9B5DE5', 'icon' => 'book'],
            ['name' => 'زندگی', 'color' => '#22D3D0', 'icon' => 'home'],
        ])->each(fn (array $category, int $index) => Category::create([
            ...$category,
            'soft_color' => $this->softColor($category['color']),
            'user_id' => $userId,
            'sort_order' => $index + 1,
            'is_default' => true,
            'is_active' => true,
        ]));
    }

    private function ensurePrioritySettings(int $userId): void
    {
        if (PrioritySetting::where('user_id', $userId)->exists()) {
            return;
        }

        collect([
            ['key' => 'low', 'label' => 'کم', 'color' => '#6B7280'],
            ['key' => 'medium', 'label' => 'متوسط', 'color' => '#2563EB'],
            ['key' => 'high', 'label' => 'زیاد', 'color' => '#F97316'],
            ['key' => 'urgent', 'label' => 'فوری', 'color' => '#DC2626'],
        ])->each(fn (array $priority, int $index) => PrioritySetting::create([
            ...$priority,
            'soft_color' => $this->softColor($priority['color']),
            'user_id' => $userId,
            'sort_order' => $index + 1,
            'is_default' => true,
            'is_active' => true,
        ]));
    }

    private function prioritySettings(int $userId)
    {
        $this->ensurePrioritySettings($userId);

        return PrioritySetting::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    private function taskGroupsForUser(int $userId, bool $includeInactive = false)
    {
        return TaskGroup::query()
            ->where('user_id', $userId)
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get();
    }

    private function groupTaskCatalogPayload(TaskGroup $group, bool $alreadyAdded): array
    {
        return [
            'id' => $group->id,
            'category_id' => $group->category_id,
            'name' => $group->name,
            'color' => $group->color,
            'soft_color' => $group->soft_color,
            'already_added' => $alreadyAdded,
        ];
    }

    private function groupTaskProjectPayload(GroupTaskProject $project): array
    {
        $project->loadMissing(['taskGroup', 'items']);

        return [
            'id' => $project->id,
            'category_id' => $project->category_id,
            'task_group_id' => $project->task_group_id,
            'name' => $project->taskGroup?->name ?? 'بدون نام',
            'color' => $project->taskGroup?->color ?? '#2563EB',
            'soft_color' => $project->taskGroup?->soft_color ?? '#EEF2FF',
            'done_count' => $project->items->where('is_done', true)->count(),
            'total_count' => $project->items->count(),
            'items' => $project->items->map(fn (GroupTaskItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'is_done' => $item->is_done,
                'sort_order' => $item->sort_order,
            ])->values(),
        ];
    }

    private function validatedTaskGroupId(int $userId, int $categoryId, mixed $taskGroupId): ?int
    {
        if (! $taskGroupId) {
            return null;
        }

        return TaskGroup::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->findOrFail((int) $taskGroupId)
            ->id;
    }

    private function priorityKeys(int $userId): array
    {
        return $this->prioritySettings($userId)->pluck('key')->all();
    }

    private function softColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('#%02X%02X%02X',
            (int) round($r + (255 - $r) * .84),
            (int) round($g + (255 - $g) * .84),
            (int) round($b + (255 - $b) * .84),
        );
    }

    private function ensureRoutineItems(int $userId): void
    {
        if (RoutineItem::where('user_id', $userId)->exists()) {
            return;
        }

        collect([
            ['title' => 'نماز صبح', 'color' => '#2563EB'],
            ['title' => 'نماز ظهر', 'color' => '#22D3D0'],
            ['title' => 'نماز عصر', 'color' => '#A855F7'],
            ['title' => 'مرتب کردن میز کار', 'color' => '#FF8A3D'],
            ['title' => 'تمیز کردن شرکت', 'color' => '#16A34A'],
            ['title' => 'خوردن ۵ لیوان آب', 'color' => '#0EA5E9'],
            ['title' => 'خوردن کراتین', 'color' => '#D63384'],
            ['title' => 'خوردن مکمل‌ها', 'color' => '#F43F5E'],
        ])->each(fn (array $item, int $index) => RoutineItem::create([
            ...$item,
            'user_id' => $userId,
            'sort_order' => $index + 1,
            'is_default' => true,
        ]));
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        abort_unless($task->user_id === $request->user()->id, 404);
    }
}
