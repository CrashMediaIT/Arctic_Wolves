# Intelligent Feature Import System

## Overview

The enhanced feature import system provides intelligent database schema change handling across versions, with automatic code updates, version tracking, and comprehensive rollback capabilities.

## Key Features

### 1. **Intelligent Schema Migrations**
- Automatic detection of table/column renames
- Smart code reference updates across all PHP files
- Schema.sql synchronization
- Validation before execution

### 2. **Version Management**
- Semantic versioning support (e.g., 1.0.0, 2.0.0)
- Version compatibility checking
- Prevent downgrades
- Track installation history
- Upgrade path validation

### 3. **Pre-Import Analysis**
- Database schema validation
- Conflict detection
- Impact assessment (tables, columns, files affected)
- File existence verification

### 4. **Automated Code Updates**
- Update SQL queries (SELECT, UPDATE, INSERT, JOIN)
- Update array keys in PHP code
- Update file path references
- Update require/include statements

### 5. **Comprehensive Rollback**
- Database transaction support
- File backup before changes
- Automatic rollback on errors
- Backup ID tracking

## Usage

### Creating a Feature Package

#### 1. Create manifest.json

```json
{
  "name": "My Feature",
  "version": "2.0.0",
  "requires_version": "1.0.0",
  "requires_validation": true,
  
  "database_migrations": [
    {
      "type": "rename_table",
      "old_name": "sessions_old",
      "new_name": "training_sessions"
    },
    {
      "type": "rename_column",
      "table": "users",
      "old_name": "user_type",
      "new_name": "account_type",
      "definition": "VARCHAR(50) NOT NULL DEFAULT 'standard'"
    },
    {
      "type": "add_column",
      "table": "users",
      "column_definition": "premium_until TIMESTAMP NULL DEFAULT NULL"
    },
    {
      "type": "drop_column",
      "table": "users",
      "column_name": "legacy_field"
    },
    {
      "type": "modify_column",
      "table": "users",
      "column_name": "email",
      "new_definition": "VARCHAR(320) NOT NULL UNIQUE"
    }
  ],
  
  "file_migrations": [
    {
      "type": "move",
      "old_path": "views/sessions.php",
      "new_path": "views/training_sessions.php"
    },
    {
      "type": "rename",
      "old_path": "process_session.php",
      "new_path": "process_training_session.php"
    }
  ],
  
  "files": {
    "create": [
      "views/new_feature.php"
    ],
    "update": [
      "dashboard.php"
    ],
    "delete": [
      "old_deprecated.php"
    ]
  },
  
  "directories": [
    "uploads/feature_data/"
  ],
  
  "navigation": {
    "add": [
      {
        "label": "New Feature",
        "url": "?page=new_feature",
        "view": "views/new_feature.php",
        "role": "admin"
      }
    ]
  }
}
```

#### 2. Create ZIP Structure

```
feature_package.zip
├── manifest.json
├── files/
│   ├── views/
│   │   └── new_feature.php
│   └── dashboard.php
└── migration_001.sql (optional, for custom SQL)
```

### Migration Types

#### Database Migrations

**rename_table**
```json
{
  "type": "rename_table",
  "old_name": "old_table_name",
  "new_name": "new_table_name"
}
```
- Renames table in database
- Updates schema.sql
- Updates all SQL queries in code
- Updates foreign key references

**rename_column**
```json
{
  "type": "rename_column",
  "table": "users",
  "old_name": "old_column",
  "new_name": "new_column",
  "definition": "VARCHAR(255) NULL" // optional
}
```
- Renames column in database
- Updates schema.sql
- Updates all references in code

**add_column**
```json
{
  "type": "add_column",
  "table": "users",
  "column_definition": "new_column VARCHAR(255) DEFAULT NULL"
}
```
- Adds new column if it doesn't exist
- Updates schema.sql

**drop_column**
```json
{
  "type": "drop_column",
  "table": "users",
  "column_name": "deprecated_column"
}
```
- Removes column from database
- Updates schema.sql

**modify_column**
```json
{
  "type": "modify_column",
  "table": "users",
  "column_name": "email",
  "new_definition": "VARCHAR(320) NOT NULL UNIQUE"
}
```
- Changes column definition
- Updates schema.sql

#### File Migrations

**move/rename**
```json
{
  "type": "move",
  "old_path": "views/old_location.php",
  "new_path": "views/new_location.php"
}
```
- Moves/renames file
- Updates all require/include statements
- Updates path references in code

### Importing a Feature

1. Navigate to Admin > Feature Import
2. View currently installed versions
3. Upload ZIP package
4. System performs pre-import analysis
5. Review analysis results (tables, columns, files affected)
6. Click "Import Feature"
7. Monitor progress with real-time logs
8. System automatically:
   - Validates compatibility
   - Creates backup
   - Executes migrations
   - Updates code references
   - Updates schema.sql
   - Records version
9. View success/error results

## Version Management

### Semantic Versioning

Features use semantic versioning (MAJOR.MINOR.PATCH):
- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes

### Version Checking

The system automatically:
- Prevents installation of same version twice
- Prevents downgrades (2.0.0 → 1.0.0)
- Validates `requires_version` matches installed version
- Tracks upgrade path history

### Installed Versions Table

All feature installations are recorded in `feature_versions`:
- `feature_name`: Name of the feature
- `version`: Installed version
- `applied_at`: Installation timestamp
- `applied_by`: User who installed it
- `database_changes`: JSON of database migrations applied
- `file_changes`: JSON of file migrations applied
- `manifest`: Complete manifest for reference

## Code Update Patterns

### SQL Queries

**Before:**
```php
$stmt = $pdo->query("SELECT * FROM old_table");
$data = $stmt->fetch()['old_column'];
```

**After (automatic):**
```php
$stmt = $pdo->query("SELECT * FROM new_table");
$data = $stmt->fetch()['new_column'];
```

### Array Keys

**Before:**
```php
$user['old_column']
$row['old_column']
```

**After (automatic):**
```php
$user['new_column']
$row['new_column']
```

### File Paths

**Before:**
```php
require_once 'views/old_path.php';
include 'views/old_path.php';
```

**After (automatic):**
```php
require_once 'views/new_path.php';
include 'views/new_path.php';
```

## Error Handling & Rollback

### Automatic Rollback Triggers

1. Database migration failure
2. File operation failure
3. Version conflict
4. Validation failure
5. Missing dependencies

### Rollback Process

1. Database transaction rolled back
2. Files restored from backup
3. Error logged with details
4. User notified with specific error

### Backup Structure

```
tmp/feature_backups/
└── backup_1234567890/
    ├── manifest.json
    ├── dashboard.php
    └── views/
        └── modified_file.php
```

## API Reference

### DatabaseMigrator Class

```php
$migrator = new DatabaseMigrator($pdo, $base_path);

// Execute migration
$result = $migrator->executeMigration($migration);

// Validate before execution
$validation = $migrator->validateMigration($migration);

// Check table/column existence
$exists = $migrator->tableExists('table_name');
$exists = $migrator->columnExists('table', 'column');

// Update schema file
$migrator->updateSchemaFile($migrations);
```

### CodeUpdater Class

```php
$updater = new CodeUpdater($base_path);

// Update table references
$result = $updater->updateTableReferences('old_table', 'new_table');

// Update column references
$result = $updater->updateColumnReferences('table', 'old_col', 'new_col');

// Update file path references
$result = $updater->updateFilePathReferences('old/path.php', 'new/path.php');
```

### FeatureImporter Class

```php
$importer = new FeatureImporter($pdo, $base_path);

// Import feature
$result = $importer->importFeature($zip_file_path);

// Get installed versions
$versions = $importer->getInstalledVersions();
```

## Best Practices

### Creating Migrations

1. **Test Locally First**: Always test migrations on development environment
2. **Backup Data**: Create database backups before major migrations
3. **Incremental Changes**: Break large changes into smaller versions
4. **Document Changes**: Include clear descriptions in manifest
5. **Version Properly**: Follow semantic versioning strictly

### Database Migrations

1. **Preserve Data**: Ensure renames don't lose data
2. **Handle NULLs**: Consider existing NULL values when adding columns
3. **Foreign Keys**: Update foreign key references for table renames
4. **Indexes**: Recreate indexes after column renames if needed

### File Migrations

1. **Check Dependencies**: Ensure all file references are updated
2. **Session Files**: Be careful with files that might be in use
3. **Permissions**: Verify file permissions after moves

### Version Strategy

1. **Patch (X.X.1)**: Bug fixes, no schema changes
2. **Minor (X.1.0)**: New features, additive schema changes
3. **Major (2.0.0)**: Breaking changes, renames, restructuring

## Troubleshooting

### Import Fails: Version Conflict
- **Cause**: Version already installed or downgrade attempt
- **Solution**: Check installed versions, increment version number

### Import Fails: Table Not Found
- **Cause**: Source table doesn't exist in database
- **Solution**: Verify current database schema, adjust migration

### Import Fails: Column Already Exists
- **Cause**: Target column name already in use
- **Solution**: Choose different name or remove existing column first

### Code References Not Updated
- **Cause**: Non-standard code patterns
- **Solution**: Manually update remaining references

### Rollback Failed
- **Cause**: File permissions or transaction issues
- **Solution**: Check logs, restore from backup_id manually

## Security Considerations

1. **Admin Only**: Feature import restricted to admin role
2. **CSRF Protection**: All requests include CSRF tokens
3. **File Validation**: ZIP files validated before extraction
4. **SQL Injection**: Prepared statements used for all queries
5. **Path Traversal**: File paths sanitized and validated
6. **Transaction Safety**: All database changes in transactions

## Performance

- **Schema Parsing**: Cached during import process
- **Code Updates**: Efficient regex patterns, single pass per file
- **File Operations**: Atomic moves, batch processing
- **Database**: Migrations executed in single transaction
- **Backup**: Only modified files backed up

## Limitations

1. **Code Patterns**: Complex code patterns may not be auto-updated
2. **Custom SQL**: Dynamic SQL strings may need manual updates
3. **External References**: References outside base_path not updated
4. **Concurrent Imports**: Sequential processing only
5. **Large Files**: 50MB package size limit

## Future Enhancements

- [ ] Preview mode (dry-run without applying changes)
- [ ] Manual rollback interface
- [ ] Migration scheduling
- [ ] Dependency resolution between features
- [ ] API documentation generation
- [ ] Automated testing integration
- [ ] Cloud backup storage
- [ ] Multi-language support for migrations

## Support

For issues or questions:
1. Check logs in `logs/` directory
2. Review backup in `tmp/feature_backups/`
3. Consult this documentation
4. Check system validation results
