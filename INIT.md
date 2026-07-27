# Planner Project Init

This file is the working context for the daily planner and task management system.
The full Persian product specification is stored at:

- `docs/project-specification.md`

## Product Goal

Build a real production-oriented daily planning and task management system, not a prototype.
Users must be able to plan each day, organize tasks by category, schedule planned time, track actual time with timers, manage subtasks, follow-ups, reminders, backlog items, recurring tasks, and review daily performance.

The product must feel simple, fast, professional, motivational, Persian-first, RTL-first, responsive, and suitable for daily use.

## Stack

Backend:

- Laravel
- REST API
- MySQL
- Laravel Sanctum authentication
- Form Requests
- API Resources
- Policies
- Service Layer where business logic is meaningful
- Repository Pattern only where it removes real duplication or complexity
- Queues for notifications and long-running work
- Scheduler for reminders and timed jobs

Frontend:

- Vue 3
- Composition API
- TypeScript
- Pinia
- Vue Router
- Axios
- Responsive UI for desktop, tablet, and mobile
- Persian RTL interface
- PWA support later

## Core Modules

- Authentication
- Categories
- Daily planner
- Tasks
- Subtasks using `tasks.parent_id`
- Timer and time sessions
- Follow-ups
- Reminders
- Daily reports
- Daily reviews
- Backlog
- Recurring tasks
- Tags
- Activity logs
- Settings

## Default Categories

Initial categories:

- کاری: blue
- ورزش: orange/red
- تغذیه: green
- آموزش: purple
- زندگی: yellow/turquoise

Each category needs title, color, icon, sort order, active state, daily time total, task counts, completed count, remaining count, and daily progress.
Architecture must allow users to add custom categories later.

## Key UX Rules

- Main daily page must load by selected date without full page reload.
- Date navigation needs previous day, next day, today, Jalali date, weekday, and date picker.
- Views: category view, list view, timeline view, remaining tasks, completed tasks.
- Tasks can be scheduled, unscheduled, timed, manually logged, or completed without time.
- Only one active timer is allowed in phase one.
- Timer state must survive refresh and use backend as source of truth.
- Completion and drag/drop should use optimistic UI with rollback on API error.
- Persian and RTL must be first-class from the beginning.
- No hard-coded product data in Vue components; data should come from API and seeders.

## Backend Data Model From Spec

Primary tables:

- `users`
- `categories`
- `tasks`
- `task_time_sessions`
- `reminders`
- `follow_ups`
- `daily_reviews`
- `tags`
- `task_tag`
- `task_activity_logs`
- `task_recurrences`

Important rules:

- Store dates in database as Gregorian/UTC.
- Display Jalali dates only in UI.
- Respect user timezone in calculations.
- Scope all queries by `user_id`.
- Use Soft Deletes where deletion is needed.
- Use Policies for authorization.
- Avoid N+1 queries.
- Keep reporting logic in a dedicated service.
- Feature tests for endpoints.
- Unit tests for timer and reporting logic.

## API Surface

Required API groups:

- Auth: login, logout, current user
- Categories: index, create, update, delete, reorder
- Daily planner: daily data, summary, timeline
- Tasks: CRUD, complete, reopen, move, reorder, bulk move, duplicate
- Subtasks: create, update, delete, complete, reorder
- Timer: start, pause, resume, stop, active timer, sessions, manual time
- Follow-ups: CRUD, complete, reschedule
- Reports: daily, weekly, monthly, categories
- Daily reviews: create, update

## Suggested Vue Structure

Pages/components:

- `DailyPlannerPage.vue`
- `PlannerHeader.vue`
- `DateNavigator.vue`
- `DailySummary.vue`
- `CategorySection.vue`
- `CategoryProgress.vue`
- `TaskCard.vue`
- `TaskQuickAdd.vue`
- `TaskFormModal.vue`
- `TaskCheckbox.vue`
- `TaskTimer.vue`
- `ActiveTimerBar.vue`
- `SubtaskList.vue`
- `SubtaskItem.vue`
- `DailyTimeline.vue`
- `FollowUpSection.vue`
- `FollowUpCard.vue`
- `DailyReport.vue`
- `DailyReviewForm.vue`
- `ProgressCircle.vue`
- `TimeDistributionChart.vue`
- `MoveTaskModal.vue`
- `ReminderModal.vue`
- `ConfirmDialog.vue`

Pinia stores:

- `useAuthStore`
- `usePlannerStore`
- `useTaskStore`
- `useCategoryStore`
- `useTimerStore`
- `useFollowUpStore`
- `useReportStore`
- `useSettingsStore`
- `useNotificationStore`

## Implementation Phases

Phase 1:

- Authentication
- Five default categories
- Daily planner
- Create, edit, delete tasks
- Complete/reopen tasks
- Subtasks
- Date selection
- Simple daily report

Phase 2:

- Full timer
- Time sessions
- Daily timeline
- Drag and drop
- Follow-ups
- In-app notifications

Phase 3:

- Recurring tasks
- Weekly and monthly reports
- Charts
- Backlog
- Advanced settings
- Dark mode
- PWA and push notifications

## Build Order

1. Present and lock the architecture and folder structure.
2. Implement database and backend.
3. Implement frontend.
4. Connect frontend fully to API.
5. Add loading, empty, and error states.
6. Add tests and seed data.
7. Update README and `.env.example`.

