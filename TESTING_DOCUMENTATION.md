# Arctic Wolves Application - Testing Documentation

**Version:** 1.0  
**Last Updated:** February 2026  
**Purpose:** This document provides a comprehensive breakdown of all views, tabs, and functionality within the Arctic Wolves hockey training management system. It is designed for testers to understand what each part of the site is designed to do.

---

## Table of Contents

1. [User Roles & Access Levels](#user-roles--access-levels)
2. [Navigation Structure Overview](#navigation-structure-overview)
3. [Main Menu (All Users)](#main-menu-all-users)
4. [Team Section (Team Coaches)](#team-section-team-coaches)
5. [Coaches Corner (Coaches & Admins)](#coaches-corner-coaches--admins)
6. [Health Management (Health Coaches & Admins)](#health-management-health-coaches--admins)
7. [Accounting & Reports (Admins Only)](#accounting--reports-admins-only)
8. [Point of Sale (Admins & Front Desk)](#point-of-sale-admins--front-desk)
9. [HR Section (Admins Only)](#hr-section-admins-only)
10. [Administration (Admins Only)](#administration-admins-only)
11. [Profile & Settings](#profile--settings)
12. [Key Testing Scenarios](#key-testing-scenarios)

---

## User Roles & Access Levels

The Arctic Wolves application has multiple user roles with different access permissions:

| Role | Description | Access Level |
|------|-------------|--------------|
| **Admin** | System administrators | Full access to all features and sections |
| **Coach** | Hockey coaches | Access to Coaches Corner, can view athlete data and create drills/practices |
| **Health Coach** | Fitness/nutrition specialists | Access to Health Management section (workouts, nutrition, health roster) |
| **Team Coach** | Team-specific coaches | Limited access to team roster and related features |
| **Athlete** | Players/students | Access to Main Menu features (sessions, stats, video, health, shop) |
| **Parent** | Parents of athletes | Access to shop, payment history, can select child athletes to view their data |
| **Front Desk Staff** | Reception/admin staff | Limited to POS (Point of Sale) system only |

### Special Features:
- **Multi-Role Support**: Users can have multiple roles assigned via the `user_roles` table
- **Persona Mode**: Admins can switch personas to view the system as different role types for testing and support
- **Parent-Child Linking**: Parents can select which child athlete's information to view

---

## Navigation Structure Overview

The Arctic Wolves application uses a hierarchical navigation menu organized into the following sections:

1. **Main Menu** - Available to all users (Athletes, Parents, Coaches, Admins)
2. **Team** - Team Coaches only
3. **Coaches Corner** - On-ice Coaches and Admins (not Health Coaches)
4. **Health Management** - Health Coaches and Admins
5. **Accounting & Reports** - Admins only
6. **Point of Sale** - Admins and Front Desk Staff only
7. **HR** - Admins only
8. **Administration** - Admins only
9. **Profile & Settings** - Available to all users (footer section)

The following documentation follows the exact order of items as they appear in the dashboard navigation menu.

---

## Main Menu (All Users)

These pages are accessible to all authenticated users (Athlete, Parent, Coach, Admin). Items appear in this exact order:

### 1. Home
**Page:** `?page=home`  
**Icon:** House  
**File:** `views/home.php`

**Purpose:** Central landing page providing personalized overview based on user role.

**What It Shows:**
- Welcome message with user name
- Upcoming sessions (next 5 scheduled)
- Recent unread notifications (up to 5)
- Performance statistics for athletes:
  - Total sessions attended
  - Total training hours
  - Current active goals
  - Skills assessed
- Role-specific widgets (coaches see additional team information)

**Testing Focus:**
- Verify correct data displays for each role type
- Check session count accuracy
- Validate notification display and read/unread status
- Ensure stats calculations are correct

---

### 2. Performance Stats
**Page:** `?page=stats`  
**Icon:** Chart Line  
**File:** `views/stats.php`

**Purpose:** Track athlete performance metrics, goals, and skill development over time.

**Tabs:**
1. **Goals Tab** (`?page=stats&tab=goals`)
   - View personal training goals
   - Track goal progress (percentage complete)
   - Filter by status: Active, Completed, Archived
   - Create new goals with target dates
   - Mark goals as complete
   
2. **Statistics Tab** (`?page=stats&tab=statistics`)
   - Performance graphs and charts
   - Session attendance history
   - Skill assessment scores
   - Progression over time

**Special Features:**
- **Coach View**: Coaches can select different athletes from dropdown to view their stats
- **Parent View**: Parents can switch between their children's stats
- **Export**: Ability to export stats data (coaches/admins)

**Testing Focus:**
- Test athlete selector dropdown (coaches/parents)
- Verify goal creation and progress tracking
- Check statistics calculations and graph rendering
- Test filtering and sorting functionality
- Validate date range filters work correctly

---

### 3. Messages
**Page:** `?page=messages`  
**Icon:** Comments  
**File:** `views/messages.php`

**Purpose:** Internal messaging system for communication between users.

**What It Does:**
- Send messages to coaches, staff, or other athletes
- Receive and read incoming messages
- View message history and threads
- Mark messages as read/unread
- Delete messages

**Testing Focus:**
- Test sending messages to different user types
- Verify message delivery and notification
- Check read/unread status updates
- Test message search and filtering

---

### 4. Sessions
**Page:** `?page=sessions`  
**Icon:** Calendar Check  
**File:** `views/sessions.php` (parent page with tabs)

**Purpose:** Manage training session scheduling and bookings.

**Tabs:**

#### 4.1 Upcoming Sessions
**Page:** `?page=upcoming_sessions`  
**File:** `views/sessions_upcoming.php`

**Goal:** Display all upcoming training sessions the user is registered for.

**Features:**
- List view of future sessions with date, time, location
- Session details: type, duration, coach, arena
- Session status indicators (scheduled, confirmed, cancelled)
- Calendar view toggle
- Filter by date range, session type, or coach
- Check-in functionality (for current sessions)
- Cancel booking option (with cancellation policy)

**What Testers Should Verify:**
- Sessions display in chronological order
- Correct session details are shown
- Filters work properly
- Calendar view matches list view
- Check-in only available during session time window
- Cancellation policy is enforced

#### 4.2 Booking
**Page:** `?page=booking`  
**File:** `views/sessions_booking.php`

**Goal:** Book new training sessions and purchase session packages.

**Features:**
- Browse available sessions by date/time
- View session capacity and availability
- Select and book individual sessions
- Purchase session packages (bulk discounts)
- View package balances
- Apply credits or packages to bookings
- Payment processing integration

**What Testers Should Verify:**
- Only available sessions are bookable (not at capacity)
- Package credits apply correctly
- Payment processing works end-to-end
- Booking confirmation sent
- Session capacity updates after booking
- Duplicate bookings prevented

---

### 5. Video
**Page:** `?page=video`  
**Icon:** Video  
**File:** `views/video.php` (parent page)

**Purpose:** Video recording, review, and feedback system for skill development.

**Tabs:**

#### 5.1 Drill Review
**Page:** `?page=drill_review`  
**File:** `views/video_drill_review.php`

**Goal:** Athletes view videos of themselves performing drills, with coach feedback.

**Features:**
- Grid/list of drill videos
- Play video with controls
- View coach comments and ratings
- Filter by drill type or date
- Download videos

**Testing Focus:**
- Video playback functionality
- Coach feedback displays correctly
- Filtering and search work
- Video quality and loading speed

#### 5.2 Coach Review
**Page:** `?page=coaches_reviews`  
**File:** `views/video_coach_reviews.php`

**Goal:** Coaches upload and annotate videos, provide feedback to athletes.

**Features:**
- Upload new videos (athletes or drills)
- Tag athletes in videos
- Add timestamps with comments
- Rate performance aspects
- Assign videos to athletes
- Bulk upload capability

**Testing Focus:**
- Video upload works (file size limits)
- Multiple format support (mp4, mov, etc.)
- Tagging and athlete assignment
- Comment/annotation system
- Rating submission

#### 5.3 Record Video
**Page:** `?page=record_drill_video`  
**File:** `views/video_record_drill.php`

**Goal:** Record video directly from webcam/camera for immediate upload.

**Features:**
- In-browser video recording
- Camera/microphone selection
- Preview before saving
- Metadata entry (drill type, date, notes)
- Direct upload after recording

**Testing Focus:**
- Camera permissions and access
- Recording quality settings
- Preview playback works
- Upload after recording succeeds
- Works on different devices/browsers

---

### 6. Health
**Page:** `?page=health`  
**Icon:** Heart Pulse  
**File:** `views/health.php` (parent page)

**Purpose:** Manage strength, conditioning, and nutrition programs.

**Tabs:**

#### 6.1 Strength & Conditioning
**Page:** `?page=strength_conditioning` or `?page=workouts`  
**File:** `views/health_workouts.php`

**Goal:** View and track assigned workout programs.

**Features:**
- List of assigned workout programs
- Workout details: exercises, sets, reps, weight
- Mark exercises as complete
- Log workout sessions
- Progress tracking over time
- Workout history
- Print workout sheets

**Testing Focus:**
- Workout assignment appears correctly
- Completion tracking updates properly
- Progress calculations accurate
- Exercise instructions display
- History logs correctly

#### 6.2 Nutrition
**Page:** `?page=nutrition`  
**File:** `views/health_nutrition.php`

**Goal:** View personalized nutrition plans and meal guidance.

**Features:**
- Meal plans (breakfast, lunch, dinner, snacks)
- Nutritional goals and macros
- Recipe library
- Meal logging and tracking
- Hydration tracking
- Supplement recommendations

**Testing Focus:**
- Meal plans display correctly
- Macro calculations accurate
- Logging functionality works
- Recipe details are complete
- Tracking history saves properly

---

### 7. Shop
**Page:** `?page=shop`  
**Icon:** Store  
**File:** `views/shop.php`

**Purpose:** E-commerce store for team merchandise and equipment.

**Features:**
- Product catalog with images
- Product categories (apparel, equipment, accessories)
- Size and color selection
- Add to cart functionality
- Shopping cart view/edit
- Checkout process
- Order history
- Product search

**Testing Focus:**
- Product images load properly
- Add to cart works for all products
- Size/color selections save correctly
- Cart calculations (subtotal, tax, total)
- Checkout flow completes successfully
- Payment processing integration
- Order confirmation emails sent

---

### 8. Purchase History
**Page:** `?page=payment_history`  
**Icon:** Receipt  
**File:** `views/payment_history.php`

**Purpose:** View complete transaction and payment history.

**Features:**
- List of all payments made
- Transaction details (date, amount, method, status)
- Filter by date range or payment type
- Invoice downloads (PDF)
- Receipt downloads
- Refund status tracking
- Package purchase history

**Testing Focus:**
- All transactions appear in history
- Date filters work correctly
- Download buttons generate proper PDFs
- Transaction details are accurate
- Refunds show correct status
- Search functionality works

---

## Team Section (Team Coaches)

These features are available only to users with the Team Coach role.

### 9. Roster
**Page:** `?page=team_roster`  
**Icon:** Users  
**File:** `views/team_roster.php`

**Purpose:** Manage team roster, player information, and team assignments.

**Features:**
- View all team members
- Player details: name, position, jersey number
- Contact information
- Attendance tracking
- Add/remove players from team
- Team season assignments
- Export roster to PDF/Excel

**Testing Focus:**
- Roster displays all team members
- Player information accurate and complete
- Add/remove operations work
- Season assignments correct
- Export functions produce proper files
- Contact info displays properly

---

## Coaches Corner (Coaches & Admins)

These pages are available to On-ice Coaches and Administrators only (not Health Coaches).

### 10. Calendar
**Page:** `?page=coach_calendar`  
**Icon:** Calendar  
**File:** `views/coach_calendar.php`

**Purpose:** Coach's personal calendar showing all sessions, practices, and commitments.

**Features:**
- Calendar view (month/week/day)
- Color-coded by session type
- Click to view session details
- Drag-and-drop rescheduling
- Session creation from calendar
- Export calendar (iCal format)
- Sync with external calendars

**Testing Focus:**
- Calendar renders correctly for all view types
- Events display at correct date/time
- Drag-and-drop reschedule works
- Color coding is consistent
- Export includes all events
- External sync functionality

---

### 11. Drills
**Page:** `?page=drills`  
**Icon:** Clipboard List  
**File:** `views/drills.php` (parent page)

**Purpose:** Manage drill library, create new drills, and import drills.

**Tabs:**

#### 11.1 Drill Library
**Page:** `?page=drill_library`  
**File:** `views/drills_library.php`

**Goal:** Browse and search existing drills in the system.

**Features:**
- Searchable drill library
- Filter by category, skill level, duration
- Drill preview cards with diagrams
- Favorite/bookmark drills
- Drill details view
- Copy drill to create variations
- Print drill sheets
- Export drills

**Testing Focus:**
- Search returns relevant results
- Filters narrow results correctly
- Drill diagrams display properly
- Favorites save and persist
- Copy function creates new drill
- Print format is usable
- Export works

#### 11.2 Create Drill
**Page:** `?page=create_drill`  
**File:** `views/drills_create.php`

**Goal:** Create new custom drills with visual ice surface designer.

**Features:**
- Interactive ice surface canvas
- Drag-and-drop player/puck markers
- Draw lines for movement paths
- Add cones, nets, zones
- Set drill parameters:
  - Name and description
  - Category and skill focus
  - Duration and difficulty
  - Player count
- Save as draft or publish
- Attach video demonstrations

**Testing Focus:**
- Canvas tools work (drag, draw, place)
- Save preserves all elements
- Drill parameters validate
- Published drills appear in library
- Video attachment works
- Undo/redo functionality

#### 11.3 Import Drill
**Page:** `?page=import_drill`  
**File:** `views/drills_import.php`

**Goal:** Import drills from Ice Hockey Systems (IHS) or other sources.

**Features:**
- IHS drill import
- Search IHS drill database
- Preview before import
- Bulk import multiple drills
- Map categories to local system
- Edit after import

**Testing Focus:**
- IHS connection/authentication works
- Search returns results from IHS
- Preview shows drill correctly
- Import saves to local database
- Category mapping works
- Bulk import handles multiple items

#### 11.4 Export / Import All
**Page:** `?page=export_import_drills`  
**File:** `views/drills_export_import.php`

**Goal:** Bulk export all drills to JSON and import drills from a JSON backup file.

**Features:**
- Export all drills, categories, and tags as a single JSON file
- Shows total drill and category counts before export
- Import drills from a previously exported JSON file
- Skip duplicates option (matches by drill title)
- Category mapping during import (matches existing categories by name, creates new ones as needed)
- Drill tags preserved during export/import

**Testing Focus:**
- Export button downloads a valid JSON file
- Exported file contains all drills, categories, and tags
- Import accepts only `.json` files
- Import creates new drills and categories correctly
- Skip duplicates option prevents duplicate drill entries
- Import error messages display correctly for invalid files
- Round-trip export → import produces identical data

---

### 12. Practice Plans
**Page:** `?page=practice`  
**Icon:** File Lines  
**File:** `views/practice.php` (parent page)

**Purpose:** Create and manage complete practice plans with multiple drills.

**Tabs:**

#### 12.1 Practice Library
**Page:** `?page=practice_library`  
**File:** `views/practice_library.php`

**Goal:** Browse saved practice plans.

**Features:**
- List of all practice plans
- Search and filter by date, team, duration
- Preview practice schedule
- Copy existing practices
- Print practice plans
- Share practices with other coaches

**Testing Focus:**
- All practices display
- Search finds correct practices
- Filters work properly
- Preview shows complete plan
- Copy creates new practice
- Print is formatted well

#### 12.2 Create Practice
**Page:** `?page=practice_create` or `?page=create_practice`  
**File:** `views/practice_create.php`

**Goal:** Build a complete practice plan by selecting and arranging drills.

**Features:**
- Drag-and-drop drill selection from library
- Set practice duration and segments:
  - Warm-up
  - Skill development
  - Game situations
  - Cool-down
- Assign time to each drill
- Auto-calculate practice duration
- Add notes and coaching points
- Assign to teams/sessions
- Save and publish

**Testing Focus:**
- Drill selection/search works
- Drag-and-drop arranges drills
- Duration calculations accurate
- Notes save properly
- Team/session assignment works
- Published plans appear in library

#### 12.3 Import Practice
**Page:** `?page=practice_import`  
**File:** `views/practice_import.php`

**Goal:** Import practice plans from external sources.

**Features:**
- Import from IHS
- Import from file (CSV, JSON)
- Preview practice before import
- Edit during import
- Bulk import capability

**Testing Focus:**
- File upload works
- Format parsing correct
- Preview displays properly
- Import saves to database
- Edit during import functional

#### 12.4 Export / Import All
**Page:** `?page=export_import_plans`  
**File:** `views/practice_export_import.php`

**Goal:** Bulk export all practice plans to JSON and import plans from a JSON backup file.

**Features:**
- Export all practice plans with associated drills as a single JSON file
- Includes drill details (title, description, coaching points, etc.) in the export
- Shows total plan and drill counts before export
- Import practice plans from a previously exported JSON file
- Automatically creates missing drills during import
- Skip duplicates option (matches by plan name)
- Drill categories preserved during export/import

**Testing Focus:**
- Export button downloads a valid JSON file
- Exported file contains all plans with their associated drills
- Import accepts only `.json` files
- Import creates new plans and links drills correctly
- Missing drills are created during import
- Skip duplicates option prevents duplicate plan entries
- Import error messages display correctly for invalid files
- Round-trip export → import produces identical data

---

### 13. Roster
**Page:** `?page=roster`  
**Icon:** Users Gear  
**File:** `views/coach_roster.php`

**Purpose:** Coach's view of all their assigned athletes.

**Features:**
- Complete athlete list
- Search and filter athletes
- Athlete quick stats
- Contact information
- Emergency contacts
- Medical information
- Attendance records
- Performance summaries
- Bulk actions (email, assign sessions)

**Testing Focus:**
- All assigned athletes appear
- Search works across all fields
- Filters narrow results
- Contact info displays correctly
- Medical alerts are prominent
- Bulk email sends successfully
- Performance data accurate

---

### 14. Session Evaluations
**Page:** `?page=coach_session_evaluations`  
**Icon:** Clipboard Check  
**File:** `views/coach_session_evaluations.php` (parent page)

**Purpose:** Evaluate athlete performance during training sessions.

**Tabs:**

#### 14.1 Evaluations List
**Main view** - List of sessions needing evaluation

**Features:**
- Sessions awaiting evaluation
- Completed evaluations
- Filter by date, athlete, session type
- Quick evaluate button

**Testing Focus:**
- Unevaluated sessions appear
- Completed evaluations marked
- Filters work correctly

#### 14.2 Evaluation Form
**Page:** `?page=session_evaluation_form`  
**File:** `views/session_evaluation_form.php`

**Goal:** Fill out detailed evaluation for a specific session.

**Features:**
- Session details at top
- Athlete selector (multi-athlete sessions)
- Skill rating scales (1-5 or 1-10)
- Category evaluation:
  - Technical skills
  - Tactical understanding
  - Physical conditioning
  - Mental/attitude
- Comments section
- Overall rating
- Areas for improvement
- Recommendations
- Save draft or submit

**Testing Focus:**
- Session info loads correctly
- Athlete selector works
- All rating scales functional
- Comments save properly
- Draft saves without submitting
- Submit finalizes evaluation
- Evaluation appears in athlete history

---

### 15. Travel
**Page:** `?page=travel` or `?page=mileage`  
**Icon:** Plane  
**File:** `views/travel_mileage.php`

**Purpose:** Track mileage and travel expenses for reimbursement.

**Features:**
- Log trips with:
  - Date
  - Start/end locations
  - Mileage (km or miles)
  - Purpose
  - Session/event reference
- Auto-calculate reimbursement amount
- Mileage rate settings
- Trip history
- Filter by date range
- Export for accounting (CSV, PDF)
- Submit for approval
- Approval status tracking

**Testing Focus:**
- Trip logging saves all fields
- Mileage calculations correct
- Reimbursement rates accurate
- Location autocomplete works
- History displays all trips
- Export includes all trips in range
- Submission workflow functions
- Approval status updates

---

### 16. Video Recording
**Page:** `?page=record_drill_video`  
**Icon:** Video  
**File:** `views/video_record_drill.php`

**Purpose:** Coaches record demonstration videos or athlete performances.

**Features:**
- Same as athlete video recording
- Additional: Assign to specific drill
- Tag multiple athletes
- Add drill instructions overlay
- Set visibility (public/private/athletes-only)

**Testing Focus:**
- All recording features work
- Athlete tagging supports multiple
- Drill assignment links properly
- Visibility settings enforced
- Upload completes successfully

---

## Health Management (Health Coaches & Admins)

These pages are available to Health Coaches and Administrators.

### 17. Strength & Conditioning
**Page:** `?page=library_workouts`  
**Icon:** Dumbbell  
**File:** `views/library_workouts.php`

**Purpose:** Manage workout templates and programs library.

**Features:**
- Create workout templates
- Exercise library with instructions/videos
- Build multi-week programs
- Assign workouts to athletes
- Track program completion
- Progress photos upload
- Measurement tracking (weight, body comp, etc.)

**Testing Focus:**
- Template creation saves properly
- Exercise library comprehensive
- Multi-week programs structure correctly
- Athlete assignment works
- Completion tracking accurate
- Photo uploads work
- Measurements log correctly

---

### 18. Nutrition
**Page:** `?page=library_nutrition`  
**Icon:** Utensils  
**File:** `views/library_nutrition.php`

**Purpose:** Manage nutrition plan templates and recipes.

**Features:**
- Meal plan templates
- Recipe library with ingredients/instructions
- Nutritional information database
- Macro/calorie calculators
- Assign plans to athletes
- Dietary restrictions/preferences
- Shopping lists generation
- Meal prep guides

**Testing Focus:**
- Templates save correctly
- Recipe details complete
- Nutritional data accurate
- Calculators work properly
- Athlete assignment functions
- Restrictions respected in plans
- Shopping lists generate correctly

---

### 19. Roster
**Page:** `?page=roster` or `?page=health_coach_roster`  
**Icon:** Users Gear  
**File:** `views/health_coach_roster.php`

**Purpose:** Health coach's view of assigned athletes with health metrics.

**Features:**
- Athlete list with health metrics
- Current programs assigned
- Progress tracking
- Compliance/completion rates
- Measurements history
- Injury status
- Medical clearances
- Communication log

**Testing Focus:**
- All assigned athletes appear
- Metrics display correctly
- Progress calculations accurate
- Injury status prominent
- Medical clearances validated
- Communication logs properly

---

## Accounting & Reports (Admins Only)

These features are restricted to Administrators only.

### 20. Finance Dashboard
**Page:** `?page=finance_dashboard`  
**Icon:** Chart Pie  
**File:** `views/finance_dashboard.php` (parent page)

**Purpose:** Central hub for financial management and reporting.

**Tabs:**

#### 20.1 Overview
**Page:** `?page=finance_dashboard&tab=overview`  
**File:** `views/finance_overview.php`

**Goal:** High-level financial summary and key metrics.

**Features:**
- Revenue summary (daily, weekly, monthly, yearly)
- Expense summary
- Net income
- Outstanding receivables
- Graphs and charts:
  - Revenue trends
  - Expense breakdown
  - Payment method distribution
- Top products/sessions by revenue
- Recent large transactions

**Testing Focus:**
- All calculations accurate
- Date range filters work
- Graphs render correctly
- Data updates in real-time
- Drill-down to details works

#### 20.2 Billing
**Page:** `?page=finance_dashboard&tab=billing`  
**File:** `views/finance_billing.php`

**Goal:** Manage invoices, payments, and billing.

**Features:**
- Invoice list (paid, unpaid, overdue)
- Create new invoice
- Payment recording
- Payment reminders
- Automatic recurring billing
- Invoice templates
- Send invoice by email
- Payment plans
- Partial payment tracking

**Testing Focus:**
- Invoice creation works
- Payment recording updates status
- Email sending functions
- Recurring billing triggers
- Overdue invoices flagged
- Partial payments calculated correctly

#### 20.3 POS Transactions
**Page:** `?page=finance_dashboard&tab=pos_transactions`  
**File:** `views/pos_transactions.php`

**Goal:** View all Point of Sale transactions.

**Features:**
- Transaction list with details
- Filter by date, staff, payment method
- Transaction details view
- Void/refund capability
- Print receipts
- Cash drawer reconciliation
- Sales reports

**Testing Focus:**
- All transactions appear
- Filters work correctly
- Void/refund updates properly
- Receipt printing works
- Cash reconciliation accurate
- Sales reports calculate correctly

#### 20.4 Shop Orders
**Page:** `?page=finance_dashboard&tab=shop_orders`  
**File:** `views/shop_orders.php`

**Goal:** Manage e-commerce orders from the shop.

**Features:**
- Order list with status
- Order details view
- Update order status
- Shipping label generation
- Tracking number entry
- Order fulfillment workflow
- Customer notifications
- Return processing

**Testing Focus:**
- Orders display correctly
- Status updates save
- Shipping labels generate
- Customer notifications sent
- Fulfillment workflow complete
- Returns process properly

---

### 21. Financial Reports Hub
**Page:** `?page=financial_reports` or `?page=reports`  
**Icon:** Chart Pie  
**File:** `views/financial_reports.php`

**Purpose:** Generate comprehensive financial reports.

**Features:**
- Report types:
  - Profit & Loss (P&L)
  - Balance Sheet
  - Cash Flow Statement
  - Revenue by Category
  - Expense by Category
  - Session Revenue
  - Product Sales
  - Tax Reports
- Date range selection
- Custom report builder
- Save report templates
- Export (PDF, Excel, CSV)
- Schedule automated reports
- Email delivery

**Testing Focus:**
- All report types generate
- Data accuracy verified
- Date ranges filter correctly
- Custom reports save/load
- Export formats work
- Scheduled reports send
- Calculations verified against source data

---

### 22. Credits & Refunds
**Page:** `?page=credits_refunds`  
**Icon:** Money Bill Transfer  
**File:** `views/accounting_credits.php`

**Purpose:** Manage customer credits, refunds, and account adjustments.

**Features:**
- Credit balance by customer
- Issue credit to customer account
- Process refunds
- Refund history
- Credit usage tracking
- Expiration dates on credits
- Refund approval workflow
- Accounting journal entries
- Stripe refund processing

**Testing Focus:**
- Credit issuance creates balance
- Refunds process through payment gateway
- Credit usage deducts properly
- Expiration enforced
- Approval workflow functions
- Journal entries created correctly
- Stripe integration works

---

### 23. Expenses
**Page:** `?page=expenses`  
**Icon:** Receipt  
**File:** `views/accounting_expenses.php`

**Purpose:** Track and categorize business expenses.

**Features:**
- Log expenses with:
  - Date
  - Amount
  - Category
  - Vendor/payee
  - Payment method
  - Description
  - Receipt upload
- Expense categories management
- Recurring expenses
- Expense approvals
- Mileage expense integration
- Tax tracking
- Vendor management
- Export for accounting

**Testing Focus:**
- Expense logging saves all fields
- Receipt upload/storage works
- Categories can be added/edited
- Recurring expenses create automatically
- Approval workflow functions
- Mileage imports correctly
- Export includes all expenses

---

### 24. Products
**Page:** `?page=products`  
**Icon:** Box Open  
**File:** `views/accounting_products.php` (parent page with tabs)

**Purpose:** Manage all revenue-generating products and services.

**Tabs:**

#### 24.1 Sessions
**Tab:** `sessions`

**Goal:** Configure session types, pricing, and scheduling.

**Features:**
- Session type creation/editing
- Pricing (regular, package rates)
- Duration settings
- Capacity limits
- Coach assignments
- Location assignments
- Age/skill level restrictions
- Session templates

**Testing Focus:**
- Session types save correctly
- Pricing updates properly
- Capacity limits enforced during booking
- Coach/location assignments work
- Restrictions validated during booking

#### 24.2 Packages
**Tab:** `packages`

**Goal:** Create and manage session package bundles.

**Features:**
- Package creation (e.g., "10-pack of sessions")
- Pricing and discounts
- Expiration dates
- Session type restrictions
- Purchase limits
- Active/inactive status
- Package usage tracking

**Testing Focus:**
- Package creation saves
- Discounts calculate correctly
- Expiration enforced
- Restrictions apply during booking
- Usage tracking accurate

#### 24.3 Discounts
**Tab:** `discounts`

**Goal:** Manage promotional discounts and coupons.

**Features:**
- Discount code creation
- Percentage or fixed amount
- Valid date ranges
- Usage limits (total and per user)
- Applicable products
- Minimum purchase requirements
- Automatic vs. code-based

**Testing Focus:**
- Discount codes validate
- Calculations correct
- Date ranges enforced
- Usage limits respected
- Product restrictions work
- Minimum purchase checked

#### 24.4 Merchandise
**Tab:** `merchandise`

**Goal:** Manage merchandise products and categories.

**Features:**
- Product creation/editing
- Categories management
- Inventory tracking
- Variants (size, color)
- Pricing
- Product images
- Active/inactive status
- Stock alerts

**Testing Focus:**
- Products save with all fields
- Categories organize properly
- Inventory decrements on purchase
- Variants create correctly
- Images upload/display
- Stock alerts trigger

---

## Point of Sale (Admins & Front Desk)

These features are available to Front Desk Staff and Administrators.

### 25. POS Terminal
**Page:** `?page=pos_terminal`  
**Icon:** Cash Register  
**File:** `views/pos_terminal.php`

**Purpose:** Process in-person sales and transactions.

**Features:**
- Product/session selection
- Customer lookup
- Shopping cart
- Multiple payment methods:
  - Cash
  - Credit/debit card
  - Gift cards
  - Customer account credit
- Split payments
- Discounts application
- Receipt printing
- Email receipt option
- Refund/void transaction
- Cash drawer management
- Quick product buttons

**Testing Focus:**
- Product selection adds to cart
- Customer lookup finds records
- All payment methods process
- Split payments calculate correctly
- Discounts apply properly
- Receipt prints correctly
- Email receipts send
- Refunds process
- Cash drawer tracks properly

---

### 26. Time Tracking
**Page:** `?page=pos_time_tracking`  
**Icon:** Clock  
**File:** `views/pos_time_tracking.php`

**Purpose:** Clock in/out for front desk staff shifts.

**Features:**
- Clock in/out buttons
- Current shift display
- Shift history
- Break tracking
- Hours summary
- Export timesheet
- Manager approval

**Testing Focus:**
- Clock in records start time
- Clock out records end time
- Multiple shifts in same day handled
- Break time calculated correctly
- Hours summary accurate
- Export includes all shifts

---

### 27. My Schedule
**Page:** `?page=pos_schedule`  
**Icon:** Calendar Alt  
**File:** `views/pos_schedule.php`

**Purpose:** View work schedule for front desk staff.

**Features:**
- Calendar view of scheduled shifts
- Filter by staff member
- Shift details (time, location, role)
- Trade shift requests
- Availability management
- Print schedule

**Testing Focus:**
- Schedule displays correctly
- Shifts show proper times
- Trade requests function
- Availability saves
- Print format usable

---

## HR Section (Admins Only)

These features are restricted to Administrators for HR management.

### 28. Staff Scheduling
**Page:** `?page=admin_staff_scheduling`  
**Icon:** Calendar Check  
**File:** `views/admin_staff_scheduling.php`

**Purpose:** Create and manage staff work schedules.

**Features:**
- Drag-and-drop schedule builder
- Staff availability tracking
- Shift templates
- Coverage requirements
- Auto-scheduling suggestions
- Shift conflicts detection
- Publish schedule
- Staff notifications
- Export schedule

**Testing Focus:**
- Drag-and-drop creates shifts
- Availability blocks conflicts
- Templates apply correctly
- Auto-schedule fills gaps
- Conflicts flagged
- Publishing notifies staff
- Export includes all shifts

---

### 29. Time Tracking
**Page:** `?page=hr_time_tracking`  
**Icon:** Clock  
**File:** `views/hr_time_tracking.php`

**Purpose:** Monitor all staff time tracking and attendance.

**Features:**
- View all staff clock in/out
- Edit time entries (corrections)
- Approve timesheets
- Overtime tracking
- PTO/vacation tracking
- Tardiness reports
- Export for payroll

**Testing Focus:**
- All staff entries appear
- Edits save correctly
- Approval updates status
- Overtime calculated properly
- PTO balances accurate
- Export format matches payroll system

---

### 30. Payroll
**Page:** `?page=payroll`  
**Icon:** Money Check Dollar  
**File:** `views/hr_payroll.php`

**Purpose:** Process staff payroll and payments.

**Features:**
- Pay period selection
- Staff list with hours worked
- Pay rate application
- Deductions (taxes, benefits)
- Gross and net pay calculation
- Payment method (direct deposit, check)
- Pay stub generation
- Payment history
- Export for accounting
- Tax form generation (T4s, etc.)

**Testing Focus:**
- Hours import from time tracking
- Pay calculations accurate
- Deductions calculate correctly
- Pay stubs generate properly
- Payment history complete
- Export includes all data
- Tax forms accurate

---

### 31. Onboarding
**Page:** `?page=onboarding`  
**Icon:** User Plus  
**File:** `views/hr_onboarding.php`

**Purpose:** Manage new employee onboarding process.

**Features:**
- Onboarding checklist templates
- Document collection:
  - ID verification
  - Tax forms
  - Emergency contacts
  - Banking info
- Training assignment
- Equipment assignment
- System access setup
- Progress tracking
- E-signature integration (DocuSeal/OpenSign)
- Completion reports

**Testing Focus:**
- Checklists assign to new employees
- Document uploads work
- E-signature sends and tracks
- Training assignments save
- Equipment tracking updates
- Progress calculates correctly
- Completion triggers notifications

---

### 32. Contracts
**Page:** `?page=employee_contracts`  
**Icon:** File Signature  
**File:** `views/hr_employee_contracts.php`

**Purpose:** Manage employment contracts and agreements.

**Features:**
- Contract templates
- Create new contracts
- E-signature integration
- Contract status tracking
- Renewal reminders
- Contract history
- Document storage
- Search and filter
- Export contracts

**Testing Focus:**
- Templates load correctly
- New contracts create
- E-signature workflow complete
- Status updates track signing
- Reminders send before expiration
- Document storage secure
- Search finds contracts

---

### 33. Complaints
**Page:** `?page=complaints`  
**Icon:** Exclamation Triangle  
**File:** `views/hr_complaints.php`

**Purpose:** Track and manage HR complaints and incidents.

**Features:**
- Submit complaint form
- Complaint tracking number
- Status workflow (new, investigating, resolved)
- Confidential notes
- Document attachments
- Investigation timeline
- Resolution documentation
- Notifications to involved parties
- Export for records

**Testing Focus:**
- Form submission saves
- Tracking number generates
- Status updates properly
- Notes remain confidential
- Documents attach securely
- Notifications send appropriately
- Export includes all details

---

### 34. Termination
**Page:** `?page=termination`  
**Icon:** User Slash  
**File:** `views/hr_termination.php`

**Purpose:** Manage employee termination process.

**Features:**
- Termination checklist
- Exit interview form
- Final paycheck calculation
- Benefits termination
- Equipment return tracking
- System access revocation
- ROE generation (Record of Employment)
- Document collection
- Reference policy
- Rehire eligibility tracking

**Testing Focus:**
- Checklist tracks completion
- Exit interview saves
- Final pay calculates correctly
- System access revokes
- Equipment return logs
- ROE generates properly
- Documents save securely

---

## Administration (Admins Only)

These features are restricted to Administrators for system management.

### 35. All Users
**Page:** `?page=all_users`  
**Icon:** Users  
**File:** `views/admin_users.php`

**Purpose:** Manage all user accounts in the system.

**Features:**
- User list with filters
- Search users
- Create new user
- Edit user details
- Change user role
- Assign multiple roles
- Reset password
- Enable/disable account
- Delete user (with confirmation)
- User activity log
- Permission management
- Bulk actions
- Export user list

**Testing Focus:**
- All users display
- Search works across fields
- Filters narrow results
- User creation saves
- Role changes apply immediately
- Password reset emails send
- Account disable prevents login
- Delete removes user (or marks inactive)
- Bulk actions work on selected users
- Export includes all users

---

### 36. Resource Management
**Page:** `?page=categories`  
**Icon:** Layer Group  
**File:** `views/admin_categories.php` (parent page with tabs)

**Purpose:** Manage system-wide resources and categories.

**Tabs:**

#### 36.1 Skills
**Tab:** `skills`

**Goal:** Manage skill categories for evaluations.

**Features:**
- Add/edit/delete skills
- Organize by category
- Display order
- Active/inactive status

**Testing Focus:**
- Skills save correctly
- Display order works
- Inactive skills hidden in forms
- Used skills cannot be deleted

#### 36.2 Drill Types
**Tab:** `drills`

**Goal:** Manage drill categories.

**Features:**
- Add/edit/delete drill types
- Categorization
- Icons/colors

**Testing Focus:**
- Drill types save
- Categories organize drills
- Icons display in drill library

#### 36.3 Merchandise Categories
**Tab:** `merchandise`

**Goal:** Organize shop products.

**Features:**
- Add/edit/delete categories
- Nested categories (parent/child)
- Display order

**Testing Focus:**
- Categories save
- Nesting works properly
- Order displays correctly in shop

#### 36.4 Teams
**Tab:** `teams`

**Goal:** Manage team information.

**Features:**
- Create/edit teams
- Assign head coach
- Assign assistant coach
- Team season assignments
- Active/inactive status

**Testing Focus:**
- Team creation saves
- Coach assignments work
- Season linking functions
- Inactive teams hidden from selection

#### 36.5 Locations
**Tab:** `locations`

**Goal:** Manage training facility locations.

**Features:**
- Add/edit/delete locations
- Address with map integration
- Contact information
- Facility details (ice surfaces, capacity)
- Hours of operation

**Testing Focus:**
- Location saves with address
- Map integration displays
- Session booking shows locations correctly

#### 36.6 Skill Levels
**Tab:** `skill_levels`

**Goal:** Define skill level categories.

**Features:**
- Add/edit skill levels
- Display order
- Description

**Testing Focus:**
- Skill levels save
- Order displays in dropdowns correctly

#### 36.7 Seasons
**Tab:** `seasons`

**Goal:** Manage training seasons.

**Features:**
- Create seasons
- Start/end dates
- Active status
- Team assignments

**Testing Focus:**
- Seasons create with dates
- Active season enforced
- Teams link to seasons

#### 36.8 Age Groups
**Tab:** `age_groups`

**Goal:** Define age categories.

**Features:**
- Add/edit age groups
- Age ranges
- Display order

**Testing Focus:**
- Age groups save
- Ranges validated (no overlaps)
- Display in registration forms

---

### 37. Eval Framework
**Page:** `?page=eval_framework`  
**Icon:** Clipboard Check  
**File:** `views/admin_eval_framework.php`

**Purpose:** Configure evaluation templates and criteria.

**Features:**
- Create evaluation templates
- Define rating scales
- Add evaluation categories
- Skill-specific criteria
- Weighting of categories
- Template versioning
- Assign to session types
- Preview template

**Testing Focus:**
- Templates save completely
- Rating scales apply correctly
- Categories organize properly
- Weighting calculations work
- Versioning tracks changes
- Assigned templates appear in evaluations
- Preview displays accurately

---

### 38. System Notification
**Page:** `?page=system_notification`  
**Icon:** Bell  
**File:** `views/admin_notifications.php`

**Purpose:** Send system-wide announcements and notifications.

**Features:**
- Create announcement
- Target audience selection:
  - All users
  - Specific role(s)
  - Specific users
- Priority level (info, warning, urgent)
- Scheduled delivery
- In-app notification
- Email notification
- SMS notification (if enabled)
- Notification history
- Read receipts
- Dismiss/archive

**Testing Focus:**
- Announcement creation saves
- Audience targeting works correctly
- Priority displays appropriately
- Scheduled delivery sends at correct time
- In-app notifications appear
- Emails send to correct users
- SMS sends (if configured)
- Read receipts track properly

---

### 39. Security
**Page:** `?page=admin_security`  
**Icon:** Shield Halved  
**File:** `views/admin_security.php`

**Purpose:** Configure system security settings and view security logs.

**Features:**
- Password policy configuration
- Two-factor authentication (2FA) settings
- Session timeout settings
- IP whitelist/blacklist
- Failed login tracking
- Security audit log
- Suspicious activity alerts
- API key management
- CORS settings
- Rate limiting

**Testing Focus:**
- Password policy enforced on user accounts
- 2FA can be enabled/required
- Session timeout logs users out
- IP restrictions block/allow correctly
- Failed logins tracked and locked out
- Audit log records all security events
- Alerts trigger on suspicious activity
- API keys generate/revoke correctly

---

### 40. System Tools
**Page:** `?page=system_tools`  
**Icon:** Screwdriver Wrench  
**File:** `views/admin_system_tools.php` (parent page with tabs)

**Purpose:** System configuration, maintenance, and database management.

**Tabs:**

#### 40.1 Settings
**Tab:** `settings`

**Goal:** Configure core system settings.

**Features:**
- Site title and branding
- Contact information
- Email settings (SMTP)
- SMS settings
- Payment gateway configuration (Stripe)
- Google Maps API key
- Timezone
- Currency
- Language
- Maintenance mode toggle
- Session duration
- Notification settings
- Province/tax settings
- Mileage rates

**Testing Focus:**
- Settings save and persist
- Email settings test button works
- SMTP configuration connects
- Stripe test mode functions
- Google Maps key validates
- Timezone affects date displays
- Maintenance mode blocks non-admin access
- Tax calculations use correct rates

#### 40.2 Theme
**Tab:** `theme`

**Goal:** Customize application appearance.

**Features:**
- Color scheme settings
- Logo upload
- Favicon settings
- Center ice logo (for drill designer)
- Custom CSS
- Font selection
- Layout options

**Testing Focus:**
- Color changes apply site-wide
- Logo uploads and displays
- Favicon changes in browser tab
- Center ice logo appears in drill designer
- Custom CSS applies
- Changes preview before saving

#### 40.3 Database Tools
**Tab:** `database`

**Goal:** Database backup, restore, and maintenance.

**Features:**
- Manual backup trigger (Quick Backup to local storage)
- Backup to File — downloads a backup file directly to the user's computer
- Force to Nextcloud — forces an immediate backup upload to Nextcloud cloud storage
- Scheduled backups configuration (cron-based with retention policies)
- Backup history/downloads
- Database restore from uploaded `.sql` or `.sql.gz` files (wizard-style interface)
- Database import for full application recovery
- PHP-based backup fallback when mysqldump is unavailable
- Optimization tools (repair, optimize, analyze tables)
- Table integrity checks and foreign key validation
- Storage usage report
- Export data (SQL, CSV)

**Testing Focus:**
- **Backup to File** button creates and downloads a backup file to the user's computer
- **Force to Nextcloud** button uploads backup to configured Nextcloud folder
- Manual backup creates file without error 500
- Scheduled backups run automatically via cron
- Backup files downloadable from history
- Restore wizard uploads, validates, and restores database correctly (TEST WITH CAUTION)
- Database import restores the application from a backup file
- PHP fallback backup works when mysqldump command is not available
- Gzip compression works (both PHP gzopen and command-line gzip)
- Optimization runs without errors
- Table repair fixes issues
- Storage report accurate
- Exports complete and downloadable
- Optimization runs without errors
- Table repair fixes issues
- Storage report accurate
- Exports complete and downloadable

#### 40.4 System Check
**Tab:** (sometimes integrated in system_tools)

**Goal:** Verify system health and requirements.

**Features:**
- PHP version check
- Required extensions check
- Database connection test
- File permissions check
- Disk space check
- Email delivery test
- API connectivity tests
- Performance metrics

**Testing Focus:**
- All checks run and report status
- Failed checks provide fix instructions
- Email test sends successfully
- API tests connect
- Metrics display current values

---

### 41. Marketing
**Page:** `?page=marketing`  
**Icon:** Bullhorn  
**File:** `views/admin_business_cards.php`

**Purpose:** Marketing tools including business cards and partnerships.

**Features:**
- Business card designer
- Partner/sponsor management
- Discount campaigns
- Email marketing templates
- Social media integration
- Referral tracking

**Testing Focus:**
- Business card designer generates cards
- Partner information saves
- Discount campaigns activate
- Email templates send
- Referral tracking works

---

## Profile & Settings

These features are available to all users in the sidebar footer.

### 42. Profile Settings
**Page:** `?page=profile`  
**Icon:** User Gear  
**File:** `views/profile.php`

**Purpose:** User's personal account settings.

**Features:**
- View/edit personal information:
  - Name
  - Email
  - Phone
  - Address
  - Emergency contacts
- Change password
- Profile photo upload
- Notification preferences
- Privacy settings
- Connected accounts
- View login history

**Testing Focus:**
- Information updates save
- Password change works and validates
- Photo upload/crop functions
- Notification preferences apply
- Privacy settings respected
- Login history accurate

---

## Additional Supporting Pages

### Session Detail
**Page:** `?page=session_detail&id=X`  
**File:** `views/session_detail.php`

**Purpose:** Detailed view of a specific session.

**Features:**
- Session information (date, time, location)
- Roster of participants
- Coach information
- Session plan/drills
- Evaluation summaries
- Attendance tracking
- Session notes
- Videos from session
- Related sessions

**Testing Focus:**
- All session details display
- Roster lists all participants
- Coach info correct
- Session plan displays drills in order
- Evaluations link correctly
- Attendance marks save
- Notes save properly
- Videos from session accessible

---

## Key Testing Scenarios

This section provides important test scenarios that span multiple features.

### 1. Role-Based Access Control Testing

**Objective:** Verify users can only access features appropriate to their role.

**Test Cases:**
- Admin attempts to access all pages → Should succeed
- Coach attempts to access HR section → Should be denied/hidden
- Athlete attempts to access Administration → Should be denied
- Front Desk Staff attempts to access anything except POS → Should be denied
- Health Coach attempts to access Accounting → Should be denied
- Parent attempts to access Coaches Corner → Should be denied

**Expected Results:**
- Navigation menu only shows accessible items
- Direct URL access to restricted pages redirects or shows error
- API endpoints reject unauthorized requests

---

### 2. Data Isolation Testing

**Objective:** Ensure users can only view/edit data they have permission to access.

**Test Cases:**
- Athlete A attempts to view Athlete B's stats → Should be denied
- Coach attempts to view athletes not in their roster → Depends on permissions
- Parent attempts to view children not linked to their account → Should be denied
- Team Coach accesses only their team's roster → Should succeed

**Expected Results:**
- Data filtered by user permissions
- Unauthorized access attempts logged
- Error messages don't reveal restricted data existence

---

### 3. Tab State Persistence

**Objective:** Verify tab selections persist across navigation.

**Test Cases:**
- User selects "Packages" tab on Products page
- User navigates away and returns
- Expected: "Packages" tab still selected (if using URL parameters)

**Test All Tabbed Pages:**
- Performance Stats (Goals/Statistics)
- Sessions (Upcoming/Booking)
- Video (Drill Review/Coach Review/Record)
- Health (Strength & Conditioning/Nutrition)
- Drills (Library/Create/Import)
- Practice Plans (Library/Create/Import)
- Finance Dashboard (Overview/Billing/POS/Shop)
- Products (Sessions/Packages/Discounts/Merchandise)
- Categories (Skills/Drills/Merchandise/Teams/Locations/Skill Levels/Seasons/Age Groups)
- System Tools (Settings/Theme/Database)

---

### 4. Booking and Payment Workflow

**Objective:** Test complete booking and payment process.

**Steps:**
1. User browses available sessions
2. User selects session and clicks "Book"
3. System checks availability and package credits
4. User selects payment method (package credit, purchase, or credit card)
5. Payment processes
6. Booking confirms
7. Confirmation email sends
8. Session appears in "Upcoming Sessions"
9. Roster updates to include user

**Validation:**
- Each step completes without errors
- Session capacity decrements
- Payment records in transaction history
- Notifications sent appropriately
- Data consistent across all views

---

### 5. Evaluation Workflow (Coach to Athlete)

**Objective:** Test evaluation creation and athlete visibility.

**Steps:**
1. Coach navigates to Session Evaluations
2. Coach selects session to evaluate
3. Coach fills evaluation form for athlete
4. Coach submits evaluation
5. Athlete logs in and views Performance Stats
6. Athlete sees new evaluation data

**Validation:**
- Evaluation saves completely
- All ratings/comments preserved
- Athlete sees evaluation immediately
- Stats calculations update
- Coach can edit evaluation before finalization

---

### 6. Multi-Role User Testing

**Objective:** Verify users with multiple roles have access to all appropriate features.

**Test Case:**
- User assigned roles: Coach + Health Coach
- Expected: User sees both Coaches Corner AND Health Management in navigation
- User can switch between role contexts seamlessly

---

### 7. Persona/Impersonation Testing (Admin)

**Objective:** Test admin ability to view system as different role.

**Steps:**
1. Admin enables persona mode
2. Admin selects "View as Coach"
3. Navigation updates to show Coach view
4. Admin navigates site
5. Admin exits persona mode
6. Admin view restored

**Validation:**
- Persona mode correctly limits access
- All features work in persona mode
- Exit persona returns to admin view
- Persona mode clearly indicated (banner/indicator)

---

### 8. Parent-Child Account Linking

**Objective:** Test parent viewing child athlete data.

**Steps:**
1. Parent logs in
2. Parent sees child selector dropdown
3. Parent selects child
4. System loads child's data (sessions, stats, videos, health)
5. Parent can book sessions for child
6. Parent can view/pay invoices for child

**Validation:**
- Only linked children appear in selector
- All child data displays correctly
- Bookings/payments associate with child
- Parent cannot edit child's data (view-only where appropriate)

---

### 9. Package Credit Application

**Objective:** Test package credits apply correctly to bookings.

**Steps:**
1. User purchases 10-session package
2. User books a session
3. System prompts: Use package credit or pay separately?
4. User selects package credit
5. Package balance decrements to 9
6. Booking confirms without payment

**Validation:**
- Package balance updates correctly
- User cannot use more credits than available
- Expired packages cannot be used
- Package restrictions enforced (session type, skill level, etc.)

---

### 10. Database Backup and Restore (CRITICAL)

**Objective:** Verify all backup, restore, and database tool functionality. **Test in staging only.**

**Steps — Backup to File:**
1. Navigate to Administration → System Tools → Database
2. Click "Backup to File" button
3. Verify a `.sql.gz` file downloads to the browser
4. Verify the file is non-empty and contains valid SQL statements

**Steps — Force Backup to Nextcloud:**
1. Ensure Nextcloud is configured in system settings
2. Click "Force to Nextcloud" button
3. Verify success message appears
4. Verify file appears in Nextcloud under `/Arctic_Wolves/Backups/`

**Steps — Manual Backup (Quick Backup):**
1. Click "Run" on an existing backup job, or trigger a quick manual backup
2. Verify backup completes without error 500
3. Verify backup appears in backup history

**Steps — Database Restore / Import:**
1. Navigate to Administration → System Tools → Database Restore
2. Upload a valid `.sql` or `.sql.gz` backup file
3. Review the validation summary (table count, insert count, file size)
4. Confirm restore
5. Verify all critical tables exist after restore (users, sessions, system_settings)
6. Verify data matches the backup

**Steps — Database Maintenance Tools:**
1. Run "Check Database Integrity" — verify orphaned record scan
2. Run "Repair Tables" — verify all tables repaired
3. Run "Optimize Tables" — verify tables optimized
4. Run "Check Foreign Keys" — verify constraint validation
5. Run "Performance Analysis" — verify large table and missing index reporting

**Validation:**
- Backup to File downloads a valid backup without error 500
- Force to Nextcloud uploads successfully when Nextcloud is configured
- Backup file is complete (not corrupted)
- PHP-based backup fallback works when mysqldump is unavailable
- Restore wizard uploads, validates, and restores correctly
- Database import function can fully recover the application from a backup
- Maintenance tools (integrity check, repair, optimize, foreign keys) all run without errors
- Backup history records are saved with correct status and metadata

**WARNING:** Test database restore ONLY on staging/test environment, never on production.

---

### 11. Financial Calculation Accuracy

**Objective:** Verify all financial calculations are accurate.

**Test Cases:**
- Invoice totals = subtotal + tax
- Package discounts apply correctly
- Refunds deduct from revenue
- Credits apply to balance properly
- Tax rates calculate based on location
- Reports match transaction totals
- Mileage reimbursements calculate per rates
- Payroll calculations (gross, deductions, net)

**Method:**
- Manual calculation verification
- Compare database transactions to reports
- Test edge cases (rounding, large numbers, negatives)

---

### 12. Email and Notification Testing

**Objective:** Ensure all notifications send correctly.

**Test All Notification Triggers:**
- User registration → Welcome email
- Booking confirmation → Email + in-app
- Session reminder → Email (24 hours before)
- Evaluation completed → Athlete notification
- Payment received → Receipt email
- Refund processed → Confirmation email
- Password reset → Reset link email
- System announcements → All targeted users
- Contract reminders → Email before expiration
- Invoice due → Reminder email

**Validation:**
- Emails send to correct recipients
- Content accurate and complete
- Links in emails work
- In-app notifications appear
- Notification preferences respected

---

### 13. File Upload Security

**Objective:** Verify file uploads are secure and functional.

**Test Cases:**
- Upload valid image → Should succeed
- Upload oversized file → Should reject with message
- Upload invalid file type → Should reject
- Upload file with malicious name → Should sanitize
- Download uploaded file → Should deliver correct file
- Delete uploaded file → Should remove from storage

**Test All Upload Features:**
- Profile photos
- Video uploads
- Receipt uploads (expenses)
- Document uploads (HR, onboarding)
- Product images
- Logo uploads

**Validation:**
- Only allowed file types accepted
- File size limits enforced
- Files stored securely (not publicly accessible unless intended)
- File names sanitized
- Malware scanning (if implemented)

---

### 14. Data Export Functionality

**Objective:** Test all export features produce correct files.

**Test All Export Features:**
- User list export (CSV/Excel)
- Financial reports (PDF/Excel)
- Athlete roster (PDF/Excel)
- Transaction history (CSV)
- Timesheet export (CSV)
- Schedule export (PDF/iCal)
- **Drill library export (JSON)** — via Drills → Export / Import All tab
- **Practice plans export (JSON)** — via Practice Plans → Export / Import All tab
- **Database backup export (SQL.GZ)** — via System Tools → Database → Backup to File

**Validation:**
- Files download successfully
- File format opens correctly
- All data included
- Formatting preserved
- Filters apply to export
- Large datasets export completely

---

### 15. Search and Filter Functionality

**Objective:** Verify search and filters work across all pages.

**Test On All Searchable Pages:**
- User management
- Session list
- Drill library
- Practice library
- Transaction history
- Athlete roster
- Product catalog

**Test Cases:**
- Search by name/keyword
- Filter by date range
- Filter by category/type
- Multi-filter combination
- Clear filters
- Empty search results
- Special characters in search

**Validation:**
- Results match search criteria
- Filters narrow results correctly
- Multiple filters work together (AND logic)
- Clear filters restores full list
- Search is case-insensitive
- No errors on empty results

---

### 16. Mobile Responsiveness (if applicable)

**Objective:** Verify application works on mobile devices.

**Test Cases:**
- Navigation menu (hamburger menu)
- Forms and inputs (touch-friendly)
- Tables (horizontal scroll)
- Buttons (touch targets)
- File uploads (camera access)
- Date pickers (mobile-friendly)
- Video playback
- Calendar views

**Devices to Test:**
- iOS (iPhone/iPad)
- Android (phone/tablet)
- Various screen sizes

---

### 17. Session Timeout and Security

**Objective:** Verify session security features.

**Test Cases:**
- User idle beyond timeout → Logout automatically
- User changes password → All other sessions logout
- User logs out → Session invalidates
- Concurrent sessions (if allowed) → Both work independently
- Session hijacking prevention → Rejects invalid tokens

---

### 18. Audit Trail Verification

**Objective:** Ensure all important actions are logged.

**Test That Audit Log Captures:**
- User login/logout
- Failed login attempts
- User creation/deletion
- Role changes
- Data modifications (who, what, when)
- Permission changes
- Payment transactions
- Refunds
- Database operations

**Validation:**
- All events captured
- Log entries complete (timestamp, user, action, details)
- Logs cannot be edited by non-admin
- Logs retained per policy
- Export audit log works

---

### 19. Performance Testing (Load Times)

**Objective:** Verify pages load within acceptable time.

**Test Cases:**
- Home dashboard load time
- Large data tables (1000+ rows)
- Video streaming latency
- Report generation time
- Search response time
- Database backup time

**Acceptable Thresholds:** (example)
- Pages < 2 seconds
- Search < 1 second
- Reports < 5 seconds

---

### 20. Integration Testing

**Objective:** Verify third-party integrations work correctly.

**Test Integrations:**
- **Stripe Payment Processing**
  - Payment succeeds
  - Payment fails gracefully
  - Refunds process
  - Webhooks update status
  
- **Email Service (SMTP/SendGrid)**
  - Emails send
  - Bounce handling
  - Rate limits respected
  
- **DocuSeal/OpenSign (E-signature)**
  - Document sends for signature
  - Webhook updates on signing
  - Signed document retrieval
  
- **Google Maps (Locations)**
  - Map displays correctly
  - Geocoding works
  
- **IHS Drill Import**
  - Authentication works
  - Drill search returns results
  - Import saves correctly

**Validation:**
- All integrations authenticate successfully
- Data exchanges correctly
- Errors handled gracefully
- Webhooks process in real-time
- API rate limits not exceeded

---

## Document Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial comprehensive testing documentation created |
| 1.1 | February 2026 | Added drill/practice plan export/import all tabs (11.4, 12.4), updated database backup/restore testing (40.3, scenario 10), added backup-to-file and force-to-Nextcloud features, added database import for recovery, updated data export section (14) |

---

## Testing Sign-Off

Use this section to track testing completion:

| Section | Tested By | Date | Status | Notes |
|---------|-----------|------|--------|-------|
| User Roles & Access |  |  |  |  |
| Main Menu |  |  |  |  |
| Team Section |  |  |  |  |
| Coaches Corner |  |  |  |  |
| Health Management |  |  |  |  |
| Accounting & Reports |  |  |  |  |
| Point of Sale |  |  |  |  |
| HR Section |  |  |  |  |
| Administration |  |  |  |  |
| Integration Testing |  |  |  |  |
| Security Testing |  |  |  |  |
| Performance Testing |  |  |  |  |

---

**End of Testing Documentation**

For questions or clarifications about any feature, please refer to the codebase or contact the development team.
