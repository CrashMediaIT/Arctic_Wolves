# Goals System - Feature Guide

## Main Interface

### Goal Cards Display
Each goal is displayed as a card with:
- **Category Badge** - Color-coded category (e.g., Skating, Shooting, Fitness)
- **Goal Title** - Clear, prominent title
- **Description** - Detailed explanation of the goal
- **Tags** - Multiple filterable tags
- **Progress Bar** - Visual representation of completion percentage
- **Step Counter** - Shows "X / Y steps" completed
- **Target Date** - When the goal should be achieved
- **Action Buttons** - View, Edit, Complete

### Filter Bar
Located at the top of the page:
- **Status Filter** - Active, Completed, Archived, All
- **Category Filter** - Dropdown of all categories
- **Tag Filter** - Dropdown of all tags

### Athlete Selector (Coaches Only)
- Dropdown to switch between athletes
- Shows athlete name: "Last Name, First Name"
- Automatically loads goals for selected athlete

## Goal Creation Modal

### Form Fields
1. **Title** (required) - Short, clear goal name
2. **Description** - Detailed explanation
3. **Category** - Free text or predefined (e.g., Skating, Shooting)
4. **Tags** - Comma-separated (e.g., speed, power, technique)
5. **Target Date** - Calendar picker

### Steps Section
- **Add Step Button** - Adds new step input
- **Drag Handle** - Reorder steps by dragging
- **Step Input** - Title for each step
- **Remove Button** - Delete step

### Actions
- **Cancel** - Close without saving
- **Save Goal** - Create or update goal

## Goal Detail Modal

### Header Section
- Goal category, title, description
- Status badge (Active, Completed, Archived)
- Tags display

### Progress Section
- Overall progress bar with percentage
- Large, visual representation

### Steps List
Each step shows:
- **Checkbox** (coaches) or icon (athletes)
- **Step Title** - What needs to be done
- **Completion Status** - Green checkmark if done
- **Completed Date** - When and by whom

### Progress History
Chronological list of progress notes:
- **User Name** - Who added the note
- **Date** - When it was added
- **Note Text** - The progress update
- **Left Border** - Purple accent

### Actions
- **Add Progress Note** (coaches only)
- Auto-refresh after changes

## Progress Note Modal

### Form Fields
1. **Progress Note** (required) - Describe the progress made
2. **Progress Percentage** (optional) - Manual override

### Usage
- Add qualitative updates about goal progress
- Optionally set custom percentage
- Appears in Progress History

## Color Scheme

### Primary Colors
- **Deep Purple**: `#7000a4` - Primary actions, progress bars
- **Dark Background**: `#06080b` - Page background
- **Card Background**: `#0d1117` - Card/modal background
- **Border**: `#1e293b` - Borders and dividers

### Status Colors
- **Active**: Blue `#3b82f6`
- **Completed**: Green `#10b981`
- **Archived**: Gray `#64748b`

### Text Colors
- **Primary**: White `#fff`
- **Secondary**: Muted `#94a3b8`
- **Tertiary**: Subtle `#64748b`

## User Flows

### Coach: Create a Goal
1. Select athlete from dropdown
2. Click "Create Goal" button
3. Fill in goal details
4. Add steps (at least one recommended)
5. Set target date
6. Click "Save Goal"

### Coach: Track Progress
1. Click "View" on goal card
2. Check off completed steps
3. Add progress notes as needed
4. When done, click "Complete" on card

### Coach: Edit a Goal
1. Click "Edit" on goal card
2. Modify any details
3. Add/remove/reorder steps
4. Click "Save Goal"

### Athlete: View Progress
1. View assigned goals on dashboard
2. Click "View" for details
3. See completed steps and progress
4. Read coach's progress notes

## Responsive Design

### Desktop (>1200px)
- 3-4 goals per row
- Full-width modals
- All features visible

### Tablet (768px - 1200px)
- 2 goals per row
- Modals at 90% width
- Compact action buttons

### Mobile (<768px)
- 1 goal per column
- Full-width modals
- Stacked action buttons
- Responsive filters

## Keyboard Shortcuts

### Modals
- **ESC** - Close active modal
- **Enter** - Submit form (when focused)

### Navigation
- **Tab** - Navigate form fields
- **Click outside** - Close modal

## Data Flow

### Creating a Goal
1. User fills form → `process_goals.php`
2. Validates input, creates goal record
3. Creates step records
4. Logs to goal_history
5. Redirects to goals page

### Completing a Step
1. User clicks checkbox → AJAX to `process_goals.php`
2. Updates step record
3. Recalculates goal percentage
4. Logs to goal_history
5. Returns updated percentage
6. UI updates in real-time

### Adding Progress Note
1. User submits note → `process_goals.php`
2. Creates progress record
3. Optionally updates percentage
4. Logs to goal_history
5. Redirects to goals page

## Permissions

### Coaches
- ✅ Create goals
- ✅ Edit goals
- ✅ Delete/archive goals
- ✅ Add/edit steps
- ✅ Complete steps
- ✅ Add progress notes
- ✅ Complete goals
- ✅ View all athlete goals

### Athletes
- ✅ View own goals
- ✅ View step completion
- ✅ View progress history
- ❌ Create/edit goals
- ❌ Complete steps
- ❌ Add progress notes

### Parents
- ❌ Access not implemented yet
- Future: View child's goals (read-only)

## Database Operations

### Automatic Calculations
- **Completion Percentage**: Recalculated on step completion
  ```
  percentage = (completed_steps / total_steps) × 100
  ```

### Cascade Deletes
- Deleting a goal also deletes:
  - All goal steps
  - All progress notes
  - All history entries

### Soft Deletes
- Goals are "archived" not deleted
- Can be filtered to show archived goals
- Preserves historical data

## Best Practices

### Creating Effective Goals
1. **Be Specific**: "Improve wrist shot accuracy to 80%" not "Get better at shooting"
2. **Set Measurable Steps**: Break down into concrete actions
3. **Use Categories**: Group similar goals together
4. **Add Tags**: Make goals easily filterable
5. **Set Realistic Dates**: Give enough time to achieve

### Tracking Progress
1. **Check Steps Regularly**: Update as soon as completed
2. **Add Context in Notes**: Explain what worked/didn't work
3. **Be Consistent**: Regular updates show commitment
4. **Celebrate Milestones**: Acknowledge progress at key points

### Organization Tips
1. **Use Categories Consistently**: Establish standard categories
2. **Tag Strategically**: Use tags for cross-cutting themes
3. **Archive Old Goals**: Keep active list focused
4. **Set Realistic Targets**: Better to extend than miss dates

## Troubleshooting

### Goals Not Showing
- Check athlete selector is set correctly
- Verify status filter (active vs. archived)
- Ensure goals exist for selected athlete

### Can't Create Goal
- Verify coach permissions
- Check CSRF token is present
- Ensure athlete is selected

### Progress Not Updating
- Refresh the page
- Check JavaScript console for errors
- Verify step checkbox is clickable

### Percentage Seems Wrong
- Check total steps count
- Verify completed steps
- Recalculation is automatic

## Security Features

### CSRF Protection
- All forms include CSRF token
- Validated on server side
- Prevents cross-site attacks

### SQL Injection Prevention
- All queries use prepared statements
- No raw SQL with user input
- PDO with bound parameters

### Permission Checks
- Every action checks user role
- Coaches-only functions blocked for athletes
- Can't modify other users' goals

### Audit Trail
- All actions logged to goal_history
- Includes user, timestamp, changes
- JSON format for detailed changes

## Future Enhancements

### Planned Features
- 📎 File attachments on progress notes
- 📧 Email notifications for goal milestones
- 📊 Analytics dashboard for goal trends
- 📱 Mobile app integration
- 👨‍👩‍👧 Parent view of child goals
- 📋 Goal templates library
- 🏆 Achievement badges
- 📈 Visual charts and graphs
- 🔔 Reminder notifications
- 📄 PDF export of goal reports

### Community Requests
Submit feature requests via GitHub Issues with tag `enhancement:goals`
