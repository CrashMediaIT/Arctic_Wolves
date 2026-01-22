# Goals and Progress Tracking System

## Overview
The Goals and Progress Tracking System allows coaches to create, manage, and track athlete goals with detailed steps and progress monitoring. Athletes can view their goals and progress history.

## Features

### For Coaches
- **Create Goals**: Set up goals for individual athletes with title, description, category, tags, and target date
- **Add Steps**: Break down goals into actionable steps that can be tracked
- **Track Progress**: Mark steps as complete and add progress notes
- **Complete Goals**: Mark goals as fully completed
- **Archive Goals**: Archive old or irrelevant goals
- **Switch Athletes**: Quickly switch between athletes to manage multiple goals
- **Filter & Search**: Filter by status, category, and tags

### For Athletes
- **View Goals**: See all goals assigned to them
- **Track Progress**: View completion percentage and step completion
- **Progress History**: Review all progress notes added by coaches
- **Filter Goals**: Filter by status, category, and tags

## Database Schema

### `goals`
Main table storing goal information:
- `id`: Primary key
- `athlete_id`: ID of the athlete (foreign key to users)
- `created_by`: ID of the coach who created the goal
- `title`: Goal title
- `description`: Detailed description
- `category`: Goal category (e.g., Skating, Shooting, Fitness)
- `tags`: Comma-separated tags
- `target_date`: Target completion date
- `status`: active, completed, or archived
- `completion_percentage`: Auto-calculated based on completed steps
- `created_at`, `updated_at`, `completed_at`: Timestamps

### `goal_steps`
Steps/tasks for each goal:
- `id`: Primary key
- `goal_id`: Foreign key to goals
- `step_order`: Display order
- `title`: Step title
- `description`: Step description
- `is_completed`: Boolean flag
- `completed_at`: When step was completed
- `completed_by`: User who completed the step
- `created_at`: When step was created

### `goal_progress`
Progress notes and updates:
- `id`: Primary key
- `goal_id`: Foreign key to goals
- `user_id`: User who added the progress note
- `progress_note`: The progress note text
- `progress_percentage`: Optional manual percentage override
- `created_at`: When note was added

### `goal_history`
Audit trail of all goal changes:
- `id`: Primary key
- `goal_id`: Foreign key to goals
- `action`: Type of action (created, updated, step_completed, etc.)
- `user_id`: User who performed the action
- `changes`: JSON data with change details
- `created_at`: When action occurred

## Files

### `views/goals.php`
Main interface for viewing and managing goals:
- Goal list with card-based layout
- Filter controls (status, category, tags)
- Athlete selector for coaches
- Create/Edit goal modal
- Goal detail modal with steps and progress
- Progress note modal

### `process_goals.php`
Backend processing for all goal operations:
- `create_goal`: Create new goal with steps
- `update_goal`: Update goal details and steps
- `delete_goal`/`archive_goal`: Archive goal
- `add_step`: Add new step to existing goal
- `update_step`: Update step details
- `complete_step`: Mark step as completed/uncompleted
- `update_progress`: Add progress note
- `complete_goal`: Mark goal as completed
- `get_goal`: Fetch goal data for editing (AJAX)
- `get_goal_detail`: Fetch full goal details with steps and progress (AJAX)

### `deployment/goals_tables.sql`
SQL script to create all necessary database tables.

## Installation

1. **Create Database Tables**
   ```bash
   mysql -u username -p database_name < deployment/goals_tables.sql
   ```

2. **Verify Files**
   - `views/goals.php` - Main interface
   - `process_goals.php` - Backend processor
   - `dashboard.php` - Updated to include 'goals' route

3. **Access the System**
   Navigate to: `dashboard.php?page=goals`

## Usage Guide

### Creating a Goal (Coaches)

1. Click "Create Goal" button
2. Fill in goal details:
   - **Title**: Clear, specific goal title
   - **Description**: Detailed explanation of the goal
   - **Category**: E.g., Skating, Shooting, Fitness, Mental
   - **Tags**: Comma-separated (e.g., speed, power, technique)
   - **Target Date**: When the goal should be achieved
3. Add steps by clicking "Add Step"
   - Enter step titles that break down the goal
   - Steps can be reordered by dragging
4. Click "Save Goal"

### Tracking Progress (Coaches)

1. Click "View" on any goal card
2. Check off steps as they're completed
3. Click "Add Progress Note" to log updates
4. When all steps are done, click "Complete" on the goal card

### Filtering Goals

Use the filter bar to:
- **Status**: View active, completed, or archived goals
- **Category**: Filter by goal category
- **Tag**: Filter by specific tag

### Athlete View

Athletes can:
- View all their assigned goals
- See progress bars and completion percentages
- Review step completion status
- Read progress notes from coaches
- Filter their goals by status, category, and tags

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Permission Checks**: Coaches can manage goals, athletes can view only
- **SQL Injection Prevention**: All queries use prepared statements
- **Input Validation**: Server-side validation of all inputs
- **Audit Trail**: All actions logged to goal_history table

## Progress Calculation

Goal completion percentage is automatically calculated based on completed steps:
```
completion_percentage = (completed_steps / total_steps) * 100
```

The percentage is recalculated whenever:
- A step is marked complete/incomplete
- Steps are added or removed
- Manual percentage is set via progress note

## Design Specifications

### Color Scheme
- Primary: `#7000a4` (Deep Purple)
- Background: `#06080b` (Dark)
- Cards: `#0d1117` (Dark Gray)
- Borders: `#1e293b` (Slate)
- Text: `#fff` (White) and `#94a3b8` (Muted)

### Layout
- Responsive grid layout (auto-fit, min 350px cards)
- Card-based design with hover effects
- Modal overlays for forms and details
- Visual progress bars with gradient effect

### Icons (Font Awesome)
- Bullseye: Main goals icon
- List-check: Steps counter
- Calendar: Target date
- Plus: Create/Add actions
- Edit: Edit actions
- Check: Complete actions
- Eye: View details

## API Endpoints (AJAX)

### GET Requests
```
GET process_goals.php?action=get_goal&goal_id=123
GET process_goals.php?action=get_goal_detail&goal_id=123
```

### POST Requests
All POST requests require CSRF token and coach permissions (except viewing).

## Future Enhancements

Potential additions:
- File/media attachments for progress notes
- Goal templates for common goals
- Milestone celebrations/notifications
- Parent view of athlete goals
- Goal sharing between coaches
- Export goal reports to PDF
- Mobile app integration
- Reminders for upcoming target dates
- Goal analytics and insights

## Support

For issues or questions about the Goals system:
1. Check that database tables are created correctly
2. Verify user has appropriate permissions (coach role)
3. Check browser console for JavaScript errors
4. Review PHP error logs for backend issues
5. Ensure CSRF tokens are being generated correctly

## Changelog

### Version 1.0.0 (Initial Release)
- Complete goal creation and management system
- Step-by-step tracking with completion percentages
- Progress notes with history
- Athlete selector for coaches
- Filter system (status, category, tags)
- Full audit trail via goal_history table
- Responsive design matching Crash Hockey theme
- CSRF protection and security measures
