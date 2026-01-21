# Goal-Based Evaluation Platform Testing Checklist

## Pre-Testing Setup

- [ ] Database tables created successfully
  ```bash
  mysql -u[user] -p [database] < deployment/sql/goal_evaluations_schema.sql
  ```
- [ ] Upload directory created and writable
  ```bash
  mkdir -p uploads/eval_media
  chmod 755 uploads/eval_media
  ```
- [ ] Navigation menu item added for evaluations_goals page
- [ ] Test users available:
  - [ ] At least one coach/admin user
  - [ ] At least two athlete users

## Database Verification

- [ ] goal_evaluations table exists
- [ ] goal_eval_steps table exists
- [ ] goal_eval_progress table exists
- [ ] goal_eval_approvals table exists
- [ ] All foreign keys properly set up
- [ ] All indexes created

## Coach User Testing

### Evaluation Creation
- [ ] Navigate to evaluations page as coach
- [ ] See "Create Evaluation" button
- [ ] Athlete dropdown selector visible and populated
- [ ] Click "Create Evaluation" button
- [ ] Modal opens with form
- [ ] Fill in:
  - [ ] Title: "Test Skating Skills Evaluation"
  - [ ] Description: "Comprehensive skating evaluation"
  - [ ] Status: Active
  - [ ] Enable public sharing checkbox
- [ ] Submit form
- [ ] Success message displayed
- [ ] Evaluation appears in grid
- [ ] Card shows correct title, status, and progress (0%)

### Step Management
- [ ] Click on evaluation card
- [ ] Detail modal opens
- [ ] Click "Add Step" button
- [ ] Add step form appears
- [ ] Fill in step 1:
  - [ ] Title: "Forward Skating Technique"
  - [ ] Description: "Demonstrate proper forward skating form"
  - [ ] Check "Requires approval"
- [ ] Submit step
- [ ] Step appears in list
- [ ] Add step 2 (without approval requirement):
  - [ ] Title: "Backward Skating"
  - [ ] Description: "Show backward skating ability"
  - [ ] Leave "Requires approval" unchecked
- [ ] Submit step
- [ ] Both steps visible

### Coach Check-Off (No Approval Needed)
- [ ] Click checkbox for step 2 (no approval required)
- [ ] Step immediately marked as completed
- [ ] Step shows as approved
- [ ] No approval workflow triggered
- [ ] Progress bar updates (50%)
- [ ] Uncheck step 2
- [ ] Step unchecked successfully
- [ ] Progress updates (0%)

### Share Link Generation
- [ ] Scroll to share section
- [ ] Click "Generate Share Link"
- [ ] Share link appears
- [ ] Link format: /public_eval.php?token=XXXXX
- [ ] Token is 32 characters
- [ ] Click "Copy" button
- [ ] Success message shown
- [ ] Click "Revoke" button
- [ ] Confirm revocation
- [ ] Share link removed
- [ ] is_public flag set to 0

### Athlete Switching
- [ ] Change athlete in dropdown selector
- [ ] Page reloads with new athlete
- [ ] Correct athlete name displayed
- [ ] Create evaluation for second athlete
- [ ] Switch back to first athlete
- [ ] Correct evaluations shown for each athlete

## Athlete User Testing

### View Evaluations
- [ ] Login as athlete
- [ ] Navigate to evaluations page
- [ ] See evaluations created for this athlete
- [ ] No "Create Evaluation" button visible
- [ ] No athlete selector visible
- [ ] Click evaluation card
- [ ] Detail view opens
- [ ] No "Add Step" button visible
- [ ] Cannot edit evaluation details

### Complete Steps (Requiring Approval)
- [ ] Click checkbox for step 1 (needs approval)
- [ ] Step marked as completed
- [ ] Status shows "⏳ Pending Approval"
- [ ] Yellow/warning indicator visible
- [ ] Cannot uncheck step
- [ ] Approval request created in database
- [ ] Coach receives notification (check notifications table)

### Attempt Unauthorized Actions
- [ ] Try to add steps (should fail)
- [ ] Try to edit evaluation (should fail)
- [ ] Try to generate share links (should fail)
- [ ] Try to approve own steps (should fail)

## Approval Workflow Testing

### Coach Approval Process
- [ ] Login as coach
- [ ] Open evaluation with pending approval
- [ ] Step 1 shows "⏳ Pending Approval"
- [ ] "Approve" and "Reject" buttons visible
- [ ] Click "Approve" button
- [ ] Confirmation dialog appears
- [ ] Confirm approval
- [ ] Step status changes to "✓ Approved"
- [ ] Status color changes to green
- [ ] Athlete receives notification
- [ ] Progress bar updates (100%)
- [ ] Approval record updated in database

### Coach Rejection Process
- [ ] Athlete checks another step requiring approval
- [ ] Coach sees pending approval
- [ ] Click "Reject" button
- [ ] Prompt for rejection reason
- [ ] Enter reason: "Needs improvement on form"
- [ ] Submit rejection
- [ ] Step unchecked automatically
- [ ] Athlete receives rejection notification
- [ ] Rejection note visible to athlete
- [ ] Progress bar updates

### Multiple Pending Approvals
- [ ] Athlete checks multiple steps
- [ ] All show pending status
- [ ] Coach can see all pending
- [ ] Coach approves some, rejects others
- [ ] Each handled independently
- [ ] Correct notifications sent

## Media Upload Testing

### Image Upload
- [ ] Click "📎 Media" button on step
- [ ] Media upload form appears
- [ ] Select image file (JPG)
- [ ] Add progress note: "Initial attempt"
- [ ] Submit upload
- [ ] File uploaded successfully
- [ ] Preview shown
- [ ] Media listed under step
- [ ] File stored in uploads/eval_media/
- [ ] Database record created

### Video Upload
- [ ] Upload video file (MP4)
- [ ] Add progress note: "Second attempt"
- [ ] Submit upload
- [ ] Video uploaded successfully
- [ ] Video thumbnail/preview shown
- [ ] Can play video inline
- [ ] File stored correctly

### Upload Validation
- [ ] Try uploading invalid file type (.exe)
- [ ] Error message shown
- [ ] Upload rejected
- [ ] Try uploading large file (>50MB if limit set)
- [ ] File size validation works
- [ ] Error message clear

## Security Testing

### CSRF Protection
- [ ] Intercept form submission
- [ ] Remove csrf_token field
- [ ] Submit form
- [ ] Request rejected with 403
- [ ] Error message: "CSRF token validation failed"

### SQL Injection Prevention
- [ ] Try SQL injection in title field: `'; DROP TABLE users; --`
- [ ] Input sanitized properly
- [ ] No SQL error
- [ ] No data corruption

### XSS Prevention
- [ ] Try XSS in title: `<script>alert('XSS')</script>`
- [ ] Input escaped properly
- [ ] Script not executed
- [ ] Displays as text

### Permission Bypassing
- [ ] As athlete, try POST to create_evaluation
- [ ] Request rejected
- [ ] Error: "Only coaches can create evaluations"
- [ ] Try accessing other athlete's evaluations
- [ ] Access denied
- [ ] Only own evaluations visible

### File Upload Security
- [ ] Try uploading PHP file disguised as image
- [ ] File type validation catches it
- [ ] Upload rejected
- [ ] No code execution possible

## UI/UX Testing

### Responsive Design
- [ ] View on desktop (1920px)
- [ ] Layout looks good
- [ ] View on tablet (768px)
- [ ] Cards stack properly
- [ ] View on mobile (375px)
- [ ] All elements accessible
- [ ] Modals fit screen

### Visual Feedback
- [ ] Hover effects on cards
- [ ] Button hover states
- [ ] Loading indicators during AJAX
- [ ] Success/error messages
- [ ] Progress bar animations
- [ ] Color coding (purple primary, green success, red danger)

### Accessibility
- [ ] Tab navigation works
- [ ] Form fields have labels
- [ ] Error messages descriptive
- [ ] Keyboard shortcuts functional
- [ ] ESC key closes modals

## Performance Testing

### Large Datasets
- [ ] Create 50+ evaluations
- [ ] Page loads in reasonable time (<2s)
- [ ] Pagination needed?
- [ ] Create evaluation with 50+ steps
- [ ] Detail view loads properly
- [ ] Scrolling smooth

### Concurrent Users
- [ ] Coach and athlete logged in simultaneously
- [ ] Coach creates evaluation
- [ ] Athlete sees it immediately (after refresh)
- [ ] Athlete checks step
- [ ] Coach sees approval request
- [ ] No race conditions

## Error Handling

### Network Errors
- [ ] Disconnect network
- [ ] Try submitting form
- [ ] Graceful error message
- [ ] Reconnect and retry
- [ ] Form submission works

### Database Errors
- [ ] Simulate database connection loss
- [ ] Error message shown
- [ ] User notified appropriately
- [ ] No data corruption

### Missing Data
- [ ] Try viewing deleted evaluation
- [ ] Appropriate error shown
- [ ] Try accessing invalid evaluation_id
- [ ] 404 or error message
- [ ] No PHP errors exposed

## Integration Testing

### Notification System
- [ ] Approval request triggers notification
- [ ] Notification appears in notifications table
- [ ] Email sent (if configured)
- [ ] Notification marked as read when viewed

### User Management
- [ ] Delete athlete user
- [ ] Evaluations cascade delete properly
- [ ] Or athlete_id set to NULL based on schema
- [ ] No orphaned records

### Dashboard Integration
- [ ] Link to evaluations from dashboard
- [ ] Navigation menu shows correct item
- [ ] Breadcrumbs work
- [ ] User can navigate back

## Cleanup Testing

### Data Deletion
- [ ] Delete evaluation with steps
- [ ] All related steps deleted
- [ ] All progress records deleted
- [ ] All approvals deleted
- [ ] Cascade deletes work
- [ ] No orphaned media files (manual cleanup may be needed)

## Documentation Verification

- [ ] EVALUATION_PLATFORM_README.md complete
- [ ] All features documented
- [ ] API endpoints listed
- [ ] Installation steps clear
- [ ] Troubleshooting section helpful

## Production Readiness

- [ ] All tests pass
- [ ] No console errors in browser
- [ ] No PHP warnings/errors in logs
- [ ] Database indexes improve performance
- [ ] Upload directory has correct permissions
- [ ] CSRF protection enabled
- [ ] SQL injection protected
- [ ] XSS vulnerabilities fixed
- [ ] Code reviewed
- [ ] Security scanning complete
- [ ] Backup plan in place

## Known Issues / Future Enhancements

Document any issues found:
- [ ] Issue 1: _______________________
- [ ] Issue 2: _______________________

Planned enhancements:
- [ ] Public share link page (public_eval.php)
- [ ] Email notifications
- [ ] Step templates
- [ ] Bulk operations
- [ ] Export to PDF

## Sign-Off

- [ ] Tested by: _____________ Date: _______
- [ ] Reviewed by: ___________ Date: _______
- [ ] Approved for production: Yes / No
