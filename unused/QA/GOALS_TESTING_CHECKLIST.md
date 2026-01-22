# Goals System - Installation & Testing Checklist

## Installation Steps

### 1. Database Setup
- [ ] Run the SQL script to create tables:
  ```bash
  mysql -u username -p database_name < deployment/goals_tables.sql
  ```
  OR use the setup script:
  ```bash
  ./deployment/setup_goals.sh
  ```

- [ ] Verify tables were created:
  ```sql
  SHOW TABLES LIKE 'goal%';
  -- Should show: goals, goal_steps, goal_progress, goal_history
  ```

### 2. File Verification
- [ ] Confirm all files are present:
  - `views/goals.php` ✓
  - `process_goals.php` ✓
  - `deployment/goals_tables.sql` ✓
  - `deployment/setup_goals.sh` ✓
  - `GOALS_SYSTEM_README.md` ✓
  - `GOALS_FEATURE_GUIDE.md` ✓

- [ ] Check dashboard routing is updated:
  ```bash
  grep "'goals'" dashboard.php
  # Should show: 'goals' => 'views/goals.php'
  ```

### 3. Permissions Check
- [ ] Verify PHP file permissions:
  ```bash
  ls -la views/goals.php process_goals.php
  # Should be readable (644 or similar)
  ```

- [ ] Verify script is executable:
  ```bash
  ls -la deployment/setup_goals.sh
  # Should be executable (755 or x flag)
  ```

## Testing Checklist

### As Coach

#### 1. Access Goals Page
- [ ] Login as coach user
- [ ] Navigate to `dashboard.php?page=goals`
- [ ] Page loads without errors
- [ ] See "Goals & Progress" header
- [ ] See "Create Goal" button

#### 2. Create a Goal
- [ ] Click "Create Goal" button
- [ ] Modal opens with form
- [ ] Fill in goal details:
  - [ ] Title: "Improve Wrist Shot Accuracy"
  - [ ] Description: "Increase accuracy from 70% to 85%"
  - [ ] Category: "Shooting"
  - [ ] Tags: "shooting, accuracy, technique"
  - [ ] Target Date: (future date)
- [ ] Add 3 steps:
  - [ ] "Practice wrist shots 100 times daily"
  - [ ] "Work on follow-through technique"
  - [ ] "Track accuracy percentages"
- [ ] Click "Save Goal"
- [ ] Redirected to goals page
- [ ] New goal appears in the list
- [ ] Progress shows 0% (0/3 steps)

#### 3. View Goal Details
- [ ] Click "View" on the goal card
- [ ] Detail modal opens
- [ ] See all goal information
- [ ] See all 3 steps with checkboxes
- [ ] Progress bar shows 0%
- [ ] No progress history yet

#### 4. Complete a Step
- [ ] Check the first step checkbox
- [ ] Step marked as completed (green)
- [ ] Progress bar updates to ~33%
- [ ] Modal refreshes automatically
- [ ] Main page shows updated percentage after refresh

#### 5. Add Progress Note
- [ ] In detail modal, click "Add Progress Note"
- [ ] Progress note modal opens
- [ ] Enter note: "Completed 100 wrist shots today. Accuracy at 72%"
- [ ] Click "Save Progress"
- [ ] Note appears in Progress History
- [ ] Shows coach name and timestamp

#### 6. Complete More Steps
- [ ] Check second step
- [ ] Progress updates to ~67%
- [ ] Check third step
- [ ] Progress updates to 100%

#### 7. Complete Goal
- [ ] Close detail modal
- [ ] Click "Complete" on goal card
- [ ] Confirm completion
- [ ] Goal status changes to "Completed"
- [ ] Card has green border/styling
- [ ] Completion date is set

#### 8. Filter Goals
- [ ] Change status filter to "Completed"
- [ ] See completed goal
- [ ] Change to "Active"
- [ ] Completed goal not shown
- [ ] Change to "All"
- [ ] See all goals

#### 9. Edit Goal
- [ ] Click "Edit" on a goal
- [ ] Modal opens with existing data
- [ ] Modify title
- [ ] Add a new step
- [ ] Remove a step
- [ ] Click "Save Goal"
- [ ] Changes are saved
- [ ] Updated data displays correctly

#### 10. Archive Goal
- [ ] Create a test goal
- [ ] Archive it (via delete action)
- [ ] Goal status becomes "Archived"
- [ ] Filter to "Archived" to see it
- [ ] Active filter doesn't show it

#### 11. Switch Athletes
- [ ] Use athlete selector dropdown
- [ ] Select different athlete
- [ ] Page reloads with new athlete's goals
- [ ] Can create goal for new athlete
- [ ] Athlete name displays correctly

### As Athlete

#### 1. Access Goals Page
- [ ] Login as athlete user
- [ ] Navigate to `dashboard.php?page=goals`
- [ ] Page loads without errors
- [ ] See own goals
- [ ] No "Create Goal" button visible
- [ ] No athlete selector visible

#### 2. View Goal Details
- [ ] Click "View" on a goal
- [ ] Detail modal opens
- [ ] See goal information
- [ ] See steps with icons (not checkboxes)
- [ ] Can see completed steps marked
- [ ] See progress history
- [ ] No "Add Progress Note" button

#### 3. No Edit Capability
- [ ] No "Edit" button on cards
- [ ] No "Complete" button on cards
- [ ] Cannot modify goals
- [ ] Cannot complete steps
- [ ] Cannot add progress notes

#### 4. Filter Works
- [ ] Can filter by status
- [ ] Can filter by category
- [ ] Can filter by tags
- [ ] Filters apply correctly

### Security Testing

#### 1. CSRF Protection
- [ ] Inspect form HTML, see `csrf_token` hidden input
- [ ] Try to submit form without token → Fails
- [ ] Try to submit with invalid token → Fails
- [ ] Normal submission with valid token → Works

#### 2. Permission Checks
- [ ] As athlete, try to POST to create_goal → Denied
- [ ] As athlete, try to POST to complete_step → Denied
- [ ] As coach, can perform all actions → Works
- [ ] Logged out user cannot access page → Redirected

#### 3. SQL Injection Prevention
- [ ] Try SQL in title: `'; DROP TABLE goals; --`
- [ ] Data is escaped, no SQL executed
- [ ] Try SQL in tags, category, description
- [ ] All inputs are safely handled

#### 4. XSS Prevention
- [ ] Try JavaScript in title: `<script>alert('XSS')</script>`
- [ ] Script is escaped/sanitized
- [ ] Try in description, tags, category
- [ ] No scripts execute

#### 5. Validation
- [ ] Try to create goal with empty title → Error
- [ ] Try to create goal with negative percentage → Rejected by DB
- [ ] Try to create goal with percentage > 100 → Rejected by DB
- [ ] Try to complete non-existent step → Error

### Database Verification

#### 1. Check Table Structure
```sql
DESCRIBE goals;
DESCRIBE goal_steps;
DESCRIBE goal_progress;
DESCRIBE goal_history;
```
- [ ] All columns present
- [ ] Foreign keys set up
- [ ] Indexes created

#### 2. Check Data Integrity
```sql
-- After creating a goal
SELECT * FROM goals WHERE athlete_id = [athlete_id];
SELECT * FROM goal_steps WHERE goal_id = [goal_id];
SELECT * FROM goal_history WHERE goal_id = [goal_id];
```
- [ ] Goal record created
- [ ] Steps created with correct order
- [ ] History entry logged

#### 3. Check Cascade Deletes
```sql
-- Archive a goal (sets status)
SELECT status FROM goals WHERE id = [goal_id];
```
- [ ] Status changed to 'archived'
- [ ] Related records still exist

#### 4. Check Calculations
```sql
-- After completing steps
SELECT completion_percentage FROM goals WHERE id = [goal_id];
```
- [ ] Percentage calculated correctly
- [ ] Matches (completed_steps / total_steps) * 100

### Performance Testing

#### 1. Load Testing
- [ ] Create 50+ goals for one athlete
- [ ] Page loads in < 2 seconds
- [ ] Filtering is responsive
- [ ] No JavaScript errors

#### 2. Concurrent Users
- [ ] Multiple coaches create goals simultaneously
- [ ] No data corruption
- [ ] All goals saved correctly

#### 3. Large Data
- [ ] Create goal with 20+ steps
- [ ] Detail modal loads correctly
- [ ] Checkboxes all work
- [ ] Progress calculates correctly

### Browser Compatibility

#### Desktop Browsers
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)

#### Mobile Browsers
- [ ] Chrome Mobile
- [ ] Safari iOS
- [ ] Samsung Internet

#### Responsive Design
- [ ] Desktop (1920x1080): 3-4 cards per row
- [ ] Tablet (768x1024): 2 cards per row
- [ ] Mobile (375x667): 1 card per column
- [ ] Modals are scrollable on small screens

## Error Handling

### 1. Network Errors
- [ ] Disconnect network during AJAX call
- [ ] User sees error message
- [ ] Page doesn't break
- [ ] Can retry action

### 2. Database Errors
- [ ] Simulate DB connection loss
- [ ] Error caught and logged
- [ ] User sees friendly message
- [ ] No sensitive data exposed

### 3. Invalid Input
- [ ] Empty required fields → Validation message
- [ ] Invalid dates → Error
- [ ] Invalid percentages → Error
- [ ] Long text inputs → Handled gracefully

## Post-Installation

### 1. Documentation
- [ ] Review GOALS_SYSTEM_README.md
- [ ] Review GOALS_FEATURE_GUIDE.md
- [ ] Understand all features
- [ ] Know troubleshooting steps

### 2. User Training
- [ ] Train coaches on creating goals
- [ ] Show how to track progress
- [ ] Demonstrate filtering
- [ ] Explain athlete view

### 3. Monitoring
- [ ] Check PHP error logs for issues
- [ ] Monitor database performance
- [ ] Watch for user feedback
- [ ] Track feature usage

## Common Issues & Solutions

### Goals Not Showing
**Issue**: Goals page is blank
**Solutions**:
- Check database tables exist
- Verify user is logged in
- Check PHP error logs
- Ensure athlete_id is valid

### Can't Create Goal
**Issue**: Create button doesn't work
**Solutions**:
- Verify user has coach role
- Check CSRF token is generated
- Inspect browser console for errors
- Check process_goals.php permissions

### Progress Not Updating
**Issue**: Percentage doesn't change
**Solutions**:
- Refresh the page
- Check step completion in database
- Verify recalculateGoalProgress function
- Check for JavaScript errors

### Modal Doesn't Close
**Issue**: Modal stuck open
**Solutions**:
- Press ESC key
- Click outside modal
- Refresh page
- Check JavaScript errors

## Success Criteria

All checkboxes above should be checked ✓

- [ ] All installation steps completed
- [ ] All coach features tested and working
- [ ] All athlete features tested and working
- [ ] All security checks passed
- [ ] Database integrity verified
- [ ] Performance acceptable
- [ ] Cross-browser compatible
- [ ] Error handling works
- [ ] Documentation reviewed

## Sign-Off

**Tested By**: ________________
**Date**: ________________
**Environment**: ________________
**Status**: ☐ Pass  ☐ Fail  ☐ Needs Work
**Notes**: 
_________________________________
_________________________________
_________________________________
