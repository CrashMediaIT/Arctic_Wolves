# Arctic Wolves - Role Structure and Permissions

**Created:** January 22, 2026  
**Version:** 1.0  
**Purpose:** Document role types, permissions, and access control for the Arctic Wolves platform

---

## Table of Contents

1. [Role Overview](#role-overview)
2. [Role Definitions](#role-definitions)
3. [Permission Matrix](#permission-matrix)
4. [Navigation Access by Role](#navigation-access-by-role)
5. [Feature Access Control](#feature-access-control)
6. [Database Tables](#database-tables)
7. [Implementation Notes](#implementation-notes)

---

## Role Overview

The Arctic Wolves platform supports **six distinct user roles**, each with specific permissions and access levels designed to support different user needs within a hockey coaching and athlete management system.

### Role Types

1. **Athlete** - Players receiving coaching and tracking their performance
2. **Coach** - Coaches providing training and managing athletes
3. **Admin** - System administrators with full access
4. **Parent** - Parents/guardians monitoring their athlete's progress
5. **Health Coach** - Specialized coaches for health, nutrition, and conditioning
6. **Team Coach** - Coaches managing entire teams and rosters

### Role Hierarchy

```
Admin (Full System Access)
├── Coach (Training & Athlete Management)
│   ├── Team Coach (Team-level Management)
│   └── Health Coach (Health & Nutrition Focus)
├── Parent (View-Only Access to Children)
└── Athlete (Personal Performance Tracking)
```

---

## Role Definitions

### 1. Athlete

**Primary Purpose:** Track personal performance, access training materials, book sessions

**Key Characteristics:**
- Can view their own stats, videos, and health plans
- Can book training sessions
- Can view assigned drills and practice plans
- Can set and track personal goals
- **Cannot** manage other users
- **Cannot** create content (drills, plans)
- **Cannot** access admin features

**Typical Use Cases:**
- Checking upcoming training sessions
- Reviewing performance statistics
- Watching drill review videos
- Following assigned workout plans
- Tracking nutrition plans

---

### 2. Coach

**Primary Purpose:** Create training content, manage athletes, provide feedback

**Key Characteristics:**
- Can view athlete stats and performance
- Can create drills and practice plans
- Can review athlete videos and provide feedback
- Can manage their assigned roster
- Can track mileage for travel
- **Cannot** access accounting/billing
- **Cannot** access admin system settings
- **Cannot** manage all users

**Typical Use Cases:**
- Creating drill libraries
- Building practice plans
- Reviewing athlete video submissions
- Managing team roster
- Logging travel mileage
- Providing performance feedback

**Note:** Coaches can also be athletes, so they may have access to athlete features for their own performance tracking.

---

### 3. Admin

**Primary Purpose:** Full system administration, billing, reporting, user management

**Key Characteristics:**
- **Full access** to all system features
- Can manage all users (create, edit, delete)
- Can access accounting and billing dashboards
- Can generate reports and schedules
- Can configure system settings
- Can manage HR functions (terminations)
- Can access audit logs and cron jobs
- Can configure categories, skills, positions, equipment
- Can manage eval frameworks

**Typical Use Cases:**
- User account management
- Financial reporting and billing
- System configuration
- Security and audit review
- Processing refunds and credits
- Managing expenses
- Configuring notification systems
- Database maintenance

**Note:** Admins may also be athletes or coaches and will have access to those features.

---

### 4. Parent

**Primary Purpose:** Monitor child athlete's progress and participation

**Key Characteristics:**
- Can view their child athlete's performance stats
- Can view their child's upcoming sessions and bookings
- Can view assigned training plans and drills
- **Cannot** modify athlete data
- **Cannot** book sessions on athlete's behalf (typically)
- **Cannot** create content
- **Cannot** access coaching or admin features

**Typical Use Cases:**
- Checking child's upcoming practice schedule
- Viewing child's performance statistics
- Monitoring training participation
- Reviewing assigned drills and workout plans

**Implementation Note:** Parent accounts are linked to specific athlete accounts through a parent-child relationship in the database.

---

### 5. Health Coach

**Primary Purpose:** Specialized coaching for nutrition and conditioning

**Key Characteristics:**
- Can create workout plans and nutrition plans
- Can view athlete health metrics
- Can create drills focused on conditioning
- Can access drill and practice plan creation
- **Cannot** manage team rosters (unless also team coach)
- **Cannot** access accounting/billing
- **Cannot** access admin features

**Typical Use Cases:**
- Creating workout programs for strength and conditioning
- Designing nutrition plans
- Creating conditioning drills
- Monitoring athlete health metrics
- Providing health and wellness feedback

**Note:** Health coaches may also be athletes and have access to athlete features.

---

### 6. Team Coach

**Primary Purpose:** Manage entire teams and team rosters

**Key Characteristics:**
- Can manage team roster
- Can view all team member stats
- Can create drills and practice plans for the team
- Can assign team-wide training sessions
- **Cannot** access accounting/billing (unless also admin)
- **Cannot** access admin system settings

**Typical Use Cases:**
- Managing team roster (add/remove players)
- Viewing team-wide statistics
- Creating team practice plans
- Assigning team training sessions
- Organizing team activities

**Note:** Team coaches may also be regular coaches, health coaches, or athletes.

---

## Permission Matrix

### Navigation Menu Access

| Feature Area | Athlete | Parent | Coach | Health Coach | Team Coach | Admin |
|-------------|---------|--------|-------|--------------|------------|-------|
| **Main Menu** |
| Home | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Performance Stats | ✓ (own) | ✓ (child) | ✓ | ✓ | ✓ | ✓ |
| Sessions | ✓ | ✓ (view) | ✓ | ✓ | ✓ | ✓ |
| Video | ✓ (own) | ✓ (child) | ✓ | ✓ | ✓ | ✓ |
| Health | ✓ (own) | ✓ (child) | ✓ | ✓ | ✓ | ✓ |
| **Team Section** |
| Roster (Team) | ✗ | ✗ | ✗ | ✗ | ✓ | ✓ |
| **Coaches Corner** |
| Drills | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Practice Plans | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Roster (Coach) | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Travel (Mileage) | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| **Accounting & Reports** |
| Accounting Dashboard | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Billing Dashboard | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Reports | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Schedules | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Credits & Refunds | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Expenses | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Products | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| **HR** |
| Termination | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| **Administration** |
| All Users | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Categories | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Eval Framework | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| System Notifications | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Audit Log | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Cron Jobs | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| System Tools | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| **User Menu** |
| Profile | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Settings | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Logout | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

**Legend:**
- ✓ = Full Access
- ✗ = No Access
- (own) = Can only view/edit their own data
- (child) = Can only view their child's data

---

## Feature Access Control

### Create/Edit/Delete Permissions

| Action | Athlete | Parent | Coach | Health Coach | Team Coach | Admin |
|--------|---------|--------|-------|--------------|------------|-------|
| **Users** |
| Create User | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Edit Own Profile | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Edit Other Users | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Delete Users | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| **Goals** |
| Create Own Goals | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Edit Own Goals | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ |
| View Athlete Goals | ✗ | ✓ (child) | ✓ (assigned) | ✓ (assigned) | ✓ | ✓ |
| Approve Goals | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| **Drills** |
| Create Drills | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Edit Drills | ✗ | ✗ | ✓ (own) | ✓ (own) | ✓ (own) | ✓ |
| Delete Drills | ✗ | ✗ | ✓ (own) | ✓ (own) | ✓ (own) | ✓ |
| View Drill Library | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Practice Plans** |
| Create Plans | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Edit Plans | ✗ | ✗ | ✓ (own) | ✓ (own) | ✓ (own) | ✓ |
| Delete Plans | ✗ | ✗ | ✓ (own) | ✓ (own) | ✓ (own) | ✓ |
| View Plans | ✓ (assigned) | ✓ (child) | ✓ | ✓ | ✓ | ✓ |
| **Sessions** |
| Book Sessions | ✓ | ? | ✓ | ✓ | ✓ | ✓ |
| Create Sessions | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Edit Sessions | ✗ | ✗ | ✓ (own) | ✓ (own) | ✓ | ✓ |
| Cancel Sessions | ✓ (own) | ✗ | ✓ | ✓ | ✓ | ✓ |
| **Videos** |
| Upload Videos | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Review Videos | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ |
| Delete Videos | ✓ (own) | ✗ | ✓ (assigned) | ✓ (assigned) | ✓ | ✓ |
| **Health Plans** |
| Create Workout Plans | ✗ | ✗ | ✗ | ✓ | ✗ | ✓ |
| Create Nutrition Plans | ✗ | ✗ | ✗ | ✓ | ✗ | ✓ |
| View Own Plans | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ |
| View Athlete Plans | ✗ | ✓ (child) | ✓ (assigned) | ✓ | ✓ | ✓ |
| **Roster Management** |
| Add Athletes | ✗ | ✗ | ✓ | ✗ | ✓ | ✓ |
| Remove Athletes | ✗ | ✗ | ✓ | ✗ | ✓ | ✓ |
| View Roster | ✗ | ✗ | ✓ (assigned) | ✓ (assigned) | ✓ | ✓ |
| **Accounting** |
| View Reports | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Create Invoices | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Process Refunds | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Manage Expenses | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Manage Products | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| **System Admin** |
| Manage Categories | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Eval Framework | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| System Settings | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Audit Logs | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Cron Jobs | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |
| Send Notifications | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ |

**Legend:**
- ✓ = Allowed
- ✗ = Not Allowed
- (own) = Only their own content
- (assigned) = Content they are responsible for
- (child) = Their child athlete's content
- ? = To be determined/configured

---

## Navigation Access by Role

### Athlete Navigation

**Visible Menu Items:**
- Home
- Performance Stats
- Sessions (Upcoming Sessions, Booking)
- Video (Drill Review, Coaches Reviews)
- Health (Strength & Conditioning, Nutrition)
- Profile
- Settings
- Logout

**Hidden Menu Items:**
- Team Section (all)
- Coaches Corner (all)
- Accounting & Reports (all)
- HR (all)
- Administration (all)

---

### Parent Navigation

**Visible Menu Items:**
- Home
- Performance Stats (child's stats)
- Sessions (child's sessions - view only)
- Video (child's videos)
- Health (child's plans)
- Profile
- Settings
- Logout

**Hidden Menu Items:**
- Team Section (all)
- Coaches Corner (all)
- Accounting & Reports (all)
- HR (all)
- Administration (all)

---

### Coach Navigation

**Visible Menu Items:**
- Home
- Performance Stats
- Sessions (Upcoming Sessions, Booking)
- Video (Drill Review, Coaches Reviews)
- Health (Strength & Conditioning, Nutrition)
- **Coaches Corner:**
  - Drills (Library, Create, Import)
  - Practice Plans (Library, Create)
  - Roster
  - Travel (Mileage)
- Profile
- Settings
- Logout

**Hidden Menu Items:**
- Team Section
- Accounting & Reports (all)
- HR (all)
- Administration (all)

---

### Health Coach Navigation

**Visible Menu Items:**
- Home
- Performance Stats
- Sessions (Upcoming Sessions, Booking)
- Video (Drill Review, Coaches Reviews)
- Health (Strength & Conditioning, Nutrition) - **Enhanced Access**
- **Coaches Corner:**
  - Drills (Library, Create, Import)
  - Practice Plans (Library, Create)
  - Roster
  - Travel (Mileage)
- Profile
- Settings
- Logout

**Hidden Menu Items:**
- Team Section
- Accounting & Reports (all)
- HR (all)
- Administration (all)

**Special Permissions:**
- Create/Edit Workout Plans
- Create/Edit Nutrition Plans
- Access athlete health metrics

---

### Team Coach Navigation

**Visible Menu Items:**
- Home
- Performance Stats
- Sessions (Upcoming Sessions, Booking)
- Video (Drill Review, Coaches Reviews)
- Health (Strength & Conditioning, Nutrition)
- **Team Section:**
  - Roster (Team Management)
- **Coaches Corner:**
  - Drills (Library, Create, Import)
  - Practice Plans (Library, Create)
  - Roster (Coach View)
  - Travel (Mileage)
- Profile
- Settings
- Logout

**Hidden Menu Items:**
- Accounting & Reports (all)
- HR (all)
- Administration (all)

---

### Admin Navigation

**Visible Menu Items:**
- **Everything** - Full system access
- Home
- Performance Stats
- Sessions
- Video
- Health
- Team Section (all)
- Coaches Corner (all)
- **Accounting & Reports:**
  - Accounting Dashboard
  - Billing Dashboard
  - Reports
  - Schedules
  - Credits & Refunds
  - Expenses
  - Products
- **HR:**
  - Termination
- **Administration:**
  - All Users
  - Categories
  - Eval Framework
  - System Notifications
  - Audit Log
  - Cron Jobs
  - System Tools
- Profile
- Settings
- Logout

**Special Permissions:**
- Full database access
- User management
- Financial operations
- System configuration
- Security and audit

---

## Database Tables

### Users Table

```sql
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach') DEFAULT 'athlete',
    ...
);
```

### Role Permissions Table

```sql
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` ENUM('athlete', 'coach', 'admin', 'parent', 'health_coach', 'team_coach') NOT NULL,
    `permission_id` INT NOT NULL,
    `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_role_permission` (`role`, `permission_id`),
    INDEX `idx_role` (`role`)
);
```

### Permissions Table

The `permissions` table defines granular permissions that can be assigned to roles.

**Common Permissions Include:**
- `view_all_athletes`
- `manage_athletes`
- `create_drills`
- `edit_drills`
- `create_practice_plans`
- `view_financials`
- `manage_billing`
- `system_admin`
- `manage_users`
- `view_audit_logs`
- etc.

---

## Implementation Notes

### Role Checking in PHP

All views and process files should check user role before displaying content or processing actions:

```php
// Example role check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$user_role = $_SESSION['role'];

// Admin-only check
if ($user_role !== 'admin') {
    die("Access denied. Admin privileges required.");
}

// Coach or Admin check
if (!in_array($user_role, ['coach', 'health_coach', 'team_coach', 'admin'])) {
    die("Access denied. Coach privileges required.");
}
```

### Navigation Menu Filtering

The `dashboard.php` file dynamically builds navigation menus based on user role:

```php
// Example navigation filtering
$show_coaches_corner = in_array($_SESSION['role'], ['coach', 'health_coach', 'team_coach', 'admin']);
$show_accounting = $_SESSION['role'] === 'admin';
$show_administration = $_SESSION['role'] === 'admin';
```

### Database Queries with Role Filtering

Queries should automatically filter data based on user role:

```php
// Example: Coach can only see assigned athletes
if ($user_role === 'coach') {
    $sql = "SELECT * FROM users WHERE coach_id = :coach_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['coach_id' => $_SESSION['user_id']]);
}
// Admin sees all
elseif ($user_role === 'admin') {
    $sql = "SELECT * FROM users";
    $stmt = $pdo->query($sql);
}
```

### Multiple Role Support

**Important:** Users can have overlapping roles. For example:
- An Admin can also be a Coach and an Athlete
- A Team Coach can also be a Health Coach
- A Coach can also be an Athlete

The system should:
1. Store primary role in `users.role` column
2. Use role-based checks for access control
3. Allow users to access features from all applicable roles
4. Show appropriate menu items based on role

### Security Best Practices

1. **Always validate role on server-side** - Never trust client-side checks
2. **Check role at page load** - Every view file should verify access
3. **Check role before processing** - Every process file should verify permissions
4. **Log access attempts** - Failed access attempts should be logged in audit log
5. **Use prepared statements** - Prevent SQL injection in role-based queries
6. **Session security** - Regenerate session IDs after role changes

---

## Common Access Patterns

### Pattern 1: View Own Data
Athletes and parents view only their own (or their child's) data:
```php
$sql = "SELECT * FROM performance_stats WHERE user_id = :user_id";
```

### Pattern 2: View Assigned Data
Coaches view data for athletes assigned to them:
```php
$sql = "SELECT * FROM users u 
        JOIN coach_athletes ca ON u.id = ca.athlete_id 
        WHERE ca.coach_id = :coach_id";
```

### Pattern 3: View All Data
Admins view all data:
```php
$sql = "SELECT * FROM users"; // No WHERE clause
```

### Pattern 4: Create Content
Only certain roles can create content:
```php
if (in_array($user_role, ['coach', 'health_coach', 'team_coach', 'admin'])) {
    // Allow drill creation
}
```

### Pattern 5: Manage System
Only admins manage system settings:
```php
if ($user_role !== 'admin') {
    die("Access denied.");
}
// Proceed with system configuration
```

---

## Future Enhancements

### Potential Role Additions
- **Assistant Coach** - Limited coaching privileges
- **Guest** - Read-only temporary access
- **Trainer** - Specialized training focus
- **Manager** - Team management without coaching

### Potential Permission Granularity
- Per-feature permissions (not just per-role)
- Custom permission sets
- Time-based access (temporary permissions)
- Organization-level permissions (multi-tenant)

---

## Version History

- **v1.0** - January 22, 2026 - Initial role structure and permissions documentation created

---

## References

Related documentation:
- `/QA/NAVIGATION_MAP.md` - Complete navigation structure
- `/QA/STRUCTURE.md` - Application structure
- `/database_schema.sql` - Database schema including users and role_permissions tables
- `/dashboard.php` - Navigation menu rendering with role checks
