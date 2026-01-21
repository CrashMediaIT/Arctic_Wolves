# Navigation Reference & Database Structure

**Last Updated**: January 21, 2026

This file provides a quick reference for the navigation structure and database schema without needing to read through multiple files.

---

## Table of Contents
1. [Navigation Structure](#navigation-structure)
2. [Page Routing Table](#page-routing-table)
3. [Database Tables](#database-tables)
4. [User Roles](#user-roles)

---

## Navigation Structure

### Main Menu (All Users)
```
├── Home (page=home)
├── Performance Stats (page=stats)
├── Sessions ▼
│   ├── Upcoming Sessions (page=upcoming_sessions)
│   └── Booking (page=booking)
├── Video ▼
│   ├── Drill Review (page=drill_review)
│   └── Coaches Reviews (page=coaches_reviews)
└── Health ▼
    ├── Strength & Conditioning (page=strength_conditioning)
    └── Nutrition (page=nutrition)
```

### Team (Team Coaches Only)
```
└── Roster (page=team_roster)
```

### Coaches Corner (Coaches, Health Coaches, Admins)
```
├── Drills ▼
│   ├── Library (page=drill_library)
│   ├── Create a Drill (page=create_drill)
│   └── Import a Drill (page=import_drill)
├── Practice Plans ▼
│   ├── Library (page=practice_library)
│   └── Create a Practice (page=create_practice)
├── Roster (page=roster)
└── Travel ▼
    └── Mileage (page=mileage)
```

### Accounting & Reports (Admins Only)
```
├── Accounting Dashboard (page=accounting_dashboard)
├── Billing Dashboard (page=billing_dashboard)
├── Reports (page=reports)
├── Schedules (page=schedules)
├── Credits & Refunds (page=credits_refunds)
├── Expenses (page=expenses)
└── Products (page=products)
```

### HR (Admins Only)
```
└── Termination (page=termination)
```

### Administration (Admins Only)
```
├── All Users (page=all_users)
├── Categories (page=categories)
├── Eval Framework (page=eval_framework)
├── System Notification (page=system_notification)
├── Audit Log (page=audit_log)
├── Cron Jobs (page=cron_jobs)
└── System Tools (page=system_tools)
```

### Account Menu (All Users)
```
├── Profile (page=profile)
└── Settings (page=settings)
```

---

## Page Routing Table

Complete mapping of page parameters to view files:

| Page Parameter | View File | Access Level |
|---|---|---|
| `home` | `views/home.php` | All users |
| `stats` | `views/stats.php` | All users |
| `upcoming_sessions` | `views/sessions_upcoming.php` | All users |
| `booking` | `views/sessions_booking.php` | All users |
| `drill_review` | `views/video_drill_review.php` | All users |
| `coaches_reviews` | `views/video_coach_reviews.php` | All users |
| `strength_conditioning` | `views/health_workouts.php` | All users |
| `nutrition` | `views/health_nutrition.php` | All users |
| `team_roster` | `views/team_roster.php` | Team coaches |
| `drill_library` | `views/drills_library.php` | Coaches, Health coaches, Admins |
| `create_drill` | `views/drills_create.php` | Coaches, Health coaches, Admins |
| `import_drill` | `views/drills_import.php` | Coaches, Health coaches, Admins |
| `practice_library` | `views/practice_library.php` | Coaches, Health coaches, Admins |
| `create_practice` | `views/practice_create.php` | Coaches, Health coaches, Admins |
| `roster` | `views/coach_roster.php` | Coaches, Health coaches, Admins |
| `mileage` | `views/travel_mileage.php` | Coaches, Health coaches, Admins |
| `accounting_dashboard` | `views/accounting_dashboard.php` | Admins |
| `billing_dashboard` | `views/accounting_billing.php` | Admins |
| `reports` | `views/accounting_reports.php` | Admins |
| `schedules` | `views/accounting_schedules.php` | Admins |
| `credits_refunds` | `views/accounting_credits.php` | Admins |
| `expenses` | `views/accounting_expenses.php` | Admins |
| `products` | `views/accounting_products.php` | Admins |
| `termination` | `views/hr_termination.php` | Admins |
| `all_users` | `views/admin_users.php` | Admins |
| `categories` | `views/admin_categories.php` | Admins |
| `eval_framework` | `views/admin_eval_framework.php` | Admins |
| `system_notification` | `views/admin_notifications.php` | Admins |
| `audit_log` | `views/admin_audit_log.php` | Admins |
| `cron_jobs` | `views/admin_cron_jobs.php` | Admins |
| `system_tools` | `views/admin_system_tools.php` | Admins |
| `profile` | `views/profile.php` | All users |
| `settings` | `views/settings.php` | All users |

---

## Database Tables

### User Management Tables

#### users
Primary user table with authentication and profile information
```sql
Columns:
- id (INT, PK)
- email (VARCHAR, UNIQUE)
- password (VARCHAR)
- first_name (VARCHAR)
- last_name (VARCHAR)
- role (ENUM: athlete, coach, admin, parent, health_coach, team_coach)
- is_verified (TINYINT)
- verification_code (VARCHAR)
- force_pass_change (TINYINT)
- phone (VARCHAR)
- date_of_birth (DATE)
- profile_image (VARCHAR)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

Indexes:
- PRIMARY KEY (id)
- UNIQUE KEY (email)
- INDEX (role)
```

#### parent_athlete_relationships
Links parents to their athletes
```sql
Columns:
- id (INT, PK)
- parent_id (INT, FK -> users.id)
- athlete_id (INT, FK -> users.id)
- relationship_type (ENUM: parent, guardian, other)
- created_at (TIMESTAMP)

Indexes:
- PRIMARY KEY (id)
- UNIQUE KEY (parent_id, athlete_id)
- FOREIGN KEY (parent_id)
- FOREIGN KEY (athlete_id)
```

#### coach_athlete_assignments
Manages coach-athlete relationships
```sql
Columns:
- id (INT, PK)
- coach_id (INT, FK -> users.id)
- athlete_id (INT, FK -> users.id)
- assignment_type (ENUM: active, past)
- assigned_date (TIMESTAMP)
- end_date (TIMESTAMP NULL)

Indexes:
- PRIMARY KEY (id)
- FOREIGN KEY (coach_id)
- FOREIGN KEY (athlete_id)
- INDEX (coach_id)
- INDEX (athlete_id)
```

### Team Management Tables

#### teams
```sql
Columns:
- id, name, age_group, skill_level, season, created_at, updated_at
```

#### team_coach_assignments
```sql
Columns:
- id, team_id, coach_id, role, assigned_date
```

#### team_roster
```sql
Columns:
- id, team_id, athlete_id, jersey_number, position, joined_date
```

### Session Management Tables

#### locations
```sql
Columns:
- id, name, address, city, province, postal_code, created_at
```

#### session_types
```sql
Columns:
- id, name, description, default_price, duration_minutes, created_at
```

#### sessions
```sql
Columns:
- id, session_type_id, location_id, title, description
- session_date, duration_minutes, price, max_participants
- age_group, skill_level, team_id, coach_id, status
- created_at, updated_at
```

#### session_bookings
```sql
Columns:
- id, session_id, user_id, booking_date
- payment_status, amount_paid, credits_used, status
```

### Drill & Practice Management Tables

#### drill_categories
```sql
Columns:
- id, name, description, created_at
```

#### drills
```sql
Columns:
- id, title, description, category_id, created_by
- diagram_data, custom_image, video_url, ihs_source_url
- created_at, updated_at, version, parent_drill_id
```

#### practice_plans
```sql
Columns:
- id, name, description, created_by
- created_at, updated_at, version, parent_plan_id
```

#### practice_plan_drills
```sql
Columns:
- id, practice_plan_id, drill_id, drill_order
- duration_minutes, notes
```

#### session_practice_plans
```sql
Columns:
- id, session_id, practice_plan_id
```

### Video Management Tables

#### videos
```sql
Columns:
- id, athlete_id, coach_id, title, description
- video_url, thumbnail_url, upload_date, video_type
- drill_id, session_id, status
- coach_notes, athlete_notes, reviewed_at
```

### Health & Fitness Tables

#### exercise_library
```sql
Columns:
- id, name, description, category
- equipment_needed, difficulty_level
- video_url, image_url, created_by, created_at
```

#### workout_plans
```sql
Columns:
- id, name, description, created_by
- duration_weeks, difficulty_level
- created_at, updated_at
```

#### workout_plan_exercises
```sql
Columns:
- id, workout_plan_id, exercise_id, day_number
- sets, reps, rest_seconds, notes, exercise_order
```

#### athlete_workout_assignments
```sql
Columns:
- id, athlete_id, workout_plan_id, assigned_by
- assigned_date, start_date, status
```

#### athlete_workout_feedback
```sql
Columns:
- id, assignment_id, exercise_id, feedback
- feedback_date, coach_response, responded_at
```

### Nutrition Tables

#### food_library
```sql
Columns:
- id, name, description, category
- calories, protein_g, carbs_g, fat_g, serving_size
- created_by, created_at
```

#### nutrition_plans
```sql
Columns:
- id, name, description, created_by
- target_calories, target_protein_g, target_carbs_g, target_fat_g
- created_at, updated_at
```

#### nutrition_plan_meals
```sql
Columns:
- id, nutrition_plan_id, meal_type, day_number, meal_order
```

#### nutrition_plan_meal_foods
```sql
Columns:
- id, meal_id, food_id, serving_quantity, notes
```

#### athlete_nutrition_assignments
```sql
Columns:
- id, athlete_id, nutrition_plan_id, assigned_by
- assigned_date, start_date, status
```

#### athlete_nutrition_feedback
```sql
Columns:
- id, assignment_id, feedback
- feedback_date, coach_response, responded_at
```

### Performance & Goals Tables

#### performance_stats
```sql
Columns:
- id, athlete_id, stat_date, stat_type, stat_value
- stat_unit, session_id, recorded_by, notes, created_at
```

#### goals
```sql
Columns:
- id, athlete_id, goal_title, goal_description
- target_value, current_value, target_date, status
- created_at, updated_at
```

### Financial Tables

#### packages
```sql
Columns:
- id, name, description, price, credits
- age_group, skill_level, team_id, valid_days
- is_active, created_at
```

#### user_packages
```sql
Columns:
- id, user_id, package_id, purchase_date
- credits_remaining, expiry_date
- payment_status, amount_paid
```

#### discount_codes
```sql
Columns:
- id, code, discount_type, discount_value
- max_uses, times_used, valid_from, valid_until
- is_active, created_at
```

#### transactions
```sql
Columns:
- id, user_id, transaction_type, amount
- hst_amount, total_amount, payment_method
- transaction_date, reference_type, reference_id
- description, status
```

#### expenses
```sql
Columns:
- id, user_id, expense_date, amount, category
- description, receipt_url, status
- approved_by, approved_at, created_at
```

#### mileage_tracking
```sql
Columns:
- id, user_id, trip_date
- start_location, end_location, distance_km
- purpose, notes, created_at
```

### System Tables

#### notifications
```sql
Columns:
- id, user_id, notification_type, title, message
- is_read, link_url, created_at
```

#### eval_categories
```sql
Columns:
- id, name, description, created_at
```

#### eval_skills
```sql
Columns:
- id, category_id, name, description, created_at
```

#### athlete_evaluations
```sql
Columns:
- id, athlete_id, evaluator_id, skill_id
- rating, comments, evaluation_date, session_id
- created_at
```

#### scheduled_reports
```sql
Columns:
- id, report_name, report_config
- schedule_frequency, schedule_day, schedule_time
- recipients, is_active, created_by
- created_at, last_run_at
```

#### system_settings
```sql
Columns:
- id, setting_key, setting_value
- setting_type, description, updated_at
```

#### audit_log
```sql
Columns:
- id, user_id, action, table_name, record_id
- old_values, new_values, ip_address, user_agent
- created_at
```

#### theme_settings
```sql
Columns:
- id, theme_name, primary_color, secondary_color
- background_color, logo_url, custom_css
- is_active, created_at, updated_at
```

#### cron_jobs
```sql
Columns:
- id, job_name, job_description, schedule
- is_active, last_run_at, next_run_at, created_at
```

---

## User Roles

### Role Definitions

| Role | Access Level | Description |
|---|---|---|
| **athlete** | Basic | Standard athlete access to training, sessions, and stats |
| **parent** | Limited | View-only access to athlete data for their children |
| **coach** | Elevated | Create drills, practice plans, manage athletes |
| **health_coach** | Specialized | Focus on workout and nutrition plans |
| **team_coach** | Team-specific | Manage team rosters and team-based sessions |
| **admin** | Full | Complete system access including accounting and administration |

### Role-Based Menu Visibility

```php
// Variable checks in dashboard.php
$isAdmin       = ($user_role === 'admin');
$isCoach       = ($user_role === 'coach');
$isHealthCoach = ($user_role === 'health_coach');
$isTeamCoach   = ($user_role === 'team_coach');
$isParent      = ($user_role === 'parent');

// Combined checks
$isAnyCoach    = ($isCoach || $isHealthCoach || $isAdmin);
$isTeamStaff   = ($isTeamCoach);
```

### Menu Visibility Matrix

| Menu Section | athlete | parent | coach | health_coach | team_coach | admin |
|---|---|---|---|---|---|---|
| Main Menu | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Team | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ |
| Coaches Corner | ✗ | ✗ | ✓ | ✓ | ✗ | ✓ |
| Accounting & Reports | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| HR | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Administration | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |

---

## Quick Reference for Developers

### Adding a New Page

1. Create view file in `views/` directory
2. Add route in `dashboard.php` `$allowed_pages` array
3. Add navigation link in appropriate menu section
4. Update this reference file

### Modifying Database Schema

1. Create SQL ALTER statements
2. Test on development database
3. Document changes in `database_schema.sql`
4. Update this reference file
5. Create migration script if needed

### Adding a New Role

1. Add role to `users` table ENUM
2. Create role check variable in `dashboard.php`
3. Add role to visibility matrix
4. Update menu sections as needed
5. Update this reference file

---

**File Locations**:
- **Deployment Guide**: `DEPLOYMENT.md`
- **Database Schema**: `database_schema.sql`
- **Setup Wizard**: `setup.php`
- **Main Dashboard**: `dashboard.php`
- **View Files**: `views/` directory

---

**Maintenance**: This file should be updated whenever:
- New navigation items are added
- Database tables are modified
- User roles are changed
- View files are added/removed
- Page routing is updated

