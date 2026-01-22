# Goal-Based Interactive Evaluation Platform

## Overview
The Goal-Based Interactive Evaluation Platform (Type 1) is a comprehensive system for creating, managing, and tracking athlete evaluations through interactive checklists with approval workflows.

## Features

### Core Functionality
- **Interactive Evaluations**: Create evaluations with customizable checklist steps
- **Quick Athlete Selector**: Coaches can easily switch between athletes
- **Progress Tracking**: Real-time progress visualization with percentage completion
- **Permission-Based Workflow**: Different capabilities for coaches vs. athletes

### Approval Workflow
- **Coach Check-Off**: Coaches can check steps without approval (instant completion)
- **Athlete Check-Off**: Athletes can check steps that require approval from coaches
- **Approval Requests**: Automated approval request system with notifications
- **Approve/Reject**: Coaches can approve or reject athlete-completed steps
- **Visual Indicators**: Clear status display (pending, approved, rejected)

### Media Attachments
- **Image Support**: Upload images (JPG, PNG, GIF)
- **Video Support**: Upload videos (MP4, MOV, AVI)
- **Per-Step Media**: Attach media to individual evaluation steps
- **Progress Notes**: Add notes with media uploads

### Shareable Links
- **Public Sharing**: Generate unique shareable links for evaluations
- **Token-Based Access**: 32-character unique tokens for security
- **Read-Only View**: External viewers can see evaluation without login
- **Revocable Access**: Coaches can revoke public access at any time

## File Structure

### Views
- **views/evaluations_goals.php**: Main evaluation interface
  - List view of all evaluations
  - Create/edit evaluation modal
  - Evaluation detail view with steps
  - Media attachment interface
  - Share link management

### Backend Processing
- **process_eval_goals.php**: Main evaluation operations
  - create_evaluation
  - update_evaluation
  - delete_evaluation
  - add_step
  - update_step
  - check_step
  - add_media
  - generate_share_link
  - revoke_share_link

- **process_eval_goal_approval.php**: Approval workflow
  - request_approval
  - approve_step
  - reject_step
  - cancel_approval_request
  - get_pending_approvals

### Database
- **deployment/sql/goal_evaluations_schema.sql**: Database schema
  - goal_evaluations: Main evaluation records
  - goal_eval_steps: Checklist items
  - goal_eval_progress: Progress tracking and media
  - goal_eval_approvals: Approval workflow records

## Database Schema

### goal_evaluations
- id: Primary key
- athlete_id: Foreign key to users
- created_by: Foreign key to users (coach who created it)
- title: Evaluation title
- description: Detailed description
- share_token: Unique token for public sharing
- is_public: Whether evaluation is publicly shareable
- status: active, completed, or archived
- created_at, updated_at: Timestamps

### goal_eval_steps
- id: Primary key
- goal_eval_id: Foreign key to goal_evaluations
- step_order: Display order
- title: Step title
- description: Step description
- is_completed: Completion status
- completed_at, completed_by: Completion tracking
- needs_approval: Whether step requires coach approval
- is_approved: Approval status
- approved_by, approved_at: Approval tracking
- created_at: Timestamp

### goal_eval_progress
- id: Primary key
- goal_eval_step_id: Foreign key to goal_eval_steps
- user_id: User who added the progress
- progress_note: Text note
- media_url: URL to uploaded media file
- media_type: image or video
- created_at: Timestamp

### goal_eval_approvals
- id: Primary key
- goal_eval_step_id: Foreign key to goal_eval_steps
- requested_by: User who requested approval
- approved_by: Coach who approved/rejected
- status: pending, approved, or rejected
- approval_note: Coach's note on approval/rejection
- created_at, updated_at: Timestamps

## Permission Model

### Coaches (coach, coach_plus, admin)
- Create evaluations for any athlete
- Edit/delete evaluations
- Add/update/delete steps
- Check steps (immediate approval, no workflow)
- Approve/reject athlete check-offs
- Generate/revoke share links
- View all evaluations

### Athletes
- View their own evaluations
- Check steps (triggers approval workflow if needed)
- Add media attachments
- View approval status
- Cannot create or edit evaluations
- Cannot add/edit steps

### External Users (with share token)
- Read-only access via public share link
- No login required
- Can view evaluation details and steps
- Cannot make any changes

## Installation

1. **Create Database Tables**
   ```bash
   mysql -u [user] -p [database] < deployment/sql/goal_evaluations_schema.sql
   ```

2. **Create Upload Directory**
   ```bash
   mkdir -p uploads/eval_media
   chmod 755 uploads/eval_media
   ```

3. **Verify Files**
   - views/evaluations_goals.php
   - process_eval_goals.php
   - process_eval_goal_approval.php

4. **Add to Navigation**
   Add menu item in dashboard navigation for coaches and athletes

## Usage Guide

### For Coaches

#### Creating an Evaluation
1. Navigate to Goal-Based Evaluations
2. Select athlete from dropdown (or use default)
3. Click "Create Evaluation"
4. Fill in title and description
5. Choose status (active/completed/archived)
6. Optionally enable public sharing
7. Save evaluation

#### Adding Steps
1. Open evaluation detail view
2. Click "Add Step"
3. Enter step title and description
4. Check "Requires approval" if athlete needs coach approval
5. Save step

#### Checking Steps
- Click checkbox next to step
- Step is immediately marked as completed and approved
- No approval workflow needed for coach check-offs

#### Managing Approvals
- View pending approvals (yellow indicator)
- Click "Approve" or "Reject" buttons
- Optionally add approval note
- Athlete receives notification

#### Sharing Evaluations
1. Open evaluation detail
2. Click "Generate Share Link"
3. Copy link to share externally
4. Revoke access anytime

### For Athletes

#### Viewing Evaluations
1. Navigate to Goal-Based Evaluations
2. View all evaluations created for you
3. Click card to view details

#### Completing Steps
1. Open evaluation detail
2. Click checkbox for step
3. If step needs approval, request is sent to coach
4. Wait for coach approval
5. If rejected, checkbox is cleared

#### Adding Media
1. Open step detail
2. Click "Media" button
3. Upload image or video
4. Add optional progress note
5. Submit

## API Endpoints

### GET Requests
- `process_eval_goals.php?action=get_evaluation&evaluation_id=X`
- `process_eval_goals.php?action=get_step_media&step_id=X`
- `process_eval_goal_approval.php?action=get_pending_approvals`

### POST Requests
All POST requests require CSRF token

#### Evaluation Management
- action=create_evaluation
- action=update_evaluation
- action=delete_evaluation

#### Step Management
- action=add_step
- action=update_step
- action=check_step

#### Media Management
- action=add_media (with file upload)

#### Sharing
- action=generate_share_link
- action=revoke_share_link

#### Approvals
- action=approve_step
- action=reject_step
- action=request_approval
- action=cancel_approval_request

## Security Features

1. **CSRF Protection**: All POST requests validated
2. **Permission Checks**: Role-based access control
3. **SQL Injection Prevention**: Prepared statements
4. **File Upload Validation**: Type and size restrictions
5. **Secure Tokens**: 32-byte random tokens for sharing
6. **XSS Prevention**: HTML escaping in templates

## Notification Integration

The system creates notification records for:
- Approval requests (to coach)
- Step approved (to athlete)
- Step rejected (to athlete)

Notifications are stored in the `notifications` table and can be sent via email using the mailer system.

## Future Enhancements

Potential improvements:
- Bulk step operations
- Step templates
- Export to PDF
- Email notifications
- Step dependencies
- Due dates and reminders
- Progress charts and analytics
- Mobile app integration
- Comments/discussion on steps
- Drag-and-drop step reordering

## Troubleshooting

### Uploads Not Working
- Check directory permissions: `chmod 755 uploads/eval_media`
- Verify PHP upload settings in php.ini
- Check file size limits

### Share Links Not Working
- Verify unique token generation
- Check is_public flag is set
- Create public_eval.php view (separate ticket)

### Approval Workflow Issues
- Verify needs_approval flag on steps
- Check user roles and permissions
- Review approval request records

## Support

For issues or questions, contact the development team or file a ticket in the project management system.
