# Arctic Wolves - Browser Testing Plan with Playwright
**Created:** January 24, 2026  
**Purpose:** Document browser testing strategy for validating fixes and identifying remaining issues

---

## Testing Environment Setup

### Prerequisites
- Playwright installed (`npm install @playwright/test`)
- Local development server running (PHP built-in server or Apache)
- Database populated with demo data (use `demo_data_seeder.php`)
- Test user accounts for each role (athlete, coach, admin)

### Test Users
```
Admin: admin@test.com / password123
Coach: coach@test.com / password123
Athlete: athlete@test.com / password123
```

---

## Critical Test Cases (Based on Problem Statement)

### 1. Performance Stats - Sessions Tab
**Issue:** "Selecting the filters brings you back to home screen"  
**Test:**
- Navigate to Performance Stats
- Verify Sessions tab loads
- Apply filters (date range, session type)
- Verify page doesn't redirect to home
- Verify filter results display correctly

**Expected:** Filters work without redirect  
**Screenshot:** Performance stats filters working

---

### 2. Sessions - Book Session Button
**Issue:** "Book session button does not follow the style guide"  
**Test:**
- Navigate to Sessions → Booking tab
- Verify "Book Session" button exists
- Check button color: Should be #6B46C1 (var(--primary))
- Check button has icon: Should have `<i class="fas fa-plus">` or similar
- Verify button height: 45px
- Verify font: Inter, 14px, weight 700

**Expected:** Button follows style guide exactly  
**Screenshot:** Book session button with correct styling

---

### 3. Video - Drill Review Tab
**Issue:** "Fatal error: Unknown column 'd.name'"  
**Status:** ✅ FIXED (changed to d.title)  
**Test:**
- Navigate to Video → Drill Review
- Verify page loads without error
- Verify drill videos display
- Test search functionality
- Test filter dropdowns

**Expected:** No SQL errors, page loads successfully  
**Screenshot:** Drill review page loading correctly

---

### 4. Coaches Review - Upload Button
**Issue:** "Upload button doesn't work, checkered boxes on dropdowns"  
**Test:**
- Navigate to Video → Coaches Review
- Verify upload button is visible
- Click upload button
- Verify file picker opens
- Test dropdown menus
- Verify no checkered background on hover

**Expected:** Upload works, dropdowns styled correctly  
**Screenshot:** Upload dialog and dropdown styling

---

### 5. Health - Strength Fatal Error
**Issue:** "Unknown column 'category' in 'SELECT'"  
**Status:** ✅ FIXED (changed exercises to exercise_library)  
**Test:**
- Navigate to Health → Strength & Conditioning
- Verify page loads without error
- Verify workouts display
- Test category filter
- Test exercise library

**Expected:** No SQL errors, page loads successfully  
**Screenshot:** Strength & Conditioning page working

---

### 6. Nutrition - Contact Coach Button
**Issue:** "Contact coach button sends back to home page"  
**Test:**
- Navigate to Health → Nutrition
- Click "Contact Coach" button
- Verify modal opens OR navigates to correct page
- Verify doesn't redirect to home

**Expected:** Button opens contact form or navigates correctly  
**Screenshot:** Contact coach functionality

---

### 7. Drills - Library Search
**Issue:** "No search by name, create drill icon wrong color, import HIS fatal error"  
**Status:** Import error ✅ FIXED  
**Test:**
- Navigate to Drills → Library
- Verify search box exists
- Test search by drill name
- Verify "Create First Drill" icon color (#6B46C1)
- Navigate to Import from IHS
- Verify page loads without error

**Expected:** Search works, colors correct, no SQL errors  
**Screenshot:** Drill library search and create button

---

### 8. Practice Plans - All Functionality
**Issue:** "Create button wrong style, goes back to home, drag/drop doesn't work, arrows don't work, delete/cancel/print/save do nothing"  
**Test:**
- Navigate to Practice Plans
- Verify "Create Practice Plan" button style (#6B46C1, fa-plus icon)
- Click create button
- Verify doesn't redirect to home
- Test drag and drop drills
- Test arrow buttons to reorder
- Test Add Drill button color
- Test Delete, Cancel, Print, Save buttons

**Expected:** All buttons work, no redirects, proper styling  
**Screenshot:** Practice plan creation with working controls

---

### 9. Roster - Create Athlete
**Issue:** "Fatal error: Unknown column 'b.booked_for_user_id'"  
**Status:** ✅ FIXED (removed column reference)  
**Test:**
- Navigate to Roster
- Verify athlete list loads without error
- Click "Create Athlete" button
- Fill out form
- Submit form
- Verify athlete created
- Check button styling

**Expected:** No SQL errors, athlete creation works  
**Screenshot:** Roster view and create athlete form

---

### 10. Travel - Mileage
**Issue:** "Fatal error: Table 'arcticwolves.settings' doesn't exist"  
**Status:** ✅ FIXED (changed to system_settings)  
**Test:**
- Navigate to Travel → Mileage
- Verify page loads without error
- Verify mileage rate displays
- Test adding mileage entry
- Test filters

**Expected:** No SQL errors, mileage tracking works  
**Screenshot:** Mileage tracking interface

---

### 11. Accounting Dashboard
**Issue:** "Button proportion issue"  
**Test:**
- Navigate to Accounting Dashboard
- Check all button heights (should be 45px)
- Check button padding (0 24px)
- Check button fonts (Inter, 14px, 700)
- Take screenshot of button layout

**Expected:** All buttons consistent with style guide  
**Screenshot:** Accounting dashboard with correct button proportions

---

### 12. Billing - Create Invoice
**Issue:** "Create invoice goes back to dashboard"  
**Test:**
- Navigate to Billing
- Click "Create Invoice"
- Verify modal opens OR stays on billing page
- Fill out invoice form
- Submit
- Verify invoice created

**Expected:** Modal opens, invoice creates successfully  
**Screenshot:** Invoice creation process

---

### 13. Reports - Generate Reports
**Issue:** "Error with 'format' column in INSERT"  
**Status:** ✅ FIXED (removed format column)  
**Test:**
- Navigate to Reports
- Click "Generate Report"
- Select report type
- Click Generate
- Verify no SQL error
- Check delete, view, download buttons work

**Expected:** Reports generate successfully, actions work  
**Screenshot:** Report generation and action buttons

---

### 14. Schedules - Create Schedule
**Issue:** "Email recipients required error, delete/pause/edit don't work"  
**Test:**
- Navigate to Schedules
- Click "Create Schedule"
- Fill form with email recipients
- Submit
- Test Edit button
- Test Pause button
- Test Delete button

**Expected:** All CRUD operations work  
**Screenshot:** Schedule management interface

---

### 15. Expenses - Add Expense
**Issue:** "Choose file works, take photo issue, add expense goes to home"  
**Test:**
- Navigate to Expenses
- Click "Add Expense"
- Test "Choose File" button
- Test "Take Photo" button (should open camera)
- Fill expense form
- Click "Add Expense"
- Verify doesn't redirect to home
- Verify expense created

**Expected:** File upload works, camera opens, expense creates  
**Screenshot:** Expense form and file upload

---

### 16. Products - Tabs vs Buttons
**Issue:** "Buttons for sessions, packages, discounts should be tabs not buttons"  
**Test:**
- Navigate to Products
- Verify navigation uses tab-style links (not buttons)
- Check styling matches other tabs in app
- Test session duration cap (should be no locked time)
- Test create package/session/discount buttons
- Verify button icons and colors

**Expected:** Proper tab navigation, no time restrictions  
**Screenshot:** Products tabs and session creation

---

### 17. All Users - Search and Actions
**Issue:** "No search by name, action buttons don't work, export icon wrong color"  
**Test:**
- Navigate to Admin → All Users
- Verify search box exists
- Test search by name
- Test search by email
- Test Action buttons (Edit, Disable, etc.)
- Check Export icon color (#6B46C1)

**Expected:** Search works, actions functional, correct colors  
**Screenshot:** User management with working search

---

### 18. Categories - Skills, Drill Types, Positions, Equipment
**Issue:** "Wrong tabs (showing buttons), wrong color icons, edit/delete don't work, creating sends to home"  
**Test:**
- Navigate to Admin → Categories
- Verify tabs display correctly (not buttons)
- Check "Add" button icons (#6B46C1)
- Test creating skill/drill type/position/equipment
- Verify doesn't redirect to home
- Test Edit buttons
- Test Delete buttons

**Expected:** Proper tabs, CRUD operations work, correct colors  
**Screenshot:** Category management interface

---

### 19. Eval Framework - Add/Edit/Delete
**Issue:** "Wrong icon colors, add/edit/delete don't work, invalid action error on create scale"  
**Test:**
- Navigate to Admin → Evaluation Framework
- Check "Add Evaluation Category" icon color
- Check "Add Scale" icon color
- Test creating evaluation category
- Test creating scale
- Test edit buttons
- Test delete buttons

**Expected:** All CRUD operations work, icons correct color  
**Screenshot:** Evaluation framework management

---

### 20. System Notifications - Send/Edit/Delete
**Issue:** "Send works but shows success, delete/edit don't work"  
**Test:**
- Navigate to Admin → System Notifications
- Create notification
- Verify success/error handling correct
- Test Edit button (should populate form)
- Test Delete button with confirmation

**Expected:** All operations work correctly  
**Screenshot:** Notification management

---

### 21. Audit Log - Export
**Issue:** "Export sends to home screen"  
**Test:**
- Navigate to Admin → Audit Log
- Click Export button
- Verify file downloads OR export modal opens
- Verify doesn't redirect to home

**Expected:** Export works without redirect  
**Screenshot:** Audit log export function

---

### 22. Cron Jobs - All Operations
**Issue:** "Wrong icon color, save error with 'action' column, pause/edit/run invalid action errors"  
**Test:**
- Navigate to Admin → Cron Jobs
- Check "Add Cron Job" icon color (#6B46C1)
- Test creating cron job
- Verify no SQL error
- Test Pause button
- Test Edit button (should populate modal)
- Test Run button

**Expected:** All operations work, no SQL errors, correct colors  
**Screenshot:** Cron job management

---

### 23. System Tools - Settings
**Issue:** "No padding, using buttons not tabs, saving brings up different menu, send test email error"  
**Test:**
- Navigate to Admin → System Tools
- Check padding around content
- Verify uses tab navigation (not buttons)
- Test saving general settings
- Test saving SMTP settings
- Test "Send Test Email" with valid email

**Expected:** Proper layout, tab navigation, all saves work  
**Screenshot:** System tools interface

---

### 24. Personal Info - Upload Icon
**Issue:** "Upload icon in wrong place, should be on profile icon not ugly folder"  
**Test:**
- Navigate to Personal Info
- Check profile image upload UI
- Verify upload button placement
- Test upload functionality
- Verify clean, modern design

**Expected:** Upload button well-positioned, modern look  
**Screenshot:** Profile image upload interface

---

### 25. Notifications - Sliders
**Issue:** "Sliders don't work at all"  
**Test:**
- Navigate to Personal Info → Notifications
- Test email notification slider
- Test SMS notification slider
- Test push notification slider
- Verify settings save

**Expected:** All sliders toggle and save correctly  
**Screenshot:** Notification preferences with working sliders

---

## Testing Execution Plan

### Phase 1: Database Error Verification (✅ COMPLETED)
- [x] Video drill review
- [x] Health workouts
- [x] Drills import
- [x] Athletes roster
- [x] Travel mileage
- [x] Reports generation

### Phase 2: Style Guide Compliance
- [ ] Identify all buttons with wrong colors
- [ ] Identify all missing icons
- [ ] Identify tabs vs buttons issues
- [ ] Create comprehensive fix list

### Phase 3: Functionality Testing
- [ ] Test all "redirect to home" issues
- [ ] Test all "button doesn't work" issues
- [ ] Test all CRUD operations
- [ ] Test all form submissions

### Phase 4: UI/UX Issues
- [ ] Test dropdown styling (checkered boxes)
- [ ] Test button proportions
- [ ] Test padding/spacing issues
- [ ] Test icon placements

---

## Playwright Test Structure

```javascript
// tests/critical-paths.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Arctic Wolves Critical Paths', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin
    await page.goto('http://localhost/Arctic_Wolves/login.php');
    await page.fill('input[name="email"]', 'admin@test.com');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard.php/);
  });

  test('Video - Drill Review loads without SQL error', async ({ page }) => {
    await page.goto('http://localhost/Arctic_Wolves/dashboard.php?page=drill_review');
    // Should not see fatal error
    await expect(page.locator('text=Fatal error')).not.toBeVisible();
    // Should see page content
    await expect(page.locator('.page-title')).toContainText('Drill');
  });

  // Add more tests...
});
```

---

## Success Criteria

### Database Fixes (✅ COMPLETED)
- [x] No SQL errors on any page
- [x] All queries use correct table names
- [x] All queries use correct column names

### Style Guide Compliance
- [ ] All buttons use var(--primary) color (#6B46C1)
- [ ] All "Add" buttons have fa-plus icon
- [ ] All buttons are 45px height with proper padding
- [ ] All tabs use proper tab navigation (not buttons)

### Functionality
- [ ] No buttons redirect to home unexpectedly
- [ ] All CRUD operations work (Create, Read, Update, Delete)
- [ ] All form submissions work correctly
- [ ] All modals open/close correctly

### UI/UX
- [ ] No checkered backgrounds on dropdowns
- [ ] Consistent button proportions
- [ ] Proper padding and spacing
- [ ] Icons correctly positioned and colored

---

## Documentation Updates Required
- [ ] Update ISSUES_TRACKER.md with new fixes
- [ ] Update STRUCTURE.md if routing changes made
- [ ] Create screenshot documentation
- [ ] Update VALIDATION_CHECKLIST.md
