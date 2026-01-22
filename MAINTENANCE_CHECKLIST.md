# Arctic Wolves - Comprehensive Maintenance Checklist

## Purpose
This checklist ensures all features, modules, and functionality are working correctly across the entire Arctic Wolves platform. Use this checklist before deploying changes or when conducting system audits.

## Pre-Deployment Checklist

### 1. Core System Checks
- [ ] **CSRF Protection**: All forms include `<?= csrfTokenInput() ?>` or use `CSRFProtection::getTokenField()`
- [ ] **Process Files**: All process_*.php files call `checkCsrfToken()` or `CSRFProtection::validate()`
- [ ] **Form Actions**: All form action attributes point to existing process files
- [ ] **Database Schema**: All ALTER TABLE statements use `ADD COLUMN IF NOT EXISTS`
- [ ] **Error Display**: `display_errors` is OFF in production
- [ ] **Session Initialization**: CSRF tokens generated on login and dashboard load

### 2. View File Validation
- [ ] **Tab Navigation**: Parent view files properly include child views
- [ ] **Data Loading**: Database queries execute without errors
- [ ] **Empty States**: All views show appropriate messages when no data exists
- [ ] **Error Handling**: Try-catch blocks around all database queries
- [ ] **CSRF Tokens**: All forms include CSRF token fields

### 3. Database Integrity
- [ ] **Column Existence**: Run schema with `IF NOT EXISTS` checks
- [ ] **Foreign Keys**: All FK constraints reference existing columns
- [ ] **Indexes**: Performance indexes on frequently queried columns
- [ ] **Data Types**: ENUM values match application logic
- [ ] **Default Values**: Sensible defaults for nullable columns

### 4. Style Guide Compliance
- [ ] **Icons**: All "Add" buttons include `<i class="fas fa-plus"></i>` icons
- [ ] **Fonts**: Consistent font-family across all pages (check style.css)
- [ ] **Button Styling**: Consistent .btn-primary, .btn-secondary classes
- [ ] **Tab Implementation**: Use tab-link/tab-content pattern consistently
- [ ] **Spacing**: Proper margins/padding on empty states
- [ ] **CSS Errors**: No unclosed style tags or CSS displayed as text

### 5. User Interface Testing

#### Home Page (All Roles)
- [ ] Dashboard loads without errors
- [ ] Upcoming sessions/today's sessions display correctly
- [ ] "Add Session" button links to booking page
- [ ] Notifications display
- [ ] Empty states show appropriate messages

#### Stats & Performance Page
- [ ] Stats overview cards display data
- [ ] "Add Goal" button has icon and links to goals page
- [ ] Goals tracker shows active goals
- [ ] Empty state has proper spacing (text not on top of button)
- [ ] Performance metrics table displays
- [ ] Skills progress table displays

#### Sessions Page
- [ ] "Upcoming Sessions" tab loads
- [ ] "Booking" tab loads
- [ ] Empty sessions show "No sessions available" message
- [ ] Session data displays in both list and calendar views
- [ ] Book session button works

#### Video Page
- [ ] "Drill Review" tab loads
- [ ] "Coaches Reviews" tab loads
- [ ] Upload functionality accessible (for coaches)
- [ ] Video list displays
- [ ] Empty state shows appropriate message

#### Drills Page (Coaches)
- [ ] "Drill Library" tab loads
- [ ] "Create Drill" tab loads
- [ ] "Import Drill" tab loads
- [ ] Add drill button has icon and works
- [ ] Drill list displays

#### Practice Plans Page (Coaches)
- [ ] "Practice Library" tab loads
- [ ] "Create Practice" tab loads
- [ ] Add practice button has icon and works
- [ ] Practice plan list displays

#### Health Page
- [ ] "Strength & Conditioning" tab loads
- [ ] "Nutrition" tab loads
- [ ] Workout plans display or show "No plans" message
- [ ] Nutrition plans display or show "No plans" message

#### Travel Page (Coaches)
- [ ] "Mileage Tracker" tab loads
- [ ] Mileage entries display
- [ ] Add mileage button works

### 6. Accounting & Reports (Admin)

#### Accounting Dashboard
- [ ] Page loads without layout issues
- [ ] Quick action boxes properly sized
- [ ] Revenue overview displays
- [ ] Package purchase data shows
- [ ] Graphs display (even with $0 data)
- [ ] No overlapping elements

#### Billing Dashboard
- [ ] Create invoice button works
- [ ] Export button works (or shows "nothing to export")
- [ ] Invoice list displays
- [ ] Filter functionality works

#### Reports Page
- [ ] Generate report form submits without CSRF error
- [ ] Report generation creates file
- [ ] Pre-built report buttons work
- [ ] Download, view, delete buttons functional
- [ ] Reports display in table

#### Scheduled Reports
- [ ] Create schedule form works without CSRF error
- [ ] Schedule created successfully with confirmation
- [ ] Active schedules display
- [ ] Edit, pause, delete buttons work

#### Credits & Refunds
- [ ] "Issue Credit/Refund" button works
- [ ] Form displays historical purchases
- [ ] Refund processing works

#### Expenses
- [ ] Add expense form displays
- [ ] Choose file button works
- [ ] Take photo button works (mobile)
- [ ] Form submits without CSRF error
- [ ] Expense list displays
- [ ] Export button works

#### Products Page
- [ ] Sessions tab displays
- [ ] Packages tab displays
- [ ] Discounts tab displays
- [ ] Tabs (not buttons) for navigation
- [ ] Add buttons have icons
- [ ] Search functionality works
- [ ] Edit and delete buttons work
- [ ] Session history shows when/where used

### 7. HR & Administration

#### Termination Page
- [ ] Page loads without fatal error
- [ ] Form displays correctly
- [ ] Process termination button works (no CSRF error)
- [ ] Confirmation provided after submission
- [ ] Recent terminations display

#### All Users Page
- [ ] User list displays
- [ ] Search by name works
- [ ] Role filter works correctly
- [ ] Add user button works (with icon)
- [ ] Export button has icon and works
- [ ] No "table not found" error on export

#### Categories Page
- [ ] Skills tab displays
- [ ] Drill Types tab displays
- [ ] Positions tab displays
- [ ] Equipment tab displays
- [ ] Use tabs (not buttons) for navigation
- [ ] Add buttons have icons
- [ ] Edit and delete buttons work

#### Eval Framework Page
- [ ] Add evaluation category works
- [ ] Category list displays
- [ ] Plus, edit, delete buttons work on categories
- [ ] Add skill works under categories
- [ ] Edit and delete work on skills
- [ ] Add scale button has icon and works

#### System Notifications Page
- [ ] Page loads without fatal error (csrfTokenInput defined)
- [ ] Create notification form displays
- [ ] Form submits successfully
- [ ] Confirmation message shown
- [ ] New notification moves to active notifications
- [ ] Edit and delete buttons work

#### Audit Log Page
- [ ] Audit entries display
- [ ] Export button works
- [ ] No "table not found" error

#### Cron Jobs Page
- [ ] Add cron job button has icon
- [ ] Add cron job form opens
- [ ] Time settings use normal time format
- [ ] Create job works
- [ ] Run, edit, pause buttons functional
- [ ] Execution history displays
- [ ] Execution history buttons work

#### System Tools Page
- [ ] Settings tab displays
- [ ] Theme tab displays
- [ ] Database tab displays
- [ ] Cron tab displays
- [ ] Tab navigation works
- [ ] Save settings works (no CSRF error)
- [ ] No CSS displayed as text on page
- [ ] Toggle sliders work

### 8. Profile Page

#### Personal Tab
- [ ] Profile form displays
- [ ] All fields editable
- [ ] Save changes works (no redirect to home)

#### Player Info Tab (Athletes)
- [ ] Height field displays and saves
- [ ] Weight field displays and saves
- [ ] Handedness/Stick hand field displays and saves
- [ ] Catching hand field displays (goalies only)
- [ ] Jersey number field displays and saves
- [ ] Save changes works

#### Photo
- [ ] Profile photo displays
- [ ] Change photo button opens file selector
- [ ] Photo upload works
- [ ] Photo displays after upload
- [ ] Remove photo button works

#### Security Tab
- [ ] Password change form displays
- [ ] Password change works

#### Notifications Tab
- [ ] Notification settings display
- [ ] Settings save correctly

### 9. Missing Features Check
Run through this list to ensure no built features are missing:
- [ ] Session booking
- [ ] Video upload
- [ ] Drill creation
- [ ] Practice plan creation
- [ ] Goal setting
- [ ] Report generation
- [ ] Invoice creation
- [ ] User management
- [ ] Category management
- [ ] Evaluation framework
- [ ] System notifications
- [ ] Cron job management

### 10. Process File Validation
For each process_*.php file:
- [ ] File is included in security.php
- [ ] Calls checkCsrfToken() early
- [ ] Validates user authentication
- [ ] Validates user authorization
- [ ] Uses parameterized queries (no SQL injection)
- [ ] Has try-catch blocks
- [ ] Returns proper JSON responses
- [ ] Logs errors appropriately

### 11. Common Issues to Check

#### CSRF Token Errors
- [ ] session_start() called before CSRF generation
- [ ] CSRFProtection::generateToken() called in dashboard.php
- [ ] generateCSRFToken() called in dashboard.php (for backward compatibility)
- [ ] Forms include csrf_token input field
- [ ] Process files call checkCsrfToken()

#### Forms Redirecting to Home
- [ ] Form action points to existing file
- [ ] Process file has handler for action
- [ ] Handler redirects to correct page
- [ ] No die() statements before redirect

#### Blank/Empty Pages
- [ ] Parent view includes child view correctly
- [ ] Database queries execute successfully
- [ ] Try-catch blocks handle errors
- [ ] Empty state displays when no data

#### Buttons Not Working
- [ ] Button has correct data-action attribute
- [ ] JavaScript handler exists in app.js
- [ ] Button is inside form (if needed)
- [ ] Form has correct action URL

#### CSS Errors Displayed
- [ ] All <style> tags properly closed
- [ ] No CSS outside of <style> tags
- [ ] CSS variables defined in style.css

### 12. Post-Fix Validation
After making fixes:
- [ ] Test all changed pages manually
- [ ] Check browser console for JavaScript errors
- [ ] Check server logs for PHP errors
- [ ] Test with different user roles
- [ ] Test empty data states
- [ ] Test with populated data
- [ ] Verify CSRF tokens work
- [ ] Verify all buttons/forms work

### 13. Documentation Updates
- [ ] Update README with any new features
- [ ] Document any new database tables
- [ ] Update API documentation (if applicable)
- [ ] Document any new configuration requirements
- [ ] Update this maintenance checklist with new checks

## Notes for Future Maintenance

### Common Patterns to Follow
1. **Forms**: Always include CSRF token
2. **Process Files**: Always check CSRF early
3. **Database Queries**: Always use prepared statements
4. **Empty States**: Always show user-friendly messages
5. **Icons**: Always include on "Add" buttons
6. **Tabs**: Use anchor tags with href, not buttons
7. **Error Handling**: Always wrap DB calls in try-catch
8. **Redirects**: Use absolute paths or query parameters

### Files to Review Regularly
- `/views/*.php` - All view files
- `/process_*.php` - All process handlers
- `/database_schema.sql` - Schema updates
- `/js/app.js` - JavaScript functionality
- `/style.css` - Global styles
- `/csrf_protection.php` - CSRF functions
- `/security.php` - Security functions

### Key Database Tables
- `users` - User accounts
- `athlete_stats` - Athlete-specific data (height, weight, etc.)
- `sessions` - Training sessions
- `videos` - Video uploads
- `drills` - Drill library
- `practice_plans` - Practice plans
- `goals` - Performance goals
- `system_notifications` - System-wide notifications

## Version History
- **Jan 22, 2026**: Initial comprehensive checklist created
- Document all future updates here
