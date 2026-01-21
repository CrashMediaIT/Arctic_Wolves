# Navigation Structure Map

**Version**: 1.0  
**Last Updated**: January 21, 2026

---

## Complete Navigation Hierarchy

### Main Menu (All Users)

```
Main Menu
├── Home (fa-house) → views/home.php
├── Performance Stats (fa-chart-line) → views/stats.php
├── Sessions (fa-calendar-check) [Submenu]
│   ├── Upcoming Sessions → views/sessions_upcoming.php
│   └── Booking → views/sessions_booking.php
├── Video (fa-video) [Submenu]
│   ├── Drill Review → views/video_drill_review.php
│   └── Coaches Reviews → views/video_coach_reviews.php
└── Health (fa-heart-pulse) [Submenu]
    ├── Strength & Conditioning → views/health_workouts.php
    └── Nutrition → views/health_nutrition.php
```

### Team Section (Team Coaches Only)

```
Team
└── Roster (fa-users) → views/team_roster.php
```

### Coaches Corner (Coaches, Health Coaches, Admins)

```
Coaches Corner
├── Drills (fa-clipboard-list) [Submenu]
│   ├── Library → views/drills_library.php
│   ├── Create a Drill → views/drills_create.php
│   └── Import a Drill → views/drills_import.php
├── Practice Plans (fa-file-lines) [Submenu]
│   ├── Library → views/practice_library.php
│   └── Create a Practice → views/practice_create.php
├── Roster (fa-users-gear) → views/coach_roster.php
└── Travel (fa-plane) [Submenu]
    └── Mileage → views/travel_mileage.php
```

### Accounting & Reports (Admins Only)

```
Accounting & Reports
├── Accounting Dashboard (fa-chart-pie) → views/accounting_dashboard.php
├── Billing Dashboard (fa-file-invoice-dollar) → views/accounting_billing.php
├── Reports (fa-chart-bar) → views/accounting_reports.php
├── Schedules (fa-calendar-days) → views/accounting_schedules.php
├── Credits & Refunds (fa-money-bill-transfer) → views/accounting_credits.php
├── Expenses (fa-receipt) → views/accounting_expenses.php
└── Products (fa-box-open) → views/accounting_products.php
```

### HR (Admins Only)

```
HR
└── Termination (fa-user-slash) → views/hr_termination.php
```

### Administration (Admins Only)

```
Administration
├── All Users (fa-users) → views/admin_users.php
├── Categories (fa-folder-tree) → views/admin_categories.php
├── Eval Framework (fa-clipboard-check) → views/admin_eval_framework.php
├── System Notification (fa-bell) → views/admin_notifications.php
├── Audit Log (fa-list-check) → views/admin_audit_log.php
├── Cron Jobs (fa-clock) → views/admin_cron_jobs.php
└── System Tools (fa-screwdriver-wrench) → views/admin_system_tools.php
```

### User Menu (All Users - Sidebar Footer)

```
User Menu
├── Profile (fa-user) → views/profile.php
├── Settings (fa-gear) → views/settings.php
└── Logout → logout.php
```

---

## Route Mapping Table

| Page Parameter | View File | User Roles | Icon |
|---------------|-----------|------------|------|
| home | views/home.php | All | fa-house |
| stats | views/stats.php | All | fa-chart-line |
| upcoming_sessions | views/sessions_upcoming.php | All | - |
| booking | views/sessions_booking.php | All | - |
| drill_review | views/video_drill_review.php | All | - |
| coaches_reviews | views/video_coach_reviews.php | All | - |
| strength_conditioning | views/health_workouts.php | All | - |
| nutrition | views/health_nutrition.php | All | - |
| team_roster | views/team_roster.php | Team Coach | fa-users |
| drill_library | views/drills_library.php | Coach, Health Coach, Admin | - |
| create_drill | views/drills_create.php | Coach, Health Coach, Admin | - |
| import_drill | views/drills_import.php | Coach, Health Coach, Admin | - |
| practice_library | views/practice_library.php | Coach, Health Coach, Admin | - |
| create_practice | views/practice_create.php | Coach, Health Coach, Admin | - |
| roster | views/coach_roster.php | Coach, Health Coach, Admin | fa-users-gear |
| mileage | views/travel_mileage.php | Coach, Health Coach, Admin | - |
| accounting_dashboard | views/accounting_dashboard.php | Admin | fa-chart-pie |
| billing_dashboard | views/accounting_billing.php | Admin | fa-file-invoice-dollar |
| reports | views/accounting_reports.php | Admin | fa-chart-bar |
| schedules | views/accounting_schedules.php | Admin | fa-calendar-days |
| credits_refunds | views/accounting_credits.php | Admin | fa-money-bill-transfer |
| expenses | views/accounting_expenses.php | Admin | fa-receipt |
| products | views/accounting_products.php | Admin | fa-box-open |
| termination | views/hr_termination.php | Admin | fa-user-slash |
| all_users | views/admin_users.php | Admin | fa-users |
| categories | views/admin_categories.php | Admin | fa-folder-tree |
| eval_framework | views/admin_eval_framework.php | Admin | fa-clipboard-check |
| system_notification | views/admin_notifications.php | Admin | fa-bell |
| audit_log | views/admin_audit_log.php | Admin | fa-list-check |
| cron_jobs | views/admin_cron_jobs.php | Admin | fa-clock |
| system_tools | views/admin_system_tools.php | Admin | fa-screwdriver-wrench |
| profile | views/profile.php | All | fa-user |
| settings | views/settings.php | All | fa-gear |

---

## Role-Based Access Matrix

| Section | Athlete | Parent | Coach | Health Coach | Team Coach | Admin |
|---------|---------|--------|-------|--------------|------------|-------|
| Main Menu | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Team | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ |
| Coaches Corner | ✗ | ✗ | ✓ | ✓ | ✗ | ✓ |
| Accounting & Reports | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| HR | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Administration | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |

---

## Parent Athlete Selector

Parents have a special dropdown in the top bar allowing them to switch between viewing different athletes they are responsible for.

**Location**: Top bar (`.top-bar`)  
**Visibility**: Parents only (`$isParent`)  
**Functionality**: Dropdown to select which athlete to view

---

## Collapsible Submenus

The following navigation items have collapsible submenus:

1. **Sessions** (Main Menu)
2. **Video** (Main Menu)
3. **Health** (Main Menu)
4. **Drills** (Coaches Corner)
5. **Practice Plans** (Coaches Corner)
6. **Travel** (Coaches Corner)

**Implementation**: JavaScript `toggleSubmenu(element)` function  
**Behavior**: Click to expand/collapse, chevron rotates 90°

---

## Navigation States

### Active State
- Background: `rgba(107, 70, 193, 0.1)`
- Color: `var(--primary-light)` (#8B5CF6)
- Applied when: `$page` matches route parameter

### Hover State
- Background: `rgba(107, 70, 193, 0.1)`
- Color: `var(--primary-light)` (#8B5CF6)
- Smooth transition (0.2s)

### Default State
- Color: `var(--text)` (#A8A8B8)
- Background: transparent

---

## Validation Status

✅ All 33 routes mapped to existing view files  
✅ All icons verified (Font Awesome 6.5.1)  
✅ Role-based access properly implemented  
✅ Collapsible submenus functional  
✅ No placeholder or broken links  

---

**Last Validated**: January 21, 2026
