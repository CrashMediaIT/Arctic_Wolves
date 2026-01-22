# Evaluation Platform Type 2 - Skills & Abilities

## Overview
The Skills & Abilities Evaluation Platform is a comprehensive system for evaluating athlete performance across multiple skill categories with a 1-10 grading scale, notes, media attachments, and historical tracking.

## Files Created

### 1. views/evaluations_skills.php
Main evaluation interface for coaches and athletes.

**Features:**
- List view of all evaluations with status badges (draft, completed, archived)
- Progress tracking showing completed vs total skills
- Create new evaluation modal with athlete selector, date, and optional title
- Detailed evaluation view with:
  - Skills grouped by category with visual separation
  - 1-10 number input for each skill score
  - Public notes (visible to athlete) and private notes (coach only)
  - Media upload/display per skill (images: jpg, png, gif; videos: mp4, mov)
  - Historical comparison showing score changes from previous evaluations
- Quick athlete switcher dropdown for coaches
- Shareable link generation with token-based access
- Mark complete/archive functionality

**Permissions:**
- Coaches: Full CRUD access, can view/edit any athlete's evaluations
- Athletes: Read-only access to their own evaluations (public notes only)
- External users: Read-only via share link (public notes only if is_public=1)

### 2. views/admin_eval_framework.php
Admin interface for managing the evaluation framework.

**Features:**
- Visual category cards with skill counts and active status
- Create/edit/delete categories with name and description
- Create/edit/delete skills within categories
- Drag-and-drop reordering for categories and skills (using SortableJS)
- Activate/deactivate categories and skills
- Skill configuration: name, description, grading criteria
- Protection: Cannot delete categories with skills or skills used in evaluations
- Usage tracking: Shows how many evaluations use each skill

**Permissions:**
- Admin only access

### 3. process_eval_skills.php
Backend processor for all evaluation operations.

**Actions:**
- `create_evaluation` - Creates evaluation and auto-populates evaluation_scores for all active skills
- `update_evaluation` - Updates evaluation date and title
- `delete_evaluation` - Deletes evaluation with cascade (media files, scores, records)
- `save_score` - Saves individual skill score (validates 1-10 or NULL)
- `save_notes` - Saves public or private notes for a skill
- `upload_media` - Handles file upload with type validation and secure storage
- `delete_media` - Removes media file and database record
- `complete_evaluation` - Changes status from draft to completed
- `archive_evaluation` - Changes status to archived
- `generate_share_link` - Creates unique share token and public access
- `revoke_share_link` - Removes share token and disables public access

**Security:**
- CSRF token validation on all POST requests
- Prepared statements for all queries
- Permission checks per action
- File type validation (images: jpg, png, gif; videos: mp4, mov)
- Secure file storage in uploads/evaluations/[evaluation_id]/

### 4. process_eval_framework.php
Backend processor for framework management.

**Actions:**
- `create_category` - Creates category with auto-incrementing display_order
- `update_category` - Updates category name and description
- `delete_category` - Deletes category (only if no skills exist)
- `reorder_categories` - Updates display_order from drag-drop
- `create_skill` - Creates skill with auto-incrementing display_order per category
- `update_skill` - Updates skill details including category assignment
- `delete_skill` - Deletes skill (only if not used in evaluations)
- `reorder_skills` - Updates display_order from drag-drop
- `toggle_active` - Activates/deactivates category or skill

**Security:**
- Admin-only access enforcement
- CSRF token validation
- Prepared statements
- Validation prevents deletion of used resources

## Database Schema

### eval_categories
```sql
- id (INT, primary key, auto_increment)
- name (VARCHAR, category name)
- description (TEXT, optional description)
- display_order (INT, for drag-drop ordering)
- is_active (TINYINT, 1=active, 0=inactive)
- created_at (TIMESTAMP)
```

### eval_skills
```sql
- id (INT, primary key, auto_increment)
- category_id (INT, foreign key to eval_categories)
- name (VARCHAR, skill name)
- description (TEXT, skill description)
- criteria (TEXT, grading criteria - what makes 1 vs 10)
- display_order (INT, for drag-drop ordering within category)
- is_active (TINYINT, 1=active, 0=inactive)
- created_at (TIMESTAMP)
```

### athlete_evaluations
```sql
- id (INT, primary key, auto_increment)
- athlete_id (INT, foreign key to users)
- created_by (INT, foreign key to users - coach who created)
- evaluation_date (DATE, date of evaluation)
- title (VARCHAR, optional title)
- share_token (VARCHAR, unique token for public sharing)
- is_public (TINYINT, 1=publicly accessible via token)
- status (ENUM: 'draft', 'completed', 'archived')
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### evaluation_scores
```sql
- id (INT, primary key, auto_increment)
- evaluation_id (INT, foreign key to athlete_evaluations)
- skill_id (INT, foreign key to eval_skills)
- score (INT, 1-10 or NULL)
- public_notes (TEXT, visible to athlete)
- private_notes (TEXT, coach only)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)

UNIQUE KEY: (evaluation_id, skill_id)
```

### evaluation_media
```sql
- id (INT, primary key, auto_increment)
- evaluation_id (INT, foreign key to athlete_evaluations)
- score_id (INT, foreign key to evaluation_scores)
- media_url (VARCHAR, file path)
- media_type (ENUM: 'image', 'video')
- caption (TEXT, optional caption)
- uploaded_by (INT, foreign key to users)
- created_at (TIMESTAMP)
```

### team_evaluations (reserved for future use)
```sql
- id (INT, primary key, auto_increment)
- team_id (INT, foreign key)
- created_by (INT, foreign key to users)
- evaluation_date (DATE)
- title (VARCHAR)
- description (TEXT)
- status (ENUM: 'draft', 'completed', 'archived')
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

## Implementation Flow

### Creating an Evaluation (Coach)
1. Navigate to Skills Evaluations page
2. Select athlete from dropdown (or use pre-selected)
3. Click "New Evaluation" button
4. Fill in evaluation date and optional title
5. Submit - creates athlete_evaluations record
6. Auto-creates evaluation_scores rows for all active skills (score=NULL)
7. Redirects to evaluation detail page

### Scoring Skills (Coach)
1. View evaluation detail page
2. Skills are grouped by category
3. For each skill:
   - Enter score 1-10 (or leave blank)
   - Add public notes (athlete will see)
   - Add private notes (coach only)
   - Upload images/videos as evidence
4. Scores auto-save on change
5. Notes auto-save on blur
6. Media uploads immediately

### Historical Comparison
1. System automatically queries previous completed evaluations
2. Shows up to 3 most recent historical evaluations
3. For each skill that has historical data:
   - Displays old score
   - Shows change indicator (↑ +2, ↓ -1, or —)
   - Color-coded: green=improvement, red=decline, gray=no change

### Sharing Evaluations
1. Click "Share" button on evaluation
2. System generates unique 64-character token
3. Sets is_public=1 on athlete_evaluations record
4. Returns shareable URL: /dashboard.php?page=view_shared_eval&token=[TOKEN]
5. Anyone with link can view (public notes only, read-only)
6. "Revoke Public Access" removes token and sets is_public=0

### Managing Framework (Admin)
1. Navigate to Admin > Evaluation Framework
2. Create categories first
3. Add skills to each category
4. Drag to reorder categories or skills
5. Toggle active/inactive as needed
6. Edit names, descriptions, criteria
7. Delete only if not used

## Design Patterns

### Theme Colors
- Primary: `#7000a4` (deep purple)
- Primary Hover: `#5a0083`
- Success: `#10b981`
- Danger: `#ef4444`
- Warning: `#f59e0b`
- Background Dark: `#0d1117`
- Background Darker: `#06080b`
- Border: `#1e293b`
- Text Light: `#94a3b8`

### UI Components
- **Modal Pattern**: Dark overlay with centered content box
- **Form Pattern**: Labeled inputs with uppercase labels
- **Button Pattern**: Rounded corners, bold font, hover transitions
- **Card Pattern**: Dark background with border, hover effects
- **Grid Pattern**: Responsive grid with auto-fill/auto-fit

### Security Pattern
```php
// Every POST action
checkCsrfToken();

// Permission checks
if (!$is_coach) {
    throw new Exception('Only coaches can...');
}

// Database queries
$stmt = $pdo->prepare("SELECT...");
$stmt->execute([$param]);
```

## Usage Examples

### Creating First Category and Skill
1. Admin logs in
2. Goes to Admin > Evaluation Framework
3. Clicks "Add Category"
4. Enters "Skating Skills" with description
5. Saves category
6. Clicks "Add Skill" on that category
7. Enters:
   - Name: "Forward Crossovers"
   - Description: "Ability to execute crossovers while skating forward"
   - Criteria: "1 = Cannot perform, 5 = Average execution, 10 = Elite with perfect form"
8. Saves skill
9. Skill appears in category with drag handle

### Conducting First Evaluation
1. Coach selects athlete from dropdown
2. Clicks "New Evaluation"
3. Enters today's date and "Pre-Season Assessment"
4. Evaluation created with all active skills loaded
5. Coach scrolls through categories
6. For "Forward Crossovers":
   - Enters score: 7
   - Public notes: "Good technique, needs work on speed"
   - Private notes: "Recommend extra ice time on Tuesdays"
   - Uploads video of athlete performing crossovers
7. All data auto-saves
8. Clicks "Mark Complete" when done
9. Clicks "Share" to generate link for athlete/parents

### Athlete Viewing Evaluation
1. Athlete logs in
2. Goes to Skills Evaluations
3. Sees their evaluations listed
4. Clicks on "Pre-Season Assessment"
5. Views scores, public notes, and media
6. Cannot see private notes
7. Cannot edit anything (read-only)

## API Endpoints

### process_eval_skills.php
POST with action parameter:
- `action=create_evaluation&athlete_id=X&evaluation_date=YYYY-MM-DD&title=...`
- `action=save_score&score_id=X&score=7`
- `action=save_notes&score_id=X&note_type=public&notes=...`
- `action=upload_media&score_id=X&media=[file]`
- `action=complete_evaluation&evaluation_id=X`
- `action=generate_share_link&evaluation_id=X`

### process_eval_framework.php
POST with action parameter (admin only):
- `action=create_category&name=...&description=...`
- `action=create_skill&category_id=X&name=...&description=...&criteria=...`
- `action=reorder_categories&category_ids=[1,3,2,4]`
- `action=toggle_active&type=skill&id=X&active=1`

## Future Enhancements
- Team evaluations support (using team_evaluations table)
- Export evaluations to PDF
- Email notifications when evaluation completed
- Skill comparison charts/graphs
- Custom skill templates
- Bulk operations (score multiple athletes at once)
- Mobile app integration
- Video playback with annotation tools
- AI-powered score recommendations based on video analysis

## Troubleshooting

### Drag-and-drop not working
- Ensure SortableJS CDN is loaded: `https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js`
- Check browser console for JavaScript errors
- Verify drag handles have class `drag-handle`

### Media uploads failing
- Check directory permissions: `uploads/evaluations/` must be writable
- Verify file size limits in php.ini: `upload_max_filesize` and `post_max_size`
- Ensure allowed file types: jpg, jpeg, png, gif, mp4, mov

### Scores not saving
- Check CSRF token is present in form
- Verify score is between 1-10
- Check browser console for AJAX errors
- Verify database connection

### Share links not working
- Verify share_token is set in athlete_evaluations
- Check is_public=1
- Ensure view_shared_eval page exists in dashboard router

## Support
For issues or questions, contact development team or refer to existing evaluation_goals.php implementation for similar patterns.
