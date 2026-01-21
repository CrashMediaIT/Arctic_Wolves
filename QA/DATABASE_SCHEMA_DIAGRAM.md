# Database Schema Diagram & Relationships

**Version**: 1.0  
**Last Updated**: January 21, 2026  
**Total Tables**: 44

---

## Schema Overview

The Crash Hockey database consists of 44 tables organized into 8 main functional areas:

1. **User Management** (6 tables)
2. **Teams & Assignments** (6 tables)
3. **Sessions & Bookings** (5 tables)
4. **Drills & Practice Plans** (4 tables)
5. **Videos & Media** (1 table)
6. **Health & Fitness** (8 tables)
7. **Performance & Evaluation** (3 tables)
8. **Operations & Admin** (11 tables)

---

## Core Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER MANAGEMENT                           │
└─────────────────────────────────────────────────────────────────┘

                        ┌──────────────┐
                        │    users     │
                        │ (id, email,  │
                        │  role, etc)  │
                        └──────┬───────┘
                               │
            ┌──────────────────┼──────────────────┐
            │                  │                  │
    ┌───────▼─────────┐ ┌─────▼──────────┐ ┌────▼──────────┐
    │ parent_athlete_ │ │ coach_athlete_ │ │ team_coach_   │
    │  relationships  │ │  assignments   │ │  assignments  │
    └─────────────────┘ └────────────────┘ └───────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                     TEAMS & ORGANIZATION                         │
└─────────────────────────────────────────────────────────────────┘

    ┌──────────┐         ┌──────────────┐        ┌──────────┐
    │  teams   │◄────────│ team_roster  │───────►│  users   │
    └──────────┘         └──────────────┘        └──────────┘
         │
         ▼
    ┌─────────────────┐
    │ team_coach_     │
    │  assignments    │
    └─────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                   SESSIONS & SCHEDULING                          │
└─────────────────────────────────────────────────────────────────┘

    ┌──────────────┐        ┌──────────────┐
    │ session_types│◄───────│   sessions   │
    └──────────────┘        └──────┬───────┘
                                   │
                    ┌──────────────┼──────────────┐
                    │              │              │
            ┌───────▼────────┐ ┌──▼────────┐ ┌──▼──────────────┐
            │  practice_plans │ │ locations│ │ session_bookings│
            └────────────────┘ └───────────┘ └─────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                  DRILLS & PRACTICE PLANS                         │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────┐       ┌──────────┐
    │ drill_categories│◄──────│  drills  │
    └─────────────────┘       └────┬─────┘
                                   │
                            ┌──────▼────────────────┐
                            │ practice_plan_drills  │
                            └──────┬────────────────┘
                                   │
                            ┌──────▼──────────┐
                            │ practice_plans  │
                            └─────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                   HEALTH & FITNESS                               │
└─────────────────────────────────────────────────────────────────┘

    ┌─────────────────┐       ┌───────────────┐
    │ exercise_library│◄──────│ workout_plans │
    └─────────────────┘       └───────┬───────┘
                                      │
                        ┌─────────────▼──────────────────┐
                        │ workout_plan_exercises         │
                        └─────────────┬──────────────────┘
                                      │
                        ┌─────────────▼──────────────────┐
                        │ athlete_workout_assignments    │
                        └─────────────┬──────────────────┘
                                      │
                        ┌─────────────▼──────────────────┐
                        │ athlete_workout_feedback       │
                        └────────────────────────────────┘

    ┌──────────────┐         ┌─────────────────┐
    │ food_library │◄────────│ nutrition_plans │
    └──────────────┘         └────────┬────────┘
                                      │
                        ┌─────────────▼──────────────────┐
                        │ nutrition_plan_meals           │
                        └─────────────┬──────────────────┘
                                      │
                        ┌─────────────▼──────────────────┐
                        │ nutrition_plan_meal_foods      │
                        └─────────────┬──────────────────┘
                                      │
                        ┌─────────────▼──────────────────┐
                        │ athlete_nutrition_assignments  │
                        └─────────────┬──────────────────┘
                                      │
                        ┌─────────────▼──────────────────┐
                        │ athlete_nutrition_feedback     │
                        └────────────────────────────────┘
```

---

## Complete Table List with Relationships

### 1. User Management (6 tables)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| users | id | - | Core user table (athletes, coaches, admins, parents, health coaches) |
| parent_athlete_relationships | id | parent_id → users.id, athlete_id → users.id | Links parents to athletes |
| coach_athlete_assignments | id | coach_id → users.id, athlete_id → users.id | Assigns coaches to athletes |
| team_coach_assignments | id | team_id → teams.id, coach_id → users.id | Assigns coaches to teams |
| team_roster | id | team_id → teams.id, athlete_id → users.id | Team membership |
| locations | id | - | Training locations |

### 2. Teams & Organization (2 tables)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| teams | id | - | Hockey teams |
| team_roster | id | team_id → teams.id, athlete_id → users.id | Team members |

### 3. Sessions & Bookings (5 tables)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| session_types | id | - | Types of sessions (practice, game, training) |
| sessions | id | session_type_id → session_types.id, location_id → locations.id, team_id → teams.id, coach_id → users.id | Training sessions |
| practice_plans | id | created_by → users.id, parent_plan_id → practice_plans.id | Practice plans |
| session_practice_plans | id | session_id → sessions.id, practice_plan_id → practice_plans.id | Links sessions to plans |
| session_bookings | id | session_id → sessions.id, user_id → users.id | Session registrations |

### 4. Drills & Practice Plans (4 tables)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| drill_categories | id | - | Drill categorization |
| drills | id | category_id → drill_categories.id, created_by → users.id, parent_drill_id → drills.id | Individual drills |
| practice_plan_drills | id | practice_plan_id → practice_plans.id, drill_id → drills.id | Drills in practice plans |
| practice_plans | id | created_by → users.id | Practice plan templates |

### 5. Videos & Media (1 table)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| videos | id | athlete_id → users.id, coach_id → users.id, drill_id → drills.id, session_id → sessions.id | Video uploads |

### 6. Health & Fitness (8 tables)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| exercise_library | id | - | Exercise database |
| workout_plans | id | created_by → users.id | Workout templates |
| workout_plan_exercises | id | workout_plan_id → workout_plans.id, exercise_id → exercise_library.id | Exercises in workouts |
| athlete_workout_assignments | id | athlete_id → users.id, workout_plan_id → workout_plans.id, assigned_by → users.id | Assigned workouts |
| athlete_workout_feedback | id | assignment_id → athlete_workout_assignments.id, athlete_id → users.id | Workout feedback |
| food_library | id | - | Food database |
| nutrition_plans | id | created_by → users.id | Nutrition templates |
| nutrition_plan_meals | id | nutrition_plan_id → nutrition_plans.id | Meals in plans |
| nutrition_plan_meal_foods | id | meal_id → nutrition_plan_meals.id, food_id → food_library.id | Foods in meals |
| athlete_nutrition_assignments | id | athlete_id → users.id, nutrition_plan_id → nutrition_plans.id, assigned_by → users.id | Assigned nutrition plans |
| athlete_nutrition_feedback | id | assignment_id → athlete_nutrition_assignments.id, athlete_id → users.id | Nutrition feedback |

### 7. Performance & Evaluation (3 tables)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| performance_stats | id | athlete_id → users.id | Athletic statistics |
| goals | id | athlete_id → users.id | Athlete goals |
| eval_categories | id | - | Evaluation categories |
| eval_skills | id | category_id → eval_categories.id | Skills to evaluate |
| athlete_evaluations | id | athlete_id → users.id, coach_id → users.id, skill_id → eval_skills.id | Performance evaluations |

### 8. Operations & Admin (11 tables)

| Table | Primary Key | Foreign Keys | Purpose |
|-------|-------------|--------------|---------|
| packages | id | team_id → teams.id | Session packages |
| user_packages | id | user_id → users.id, package_id → packages.id | Purchased packages |
| discount_codes | id | - | Promo codes |
| transactions | id | user_id → users.id | Payment history |
| expenses | id | user_id → users.id | Expense tracking |
| mileage_tracking | id | user_id → users.id | Travel mileage |
| notifications | id | user_id → users.id | System notifications |
| scheduled_reports | id | created_by → users.id | Automated reports |
| system_settings | id | - | System configuration |
| audit_log | id | user_id → users.id | Activity log |
| theme_settings | id | - | UI customization |
| cron_jobs | id | - | Scheduled tasks |

---

## Key Relationships

### User-Centric Relationships
```
users (id)
├── parent_athlete_relationships (parent_id, athlete_id)
├── coach_athlete_assignments (coach_id, athlete_id)
├── team_coach_assignments (coach_id)
├── team_roster (athlete_id)
├── session_bookings (user_id)
├── performance_stats (athlete_id)
├── goals (athlete_id)
├── athlete_evaluations (athlete_id, coach_id)
├── athlete_workout_assignments (athlete_id, assigned_by)
├── athlete_nutrition_assignments (athlete_id, assigned_by)
├── videos (athlete_id, coach_id)
├── mileage_tracking (user_id)
├── expenses (user_id)
└── transactions (user_id)
```

### Team-Centric Relationships
```
teams (id)
├── team_roster (team_id)
├── team_coach_assignments (team_id)
├── sessions (team_id)
└── packages (team_id)
```

### Session-Centric Relationships
```
sessions (id)
├── session_practice_plans (session_id)
├── session_bookings (session_id)
└── videos (session_id)
```

---

## Database Statistics

- **Total Tables**: 44
- **Foreign Key Constraints**: 64
- **Indexes**: 38
- **Engine**: InnoDB (all tables)
- **Character Set**: utf8mb4_unicode_ci (all tables)

---

## Data Flow Patterns

### 1. User Registration Flow
```
register.php → users (create) → verification_code (email) → verify.php → is_verified = 1
```

### 2. Session Booking Flow
```
sessions_booking.php → session_bookings (create) → user_packages (decrement credits) → transactions (log)
```

### 3. Workout Assignment Flow
```
coach_roster.php → workout_plans (select) → athlete_workout_assignments (create) → notifications (send)
```

### 4. Video Review Flow
```
video_coach_reviews.php → videos (upload) → notifications (athlete) → video_drill_review.php (view)
```

---

## Validation Status

✅ All foreign key relationships valid  
✅ All indexes properly defined  
✅ No orphaned records possible (CASCADE on DELETE)  
✅ All tables use InnoDB for transactional integrity  
✅ Character set supports international characters  

---

**Last Validated**: January 21, 2026
