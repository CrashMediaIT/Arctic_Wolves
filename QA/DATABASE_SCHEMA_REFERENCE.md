# Arctic Wolves - Database Schema Reference Guide

**Version**: 2.0  
**Last Updated**: January 22, 2026  
**Purpose**: Authoritative reference for all database tables, columns, and relationships

---

## 📌 IMPORTANT: Schema Governance Rules

### **NEVER modify the schema without:**
1. ✅ Updating this reference document FIRST
2. ✅ Updating `database_schema.sql`
3. ✅ Updating `setup.php` if needed
4. ✅ Running schema validation checks
5. ✅ Testing all affected queries in code

### **When fixing database errors:**
1. ❌ **DO NOT** change column names in schema to match broken code
2. ✅ **DO** fix the code to match the authoritative schema
3. ✅ **DO** check this document first before making any database changes

---

## 🗄️ Core Database Architecture

- **Database Engine**: InnoDB
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Connection**: PDO (prepared statements only)
- **Primary Key Pattern**: `id` (INT AUTO_INCREMENT)
- **Foreign Key Pattern**: `{table}_id` or `{entity}_id`
- **Timestamp Pattern**: `created_at`, `updated_at`

---

## 📊 Table Categories

### 1. User Management (Core)
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `users` | `id` | `assigned_coach_id`, `created_by_coach_id` | All system users |
| `parent_athlete_relationships` | `id` | `parent_id`, `athlete_id` | Parent-child links |
| `user_permissions` | `id` | `user_id`, `permission_id` | User-specific permissions |
| `permissions` | `id` | - | Permission definitions |
| `role_permissions` | `id` | `permission_id` | Role-based permissions |

### 2. Teams & Sessions
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `teams` | `id` | - | Team definitions |
| `team_roster` | `id` | `team_id`, `athlete_id` | Team membership |
| `team_coach_assignments` | `id` | `team_id`, `coach_id` | Coach assignments |
| `team_stats` | `id` | `team_id` | Team statistics |
| `sessions` | `id` | `coach_id`, `athlete_id`, `session_type_id`, `location_id` | Training sessions |
| `session_types` | `id` | - | Session type definitions |
| `session_attendance` | `id` | `session_id`, `athlete_id` | Attendance tracking |
| `session_feedback` | `id` | `session_id`, `user_id` | Session feedback |

### 3. Training & Development
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `training_programs` | `id` | `created_by` | Training program definitions |
| `athlete_programs` | `id` | `athlete_id`, `program_id` | Athlete program enrollments |
| `drills` | `id` | `category_id`, `created_by` | Drill library |
| `drill_categories` | `id` | - | Drill categorization |
| `practice_plans` | `id` | `created_by` | Practice plan templates |
| `practice_plan_drills` | `id` | `practice_plan_id`, `drill_id` | Drills in practice plans |

### 4. Goals & Evaluations
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `goals` | `id` | `user_id`, `coach_id`, `parent_goal_id` | Goal setting system |
| `goal_progress` | `id` | `goal_id`, `recorded_by` | Progress tracking |
| `goal_evaluations` | `id` | `goal_id`, `evaluator_id` | Goal evaluations |
| `eval_categories` | `id` | - | Evaluation categories |
| `eval_skills` | `id` | `category_id` | Evaluation skills |
| `athlete_evaluations` | `id` | `athlete_id`, `skill_id`, `session_id` | Athlete skill evaluations |
| `evaluation_scores` | `id` | `evaluation_id`, `skill_id` | Evaluation scoring |

### 5. Finance & Accounting
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `invoices` | `id` | `user_id` | Invoice records |
| `invoice_items` | `id` | `invoice_id` | Invoice line items |
| `transactions` | `id` | `user_id`, `invoice_id` | Financial transactions |
| `expenses` | `id` | `category_id`, `created_by` | Expense tracking |
| `expense_categories` | `id` | - | Expense categories |
| `credits_refunds` | `id` | `user_id`, `processed_by` | Credits and refunds |
| `packages` | `id` | - | Session packages |
| `user_packages` | `id` | `user_id`, `package_id` | User package purchases |
| `user_package_credits` | `id` | `user_package_id` | Package credit tracking |
| `user_credits` | `id` | `user_id` | Flexible credit system |
| `discount_codes` | `id` | - | Discount code definitions |

### 6. HR & Administration
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `employee_terminations` | `id` | `user_id`, `processed_by` | Employee termination records |
| `coach_availability` | `id` | `coach_id` | Coach availability schedule |
| `coach_certifications` | `id` | `coach_id` | Coach certifications |
| `mileage_logs` | `id` | `user_id` | Mileage tracking |
| `mileage_stops` | `id` | `mileage_log_id` | Mileage stop points |

### 7. System & Administration
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `system_settings` | `id` | - | System configuration |
| `system_notifications` | `id` | `created_by` | System-wide notifications |
| `audit_logs` | `id` | `user_id` | Audit trail |
| `security_logs` | `id` | `user_id` | Security event logs |
| `login_history` | `id` | `user_id` | Login tracking |
| `email_logs` | `id` | `user_id` | Email delivery logs |
| `cron_jobs` | `id` | - | Scheduled task definitions |
| `backup_history` | `id` | - | Database backup logs |
| `file_uploads` | `id` | `user_id` | File upload tracking |

### 8. Locations & Equipment
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `locations` | `id` | - | Training locations/facilities |
| `equipment` | `id` | - | Equipment inventory |
| `equipment_maintenance` | `id` | `equipment_id` | Maintenance records |

### 9. Media & Content
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `videos` | `id` | `uploaded_by` | Video library |
| `messages` | `id` | `from_user_id`, `to_user_id` | Internal messaging |
| `announcements` | `id` | `published_by` | Announcements |
| `events` | `id` | `created_by` | Event management |
| `event_registrations` | `id` | `event_id`, `user_id` | Event registration |

### 10. Nutrition & Health
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `food_library` | `id` | - | Food database |
| `nutrition_plans` | `id` | `created_by` | Nutrition plan templates |
| `nutrition_plan_meals` | `id` | `nutrition_plan_id` | Meals in nutrition plans |
| `athlete_nutrition_assignments` | `id` | `athlete_id`, `nutrition_plan_id` | Athlete nutrition assignments |

### 11. Workouts & Exercise
| Table Name | Primary Key | Key Foreign Keys | Description |
|------------|-------------|------------------|-------------|
| `exercise_library` | `id` | - | Exercise database |
| `workout_plans` | `id` | `created_by` | Workout plan templates |
| `workout_plan_exercises` | `id` | `workout_plan_id`, `exercise_id` | Exercises in workout plans |
| `athlete_workout_assignments` | `id` | `athlete_id`, `workout_plan_id` | Athlete workout assignments |
| `user_workouts` | `id` | `user_id` | User workout logs |

---

## 🔑 Critical Column Reference

### Users Table (`users`)
```sql
PRIMARY KEY: id (INT AUTO_INCREMENT)

CORE COLUMNS:
- email (VARCHAR(255) UNIQUE NOT NULL)
- password (VARCHAR(255) NOT NULL)
- first_name (VARCHAR(100) NOT NULL)
- last_name (VARCHAR(100) NOT NULL)
- role (ENUM: 'athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach')
- is_active (TINYINT(1) DEFAULT 1) -- User account status
- is_verified (TINYINT(1) DEFAULT 0) -- Email verification
- verification_code (VARCHAR(10))
- force_pass_change (TINYINT(1) DEFAULT 0)

PROFILE COLUMNS:
- phone (VARCHAR(20))
- birth_date (DATE) -- PRIMARY birth date column used in code
- date_of_birth (DATE) -- LEGACY alias for backward compatibility
- position (VARCHAR(50)) -- Player position
- primary_arena (VARCHAR(255)) -- Home arena
- profile_image (VARCHAR(255))

RELATIONSHIP COLUMNS:
- assigned_coach_id (INT) -- FK to users.id (ON DELETE SET NULL)
- created_by_coach_id (INT) -- FK to users.id (ON DELETE SET NULL)

TIMESTAMPS:
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
- updated_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)

INDEXES:
- idx_role (role)
- idx_email (email)
- idx_role_verified (role, is_verified)
- idx_assigned_coach (assigned_coach_id)
```

### Sessions Table (`sessions`)
```sql
PRIMARY KEY: id (INT AUTO_INCREMENT)

CORE COLUMNS:
- session_date (DATE NOT NULL)
- session_time (TIME NOT NULL)
- duration_minutes (INT DEFAULT 60)
- status (ENUM: 'scheduled', 'completed', 'cancelled', 'in_progress')
- session_type_id (INT) -- FK to session_types.id
- location_id (INT) -- FK to locations.id
- max_participants (INT DEFAULT 10) -- NOTE: max_parcticipants is TYPO in schema, fix needed

RELATIONSHIP COLUMNS:
- coach_id (INT) -- FK to users.id (ON DELETE SET NULL)
- assistant_coach_id (INT) -- FK to users.id (ON DELETE SET NULL)
- athlete_id (INT) -- FK to users.id (ON DELETE CASCADE)
- team_id (INT) -- FK to teams.id (ON DELETE SET NULL)
- package_id (INT) -- FK to packages.id (ON DELETE SET NULL)

BOOKING COLUMNS:
- is_booked (TINYINT(1) DEFAULT 0)
- booking_count (INT DEFAULT 0)

NOTES:
- notes (TEXT)
- private_notes (TEXT)

TIMESTAMPS:
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
- updated_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
```

---

## 🔗 Foreign Key Relationships

### **All Foreign Keys Reference `users.id`**

When joining users table, **ALWAYS** use:
```sql
-- ✅ CORRECT
LEFT JOIN users u ON table.user_id = u.id
LEFT JOIN users u ON table.coach_id = u.id
LEFT JOIN users u ON table.athlete_id = u.id

-- ❌ WRONG (will cause errors)
LEFT JOIN users u ON table.user_id = u.user_id  -- users.user_id doesn't exist!
```

### Common FK Column Names
- `user_id` → `users.id`
- `coach_id` → `users.id`
- `athlete_id` → `users.id`
- `parent_id` → `users.id`
- `created_by` → `users.id`
- `assigned_coach_id` → `users.id`
- `evaluator_id` → `users.id`
- `recorded_by` → `users.id`
- `uploaded_by` → `users.id`
- `processed_by` → `users.id`

---

## ⚠️ Common Pitfalls & How to Avoid

### Pitfall 1: Wrong Column in JOIN
```sql
-- ❌ WRONG - users table doesn't have user_id column
SELECT * FROM invoices i
LEFT JOIN users u ON i.user_id = u.user_id

-- ✅ CORRECT
SELECT * FROM invoices i
LEFT JOIN users u ON i.user_id = u.id
```

### Pitfall 2: Wrong Table Alias
```sql
-- ❌ WRONG - table is training_programs, not programs
SELECT * FROM programs p WHERE p.is_active = 1

-- ✅ CORRECT - use actual table name OR view
SELECT * FROM training_programs p WHERE p.is_active = 1
-- OR use the view we created:
SELECT * FROM programs p WHERE p.is_active = 1  -- Uses CREATE VIEW
```

### Pitfall 3: Wrong Column Name in WHERE/SELECT
```sql
-- ❌ WRONG - column is 'name', not 'category_name'
SELECT ec.category_name FROM expense_categories ec

-- ✅ CORRECT
SELECT ec.name as category_name FROM expense_categories ec
```

### Pitfall 4: Wrong Timestamp Column
```sql
-- ❌ WRONG - audit_logs uses 'created_at', not 'timestamp'
SELECT * FROM audit_logs ORDER BY timestamp DESC

-- ✅ CORRECT
SELECT * FROM audit_logs ORDER BY created_at DESC
```

---

## 🛠️ Schema Validation Checklist

Before deploying any database-related changes:

- [ ] All table names match `database_schema.sql`
- [ ] All column names match schema definitions
- [ ] All JOINs use correct foreign key columns
- [ ] All FK references use `users.id` (not `users.user_id`)
- [ ] No references to non-existent tables
- [ ] No typos in column names (check `max_parcticipants` → should be `max_participants`)
- [ ] PDO is used (no mysqli)
- [ ] Prepared statements with parameterized queries
- [ ] Error handling with try-catch blocks

---

## 🔄 Known Legacy Issues (Backward Compatibility)

### 1. Birth Date Columns
The `users` table has TWO birth date columns for backward compatibility:
- **`birth_date`** (PRIMARY) - Use this in new code
- **`date_of_birth`** (LEGACY) - Maintained for old code

**Best Practice**: Use `birth_date` in all new queries. Both columns should be kept in sync.

### 2. Programs Table Alias
The actual table is `training_programs`, but a VIEW named `programs` exists:
```sql
CREATE OR REPLACE VIEW `programs` AS SELECT * FROM `training_programs`;
```
**Best Practice**: Use `training_programs` in new code for clarity.

### 3. Nutrition Plan Columns
The `nutrition_plans` table has legacy name columns:
- **`name`** (PRIMARY)
- **`title`** (LEGACY)

---

## 📝 Schema Change Process

When you need to modify the database schema:

1. **Document the change** in this file FIRST
2. **Update `database_schema.sql`**
3. **Update `setup.php`** if creating new tables
4. **Search codebase** for all references to affected tables/columns
5. **Update all affected PHP files**
6. **Test queries** with sample data
7. **Update this reference document** with final changes
8. **Commit all changes together** in a single commit

---

## 🔍 Quick Reference Commands

### Find all references to a table:
```bash
grep -rn "table_name" --include="*.php" .
```

### Find all SQL queries with a specific column:
```bash
grep -rn "column_name" --include="*.php" .
```

### Check for mysqli usage (should return nothing):
```bash
grep -rn "mysqli_" --include="*.php" .
grep -rn "\$conn->" --include="*.php" . | grep -v "connection\|config"
```

### Validate PDO usage:
```bash
grep -rn "\$pdo->" --include="*.php" .
```

---

## 📋 Maintenance Integration

This document should be referenced:
1. **At the start** of any database-related work
2. **Before making** any schema changes
3. **After fixing** any SQL errors
4. **Before deployment** as part of validation

See `MAINTENANCE_PROCESS.md` for integration details.

---

**Version History:**
- v2.0 (2026-01-22): Comprehensive schema reference created
- v1.0 (Initial): Basic schema in database_schema.sql

---

**Maintained by**: Development Team  
**Review Frequency**: After every schema change  
**Next Review**: After next deployment
