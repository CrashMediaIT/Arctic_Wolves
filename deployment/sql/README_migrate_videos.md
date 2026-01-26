# Videos Table Migration

## Purpose
This migration adds missing columns to the `videos` table required for the coaches review page functionality.

## Background
The coaches review page was experiencing a 500 error when trying to upload videos. Investigation revealed:

1. **Code Issue**: `process_video.php` was calling a non-existent method `validateVideoUpload()` instead of `validateVideo()`
2. **Database Issue**: The `videos` table was missing columns that the view template expected

## Changes Made

### Database Schema Updates
Added the following columns to the `videos` table:
- `drill_name` (VARCHAR 255) - Name of the drill being reviewed
- `drill_type` (VARCHAR 100) - Type/category of the drill (e.g., "Skating", "Shooting")
- `duration` (VARCHAR 50) - Duration of the video
- `rating` (INT) - Coach's rating of the performance (0-5)
- `created_at` (TIMESTAMP) - Record creation timestamp

### Code Updates
- Fixed `process_video.php` to use `FileUploadValidator::validateVideo()` instead of non-existent `validateVideoUpload()`
- Updated the INSERT statement to include new fields when uploading videos

## Migration Instructions

### For New Installations
No action needed - the updated `database_schema.sql` and `deployment/schema.sql` files include all required columns.

### For Existing Deployments
Run the migration script to add the missing columns:

```bash
mysql -u [username] -p arctic_wolves < deployment/sql/migrate_videos_table.sql
```

Or execute each ALTER TABLE statement individually in your MySQL client.

**Note**: This migration is safe to run on existing data. All new columns are nullable or have default values, so existing video records will not be affected.

## Verification

After running the migration, verify the columns exist:

```sql
DESCRIBE videos;
```

You should see the new columns:
- drill_name
- drill_type  
- duration
- rating
- created_at

## Testing

1. Log in as a coach user
2. Navigate to Dashboard → Coaches Reviews
3. Click the "Upload New" tab
4. Fill in the form and click "Choose File"
5. The file picker should open without error
6. Upload a video successfully

## Rollback

If you need to rollback this migration (not recommended):

```sql
ALTER TABLE `videos` DROP COLUMN `drill_name`;
ALTER TABLE `videos` DROP COLUMN `drill_type`;
ALTER TABLE `videos` DROP COLUMN `duration`;
ALTER TABLE `videos` DROP COLUMN `rating`;
ALTER TABLE `videos` DROP COLUMN `created_at`;
```

**Warning**: Rolling back will lose any data stored in these columns.
