# Arctic Wolves - Comprehensive Maintenance Process

## Purpose
This document provides a standardized process for maintaining, updating, and repairing the Arctic Wolves platform. Follow this checklist each time you need a feature update or repair.

## Table of Contents
1. [**MANDATORY FIRST STEP: Reference Documents Review**](#mandatory-first-step-reference-documents-review)
2. [Initial Assessment](#initial-assessment)
3. [Branding Review](#branding-review)
4. [Style Guide Compliance](#style-guide-compliance)
5. [Navigation Verification](#navigation-verification)
6. [Database Schema Validation](#database-schema-validation)
7. [Functionality Testing](#functionality-testing)
8. [UI/UX Quality Assurance](#uiux-quality-assurance)
9. [Performance Verification](#performance-verification)
10. [Documentation Update](#documentation-update)
11. [**MANDATORY LAST STEP: Reference Documents Update**](#mandatory-last-step-reference-documents-update)
12. [Deployment Checklist](#deployment-checklist)

---

## 🚨 MANDATORY FIRST STEP: Reference Documents Review

**⚠️ CRITICAL**: Before making ANY changes to code or database, you MUST review these authoritative reference documents:

### Required Reference Documents

1. **`/QA/DATABASE_SCHEMA_REFERENCE.md`** - Authoritative database schema guide
   - [ ] Review table structures relevant to your work
   - [ ] Check column naming conventions
   - [ ] Verify foreign key relationships
   - [ ] Understand common pitfalls section
   - [ ] Note any legacy compatibility issues

2. **`/QA/STYLE_GUIDE.md`** - UI/UX design standards
   - [ ] Review color palette
   - [ ] Check typography standards
   - [ ] Verify component specifications
   - [ ] Understand spacing rules

3. **`/QA/NAVIGATION_MAP.md`** - Site navigation structure
   - [ ] Review page routing
   - [ ] Check role-based access
   - [ ] Verify URL patterns

### Why This Matters

**❌ WITHOUT reference document review:**
- You may change schema to fix one bug and break 10 other features
- Column name mismatches propagate through codebase
- Inconsistent implementations across similar features
- Technical debt accumulates rapidly

**✅ WITH reference document review:**
- Consistent implementations across entire codebase
- Fewer breaking changes
- Faster debugging (know what SHOULD be)
- Single source of truth for all developers

### Pre-Work Validation

Before proceeding to Initial Assessment, verify:
- [ ] I have read the relevant sections of DATABASE_SCHEMA_REFERENCE.md
- [ ] I understand the table/column naming conventions
- [ ] I know which foreign keys I'll be working with
- [ ] I have checked the "Common Pitfalls" section
- [ ] If working with UI: I have reviewed STYLE_GUIDE.md
- [ ] If working with navigation: I have reviewed NAVIGATION_MAP.md

**⚠️ DO NOT PROCEED** until you have completed this step.

---

## 1. Initial Assessment

### 1.1 Scope Definition
- [ ] Document the feature/issue being addressed
- [ ] Identify affected pages and views
- [ ] List impacted database tables
- [ ] Determine if backend (PHP) or frontend (JS/CSS) changes needed
- [ ] Estimate affected process files

### 1.2 Requirements Gathering
- [ ] Review original requirements
- [ ] Confirm expected behavior
- [ ] Identify user roles affected
- [ ] Document acceptance criteria

---

## 2. Branding Review

### 2.1 Logo and Brand Assets
- [ ] Verify Arctic Wolves logo is correctly displayed
- [ ] Check logo resolution and quality
- [ ] Confirm logo placement on all pages
- [ ] Verify brand colors match guidelines:
  - Primary: `#6B46C1` (Deep Purple)
  - Primary Hover: `#7C3AED`
  - Accent: `#8B5CF6`
  - Success: `#10B981`
  - Warning: `#F59E0B`
  - Error: `#EF4444`

### 2.2 Brand Consistency
- [ ] Check footer attribution
- [ ] Verify email templates use correct branding
- [ ] Confirm PDF exports include logo
- [ ] Review notification branding

---

## 3. Style Guide Compliance

### 3.1 Typography
- [ ] **Font Family**: All text uses 'Inter', sans-serif
- [ ] **Font Sizes**: Consistent across components
  - Body text: 14px
  - Headings: H1 (28px), H2 (20px), H3 (18px)
  - Small text: 12-13px
- [ ] **Font Weights**: 400 (normal), 600 (semibold), 700 (bold), 900 (black)
- [ ] **Line Heights**: 1.6 for body text
- [ ] **Letter Spacing**: -0.5px for headings

### 3.2 Colors
- [ ] Background colors use theme variables
- [ ] Text colors use theme variables
- [ ] No hardcoded colors outside theme
- [ ] Proper contrast ratios for accessibility

### 3.3 Spacing
- [ ] Consistent padding: 12px, 16px, 20px, 24px
- [ ] Consistent margins: 12px, 16px, 20px, 24px
- [ ] Proper gap spacing in flex/grid: 12px default
- [ ] No collisions or overlapping elements

### 3.4 Components

#### Buttons
- [ ] Height: 45px (36px for small)
- [ ] Padding: 0 24px (0 16px for small)
- [ ] Border radius: 8px
- [ ] Font size: 14px (13px for small)
- [ ] Font weight: 700
- [ ] All buttons functional (click handlers work)
- [ ] Hover effects applied consistently
- [ ] Disabled state styled correctly

#### Input Fields
- [ ] Height: 45px
- [ ] Padding: 0 16px
- [ ] Border: 1px solid `#2D2D3F`
- [ ] Border radius: 8px
- [ ] Font family: Inter
- [ ] Font size: 14px
- [ ] Focus state: border `#7C3AED` + shadow
- [ ] Placeholder color: `#6B6B7B`

#### Dropdowns/Select
- [ ] Same styling as input fields
- [ ] Custom arrow icon (purple chevron)
- [ ] Dropdown menu styled correctly
- [ ] Options clearly visible on click
- [ ] Hover states on options
- [ ] Modern appearance maintained

#### Checkboxes/Radio Buttons
- [ ] Modern custom styling
- [ ] Accent color: `#6B46C1`
- [ ] Size: 20x20px
- [ ] Consistent across entire site
- [ ] Functional (change events fire)

#### Tables
- [ ] Border radius: 8px on container
- [ ] Header background: `#16161F`
- [ ] Row border: 1px solid `#2D2D3F`
- [ ] Hover effect on rows
- [ ] Responsive with horizontal scroll
- [ ] Min-width: 600px
- [ ] Font size: 13px
- [ ] Padding: 16px in cells

#### Cards
- [ ] Background: `#16161F`
- [ ] Border: 1px solid `#2D2D3F`
- [ ] Border radius: 12px
- [ ] Padding: 24px
- [ ] No title/content collisions
- [ ] Headers properly spaced from borders

#### Modals
- [ ] Background overlay: rgba(0,0,0,0.8)
- [ ] Content background: `#16161F`
- [ ] Border radius: 12px
- [ ] Max-width: 600px
- [ ] Scrollable if content exceeds viewport
- [ ] Close on background click
- [ ] Close on ESC key
- [ ] Proper z-index: 9999

#### Tabs
- [ ] Horizontal layout
- [ ] Active tab indicator (bottom border)
- [ ] Hover effects
- [ ] Content panels switch correctly
- [ ] Accessible and keyboard navigable

#### Enable/Disable Controls
- [ ] **STANDARDIZED**: Use toggle switches (not mixed buttons/switches)
- [ ] Toggle design:
  - Width: 48px, Height: 24px
  - Active: `#6B46C1`
  - Inactive: `#2D2D3F`
  - Smooth transition
- [ ] Same implementation across all pages

---

## 4. Navigation Verification

### 4.1 Menu Structure
- [ ] All menu items visible
- [ ] Icons present and correct
- [ ] Role-based menu filtering works
- [ ] Active page highlighted
- [ ] Navigation position does NOT refresh/scroll on view change

### 4.2 Navigation Links
- [ ] All links lead to correct pages
- [ ] No broken links
- [ ] Query parameters preserved where needed
- [ ] Breadcrumbs accurate (if present)

### 4.3 Page Routing
For each affected page:
- [ ] URL route is correct
- [ ] Page loads without errors
- [ ] Redirects work as expected
- [ ] Back button functions correctly

### 4.4 Cross-References
- [ ] Links between related pages work
- [ ] "View Details" buttons navigate correctly
- [ ] "Edit" buttons load correct items
- [ ] "Delete" redirects appropriately

---

## 5. Database Schema Validation

### 5.1 Schema Review
For each affected table:
- [ ] Table exists in `database_schema.sql`
- [ ] All columns defined correctly
- [ ] Data types are appropriate
- [ ] NOT NULL constraints correct
- [ ] DEFAULT values set where needed

### 5.2 Relationships
- [ ] Foreign keys defined
- [ ] ON DELETE CASCADE/SET NULL appropriate
- [ ] Indexes created on foreign keys
- [ ] Junction tables for many-to-many relationships

### 5.3 Setup.php Verification
- [ ] Table creation SQL in `setup.php` matches `database_schema.sql`
- [ ] All foreign keys included
- [ ] All indexes included
- [ ] Initial data seeding correct

### 5.4 db-config.php Check
- [ ] Database connection settings correct
- [ ] Error handling in place
- [ ] Character set UTF-8
- [ ] Timezone configuration

### 5.5 PDO Consistency Check
**CRITICAL**: The codebase uses PDO for all database operations. Ensure consistency:
- [ ] **NO mysqli usage**: No `$conn`, `mysqli_query()`, `mysqli_fetch_assoc()`, etc.
- [ ] **Use $pdo object**: All database calls use `$pdo` from `db_config.php`
- [ ] **PDO Query Syntax**: Use `$pdo->query()` or `$pdo->prepare()->execute()`
- [ ] **PDO Fetch Methods**: Use `->fetch()`, `->fetchAll()`, `->rowCount()`
- [ ] **Prepared Statements**: Use parameterized queries with `->execute([params])`
- [ ] **Error Handling**: Wrap in try-catch blocks for PDOException
- [ ] **Column Name Verification**: Ensure all column names in queries match database_schema.sql
  - Common errors: Using `package_id` in sessions table (doesn't exist)
  - Common errors: Using `category_id` in expenses table (uses `category` VARCHAR instead)
  - Common errors: Using `assistant_coach_id` in sessions table (doesn't exist)
  - Common errors: Using `is_public` in sessions table (doesn't exist)
  - Common errors: Using `focus_areas` in sessions table (doesn't exist)
  - Common errors: Using `review_status` in videos table (uses `status` instead)
  - Common errors: Using `drill_type` in videos table (doesn't exist - JOIN to drills/drill_categories)
  - Common errors: Using `session_type` in sessions table (doesn't exist - JOIN to session_types)
  - Common errors: Using `athlete_id` in sessions table (doesn't exist)
  - Common errors: Using `workout_programs` table (correct name is `workout_plans`)
  - Common errors: Using `exercises` table (correct name is `exercise_library`)
  - Common errors: Using `mileage_tracking` table (correct name is `mileage_logs`)
  - Common errors: Using `travel_date` in mileage_logs (correct name is `trip_date`)
  - Common errors: Using `distance_miles` in mileage_logs (correct name is `distance_km`)

**Updated:** January 22, 2026 - Added comprehensive list of common column/table name errors found during systematic review.

#### Common Conversions
```php
// ❌ WRONG (mysqli)
$result = mysqli_query($conn, $sql);
while($row = mysqli_fetch_assoc($result)) { }
if(mysqli_num_rows($result) > 0) { }

// ✅ CORRECT (PDO)
$result = $pdo->query($sql);
while($row = $result->fetch()) { }
if($result && $result->rowCount() > 0) { }

// ❌ WRONG (mysqli prepared)
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $param);
$stmt->execute();
$result = $stmt->get_result();

// ✅ CORRECT (PDO prepared)
$stmt = $pdo->prepare($sql);
$stmt->execute([$param]);
while($row = $stmt->fetch()) { }
```

#### Files to Check
- [ ] `dashboard.php` - Check parent athlete selector
- [ ] All `views/*.php` - Check query syntax
- [ ] All `process_*.php` - Check database operations
- [ ] Custom scripts and cron jobs

#### Quick Scan Commands
```bash
# Find any mysqli usage (should return nothing)
grep -rn "mysqli_" --include="*.php" .
grep -rn "\$conn->" --include="*.php" . | grep -v "connection\|config\|mailer"

# Find PDO usage (should find many)
grep -rn "\$pdo->" --include="*.php" .
```

### 5.6 Process File Database Calls
For each process file:
- [ ] SELECT queries reference correct columns
- [ ] INSERT queries include all required columns
- [ ] UPDATE queries target correct columns
- [ ] DELETE queries have proper WHERE clauses
- [ ] Prepared statements used (no SQL injection)
- [ ] Error handling on database operations

### 5.6 View File Database Calls
For each view file:
- [ ] Queries pull correct data
- [ ] JOINs use correct foreign keys
- [ ] WHERE clauses filter appropriately
- [ ] ORDER BY clauses make sense
- [ ] LIMIT/OFFSET for pagination if needed

### 5.7 Cross-Page Consistency
- [ ] Same data displayed consistently across different views
- [ ] Foreign key relationships maintained
- [ ] Cascading updates/deletes work correctly
- [ ] No orphaned records created

---

## 6. Functionality Testing

### 6.1 Buttons
Test each button on the page:
- [ ] **Add/Create** buttons open modals or navigate to creation forms
- [ ] **Edit** buttons load item data correctly
- [ ] **Delete** buttons show confirmation and delete on confirm
- [ ] **Save** buttons submit forms and show success/error
- [ ] **Cancel** buttons close modals or navigate back
- [ ] **Export** buttons generate files correctly
- [ ] **Upload** buttons open file dialogs
- [ ] **Submit** buttons validate and process forms
- [ ] **Filter** buttons apply filters correctly
- [ ] **Clear** buttons reset filters/forms
- [ ] **Quick Action** buttons navigate to appropriate pages (not home)
- [ ] **Tab** buttons switch between tabs without page reload
- [ ] All buttons have appropriate `data-action` and `data-page`/`data-modal` attributes
- [ ] All forms have `action="process_*.php"` and `method="POST"` attributes
- [ ] All forms include CSRF token hidden input: `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">`
- [ ] All form inputs have proper `name` attributes matching what process files expect

### 6.2 Search Functionality
- [ ] Search input present and visible
- [ ] Search filters table rows in real-time (debounced)
- [ ] Search is case-insensitive
- [ ] "No results" message shows when appropriate
- [ ] Search clears correctly

### 6.3 Filters
- [ ] Filter dropdowns populated correctly
- [ ] Filters apply to correct columns
- [ ] Multiple filters work together
- [ ] "All" option clears filter
- [ ] Date range filters work correctly

### 6.4 Forms
- [ ] All form fields present
- [ ] Required fields marked with asterisk
- [ ] Validation works (client-side and server-side)
- [ ] Error messages display clearly
- [ ] Success messages show on submission
- [ ] Form data persists on error
- [ ] Form resets after successful submission
- [ ] **CRITICAL**: Every form has `action="process_*.php"` attribute
- [ ] **CRITICAL**: Every form has `method="POST"` attribute
- [ ] **CRITICAL**: Every form includes CSRF token: `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">`
- [ ] **CRITICAL**: All form inputs have `name` attributes (not just id/class)
- [ ] Form action points to correct process file (e.g., expense form → process_expenses.php)
- [ ] Hidden input for action type: `<input type="hidden" name="action" value="create">` or similar

### 6.5 Checkboxes
- [ ] Checkboxes toggle on click
- [ ] Selected state visually clear
- [ ] Bulk selection works (if applicable)
- [ ] Form submissions include checkbox values

### 6.6 File Uploads
- [ ] File input accessible via button click
- [ ] Drag-drop works (if implemented)
- [ ] File name displays after selection
- [ ] File validation (size, type) works
- [ ] Upload progress indicator (if applicable)
- [ ] Mobile camera access works (for expenses)

### 6.7 Date Pickers
- [ ] Date picker opens on click
- [ ] Calendar UI is modern and styled
- [ ] Can type date manually
- [ ] Can select date from calendar
- [ ] Date format consistent (YYYY-MM-DD)
- [ ] Min/max dates enforced (if applicable)

### 6.8 Modals
- [ ] Modal opens on trigger
- [ ] Modal closes on background click
- [ ] Modal closes on ESC key
- [ ] Modal closes on close button
- [ ] Form in modal submits correctly
- [ ] Modal scrolls if content is tall

### 6.9 Tables
- [ ] Data loads correctly
- [ ] Sorting works (if enabled)
- [ ] Pagination works (if enabled)
- [ ] Row actions functional
- [ ] Table scrolls horizontally on mobile

### 6.10 Tabs
- [ ] Clicking tab switches content
- [ ] Active tab visually indicated
- [ ] Tab content loads correctly
- [ ] Deep linking to tabs works (if applicable)
- [ ] Tabs use either query parameter navigation OR JavaScript switching
- [ ] Tab buttons have `data-action="switch-tab"` and `data-tab="tab-name"` for JS tabs
- [ ] Tab content containers have matching IDs or data attributes

### 6.11 Dropdowns
- [ ] Dropdown opens on click
- [ ] Options styled correctly when open
- [ ] Hover states work
- [ ] Selection updates value
- [ ] Dropdown closes after selection
- [ ] No checkered background pattern on option hover
- [ ] Options use consistent Inter font
- [ ] Selected option has distinct styling
- [ ] Dropdown matches site theme colors

### 6.12 Button Configuration Patterns
**CRITICAL**: All interactive buttons MUST have proper data attributes to work correctly.

#### Required Data Attributes by Button Type:
```html
<!-- Add/Create buttons -->
<button data-action="add" data-modal="modal-id">Add Item</button>
<button data-action="add" data-page="page-name">Add Item</button>

<!-- Edit buttons -->
<button data-action="edit" data-id="123" data-modal="edit-modal">Edit</button>

<!-- Delete buttons -->
<button data-action="delete" data-id="123" data-name="Item Name">Delete</button>

<!-- Navigation buttons -->
<button data-action="view" data-page="page-name">View</button>
<button data-action="custom-action" data-page="destination">Action</button>

<!-- Tab buttons -->
<button data-action="switch-tab" data-tab="tab-name">Tab</button>
```

#### Common Button Issues:
- [ ] **Button reloads to home**: Missing `data-page`, `data-modal`, or `data-url` attribute
- [ ] **Button does nothing**: Missing `data-action` attribute entirely
- [ ] **Tab doesn't switch**: Missing `data-action="switch-tab"` and `data-tab`
- [ ] **Modal doesn't open**: Modal ID doesn't match or modal HTML missing
- [ ] **Form doesn't submit**: Form missing `action` attribute or proper submit handler

#### Testing Procedure:
1. Click every button on the page
2. Verify expected action occurs
3. Check browser console for JavaScript errors
4. If button fails, inspect HTML for missing data attributes
5. Add appropriate attributes and test again

### 6.13 Form Submission Infrastructure
**CRITICAL**: Forms that don't submit are usually missing basic HTML attributes.

#### Required Form Structure:
```html
<form method="POST" action="process_specific_action.php">
    <!-- CSRF Protection (REQUIRED) -->
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
    
    <!-- Action identifier (REQUIRED) -->
    <input type="hidden" name="action" value="create">
    
    <!-- User context (if needed) -->
    <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?? '' ?>">
    
    <!-- Form fields (ALL need name attribute) -->
    <input type="text" name="field_name" class="form-input" required>
    <select name="category" class="form-input">
        <option value="">Select...</option>
    </select>
    
    <!-- Submit button -->
    <button type="submit" class="btn-primary">
        <i class="fas fa-save"></i> Submit
    </button>
</form>
```

#### Common Form Issues:
- [ ] **Form reloads to home**: Missing or empty `action` attribute
- [ ] **Form doesn't POST data**: Missing `method="POST"` attribute
- [ ] **Process file doesn't receive data**: Input fields missing `name` attributes (having only `id` or `class` is insufficient)
- [ ] **CSRF error**: Missing CSRF token hidden input
- [ ] **Process file can't identify action**: Missing `action` hidden input (create/update/delete)
- [ ] **Custom data-action ignored**: app.js doesn't handle custom actions without corresponding handler

#### Form Debugging Checklist:
1. [ ] Open browser DevTools → Network tab
2. [ ] Submit the form
3. [ ] Check if POST request is made
4. [ ] If no request: Form missing `action` or `method`
5. [ ] If request made but fails: Check process file exists and is correct
6. [ ] Check POST payload: All fields have values?
7. [ ] If fields empty: Inputs missing `name` attributes

#### Process File Expectations:
Every form should POST to a corresponding `process_*.php` file:
- Expense forms → `process_expenses.php`
- User forms → `process_admin_action.php` or `process_manage_athletes.php`
- Settings forms → `process_settings.php`
- Notification forms → `process_system_notifications.php`
- Report forms → `process_reports.php`
- Etc.

Check that:
- [ ] Process file exists in repository root
- [ ] Process file expects correct $_POST parameters
- [ ] Process file includes database operations
- [ ] Process file returns JSON or redirects appropriately
3. Check browser console for JavaScript errors
4. If button fails, inspect HTML for missing data attributes
5. Add appropriate attributes and test again

---

## 7. UI/UX Quality Assurance

### 7.1 Collision Check
Verify NO collisions between:
- [ ] Headers and card borders
- [ ] Text and buttons
- [ ] Icons and text
- [ ] Form fields and labels
- [ ] Table headers and borders
- [ ] Navigation items
- [ ] Modal content and modal edges
- [ ] Footer content

### 7.2 Spacing Consistency
- [ ] Equal padding inside cards
- [ ] Consistent gap between form fields
- [ ] Consistent margin between sections
- [ ] No elements touching screen edges
- [ ] Proper whitespace around buttons

### 7.3 Alignment
- [ ] Form labels aligned with inputs
- [ ] Table columns aligned
- [ ] Button groups aligned
- [ ] Card headers aligned
- [ ] Text alignment consistent

### 7.4 Visual Balance
- [ ] Elements not too large (buttons, boxes)
- [ ] Elements not too small (text, icons)
- [ ] Proper hierarchy (titles > subtitles > body)
- [ ] Icons appropriately sized
- [ ] Images/logos properly scaled

### 7.5 Visual Appeal
- [ ] Color contrast sufficient
- [ ] Gradients smooth (if used)
- [ ] Shadows subtle and appropriate
- [ ] Hover effects smooth
- [ ] Transitions not jarring
- [ ] Overall "vibrancy" - not dull

### 7.6 Responsive Design
- [ ] Layout adapts to mobile (< 768px)
- [ ] Layout adapts to tablet (768px - 1024px)
- [ ] Layout works on desktop (> 1024px)
- [ ] Text readable on all screen sizes
- [ ] Buttons accessible on touch devices
- [ ] No horizontal scroll on mobile (except tables)

---

## 8. Performance Verification

### 8.1 Load Times
- [ ] Page loads in < 3 seconds
- [ ] Images optimized (< 200KB each)
- [ ] CSS minified in production
- [ ] JavaScript minified in production

### 8.2 Database Queries
- [ ] No N+1 query problems
- [ ] Queries use indexes
- [ ] Large result sets paginated
- [ ] Slow queries logged and optimized

### 8.3 Caching
- [ ] Static assets cached
- [ ] Database query results cached where appropriate
- [ ] Cache invalidation working

---

## 🚨 MANDATORY LAST STEP: Reference Documents Update

**⚠️ CRITICAL**: After completing all work and before deployment, you MUST update reference documents if you made any changes.

### Reference Document Update Checklist

#### 1. Database Schema Changes
If you modified ANY database-related code:
- [ ] Did you add/modify/delete any tables?
  - [ ] YES → Update `DATABASE_SCHEMA_REFERENCE.md` Table Categories section
  - [ ] YES → Update `database_schema.sql`
  - [ ] NO → Skip to next check
- [ ] Did you add/modify/delete any columns?
  - [ ] YES → Update `DATABASE_SCHEMA_REFERENCE.md` Critical Column Reference
  - [ ] YES → Document in schema file with comments
  - [ ] NO → Skip to next check
- [ ] Did you add/modify foreign key relationships?
  - [ ] YES → Update Foreign Key Relationships section
  - [ ] YES → Add to Common FK Column Names list if new pattern
  - [ ] NO → Skip to next check
- [ ] Did you encounter a schema pitfall?
  - [ ] YES → Add it to the "Common Pitfalls" section
  - [ ] Include the wrong way and the right way
  - [ ] NO → Skip to next check

#### 2. Style Guide Changes
If you modified ANY UI/styling:
- [ ] Did you create new UI components?
  - [ ] YES → Add component specifications to STYLE_GUIDE.md
  - [ ] YES → Include code examples
  - [ ] NO → Skip to next check
- [ ] Did you modify existing component styles?
  - [ ] YES → Update component specifications
  - [ ] YES → Update screenshots if applicable
  - [ ] NO → Skip to next check
- [ ] Did you add new colors/typography?
  - [ ] YES → Update color palette or typography section
  - [ ] YES → Ensure CSS variables are documented
  - [ ] NO → Skip to next check

#### 3. Navigation Changes
If you modified ANY routing or navigation:
- [ ] Did you add new pages?
  - [ ] YES → Update NAVIGATION_MAP.md with new routes
  - [ ] YES → Document role-based access requirements
  - [ ] YES → Update dashboard.php routing table
  - [ ] NO → Skip to next check
- [ ] Did you modify page URLs?
  - [ ] YES → Update all affected route documentation
  - [ ] YES → Check for hardcoded links in code
  - [ ] NO → Skip to next check
- [ ] Did you change role-based access?
  - [ ] YES → Update access control documentation
  - [ ] NO → Skip to next check

#### 4. Process Files
If you created/modified ANY process_*.php files:
- [ ] Document expected parameters in code comments
- [ ] Document JSON response format
- [ ] If using redirects, document why (should use JSON instead)
- [ ] List all database tables accessed

#### 5. Version History
- [ ] Update version history in modified reference documents
- [ ] Include date, change summary, and your name/identifier
- [ ] Reference related issue/ticket number if applicable

### Validation Before Commit

Run these checks before committing:
```bash
# Check for schema consistency
grep -rn "FROM users.*user_id = u.user_id" --include="*.php" .
# Should return NOTHING - if it does, you have a bug

# Check for mysqli usage
grep -rn "mysqli_" --include="*.php" .
# Should return NOTHING - we use PDO only

# Check for hardcoded table names that should be variables
grep -rn "FROM programs" --include="*.php" .
# Verify these all match schema (should be training_programs OR use VIEW)
```

### Cross-Reference Validation

Before marking complete:
- [ ] All changes documented in relevant reference files
- [ ] Reference files match actual implementation
- [ ] No discrepancies between schema and code
- [ ] All new patterns added to reference guides
- [ ] Version history updated

**⚠️ DO NOT DEPLOY** until reference documents are updated and validated.

---

## 10. Deployment Checklist

### 9.1 Code Comments
- [ ] New functions commented
- [ ] Complex logic explained
- [ ] TODOs removed or addressed

### 9.2 README Files
- [ ] Feature documented in relevant README
- [ ] Examples provided if needed
- [ ] Dependencies listed

### 9.3 Style Guide
- [ ] New components added to style guide
- [ ] Color changes documented
- [ ] Typography changes documented

### 9.4 Navigation Map
- [ ] New routes added to NAVIGATION_MAP.md
- [ ] Route permissions documented

### 9.5 Database Schema
- [ ] Changes reflected in database_schema.sql
- [ ] ER diagram updated if needed
- [ ] DATABASE_VALIDATION.md updated

---

## 10. Deployment Checklist

### 10.1 Pre-Deployment
- [ ] All tests passing
- [ ] No console errors in browser
- [ ] No PHP errors in logs
- [ ] Code reviewed
- [ ] Backup database

### 10.2 Deployment
- [ ] Deploy to staging first
- [ ] Test on staging
- [ ] Deploy to production
- [ ] Run database migrations

### 10.3 Post-Deployment
- [ ] Smoke test critical paths
- [ ] Monitor error logs
- [ ] Verify with stakeholders
- [ ] Update deployment log

---

## Quick Reference: File Locations

### Frontend Files
- **Main CSS**: `/style.css`
- **Shared Styles**: `/views/shared_styles.css`
- **Main JavaScript**: `/js/app.js`

### Backend Files
- **Database Schema**: `/database_schema.sql`
- **Setup Script**: `/setup.php`
- **DB Config**: `/db_config.php`
- **Process Files**: `/process_*.php`

### View Files
- **All Views**: `/views/*.php`

### Documentation
- **Style Guide**: `/QA/STYLE_GUIDE.md`
- **Navigation Map**: `/QA/NAVIGATION_MAP.md`
- **Database Validation**: `/QA/DATABASE_VALIDATION.md`
- **Database Schema Diagram**: `/QA/DATABASE_SCHEMA_DIAGRAM.md`

---

## Issue Reporting Template

When reporting an issue, include:

1. **Page/Feature**: Which page or feature is affected?
2. **Issue Type**: Bug, UI issue, functionality problem, etc.
3. **Steps to Reproduce**: How to recreate the issue
4. **Expected Behavior**: What should happen?
5. **Actual Behavior**: What actually happens?
6. **Screenshots**: Visual evidence
7. **Browser/Device**: Chrome, Firefox, Safari, Mobile?
8. **User Role**: Admin, Coach, Athlete, Parent?
9. **Database Impact**: Which tables are involved?
10. **Priority**: Critical, High, Medium, Low

---

## Version History

- **v1.2** - January 22, 2026 - Added critical form submission infrastructure section, enhanced PDO column name verification, added form debugging checklist and process file expectations
- **v1.1** - January 22, 2026 - Added comprehensive button testing procedures, tab navigation checks, dropdown validation, and button configuration patterns section
- **v1.0** - January 22, 2026 - Initial maintenance process document created

---

**Note**: This document should be reviewed and updated regularly as the platform evolves. All team members should be familiar with this process and follow it consistently.
