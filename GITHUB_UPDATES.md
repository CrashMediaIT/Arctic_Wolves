# System Updates Feature Documentation

## Overview
The System Updates feature allows administrators to apply update packages that include new features, bug fixes, and security patches. Updates are applied via the Feature Importer system which supports intelligent database migrations, file changes, version tracking, and automatic rollback on errors.

## Location
The Updates feature is available in the System Tools page under the "Updates" tab:
- **Menu:** Admin → System Tools
- **Tab:** Updates

## Features

### 1. Update Package Import
- Upload ZIP update packages containing new features and fixes
- Supports drag-and-drop file upload
- Validates package structure before applying

### 2. Intelligent Database Migrations
- Automatic table/column renames with code reference updates
- Add, modify, or drop columns and tables
- Data migrations with full transaction support

### 3. File Management
- Create, update, or delete files automatically
- Move/rename files with path reference updates
- Preserve configuration files during updates

### 4. Version Tracking
- Track all installed feature versions
- Prevent duplicate installations
- Version compatibility checking

### 5. Rollback Support
- Automatic backup before changes
- Full rollback on any error
- Transaction-safe database changes

## Usage

### Applying Update Packages

1. Navigate to Admin → System Tools → Updates tab
2. Click "Browse Files" or drag and drop a ZIP update package
3. Review the package name and size displayed
4. Click "Import Update Package"
5. Confirm the backup warning
6. Wait for the import to complete
7. Review the import log for any warnings or errors
8. The page will reload to show the updated feature versions

### Update Package Structure

Update packages are ZIP files containing:
- `manifest.json` - Package definition with version, migrations, and file lists
- `files/` - Directory containing new or updated files
- `README.md` - Optional documentation
- `CHANGELOG.md` - Optional change history

### Files Excluded from Updates
The following files/directories are never modified by the updater to preserve your configuration:
- `db_config.php` - Database configuration
- `uploads/` - User uploaded files
- `.git/` - Git repository data
- `.env` - Environment configuration
- `config.php` - Custom configuration
- `vendor/` - Composer dependencies
- `node_modules/` - NPM dependencies

## Security Considerations

1. **Access Control:** Only administrators can access the Updates feature
2. **CSRF Protection:** All update actions are protected against CSRF attacks
3. **File Validation:** Package manifests are validated before execution
4. **Transaction Safety:** Database changes use transactions with rollback on error
5. **Automatic Backup:** Files are backed up before any changes

## Troubleshooting

### "manifest.json not found"
- Verify the ZIP file contains a valid manifest.json at the root
- Check the package was created correctly

### "Version already installed"
- This version has already been applied
- Check the installed versions table for duplicates

### Import Failed with Errors
- Review the import log for specific error messages
- Check file permissions on the server
- Ensure sufficient disk space
- Verify PHP has write access to application directories
- Check database connection and permissions

### Files Not Updating
- Verify files exist in the package's files/ directory
- Check the manifest file lists match the package contents
- Check server file permissions

## Technical Details

### Implementation Files
- **Library:** `/admin/feature_importer.php` - Core import logic
- **Library:** `/lib/update_package_generator.php` - Package creation utility
- **Library:** `/lib/database_migrator.php` - Database migration handler
- **Library:** `/lib/code_updater.php` - Code reference updater
- **UI:** `/views/admin_system_tools.php` - Updates tab interface
- **Handler:** `/process_feature_import.php` - Import action handler

### Database Tables
The following table tracks installed features:
- `feature_versions` - Records feature name, version, applied date, and changes

### Update Process Flow
1. Upload and validate ZIP package
2. Extract and parse manifest.json
3. Check version compatibility
4. Create backup of affected files
5. Begin database transaction
6. Execute database migrations
7. Process file migrations (move/rename)
8. Create, update, or delete files
9. Update navigation routes if needed
10. Record feature version
11. Commit transaction
12. Clean up temporary files

## Best Practices

1. **Test in Staging:** Always test updates in a staging environment first
2. **Backup First:** Create database and file backups before updating
3. **Review Package:** Check the package contents before importing
4. **Monitor Errors:** Review any error messages carefully
5. **Verify Functionality:** Test critical features after applying updates

## Creating Update Packages

Use the UpdatePackageGenerator class to create update packages:

```php
require_once 'lib/update_package_generator.php';

$generator = new UpdatePackageGenerator();
$generator->initPackage('MyFeature', '1.0.0', 'Description of the feature');

// Add database changes
$generator->addColumn('users', 'new_column VARCHAR(255) DEFAULT NULL');

// Add files
$generator->addFile('views/new_view.php', $file_content);

// Generate package
$zip_path = $generator->generatePackage();
```

## Version Information
- **Feature Updated:** February 2026
- **Repository:** CrashMediaIT/Arctic_Wolves
- **Default Branch:** main
