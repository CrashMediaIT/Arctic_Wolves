# Browser Testing Guide for Arctic Wolves
**Created:** January 23, 2026  
**Purpose:** Manual testing checklist for verifying fixes from PR: Fix SQL schema mismatches, CSP violations, and missing modals

---

## Prerequisites

### 1. Environment Setup
```bash
# Start web server (Apache/Nginx with PHP)
# Ensure MySQL/MariaDB is running
# Navigate to application URL in browser
```

### 2. Database Setup
```bash
# Seed demo data for testing
php demo_data_seeder.php
```

### 3. Browser Console
- Open Developer Tools (F12)
- Monitor Console tab for errors
- Monitor Network tab for failed requests

---

## Critical Fixes to Verify

### ✅ 1. Content Security Policy (CSP) Fixes

**What Was Fixed:**
- Added `cdn.jsdelivr.net` to allowed style sources
- Enabled `setSecurityHeaders()` call in dashboard.php
- Google Fonts now whitelisted

**Testing Steps:**
1. Navigate to any page in the application
2. Open browser Developer Tools → Console
3. **VERIFY:** No CSP violation errors for:
   - `fonts.googleapis.com`
   - `cdn.jsdelivr.net`
   - `cdnjs.cloudflare.com`

**Expected Result:**
- ✅ No "Content Security Policy" errors in console
- ✅ Google Fonts (Inter) loads correctly
- ✅ Font Awesome icons display properly
- ✅ Page styling renders correctly

**If Failed:**
- Check if security.php changes are deployed
- Check if dashboard.php calls setSecurityHeaders()
- Check browser console for specific CSP violation

---

### ✅ 2. Roster - Create Player SQL Error

**What Was Fixed:**
- Changed `user_id` → `athlete_id` in athlete_teams queries
- Changed `is_current` → `status = 'active'`
- Fixed team names to join `teams` table

**Testing Steps:**
1. Login as Coach or Admin
2. Navigate to **Roster** page
3. Click **"Add Athlete"** button
4. Fill out form:
   - First Name: Test
   - Last Name: Player
   - Email: testplayer@example.com
   - Birth Date: 2010-01-01
   - Position: Forward
5. Click **"Create Athlete"**

**Expected Result:**
- ✅ Form submits successfully
- ✅ Success message appears
- ✅ New athlete appears in roster list
- ✅ No SQL error in console or on page

**If Failed:**
- Check browser console for error details
- Check Network tab for response from process_create_athlete.php
- Verify database schema matches (athlete_teams uses athlete_id, not user_id)

---

### ✅ 3. Reports - Invalid Action Error

**What Was Fixed:**
- Added support for action name variants (generate_report, delete_report, etc.)
- Improved error messages to show received action

**Testing Steps:**
1. Login as Admin or Coach
2. Navigate to **Reports** page
3. Fill out "Generate Report" form:
   - Report Type: Revenue Summary
   - Date Range: This Month
   - Format: PDF
4. Click **"Generate Report"**

**Expected Result:**
- ✅ Report generates successfully
- ✅ No "Invalid action" error
- ✅ Report appears in Recent Reports section
- ✅ Download/View/Delete buttons work

**If Failed:**
- Check browser console Network tab
- Look for response from process_reports.php
- Verify action parameter being sent
- Check if form has name="action" value="generate_report"

---

### ✅ 4. Schedules - SQL Error (report_schedules)

**What Was Fixed:**
- Changed `user_id` → `created_by`
- Changed `frequency` → `schedule_frequency`
- Changed `email_recipients` → `recipients`
- Added `report_name` field

**Testing Steps:**
1. Login as Admin or Coach
2. Navigate to **Reports** page
3. Scroll to "Schedule Report" section
4. Fill out form:
   - Report Type: Revenue Summary
   - Frequency: Weekly
   - Email: test@example.com
5. Click **"Create Schedule"**

**Expected Result:**
- ✅ Schedule creates successfully
- ✅ No SQL error about missing columns
- ✅ Schedule appears in Active Schedules list
- ✅ Edit/Pause/Delete buttons work

**Testing Schedule Operations:**
1. Click **"Pause"** button → should toggle to inactive
2. Click **"Edit"** button → should open edit form
3. Click **"Delete"** button → should remove schedule

**If Failed:**
- Check browser console for SQL errors
- Check Network tab for process_reports.php response
- Verify database schema (report_schedules table columns)

---

### ✅ 5. Edit Notification Modal

**What Was Fixed:**
- Added complete `edit-notification-modal` with form

**Testing Steps:**
1. Login as Admin
2. Navigate to **Administration** → **System Notifications**
3. Find any existing notification
4. Click **"Edit"** button (pencil icon)

**Expected Result:**
- ✅ Modal opens with edit form
- ✅ Form fields populated with current values
- ✅ Can modify title, type, message, audience, priority
- ✅ **"Cancel"** button closes modal without saving
- ✅ **"Update Notification"** button saves changes
- ✅ No "Modal with ID edit-notification-modal not found" error

**If Failed:**
- Check browser console for modal error
- Verify admin_notifications.php has modal HTML
- Check if button has data-modal="edit-notification-modal"

---

### ✅ 6. Edit Cron Job Modal

**What Was Fixed:**
- Added complete `edit-cron-job-modal` with form

**Testing Steps:**
1. Login as Admin
2. Navigate to **Administration** → **Cron Jobs**
3. Find any existing cron job
4. Click **"Edit"** button (pencil icon)

**Expected Result:**
- ✅ Modal opens with edit form
- ✅ Form fields populated with current values
- ✅ Can modify name, description, schedule, status
- ✅ **"Cancel"** button closes modal without saving
- ✅ **"Update Cron Job"** button saves changes
- ✅ No "Modal with ID edit-cron-job-modal not found" error

**If Failed:**
- Check browser console for modal error
- Verify admin_cron_jobs.php has modal HTML
- Check if button has data-modal="edit-cron-job-modal"

---

## Data Display Verification

The following pages showed "No Data" issues - these are **NOT bugs**, just empty database:

### Sessions
1. Navigate to **Sessions** → **Upcoming Sessions**
2. If no data, should show: "You don't have any upcoming sessions"
3. Toggle between List and Calendar views
4. **Expected:** Both views work, proper empty state shows

### Video
1. Navigate to **Video** → **Drill Review**
2. If no data, should show: "No drill videos available yet"
3. Navigate to **Coaches Reviews**
4. Check all three sub-tabs: Pending, Reviewed, Upload
5. **Expected:** Proper empty states, Upload form visible for coaches

### Health
1. Navigate to **Health** → **Strength & Conditioning**
2. Should show: "No Workout Plan Currently Assigned"
3. Navigate to **Nutrition**
4. Should show: "No Nutrition Plan Currently Assigned"
5. **Expected:** Friendly empty state messages with CTA

### Drills
1. Navigate to **Coaches Corner** → **Drills** → **Library**
2. Should show: "No Drills Yet" with "Create Your First Drill" button
3. Click **"Create a Drill"** tab
4. **Expected:** Drill creation form loads
5. Click **"Import a Drill"** tab
6. **Expected:** Import form loads

---

## SQL Query Verification

### Verify Athlete Teams Query
```sql
-- This should NOT error (fixed in athletes.php)
SELECT u.*, 
       (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') 
        FROM athlete_teams at2 
        INNER JOIN teams t ON at2.team_id = t.id 
        WHERE at2.athlete_id = u.id AND at2.status = 'active') as team_names
FROM users u
WHERE u.assigned_coach_id = ? AND u.role = 'athlete'
```

### Verify Report Schedules Query
```sql
-- This should NOT error (fixed in process_reports.php)
INSERT INTO report_schedules 
(created_by, report_type, parameters, schedule_frequency, recipients, next_run, is_active, report_name)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
```

### Verify Delete Schedule Query
```sql
-- This should NOT error (fixed in process_reports.php)
DELETE FROM report_schedules WHERE id = ? AND created_by = ?
```

---

## Browser Console Checks

### No Errors Expected For:
- ✅ CSP violations (fonts.googleapis.com, cdn.jsdelivr.net)
- ✅ "Modal with ID not found" errors
- ✅ SQL errors in JSON responses
- ✅ "Invalid action" errors from process_reports.php

### Acceptable Warnings:
- ⚠️ "Button clicked but no action handler found" - for unimplemented actions
- ⚠️ Empty data warnings (if database is empty)

---

## Network Tab Verification

### Check These Endpoints:
1. **process_create_athlete.php** - Should return success
2. **process_reports.php** - Should handle generate_report action
3. **process_reports.php** - Should handle schedule CRUD operations
4. **process_system_notifications.php** - Should handle update action
5. **process_cron_jobs.php** - Should handle update action

### Success Response Format:
```json
{
    "success": true,
    "message": "Operation completed successfully"
}
```

### Error Response Format:
```json
{
    "success": false,
    "message": "Specific error description"
}
```

---

## Regression Testing

After verifying fixes, test these areas for regressions:

### 1. Authentication
- ✅ Login works
- ✅ Logout works
- ✅ Session persists

### 2. Navigation
- ✅ All sidebar links work
- ✅ Tab navigation works
- ✅ Breadcrumbs update correctly

### 3. Forms
- ✅ CSRF tokens present
- ✅ Validation works
- ✅ Success/error messages display

### 4. Modals
- ✅ Open correctly
- ✅ Close on X button
- ✅ Close on Cancel button
- ✅ Close on background click (if applicable)
- ✅ Form submission works

---

## Known Limitations (Not Fixed)

These issues require additional development:

### UI/Styling Issues
- Icon colors may be wrong (CSS issue)
- Tab formatting (some use buttons instead of tabs)
- Package card styling

### Missing JavaScript Handlers
- `toggle-status` action
- `run` action (cron jobs)
- `permissions` action
- `add-skill` action

### Inline Event Handlers
- Many pages use `onclick`, `onchange` attributes
- These work but violate strict CSP
- Would require major refactoring to remove

---

## Reporting Issues

If you find issues during testing:

1. **Document the Issue:**
   - Page/feature affected
   - Steps to reproduce
   - Expected vs actual behavior
   - Browser console errors
   - Network response errors

2. **Check These First:**
   - Is demo data seeded?
   - Are database migrations applied?
   - Is PHP error logging enabled?
   - Are file permissions correct?

3. **Create GitHub Issue:**
   - Include all documentation from step 1
   - Reference this testing guide
   - Tag with appropriate labels (bug, testing, etc.)

---

## Success Criteria

All fixes verified when:
- ✅ No CSP violations in console
- ✅ Roster create player works without SQL error
- ✅ Reports generation works without "Invalid action" error
- ✅ Schedule CRUD operations work without SQL errors
- ✅ Edit notification modal opens and works
- ✅ Edit cron job modal opens and works
- ✅ All data display pages show proper empty states
- ✅ No regressions in existing functionality

---

**Testing Completed By:** ________________  
**Date:** ________________  
**Browser/Version:** ________________  
**Issues Found:** ________________  

**End of Testing Guide**
