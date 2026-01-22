# Directory Structure

This document describes the complete directory structure of the Crash Hockey application.

## Root Directories

```
/crashhockey/
├── backups/          # Database and file backups
├── cache/            # Application cache files
├── config/           # Configuration files (not in version control)
├── logs/             # Application and error logs
├── receipts/         # Uploaded expense receipts
├── tmp/              # Temporary files
├── uploads/          # General file uploads
├── videos/           # Video file uploads
└── views/            # View template files (33 PHP files)
```

## Directory Purposes

### backups/
Stores database backups and file system backups. Created by backup scripts.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: `.sql.gz`, `.sql.zip`, `.tar.gz` files
**Git**: Directory tracked, contents ignored

### cache/
Application cache for improved performance. Automatically managed.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: Cache files, session data
**Git**: Directory tracked, contents ignored

### config/
Configuration files not in version control. Environment-specific settings.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: Custom configs, non-sensitive settings
**Git**: Directory tracked, sensitive files ignored

### logs/
Application logs, error logs, access logs.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: `.log` files, error_log
**Git**: Directory tracked, contents ignored

### receipts/
Uploaded expense receipts from coaches and admins.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: PDF, JPG, PNG receipt files
**Git**: Directory tracked, contents ignored

### tmp/
Temporary files created during application runtime.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: Temporary uploads, processing files
**Git**: Directory tracked, contents ignored

### uploads/
General file uploads (profile pictures, documents, etc.).

**Permissions**: `755` (drwxr-xr-x)
**Contents**: User-uploaded files
**Git**: Directory tracked, contents ignored

### videos/
Video files uploaded by athletes and coaches.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: MP4, AVI, MOV video files
**Git**: Directory tracked, contents ignored

### views/
PHP view template files for the dashboard.

**Permissions**: `755` (drwxr-xr-x)
**Contents**: 33 PHP view files
**Git**: Fully tracked in version control

## File Permissions Setup

After deployment, set proper permissions:

```bash
# Set directory permissions
chmod 755 backups cache config logs receipts tmp uploads videos views

# Set file permissions  
find backups cache logs receipts tmp uploads videos -type f -exec chmod 644 {} \;

# For Docker
docker exec crashhockey_web chmod 755 backups cache config logs receipts tmp uploads videos
docker exec crashhockey_web chown -R www-data:www-data backups cache config logs receipts tmp uploads videos
```

## Disk Space Recommendations

- **uploads/**: 5-10 GB minimum
- **videos/**: 50-100 GB minimum (video files are large)
- **receipts/**: 1-2 GB minimum
- **backups/**: 10-20 GB minimum
- **logs/**: 1-2 GB minimum
- **cache/**: 500 MB minimum
- **tmp/**: 2-5 GB minimum

## Backup Strategy

Important directories to backup regularly:
- `uploads/` - User uploads
- `videos/` - Video content
- `receipts/` - Expense receipts
- Database (via mysqldump)

Directories that don't need backup:
- `cache/` - Can be regenerated
- `tmp/` - Temporary files
- `logs/` - Can be archived separately

## Monitoring

Monitor disk usage for these directories:
```bash
du -sh backups cache logs receipts tmp uploads videos
```

Set up alerts when directories exceed:
- videos/ > 80% of allocated space
- uploads/ > 80% of allocated space
- logs/ > 2 GB (rotate logs)

---

**Last Updated**: January 21, 2026
