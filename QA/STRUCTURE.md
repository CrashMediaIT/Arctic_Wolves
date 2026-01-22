# Arctic Wolves - Application Structure Document

**Version:** 1.0  
**Created:** January 22, 2026  
**Purpose:** Master layout of the entire Arctic Wolves application documenting navigation, dependencies, and file structure

---

## Table of Contents

1. [Application Overview](#application-overview)
2. [Navigation Hierarchy](#navigation-hierarchy)
3. [Page Dependencies](#page-dependencies)
4. [Database Schema Cross-Reference](#database-schema-cross-reference)
5. [Process Handlers](#process-handlers)
6. [JavaScript Dependencies](#javascript-dependencies)
7. [File Structure](#file-structure)

---

## Application Overview

**Platform:** PHP-based Hockey Coaching Management System  
**Database:** MySQL  
**Frontend:** HTML5, CSS3, JavaScript (Vanilla)  
**Authentication:** Session-based  
**Roles:** Athlete, Coach, Admin, Parent, Health Coach, Team Coach

---

## Navigation Hierarchy

### Entry Points

| File | Purpose | Database Dependencies | JavaScript Dependencies |
|------|---------|----------------------|------------------------|
| `index.php` | Main entry point, redirects to dashboard or marketing page | `users` (session check) | None |
| `index_default.php` | Marketing/landing page for non-authenticated users | None | None |
| `login.php` | User authentication page | None directly (processed by process_login.php) | None |
| `dashboard.php` | Main application dashboard with routing | All tables via views | `app.js` |

### Main Menu (All Users)

#### 1. Home
- **Route:** `?page=home`
- **View:** `views/home.php`
- **Database Tables:** 
  - `users` (user profile)
  - `sessions` (upcoming sessions)
  - `notifications` (user notifications)
  - `goals` (active goals)
  - `performance_stats` (recent stats)
- **JavaScript:** `app.js`
- **Process Files:** None (read-only)

#### 2. Performance Stats
- **Route:** `?page=stats`
- **View:** `views/stats.php`
- **Database Tables:**
  - `performance_stats`
  - `athlete_stats`
  - `testing_results`
  - `users`
- **JavaScript:** Chart.js (CDN), `app.js`
- **Process Files:** `process_stats_update.php`, `process_stats_bulk_update.php`

#### 3. Sessions (Parent with Tabs)
- **Route:** `?page=sessions`, `?page=upcoming_sessions`, `?page=booking`
- **Parent View:** `views/sessions.php`
- **Child Views:**
  - `views/sessions_upcoming.php` (Upcoming Sessions tab)
  - `views/sessions_booking.php` (Booking tab)
- **Database Tables:**
  - `sessions`
  - `session_types`
  - `bookings`
  - `waitlists`
  - `session_attendance`
  - `locations`
  - `coach_availability`
- **JavaScript:** `calendar.js`, `app.js`
- **Process Files:** `process_booking.php`, `process_create_session.php`, `process_edit_session.php`

#### 4. Video (Parent with Tabs)
- **Route:** `?page=video`, `?page=drill_review`, `?page=coaches_reviews`
- **Parent View:** `views/video.php`
- **Child Views:**
  - `views/video_drill_review.php` (Drill Review tab)
  - `views/video_coach_reviews.php` (Coaches Reviews tab)
- **Database Tables:**
  - `videos`
  - `evaluation_media`
  - `users`
  - `drills` (for drill association)
- **JavaScript:** Video player (HTML5), `app.js`
- **Process Files:** None (uses file upload validator)

#### 5. Health (Parent with Tabs)
- **Route:** `?page=health`, `?page=strength_conditioning`, `?page=nutrition`
- **Parent View:** `views/health.php`
- **Child Views:**
  - `views/health_workouts.php` (Strength & Conditioning tab)
  - `views/health_nutrition.php` (Nutrition tab)
- **Database Tables:**
  - `workouts`, `workout_plans`, `workout_plan_exercises`, `exercises`
  - `nutrition_plans`, `nutrition_plan_meals`, `foods`, `food_library`
  - `athlete_workout_assignments`, `athlete_nutrition_assignments`
- **JavaScript:** `app.js`
- **Process Files:** None directly (uses admin configuration)

---

### Team Section (Team Coaches Only)

#### Team Roster
- **Route:** `?page=team_roster`
- **View:** `views/team_roster.php`
- **Database Tables:**
  - `teams`
  - `team_roster`
  - `team_coach_assignments`
  - `users` (athlete data)
  - `team_stats`
- **JavaScript:** `app.js`
- **Process Files:** `process_admin_team_coaches.php`

---

### Coaches Corner (Coaches, Health Coaches, Admins)

#### 1. Drills (Parent with Tabs)
- **Route:** `?page=drills`, `?page=drill_library`, `?page=create_drill`, `?page=import_drill`
- **Parent View:** `views/drills.php`
- **Child Views:**
  - `views/drills_library.php` (Library tab)
  - `views/drills_create.php` (Create a Drill tab)
  - `views/drills_import.php` (Import a Drill tab)
- **Database Tables:**
  - `drills`
  - `drill_categories`
  - `drill_tags`
- **JavaScript:** `app.js`
- **Process Files:** `process_drills.php`, `process_library.php`

#### 2. Practice Plans (Parent with Tabs)
- **Route:** `?page=practice`, `?page=practice_library`, `?page=create_practice`
- **Parent View:** `views/practice.php`
- **Child Views:**
  - `views/practice_library.php` (Library tab)
  - `views/practice_create.php` (Create a Practice tab)
- **Database Tables:**
  - `practice_plans`
  - `practice_plan_categories`
  - `practice_plan_drills`
  - `session_practice_plans` (linking sessions to plans)
- **JavaScript:** `app.js`
- **Process Files:** `process_practice_plans.php`, `process_plan_categories.php`

#### 3. Roster
- **Route:** `?page=roster`
- **View:** `views/coach_roster.php`
- **Database Tables:**
  - `users` (role='athlete')
  - `managed_athletes` (coach-athlete assignments)
  - `athlete_stats`
  - `athlete_teams`
- **JavaScript:** `app.js`
- **Process Files:** `process_manage_athletes.php`, `process_create_athlete.php`, `process_coach_action.php`

#### 4. Travel (Parent with Tabs)
- **Route:** `?page=travel`, `?page=mileage`
- **Parent View:** `views/travel.php`
- **Child Views:**
  - `views/travel_mileage.php` (Mileage tab)
- **Database Tables:**
  - Currently uses expenses table for mileage tracking
- **JavaScript:** `app.js`
- **Process Files:** `process_mileage.php`, `process_expenses.php`

---

### Accounting & Reports (Admins Only)

#### 1. Accounting Dashboard
- **Route:** `?page=accounting_dashboard`
- **View:** `views/accounting_dashboard.php`
- **Database Tables:**
  - `transactions`
  - `payments`
  - `invoices`
  - `expenses`
  - `user_packages`
  - `packages`
- **JavaScript:** Chart.js (CDN), `app.js`
- **Process Files:** None (read-only dashboard)

#### 2. Billing Dashboard
- **Route:** `?page=billing_dashboard`
- **View:** `views/accounting_billing.php`
- **Database Tables:**
  - `invoices`
  - `invoice_items`
  - `payments`
  - `users`
- **JavaScript:** `app.js`
- **Process Files:** None (invoice generation handled elsewhere)

#### 3. Reports
- **Route:** `?page=reports`
- **View:** `views/accounting_reports.php`
- **Database Tables:**
  - `reports`
  - All tables (depending on report type)
- **JavaScript:** Chart.js (CDN), `app.js`
- **Process Files:** `process_reports.php`

#### 4. Schedules
- **Route:** `?page=schedules`
- **View:** `views/accounting_schedules.php`
- **Database Tables:**
  - `report_schedules`
  - `scheduled_reports`
- **JavaScript:** `calendar.js`, `app.js`
- **Process Files:** `process_reports.php`

#### 5. Credits & Refunds
- **Route:** `?page=credits_refunds`
- **View:** `views/accounting_credits.php`
- **Database Tables:**
  - `credits_refunds`
  - `refunds`
  - `user_credits`
  - `user_package_credits`
- **JavaScript:** `app.js`
- **Process Files:** `process_refunds.php`

#### 6. Expenses
- **Route:** `?page=expenses`
- **View:** `views/accounting_expenses.php`
- **Database Tables:**
  - `expenses`
  - `expense_categories`
- **JavaScript:** `app.js`
- **Process Files:** `process_expenses.php`

#### 7. Products
- **Route:** `?page=products`
- **View:** `views/accounting_products.php`
- **Database Tables:**
  - `packages`
  - `package_sessions`
- **JavaScript:** `app.js`
- **Process Files:** `process_packages.php`

---

### HR (Admins Only)

#### Termination
- **Route:** `?page=termination`
- **View:** `views/hr_termination.php`
- **Database Tables:**
  - `employee_terminations`
  - `users`
- **JavaScript:** `app.js`
- **Process Files:** `process_coach_termination.php`

---

### Administration (Admins Only)

#### 1. All Users
- **Route:** `?page=all_users`
- **View:** `views/admin_users.php`
- **Database Tables:**
  - `users`
  - `permissions`
  - `user_permissions`
  - `role_permissions`
- **JavaScript:** `app.js`
- **Process Files:** `process_admin_action.php`, `process_permissions.php`

#### 2. Categories
- **Route:** `?page=categories`
- **View:** `views/admin_categories.php`
- **Database Tables:**
  - `drill_categories`
  - `practice_plan_categories`
  - `expense_categories`
  - `eval_categories`
  - `workout_plan_categories`
  - `nutrition_plan_categories`
- **JavaScript:** `app.js`
- **Process Files:** `process_plan_categories.php`

#### 3. Eval Framework
- **Route:** `?page=eval_framework`
- **View:** `views/admin_eval_framework.php`
- **Database Tables:**
  - `eval_categories`
  - `eval_skills`
  - `athlete_evaluations`
- **JavaScript:** `app.js`
- **Process Files:** `process_eval_framework.php`, `process_eval_skills.php`

#### 4. System Notification
- **Route:** `?page=system_notification`
- **View:** `views/admin_notifications.php`
- **Database Tables:**
  - `system_notifications`
  - `notifications`
- **JavaScript:** `app.js`
- **Process Files:** `process_system_notifications.php`

#### 5. Audit Log
- **Route:** `?page=audit_log`
- **View:** `views/admin_audit_log.php`
- **Database Tables:**
  - `audit_logs`
  - `users`
- **JavaScript:** `app.js`
- **Process Files:** `process_audit_restore.php`

#### 6. Cron Jobs
- **Route:** `?page=cron_jobs`
- **View:** `views/admin_cron_jobs.php`
- **Database Tables:**
  - `cron_jobs`
- **JavaScript:** `app.js`
- **Process Files:** `process_cron_jobs.php`

#### 7. System Tools
- **Route:** `?page=system_tools`
- **View:** `views/admin_system_tools.php`
- **Database Tables:**
  - `system_settings`
  - `database_maintenance_logs`
  - `backup_jobs`
- **JavaScript:** `app.js`
- **Process Files:** `process_database_backup.php`, `process_database_restore.php`, `process_feature_import.php`

---

### User Menu (All Users)

#### 1. Profile
- **Route:** `?page=profile`
- **View:** `views/profile.php`
- **Database Tables:**
  - `users`
  - `file_uploads` (profile image)
- **JavaScript:** `app.js`
- **Process Files:** `process_profile_update.php`

#### 2. Settings
- **Route:** `?page=settings`
- **View:** `views/settings.php`
- **Database Tables:**
  - `users`
  - `system_settings`
- **JavaScript:** `app.js`
- **Process Files:** `process_settings.php`

#### 3. Logout
- **Route:** Direct link
- **File:** `logout.php`
- **Database Tables:** None (session destroy)
- **JavaScript:** None
- **Process Files:** None

---

## Page Dependencies

### Additional Views Not in Main Navigation

These views are accessed through specific contexts or as sub-pages:

#### Athlete Management
- **`views/athletes.php`** - List all athletes (admin/coach view)
  - Tables: `users`, `athlete_stats`, `athlete_teams`
  - Process: `process_manage_athletes.php`
  
- **`views/athlete_detail.php`** - Individual athlete profile
  - Tables: `users`, `performance_stats`, `goals`, `athlete_evaluations`, `sessions`
  - Process: Multiple (stats, goals, evaluations)

- **`views/athlete_evaluations.php`** - Athlete evaluation details
  - Tables: `athlete_evaluations`, `evaluation_scores`, `evaluation_media`
  - Process: `process_evaluations.php`

- **`views/athlete_goals.php`** - Athlete goals management
  - Tables: `goals`, `goal_steps`, `goal_progress`
  - Process: `process_goals.php`

- **`views/manage_athletes.php`** - Athlete management interface
  - Tables: `users`, `managed_athletes`, `parent_athlete_relationships`
  - Process: `process_manage_athletes.php`, `process_create_athlete.php`

#### Sessions Detail
- **`views/create_session.php`** - Create new session form
  - Tables: `sessions`, `session_types`, `locations`, `coach_availability`
  - Process: `process_create_session.php`

- **`views/session_detail.php`** - Individual session details
  - Tables: `sessions`, `session_attendance`, `session_feedback`, `bookings`
  - Process: `process_edit_session.php`

- **`views/session_history.php`** - Historical sessions view
  - Tables: `sessions`, `session_attendance`
  - Process: None (read-only)

#### Goals & Evaluations
- **`views/goals.php`** - Goals management page
  - Tables: `goals`, `goal_steps`, `goal_progress`, `goal_history`
  - Process: `process_goals.php`, `process_goal_templates.php`

- **`views/coach_goals.php`** - Coach view of athlete goals
  - Tables: `goals`, `goal_evaluations`, `goal_eval_approvals`
  - Process: `process_eval_goals.php`, `process_eval_goal_approval.php`

- **`views/coach_evaluations.php`** - Coach evaluations interface
  - Tables: `athlete_evaluations`, `evaluation_scores`, `eval_skills`
  - Process: `process_evaluations.php`, `process_eval_skills.php`

- **`views/evaluations_goals.php`** - Goals evaluation interface
  - Tables: `goal_evaluations`, `goal_eval_steps`, `goal_eval_progress`
  - Process: `process_eval_goals.php`

- **`views/evaluations_skills.php`** - Skills evaluation interface
  - Tables: `athlete_evaluations`, `evaluation_scores`, `eval_skills`
  - Process: `process_eval_skills.php`

#### Library Management
- **`views/library_sessions.php`** - Session templates library
  - Tables: `session_templates`
  - Process: `process_library.php`

- **`views/library_workouts.php`** - Workout templates library
  - Tables: `workout_templates`, `workout_template_items`, `exercises`
  - Process: `process_library.php`

- **`views/library_nutrition.php`** - Nutrition templates library
  - Tables: `nutrition_templates`, `nutrition_template_items`, `food_library`
  - Process: `process_library.php`

#### Admin Configuration Pages
- **`views/admin_age_skill.php`** - Age group and skill level management
  - Tables: `age_groups`, `skill_levels`
  - Process: `process_admin_age_skill.php`

- **`views/admin_packages.php`** - Package configuration
  - Tables: `packages`, `package_sessions`
  - Process: `process_packages.php`

- **`views/admin_session_types.php`** - Session type management
  - Tables: `session_types`
  - Process: `process_admin_action.php`

- **`views/admin_locations.php`** - Location management
  - Tables: `locations`
  - Process: `process_admin_action.php`

- **`views/admin_discounts.php`** - Discount code management
  - Tables: `discount_codes`
  - Process: `process_admin_action.php`

- **`views/admin_permissions.php`** - Permission management
  - Tables: `permissions`, `role_permissions`, `user_permissions`
  - Process: `process_permissions.php`

- **`views/admin_theme_settings.php`** - Theme customization
  - Tables: `theme_settings`, `system_settings`
  - Process: `process_settings.php`

- **`views/admin_database_tools.php`** - Database management tools
  - Tables: All (backup/restore)
  - Process: `process_database_backup.php`, `process_database_restore.php`

- **`views/admin_database_backup.php`** - Database backup interface
  - Tables: `backup_jobs`, `backup_history`
  - Process: `process_database_backup.php`

- **`views/admin_database_restore.php`** - Database restore interface
  - Tables: `backup_history`
  - Process: `process_database_restore.php`

- **`views/admin_system_check.php`** - System health check
  - Tables: `system_settings`, `cron_jobs`, `security_scans`
  - Process: None (diagnostic only)

- **`views/admin_feature_import.php`** - Feature import from IHS
  - Tables: Multiple (depends on import)
  - Process: `process_feature_import.php`, `process_ihs_import.php`

- **`views/admin_team_coaches.php`** - Team coach assignments
  - Tables: `teams`, `team_coach_assignments`, `users`
  - Process: `process_admin_team_coaches.php`

- **`views/admin_system_notifications.php`** - System notification management
  - Tables: `system_notifications`
  - Process: `process_system_notifications.php`

#### Reports & Analytics
- **`views/reports.php`** - General reports interface
  - Tables: All (depending on report)
  - Process: `process_reports.php`

- **`views/report_view.php`** - Individual report viewer
  - Tables: `reports`
  - Process: None (display only)

- **`views/reports_income.php`** - Income reports
  - Tables: `transactions`, `payments`, `invoices`
  - Process: `process_reports.php`

- **`views/reports_athlete.php`** - Athlete reports
  - Tables: `users`, `athlete_stats`, `performance_stats`
  - Process: `process_reports.php`

- **`views/scheduled_reports.php`** - Scheduled report management
  - Tables: `report_schedules`, `scheduled_reports`
  - Process: `process_reports.php`

- **`views/email_logs.php`** - Email log viewer
  - Tables: `email_logs`
  - Process: None (read-only)

#### Billing & Payments
- **`views/packages.php`** - Package selection/purchase
  - Tables: `packages`, `package_sessions`, `user_packages`
  - Process: `process_purchase_package.php`

- **`views/payment_history.php`** - Payment history
  - Tables: `payments`, `transactions`, `invoices`
  - Process: None (read-only)

- **`views/user_credits.php`** - User credits management
  - Tables: `user_credits`, `user_package_credits`
  - Process: `process_refunds.php`

- **`views/refunds.php`** - Refunds interface
  - Tables: `refunds`, `credits_refunds`
  - Process: `process_refunds.php`

- **`views/billing_dashboard.php`** - Billing overview (possible duplicate)
  - Tables: `invoices`, `payments`
  - Process: None

#### Accounting Extended
- **`views/accounting.php`** - Main accounting page (possible parent)
  - Tables: Multiple accounting tables
  - Process: Multiple

- **`views/accounts_payable.php`** - Accounts payable
  - Tables: `expenses`, `invoices`
  - Process: `process_expenses.php`

- **`views/expense_categories.php`** - Expense category management
  - Tables: `expense_categories`
  - Process: `process_expenses.php`

#### Health & Fitness Extended
- **`views/workouts.php`** - Workout management
  - Tables: `workouts`, `workout_plans`, `user_workouts`
  - Process: None directly

- **`views/nutrition.php`** - Nutrition planning
  - Tables: `nutrition_plans`, `foods`
  - Process: None directly

#### Other Pages
- **`views/parent_home.php`** - Parent-specific home page
  - Tables: `users`, `parent_athlete_relationships`, `sessions`, `notifications`
  - Process: None (read-only)

- **`views/notifications.php`** - Notifications center
  - Tables: `notifications`, `system_notifications`
  - Process: None (mark as read handled elsewhere)

- **`views/testing.php`** - Testing/diagnostics page
  - Tables: Testing data
  - Process: Various (testing purposes)

- **`views/ihs_import.php`** - IHS import interface
  - Tables: Multiple (depends on import)
  - Process: `process_ihs_import.php`

- **`views/user_permissions.php`** - User-specific permissions
  - Tables: `user_permissions`, `permissions`
  - Process: `process_permissions.php`

- **`views/mileage_tracker.php`** - Mileage tracking (possible duplicate of travel_mileage)
  - Tables: `expenses` (mileage as expense type)
  - Process: `process_mileage.php`

- **`views/practice_plans.php`** - Practice plans list (possible duplicate)
  - Tables: `practice_plans`
  - Process: `process_practice_plans.php`

- **`views/schedule.php`** - Schedule view
  - Tables: `sessions`, `events`
  - Process: None

- **`views/admin_coach_termination.php`** - Coach termination (duplicate of hr_termination)
  - Tables: `employee_terminations`, `users`
  - Process: `process_coach_termination.php`

- **`views/admin_audit_logs.php`** - Audit logs (possible duplicate)
  - Tables: `audit_logs`
  - Process: None

---

## Database Schema Cross-Reference

### Core Tables (Referenced by Most Views)

1. **`users`** - User accounts and profiles
   - Used by: Almost all views
   - Key columns: id, email, role, first_name, last_name, is_active

2. **`permissions`** - System permissions
   - Used by: Admin views, dashboard
   - Key columns: id, name, description

3. **`audit_logs`** - Activity tracking
   - Used by: Admin audit views
   - Key columns: id, user_id, action, table_name, created_at

4. **`system_settings`** - Application configuration
   - Used by: Settings, admin tools
   - Key columns: setting_key, setting_value

### Session Management Tables

5. **`sessions`** - Training sessions
   - Used by: Sessions views, booking, dashboard, stats
   - Key columns: id, session_type_id, coach_id, location_id, date, time

6. **`session_types`** - Session categories
   - Used by: Session creation, admin config
   - Key columns: id, name, default_price

7. **`bookings`** - Session bookings
   - Used by: Booking views, session detail
   - Key columns: id, user_id, session_id, status

8. **`session_attendance`** - Attendance tracking
   - Used by: Session detail, stats
   - Key columns: id, session_id, user_id, status

### Practice & Training Tables

9. **`practice_plans`** - Practice plan library
   - Used by: Practice views, session planning
   - Key columns: id, name, category_id, created_by

10. **`drills`** - Drill library
    - Used by: Drill views, practice planning
    - Key columns: id, name, category_id, description

11. **`workout_plans`** - Workout programs
    - Used by: Health/fitness views
    - Key columns: id, name, category_id

12. **`nutrition_plans`** - Nutrition programs
    - Used by: Nutrition views
    - Key columns: id, name, category_id

### Goals & Evaluation Tables

13. **`goals`** - Athlete goals
    - Used by: Goals views, home dashboard
    - Key columns: id, user_id, title, status

14. **`athlete_evaluations`** - Performance evaluations
    - Used by: Evaluation views, athlete detail
    - Key columns: id, athlete_id, coach_id, evaluation_date

15. **`eval_skills`** - Evaluation skill categories
    - Used by: Evaluation framework, skills eval
    - Key columns: id, category_id, name

### Financial Tables

16. **`packages`** - Service packages
    - Used by: Accounting, billing, package purchase
    - Key columns: id, name, price, session_count

17. **`transactions`** - Financial transactions
    - Used by: Accounting dashboard, reports
    - Key columns: id, user_id, amount, type

18. **`payments`** - Payment records
    - Used by: Billing, payment history
    - Key columns: id, transaction_id, payment_method

19. **`expenses`** - Expense tracking
    - Used by: Expense views, accounting
    - Key columns: id, category_id, amount, date

20. **`invoices`** - Invoice management
    - Used by: Billing, accounting reports
    - Key columns: id, user_id, total, status

### Team Management Tables

21. **`teams`** - Team information
    - Used by: Team roster, team coach views
    - Key columns: id, name, age_group, skill_level

22. **`team_roster`** - Team member assignments
    - Used by: Team roster views
    - Key columns: id, team_id, athlete_id

23. **`team_coach_assignments`** - Coach-team relationships
    - Used by: Team management, admin team coaches
    - Key columns: id, team_id, coach_id, role

### Performance Tracking Tables

24. **`performance_stats`** - Performance metrics
    - Used by: Stats views, athlete detail, dashboard
    - Key columns: id, user_id, metric_type, value, date

25. **`athlete_stats`** - Athlete statistics
    - Used by: Stats, roster views
    - Key columns: id, user_id, games_played, goals, assists

### System Administration Tables

26. **`cron_jobs`** - Scheduled tasks
    - Used by: Admin cron jobs view
    - Key columns: id, name, schedule, last_run

27. **`backup_jobs`** - Database backup jobs
    - Used by: Database tools
    - Key columns: id, created_at, status

28. **`notifications`** - User notifications
    - Used by: Notifications view, dashboard
    - Key columns: id, user_id, message, is_read

### Complete Table Count: 120 Tables

**Note:** The database schema contains 120 tables total. The most commonly used tables are documented above. For the complete list of all 120 tables, see `database_schema.sql` and `QA/DATABASE_SCHEMA_REFERENCE.md`.

**Complete Table List (120 tables):**
age_groups, announcements, api_keys, athlete_evaluations, athlete_notes, athlete_nutrition_assignments, athlete_nutrition_feedback, athlete_programs, athlete_stats, athlete_teams, athlete_workout_assignments, athlete_workout_feedback, audit_logs, backup_history, backup_jobs, bookings, cloud_receipts, coach_availability, coach_certifications, credits_refunds, cron_jobs, database_maintenance_logs, discount_codes, drill_categories, drill_tags, drills, email_logs, employee_terminations, equipment, equipment_maintenance, eval_categories, eval_skills, evaluation_media, evaluation_scores, event_registrations, events, exercise_library, exercises, expense_categories, expenses, feature_versions, file_uploads, food_library, foods, game_schedules, goal_eval_approvals, goal_eval_progress, goal_eval_steps, goal_evaluations, goal_history, goal_progress, goal_steps, goals, invoice_items, invoices, locations, login_history, managed_athletes, message_attachments, messages, mileage_logs, mileage_stops, notifications, nutrition_plan_categories, nutrition_plan_meal_foods, nutrition_plan_meals, nutrition_plans, nutrition_template_items, nutrition_templates, package_sessions, packages, parent_athlete_relationships, password_resets, payment_methods, payments, performance_stats, permissions, practice_plan_categories, practice_plan_drills, practice_plans, refunds, report_schedules, reports, role_permissions, scheduled_reports, seasons, security_logs, security_scans, session_attendance, session_feedback, session_practice_plans, session_templates, session_types, sessions, skill_levels, system_notifications, system_settings, team_coach_assignments, team_roster, team_stats, teams, testing_results, theme_settings, training_programs, transactions, user_credits, user_package_credits, user_packages, user_permissions, user_workout_items, user_workouts, users, videos, waitlists, workout_plan_categories, workout_plan_exercises, workout_plans, workout_template_items, workout_templates, workouts

---

## Process Handlers

### Authentication & User Management
- `process_login.php` - User login
- `process_login_debug.php` - Debug login issues
- `process_register.php` - User registration
- `process_profile_update.php` - Profile updates

### Admin Operations
- `process_admin_action.php` - General admin actions
- `process_admin_age_skill.php` - Age/skill management
- `process_admin_team_coaches.php` - Team coach assignments
- `process_permissions.php` - Permission management

### Athlete & Coach Management
- `process_manage_athletes.php` - Athlete management
- `process_create_athlete.php` - New athlete creation
- `process_coach_action.php` - Coach actions
- `process_coach_termination.php` - Coach termination

### Sessions & Bookings
- `process_create_session.php` - Create sessions
- `process_edit_session.php` - Edit sessions
- `process_booking.php` - Session booking
- `process_assign_module.php` - Module assignments

### Practice & Training
- `process_practice_plans.php` - Practice plan management
- `process_drills.php` - Drill management
- `process_library.php` - Library item management

### Goals & Evaluations
- `process_goals.php` - Goal management
- `process_eval_goals.php` - Goal evaluations
- `process_eval_goal_approval.php` - Goal approval
- `process_evaluations.php` - Performance evaluations
- `process_eval_skills.php` - Skills evaluations
- `process_eval_framework.php` - Evaluation framework config
- `process_evaluation_templates.php` - Evaluation templates
- `process_goal_templates.php` - Goal templates

### Financial
- `process_packages.php` - Package management
- `process_purchase_package.php` - Package purchase
- `process_refunds.php` - Refund processing
- `process_expenses.php` - Expense tracking
- `process_reports.php` - Report generation

### System & Settings
- `process_settings.php` - System settings
- `process_plan_categories.php` - Category management
- `process_system_notifications.php` - System notifications
- `process_cron_jobs.php` - Cron job management

### Data Management
- `process_database_backup.php` - Database backup
- `process_database_restore.php` - Database restore
- `process_audit_restore.php` - Audit log restore
- `process_stats_update.php` - Stats update
- `process_stats_bulk_update.php` - Bulk stats update
- `process_mileage.php` - Mileage tracking

### Import/Export
- `process_feature_import.php` - Feature import from IHS
- `process_ihs_import.php` - IHS data import

---

## JavaScript Dependencies

### Core JavaScript Files

#### `js/app.js`
**Purpose:** Main application JavaScript  
**Used By:** All views in dashboard  
**Functions:**
- AJAX request handling
- Form validation
- UI interactions
- Modal management
- Toast notifications

#### `js/calendar.js`
**Purpose:** Calendar and date picker functionality  
**Used By:**
- Session booking views
- Schedule views
- Reporting schedule views
**Functions:**
- Date picker initialization
- Calendar rendering
- Event handling

### CDN Dependencies

#### Chart.js
**Used By:**
- `views/stats.php` (Performance charts)
- `views/accounting_dashboard.php` (Financial charts)
- `views/accounting_reports.php` (Report visualizations)
**Functions:**
- Line charts (performance over time)
- Bar charts (comparative stats)
- Pie charts (financial breakdown)

#### Font Awesome 6.5.1
**Used By:** All views  
**Purpose:** Icon library

#### Inter Font (Google Fonts)
**Used By:** All views  
**Purpose:** Typography

### Inline JavaScript

Many views contain inline JavaScript for:
- Tab switching
- Form submission handling
- Dynamic content loading
- Data table interactions

---

## File Structure

### Root Directory Files

#### PHP Core Files
- `index.php` - Main entry
- `index_default.php` - Marketing page
- `dashboard.php` - Main dashboard
- `login.php` - Login page
- `logout.php` - Logout handler
- `setup.php` - Initial setup
- `verify.php` - Email verification
- `verify_database.php` - Database check
- `payment_success.php` - Payment confirmation

#### Configuration Files
- `db_config.php` - Database config
- `security.php` - Security functions
- `csrf_protection.php` - CSRF protection
- `cloud_config.php` - Cloud services
- `error_logger.php` - Error logging
- `file_upload_validator.php` - Upload validation
- `mailer.php` - Email sending
- `notifications.php` - Notification handler
- `force_change_password.php` - Password reset

#### Cron Jobs
- `cron_audit_cleanup.php`
- `cron_database_backup.php`
- `cron_notifications.php`
- `cron_receipt_scanner.php`
- `cron_security_scan.php`
- `cron_session_reminders.php`
- `cron_stats_snapshot.php`

#### Process Files (40+)
See "Process Handlers" section above for complete list

#### Data Files
- `database_schema.sql` - Database schema
- `database_schema.sql.backup` - Schema backup
- `style.css` - Legacy styles (deprecated)

### Directories

#### `/views/` - 89+ view files
All view templates (see Navigation Hierarchy and Page Dependencies sections)

#### `/QA/` - Quality assurance documentation
- `MAINTENANCE_PROCESS.md` - Maintenance procedures ✓ KEEP
- `STYLE_GUIDE.md` - UI/UX standards ✓ KEEP
- `STRUCTURE.md` - This file ✓ KEEP
- `DATABASE_SCHEMA_REFERENCE.md` - Database documentation
- `NAVIGATION_MAP.md` - Navigation reference
- Other QA reports and documentation

#### `/admin/` - Admin utilities
- `feature_importer.php` - Feature import tool
- `system_validator.php` - System validation

#### `/css/` - Stylesheets
- `components.css` - Component styles
- Other CSS files

#### `/js/` - JavaScript files
- `app.js` - Main application JS
- `calendar.js` - Calendar functionality
- `app_implementation_plan.txt` - Implementation notes

#### `/lib/` - PHP libraries
Shared PHP libraries and helper functions

#### `/config/` - Configuration files
Additional configuration files

#### `/goals/` - Goals module
Goal-related files (if any)

#### `/deployment/` - Deployment resources
- `/deployment/sql/` - SQL migration scripts

#### `/backups/` - Database backups
Automated backup storage

#### `/cache/` - Cache files
Application cache

#### `/logs/` - Log files
Application and error logs

#### `/tmp/` - Temporary files
Temporary file storage

#### `/uploads/` - User uploads
File upload storage

#### `/receipts/` - Receipt storage
Scanned receipts

#### `/videos/` - Video storage
Video file storage

---

## Summary

**Total Pages in Navigation:** 33 routes  
**Total View Files:** 97 files  
**Total Process Files:** 44 files  
**Total Database Tables:** 120 tables  
**JavaScript Files:** 2 main files + CDN libraries  
**User Roles:** 6 (Athlete, Coach, Admin, Parent, Health Coach, Team Coach)

### File Usage Patterns

**View Files Organization:**
- **Parent Views (27 files):** Main pages accessible via dashboard routing table
- **Child Views (12 files):** Tab content included by parent views
- **Component Views (58 files):** Accessed via modals, AJAX, JavaScript redirects, or specific contexts

**All 97 view files are actively used** by the application through various mechanisms:
- Direct routing via `dashboard.php` routing table
- Included by parent views as tab content
- Loaded as modals or embedded content
- Accessed via JavaScript navigation
- Linked from process handlers or specific user actions

**Process Files:**
All 44 process files are actively handling form submissions and backend operations.

**Documentation Files:**
- **Active Documentation (in `/QA/`):** MAINTENANCE_PROCESS.md, STYLE_GUIDE.md, STRUCTURE.md, DATABASE_SCHEMA_REFERENCE.md, NAVIGATION_MAP.md
- **Historical Documentation (moved to `/unused/QA/`):** 35+ historical reports, summaries, and implementation documents

**Last Updated:** January 22, 2026  
**Maintained By:** QA Team  
**Cross-Reference:** See `DATABASE_SCHEMA_REFERENCE.md`, `MAINTENANCE_PROCESS.md`, `STYLE_GUIDE.md`
