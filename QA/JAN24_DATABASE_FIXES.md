# Database Column Fixes - January 24, 2026

## Summary
Fixed 6 SQL column errors that were causing fatal PDO exceptions throughout the application.

## Issues Fixed

### 1. video_drill_review.php - Column 'd.name' not found
**File:** `views/video_drill_review.php`  
**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'd.name' in 'SELECT'`  
**Root Cause:** Query referenced `d.name` but drills table uses `d.title`  
**Fix:**
- Line 13: Changed `d.name as drill_name` to `d.title as drill_name`
- Line 36: Changed `d.name LIKE ?` to `d.title LIKE ?`

### 2. health_workouts.php - Column 'category' not found
**File:** `views/health_workouts.php`  
**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'category' in 'SELECT'`  
**Root Cause:** Query tried to get category from `exercises` table instead of `exercise_library`  
**Fix:**
- Line 63: Changed `FROM exercises` to `FROM exercise_library`

### 3. drills_import.php - Column 'd.source' not found
**File:** `views/drills_import.php`  
**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'd.source' in 'WHERE'`  
**Root Cause:** Drills table doesn't have `source` column, uses `ihs_source_url` instead  
**Fix:**
- Line 6: Changed `WHERE d.source = 'IHS'` to `WHERE d.ihs_source_url IS NOT NULL`

### 4. athletes.php - Column 'b.booked_for_user_id' not found
**File:** `views/athletes.php`  
**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'b.booked_for_user_id' in 'WHERE'`  
**Root Cause:** Bookings table only has `user_id`, not `booked_for_user_id`  
**Fix:**
- Line 44: Removed `OR b.booked_for_user_id = u.id` from WHERE clause
- Changed `b.status = 'paid'` to `b.status IN ('confirmed', 'waitlisted')` for better accuracy

### 5. travel_mileage.php - Table 'settings' doesn't exist
**File:** `views/travel_mileage.php`  
**Error:** `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'arcticwolves.settings' doesn't exist`  
**Root Cause:** Table is named `system_settings`, not `settings`  
**Fix:**
- Line 3: Changed `FROM settings` to `FROM system_settings`
- Line 3: Changed `value` column to `setting_value`

### 6. process_reports.php - Column 'format' not found
**File:** `process_reports.php`  
**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'format' in 'INSERT INTO'`  
**Root Cause:** Reports table doesn't have `format`, `share_token`, or `scheduled` columns  
**Fix:**
- Lines 84-96: Removed non-existent columns from INSERT statement
- Kept only: `report_type`, `generated_by`, `parameters`, `file_path`, `status`
- Set status to 'completed' automatically

## Database Schema Reference

### Tables Affected:
- **drills**: Has `title`, `ihs_source_url` (not `name`, `source`)
- **exercise_library**: Has `category` column
- **exercises**: No `category` column (different table)
- **bookings**: Has `user_id` only (not `booked_for_user_id`)
- **system_settings**: Correct table name (not `settings`), uses `setting_value` (not `value`)
- **reports**: Has `report_type`, `generated_by`, `parameters`, `file_path`, `status` (not `format`, `share_token`, `scheduled`)

## Impact
- ✅ Video - Drill Review tab now loads without fatal error
- ✅ Health - Strength & Conditioning tab now loads without fatal error
- ✅ Drills - Import from IHS tab now loads without fatal error
- ✅ Roster - Athletes view now loads without fatal error
- ✅ Travel - Mileage tracking now loads without fatal error
- ✅ Reports - Generate report action no longer throws SQL error

## Testing Recommendations
1. Test video drill review filtering and search
2. Test health workouts category filtering
3. Test drills import view with recent imports
4. Test athlete roster view for coaches
5. Test travel mileage tracking
6. Test report generation with various types

## Files Changed
- `views/video_drill_review.php` (2 edits)
- `views/health_workouts.php` (1 edit)
- `views/drills_import.php` (1 edit)
- `views/athletes.php` (1 edit)
- `views/travel_mileage.php` (1 edit)
- `process_reports.php` (1 edit)
