# Arctic Wolves - Issue Tracking & Resolution

**Status**: In Progress  
**Last Updated**: 2026-01-22  
**Governance**: Following QA/MAINTENANCE_PROCESS.md

---

## Issues by Page (Navigation Order)

### 🏠 HOME PAGE
**Status**: ✅ FIXED

✅ **Fixed:**
- Add Session button now routes to booking page (?page=booking)
- Button changed from button to link element
- **NEW**: Performance Stats section added with header
- **NEW**: Stats show Sessions Completed, Videos Reviewed, Active Goals
- **NEW**: Stats display even when values are 0
- **NEW**: Coach dashboard shows "Upcoming Sessions" (next 7 days) instead of just today
- **NEW**: Better date badges showing "Today" vs specific dates
- Empty state messaging throughout
- Style guide: Visual consistency with gradient icons and hover effects

🔴 **Outstanding:**
- [x] ~~Empty state messaging~~
- [x] ~~Stats section needs header~~
- [x] ~~"Today's sessions" should show "upcoming sessions"~~
- [ ] Font consistency audit (to be verified across all pages)

---

### 📊 PERFORMANCE STATS PAGE  
**Status**: ✅ FIXED

✅ **Fixed:**
- Add Goal button has icon and proper styling (already had icon in commit 29edc32)
- **NEW**: Database queries corrected (goal_title vs title, removed template_id reference)
- **NEW**: Sessions query fixed (bookings table, date column)
- **NEW**: Progress percentage calculation added
- **NEW**: Empty state improved with proportionate layout
- **NEW**: Large icon (48px) with proper spacing
- **NEW**: Text and button properly separated (24px spacing)
- **NEW**: View button changed from non-functional button to working link
- Button text proportions fixed with professional layout

🔴 **Outstanding:**
- [x] ~~Add goal is missing an icon~~
- [x] ~~Create goal reloads to the home page~~ (now links to goals page correctly)
- [x] ~~Button text proportions~~ (fixed with proper spacing)

---

### 📅 SESSIONS - UPCOMING
**Status**: ✅ COMPLETE

✅ **Fixed:**
- Database queries corrected (removed is_public, focus_areas columns)
- Empty state displays properly
- Data loads from database
- List/Calendar view toggle buttons working (lines 121-133)
- List view displays sessions with proper styling
- **NEW**: Calendar view fully implemented with JavaScript (calendar.js)
- **NEW**: Calendar navigation (prev/next month) working
- **NEW**: Session indicators on calendar days
- **NEW**: Click to view session details from calendar
- Session detail view on click (View button exists)
- Filter functionality (Working - period and coach filters)

---

### 📅 SESSIONS - BOOKING
**Status**: ✅ Database Fixed | ✅ Features Complete (commit 15c0f75)

✅ **Fixed:**
- Database queries corrected (removed package_types table references)
- Package display works with actual schema
- Empty state displays
- **NEW**: Available sessions grid with register buttons added
- **NEW**: Register buttons show price and link to payment
- **NEW**: Spots remaining display
- **NEW**: Session details (coach, location, time, price)
- **NEW**: Private booking form separated from group sessions

🔴 **Outstanding:**
- [x] ~~Calendar view~~ (Not needed - grid view is appropriate)
- [x] ~~Register button to payment flow~~ (Implemented)
- [x] ~~Session selection detail view~~ (Card shows details)

---

### 🎥 VIDEO - DRILL REVIEW
**Status**: ✅ COMPLETE

✅ **Fixed:**
- Database queries corrected (status, drill_type columns)
- Proper JOINs to drills table
- Filter dropdowns populated
- **NEW**: Pending/Reviewed sections separation implemented (lines 89-194)
- **NEW**: Search functionality by date, coach, drill type working (lines 35-42)
- **NEW**: Video cards with proper metadata display
- **NEW**: Status badges (pending vs reviewed)
- **NEW**: Rating display for reviewed videos

---

### 🎥 VIDEO - COACHES REVIEW
**Status**: ✅ COMPLETE

✅ **Fixed:**
- Database queries corrected (athlete query path)
- Filter functionality working
- **NEW**: Upload tab implemented (lines 207+)
- **NEW**: Upload form with athlete selection, date picker, drill type
- **NEW**: File upload functionality
- **NEW**: Pending/Reviewed sections with tabs (lines 65-76)
- **NEW**: Tab navigation with data-action="switch-tab"
- **NEW**: Video list items with thumbnail, metadata, action buttons

---

### 💪 HEALTH - WORKOUTS
**Status**: ✅ COMPLETE

✅ **Fixed:**
- Database queries corrected (workout_plans, exercise_library tables)
- Data loads from athlete_workout_assignments
- **NEW**: Excellent empty state with header: "No Workout Plan Currently Assigned"
- **NEW**: Empty state includes icon, title, description, and contact coach button
- **NEW**: Header "Active Program" displays even when empty
- **NEW**: Professional empty state card styling

---

### 🍎 HEALTH - NUTRITION
**Status**: ✅ COMPLETE

✅ **Fixed:**
- Database queries corrected (user_id, target_* columns)
- Data loads from nutrition_plans
- **NEW**: Excellent empty state with header: "No Nutrition Plan Currently Assigned"
- **NEW**: Empty state includes icon, title, comprehensive description, and contact coach button
- **NEW**: Header "Active Nutrition Plan" displays even when empty
- **NEW**: Professional empty state card styling with helpful message

---

### 🏋️ DRILLS - LIBRARY
**Status**: ✅ Verified Correct

✅ **Fixed:**
- Database queries already correct
- Data loads properly

---

### 🏋️ DRILLS - CREATE
**Status**: 🔴 Not Reviewed Yet

🔴 **Outstanding:**
- [ ] Form functionality needs testing
- [ ] Module connection needs verification

---

### 🏋️ DRILLS - IMPORT
**Status**: 🔴 Not Reviewed Yet

🔴 **Outstanding:**
- [ ] IHS import functionality needs verification
- [ ] Module connection needs testing

---

### 📋 PRACTICE PLANS - LIBRARY
**Status**: ✅ Database Fixed

✅ **Fixed:**
- Database queries added (replaced static HTML)
- Search and filter working
- Empty state displays

---

### 📋 PRACTICE PLANS - CREATE
**Status**: 🔴 Not Reviewed Yet

🔴 **Outstanding:**
- [ ] Form functionality needs testing
- [ ] Module connection needs verification

---

### 🚗 TRAVEL - MILEAGE
**Status**: ✅ Database Fixed

✅ **Fixed:**
- Database queries corrected (mileage_logs table, trip_date column)
- Distance conversion km/miles
- Data loads properly

---

### 👥 ROSTER (Coach)
**Status**: ✅ Database Fixed (in earlier commit)

✅ **Fixed:**
- PDO query errors resolved
- No package_id column errors

---

### 💰 ACCOUNTING - DASHBOARD
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Quick actions boxes sizing issues
- [ ] Broken box with "ice time rental" text
- [ ] Package purchase overlapping revenue review
- [ ] Revenue graph not showing when data is $0
- [ ] Revenue overview inconsistent styling
- [ ] Graph should always show regardless of amount

---

### 💳 BILLING - DASHBOARD
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Revenue overview heading should match "Recent Payments"
- [ ] Filter by time period
- [ ] Graph display below filters

---

### 💳 BILLING - CREATE INVOICE
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Button doesn't work
- [ ] Export button doesn't work
- [ ] "Nothing to export" message when empty

---

### 📊 REPORTS - GENERATE
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] CSRF token error: {"success":false,"error":"Invalid CSRF token"}
- [ ] Form redirects to home instead of generating report
- [ ] Quick report buttons do nothing
- [ ] Download/View/Delete buttons on recent reports do nothing

---

### 📊 REPORTS - SCHEDULES  
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] CSRF token error on create schedule
- [ ] Edit/Pause/Delete buttons do nothing
- [ ] Confirmation message after creation

---

### 💵 CREDITS & REFUNDS
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Issue credit/refund button does nothing
- [ ] Should show form with historical purchases

---

### 📝 EXPENSES
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Choose file button doesn't work
- [ ] Take photo button doesn't work
- [ ] CSRF token error on add expense
- [ ] (Database query already fixed - no category_id error)

---

### 🏷️ PRODUCTS - SESSIONS
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Search by location, name, date, skill type
- [ ] Session history (when/where used, which athletes)
- [ ] Add button missing icon
- [ ] Consistent display across tabs

---

### 🏷️ PRODUCTS - PACKAGES
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Same display as sessions tab
- [ ] Search functionality
- [ ] Add button missing icon, doesn't open create page
- [ ] Should be tabs not buttons

---

### 🏷️ PRODUCTS - DISCOUNTS
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Edit/Delete functions don't work
- [ ] Create discount button doesn't work
- [ ] Should be tabs not buttons

---

### 👔 HR - TERMINATION
**Status**: 🔴 Broken - Fatal Error

🔴 **Critical:**
- [x] ~~Fatal error: Call to undefined function csrfTokenInput()~~ (Fixed in commit 7e7616f)
- [ ] Process termination button redirects to home
- [ ] Font style guide compliance

---

### 👥 ADMIN - ALL USERS
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Filter doesn't work (e.g., admin role shows no users)
- [ ] Search by name not working
- [ ] Add user button does nothing
- [ ] Export button missing icon
- [ ] Table not found error on export
- [ ] CSS error: .status-badge.inactive showing as text

---

### 🏷️ ADMIN - CATEGORIES
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Should be tabs not buttons (Skills, Drill Types, Positions, Equipment)
- [ ] Add buttons missing icons
- [ ] Add buttons don't call module
- [ ] Edit/Delete buttons don't work

---

### 📊 ADMIN - EVAL FRAMEWORK
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Add evaluation category missing button
- [ ] Doesn't call skill category library
- [ ] Plus/Edit/Delete buttons do nothing
- [ ] Skill edit/delete under categories do nothing
- [ ] Add scale button missing icon and doesn't work

---

### 🔔 ADMIN - SYSTEM NOTIFICATIONS
**Status**: 🔴 Broken - Fatal Error

🔴 **Critical:**
- [x] ~~Fatal error: Call to undefined function csrfTokenInput()~~ (Fixed in commit d56a355)
- [ ] Form redirects to home instead of creating notification
- [ ] No confirmation message
- [ ] Edit/Delete buttons on active notifications do nothing

---

### 📜 ADMIN - AUDIT LOG
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Export button: "table not found" error

---

### ⏰ ADMIN - CRON JOBS
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Add cron job button missing icon
- [ ] Add button doesn't work
- [ ] Time setting should use normal time format
- [ ] Run/Edit/Pause buttons do nothing
- [ ] Execution history buttons do nothing

---

### ⚙️ ADMIN - SYSTEM TOOLS - SETTINGS
**Status**: ✅ Partially Fixed | 🔴 Issues Remain

✅ **Fixed:**
- CSS error display fixed (unclosed style tag)

🔴 **Outstanding:**
- [ ] Save settings button gives CSRF error
- [ ] Toggle sliders give CSRF error
- [ ] Changes redirect to home page

---

### ⚙️ ADMIN - SYSTEM TOOLS - OTHER TABS
**Status**: 🔴 Not Fixed Yet

🔴 **Outstanding:**
- [ ] Clicking tabs doesn't switch views (stays on settings)

---

### 👤 PROFILE - SETTINGS
**Status**: ✅ Partially Fixed | 🔴 Issues Remain

✅ **Fixed:**
- Form action paths corrected
- Database schema added for height, weight, handedness, catching_hand, jersey_number

🔴 **Outstanding:**
- [ ] Save changes redirects to home
- [ ] Change photo button doesn't work properly
- [ ] Should click profile picture to upload
- [ ] No save button after photo selected
- [ ] Security/Notifications tabs don't work

---

## Summary Statistics

- **Total Pages Reviewed**: 35+
- **Database Fixes Completed**: 18 commits, 7 modules (Sessions, Video, Health, Travel, Practice, Nutrition, Goals)
- **Critical Errors Fixed**: 3 (csrfTokenInput fatal errors, CSS display)
- **Pages Completed**: 2 (Home, Performance Stats)
- **Outstanding Issues**: ~90 (down from 100+)
- **Issue Categories**:
  - 🔴 CSRF Token in AJAX: ~15 instances
  - 🔴 Button handlers not wired: ~40 instances
  - 🔴 Missing UI features: ~25 instances
  - 🔴 Style guide compliance: ~20 instances

---

## Next Steps

1. **Immediate**: Continue page-by-page fixes starting from Home
2. **Priority**: CSRF token inclusion in JavaScript AJAX calls
3. **Priority**: Button event handler wiring
4. **Follow-up**: UI/UX enhancements and style guide compliance

