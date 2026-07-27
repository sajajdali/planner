<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PlannerController;
use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');

Route::prefix('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
    Route::post('/phone-code', [AuthController::class, 'sendPhoneCode'])->middleware('guest');
    Route::post('/phone-login', [AuthController::class, 'phoneLogin'])->middleware('guest');
    Route::post('/phone-register', [AuthController::class, 'phoneRegister'])->middleware('guest');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/profile', [AuthController::class, 'updateProfile']);
        Route::get('/categories', [PlannerController::class, 'categories']);
        Route::post('/categories', [PlannerController::class, 'storeCategory']);
        Route::post('/categories/reorder', [PlannerController::class, 'reorderCategories']);
        Route::put('/categories/{category}', [PlannerController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [PlannerController::class, 'destroyCategory']);
        Route::get('/priorities', [PlannerController::class, 'priorities']);
        Route::post('/priorities', [PlannerController::class, 'storePriority']);
        Route::put('/priorities/{priority}', [PlannerController::class, 'updatePriority']);
        Route::delete('/priorities/{priority}', [PlannerController::class, 'destroyPriority']);
        Route::get('/daily-planner', [PlannerController::class, 'daily']);
        Route::get('/monthly-report', [PlannerController::class, 'monthlyReport']);
        Route::get('/finance-dashboard', [PlannerController::class, 'financeDashboard']);
        Route::post('/finance-obligations', [PlannerController::class, 'storeFinanceObligation']);
        Route::post('/finance-obligations/{obligation}/pay', [PlannerController::class, 'payFinanceObligation']);
        Route::delete('/finance-obligation-payments/{payment}', [PlannerController::class, 'destroyFinanceObligationPayment']);
        Route::get('/support-tickets', [PlannerController::class, 'supportTickets']);
        Route::post('/support-tickets', [PlannerController::class, 'storeSupportTicket']);
        Route::delete('/support-tickets/{ticket}', [PlannerController::class, 'destroySupportTicket']);
        Route::get('/admin/support-tickets', [PlannerController::class, 'adminSupportTickets']);
        Route::put('/admin/support-tickets/{ticket}/reply', [PlannerController::class, 'replySupportTicket']);
        Route::post('/tasks', [PlannerController::class, 'storeTask']);
        Route::put('/tasks/{task}', [PlannerController::class, 'updateTask']);
        Route::post('/tasks/reorder', [PlannerController::class, 'reorderTasks']);
        Route::post('/tasks/{task}/complete', [PlannerController::class, 'complete']);
        Route::post('/tasks/{task}/refer', [PlannerController::class, 'referTask']);
        Route::delete('/tasks/{task}', [PlannerController::class, 'destroyTask']);
        Route::post('/tasks/{task}/timer/{action}', [PlannerController::class, 'timer'])->whereIn('action', ['start', 'pause', 'resume', 'stop']);
        Route::post('/follow-ups', [PlannerController::class, 'storeFollowUp']);
        Route::post('/follow-ups/{followUp}/toggle', [PlannerController::class, 'toggleFollowUp']);
        Route::post('/expenses', [PlannerController::class, 'storeExpense']);
        Route::delete('/expenses/{expense}', [PlannerController::class, 'destroyExpense']);
        Route::get('/expense-categories', [PlannerController::class, 'expenseCategories']);
        Route::post('/expense-categories', [PlannerController::class, 'storeExpenseCategory']);
        Route::post('/expense-categories/reorder', [PlannerController::class, 'reorderExpenseCategories']);
        Route::put('/expense-categories/{expenseCategory}', [PlannerController::class, 'updateExpenseCategory']);
        Route::delete('/expense-categories/{expenseCategory}', [PlannerController::class, 'destroyExpenseCategory']);
        Route::get('/financial-accounts', [PlannerController::class, 'financialAccounts']);
        Route::post('/financial-accounts', [PlannerController::class, 'storeFinancialAccount']);
        Route::post('/financial-accounts/reorder', [PlannerController::class, 'reorderFinancialAccounts']);
        Route::put('/financial-accounts/{account}', [PlannerController::class, 'updateFinancialAccount']);
        Route::delete('/financial-accounts/{account}', [PlannerController::class, 'destroyFinancialAccount']);
        Route::put('/daily-note', [PlannerController::class, 'updateDailyNote']);
        Route::post('/meals', [PlannerController::class, 'storeMeal']);
        Route::put('/meals/{meal}', [PlannerController::class, 'updateMeal']);
        Route::post('/meals/reorder', [PlannerController::class, 'reorderMeals']);
        Route::post('/meals/{meal}/toggle', [PlannerController::class, 'toggleMeal']);
        Route::put('/routine', [PlannerController::class, 'updateRoutine']);
        Route::post('/routine-items', [PlannerController::class, 'storeRoutineItem']);
        Route::post('/routine-items/{routineItem}/toggle', [PlannerController::class, 'toggleRoutineItem']);
        Route::delete('/routine-items/{routineItem}', [PlannerController::class, 'destroyRoutineItem']);
        Route::post('/daily-reviews', [PlannerController::class, 'review']);
    });
});
