# Arctic Wolves - Comprehensive Maintenance Process

## Purpose
This document provides a standardized process for maintaining, updating, and repairing the Arctic Wolves platform. Follow this checklist each time you need a feature update or repair.

## Table of Contents
1. [Initial Assessment](#initial-assessment)
2. [Branding Review](#branding-review)
3. [Style Guide Compliance](#style-guide-compliance)
4. [Navigation Verification](#navigation-verification)
5. [Database Schema Validation](#database-schema-validation)
6. [Functionality Testing](#functionality-testing)
7. [UI/UX Quality Assurance](#uiux-quality-assurance)
8. [Performance Verification](#performance-verification)
9. [Documentation Update](#documentation-update)
10. [Deployment Checklist](#deployment-checklist)

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

### 6.11 Dropdowns
- [ ] Dropdown opens on click
- [ ] Options styled correctly when open
- [ ] Hover states work
- [ ] Selection updates value
- [ ] Dropdown closes after selection

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

## 9. Documentation Update

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

- **v1.0** - January 22, 2026 - Initial maintenance process document created

---

**Note**: This document should be reviewed and updated regularly as the platform evolves. All team members should be familiar with this process and follow it consistently.
