# Update Package System Documentation

This document describes the Arctic Wolves update package system, which allows for structured, versioned updates to the application including database migrations, file changes, and navigation updates.

## Overview

The update package system consists of three main components:

1. **UpdatePackageGenerator** (`lib/update_package_generator.php`) - Creates update packages with proper JSON manifests
2. **FeatureImporter** (`admin/feature_importer.php`) - Imports and applies update packages
3. **DatabaseMigrator** (`lib/database_migrator.php`) - Handles database schema migrations

## Creating an Update Package

### Using the UpdatePackageGenerator Class

```php
<?php
require_once 'lib/update_package_generator.php';

$generator = new UpdatePackageGenerator();

// Initialize a new package
$generator->initPackage(
    'my_feature',           // Package name (alphanumeric with underscores)
    '1.0.0',                // Semantic version
    'Add new feature X',    // Description
    '0.9.0'                 // Optional: Required base version
);

// Add database migrations
$generator->addColumn('users', 'preferred_language VARCHAR(10) DEFAULT "en"', 'email');
$generator->renameColumn('sessions', 'old_column', 'new_column');
$generator->addIndex('users', 'idx_email', ['email'], true);

// Add new files
$generator->addFile('views/new_feature.php', '<?php // New feature code');

// Add navigation item
$generator->addNavigationItem(
    '?page=new_feature',
    'views/new_feature.php',
    'New Feature',
    'fas fa-star',
    'admin_menu',
    ['admin', 'coach']
);

// Generate the package
$zip_path = $generator->generatePackage();
echo "Package created: $zip_path";
```

## Manifest Schema

The `manifest.json` file is the heart of an update package. Here's the complete schema:

```json
{
    "name": "feature_name",
    "version": "1.0.0",
    "description": "Description of the update",
    "created_at": "2026-02-04 12:00:00",
    "author": "Arctic Wolves System",
    "requires_version": "0.9.0",
    "requires_validation": true,
    "database_migrations": [
        {
            "type": "add_column",
            "table": "users",
            "column_definition": "new_field VARCHAR(255) DEFAULT NULL",
            "after": "existing_column"
        }
    ],
    "file_migrations": [
        {
            "type": "move",
            "old_path": "old/path/file.php",
            "new_path": "new/path/file.php"
        }
    ],
    "files": {
        "create": ["path/to/new/file.php"],
        "update": ["path/to/existing/file.php"],
        "delete": ["path/to/remove/file.php"]
    },
    "directories": ["new/directory/path"],
    "navigation": {
        "add": [
            {
                "url": "?page=new_page",
                "view": "views/new_page.php",
                "label": "New Page",
                "icon": "fas fa-icon",
                "parent": "admin_menu",
                "roles": ["admin"]
            }
        ],
        "remove": ["old_page"]
    }
}
```

## Database Migration Types

The system supports the following database migration types:

### 1. Create Table

```json
{
    "type": "create_table",
    "table": "new_table",
    "columns": {
        "id": {"type": "INT AUTO_INCREMENT", "primary": true},
        "name": {"type": "VARCHAR(255)", "nullable": false},
        "created_at": {"type": "TIMESTAMP", "default": "CURRENT_TIMESTAMP"}
    },
    "indexes": {
        "idx_name": {"columns": ["name"], "unique": false}
    },
    "foreign_keys": {
        "fk_user": {
            "column": "user_id",
            "ref_table": "users",
            "ref_column": "id",
            "on_delete": "CASCADE",
            "on_update": "CASCADE"
        }
    }
}
```

### 2. Add Column

```json
{
    "type": "add_column",
    "table": "existing_table",
    "column_definition": "new_column VARCHAR(100) DEFAULT 'value'",
    "after": "existing_column"
}
```

### 3. Drop Column

```json
{
    "type": "drop_column",
    "table": "table_name",
    "column_name": "column_to_remove"
}
```

### 4. Rename Column

```json
{
    "type": "rename_column",
    "table": "table_name",
    "old_name": "old_column",
    "new_name": "new_column",
    "definition": "VARCHAR(255) NOT NULL"
}
```

### 5. Rename Table

```json
{
    "type": "rename_table",
    "old_name": "old_table_name",
    "new_name": "new_table_name"
}
```

### 6. Modify Column

```json
{
    "type": "modify_column",
    "table": "table_name",
    "column_name": "column_to_modify",
    "new_definition": "VARCHAR(500) NOT NULL DEFAULT ''"
}
```

### 7. Add Index

```json
{
    "type": "add_index",
    "table": "table_name",
    "index_name": "idx_name",
    "columns": ["column1", "column2"],
    "unique": false
}
```

### 8. Drop Index

```json
{
    "type": "drop_index",
    "table": "table_name",
    "index_name": "idx_to_remove"
}
```

### 9. Add Foreign Key

```json
{
    "type": "add_foreign_key",
    "table": "child_table",
    "constraint_name": "fk_parent",
    "column": "parent_id",
    "ref_table": "parent_table",
    "ref_column": "id",
    "on_delete": "CASCADE",
    "on_update": "CASCADE"
}
```

### 10. Drop Foreign Key

```json
{
    "type": "drop_foreign_key",
    "table": "table_name",
    "constraint_name": "fk_to_remove"
}
```

### 11. Data Migration

```json
{
    "type": "data_migration",
    "sql": "UPDATE users SET status = 'active' WHERE status IS NULL",
    "description": "Set default status for users without one"
}
```

## Package Structure

A complete update package ZIP file should have this structure:

```
my_feature_v1.0.0_20260204_120000.zip
├── manifest.json           # Required: Package manifest
├── README.md               # Auto-generated documentation
├── CHANGELOG.md            # Auto-generated changelog
└── files/                  # Directory containing files to be deployed
    ├── views/
    │   └── new_feature.php
    ├── js/
    │   └── new_feature.js
    └── css/
        └── new_feature.css
```

## Importing an Update Package

### Via Admin Interface

1. Navigate to **Admin → System Tools → Updates**
2. Click **Choose File** and select the update package ZIP
3. Click **Import Feature**
4. Review the import log for success/failure messages

### Via PHP Code

```php
<?php
require_once 'admin/feature_importer.php';
require_once 'db_config.php';

$importer = new FeatureImporter($pdo, __DIR__);
$result = $importer->importFeature('/path/to/package.zip');

if ($result['success']) {
    echo "Import successful: " . $result['message'];
} else {
    echo "Import failed: " . $result['error'];
}
```

## Version Control

The system tracks installed feature versions in the `feature_versions` table:

```sql
CREATE TABLE feature_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_name VARCHAR(255) NOT NULL,
    version VARCHAR(50) NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by INT DEFAULT NULL,
    database_changes JSON DEFAULT NULL,
    file_changes JSON DEFAULT NULL,
    manifest JSON DEFAULT NULL,
    UNIQUE KEY unique_feature_version (feature_name, version),
    INDEX idx_feature_name (feature_name)
);
```

This allows:
- Tracking which features are installed
- Preventing duplicate installations
- Preventing version downgrades
- Requiring specific base versions for upgrades

## Rollback and Backup

The system automatically:
1. Creates backups of files that will be modified or deleted
2. Uses database transactions for all migrations
3. Automatically rolls back on failure

Backups are stored in `tmp/feature_backups/` with a unique ID.

## Validation

Before importing, the system validates:
- Manifest structure and required fields
- Version format (semantic versioning)
- Database migration syntax
- File paths and existence
- Required base version compatibility

### Validating a Manifest Programmatically

```php
<?php
require_once 'lib/update_package_generator.php';

$manifest = json_decode(file_get_contents('manifest.json'), true);
$result = UpdatePackageGenerator::validateManifest($manifest);

if ($result['valid']) {
    echo "Manifest is valid";
} else {
    foreach ($result['errors'] as $error) {
        echo "Error: $error\n";
    }
}
```

## Best Practices

1. **Always use semantic versioning** (MAJOR.MINOR.PATCH)
2. **Test migrations on a development database first**
3. **Create database backups before applying updates**
4. **Keep migrations atomic** - each migration should do one thing
5. **Document all changes** in the package description
6. **Use `requires_version`** for dependent updates
7. **Avoid destructive operations** unless absolutely necessary

## Example: Complete Feature Package

Here's a complete example creating a new "Announcements" feature:

```php
<?php
require_once 'lib/update_package_generator.php';

$generator = new UpdatePackageGenerator();

$generator->initPackage(
    'announcements',
    '1.0.0',
    'Add announcement system for coaches to post updates'
);

// Create the announcements table
$generator->addTableCreate('announcements', [
    'id' => ['type' => 'INT AUTO_INCREMENT', 'primary' => true],
    'title' => ['type' => 'VARCHAR(255)', 'nullable' => false],
    'content' => ['type' => 'TEXT', 'nullable' => false],
    'author_id' => ['type' => 'INT', 'nullable' => false],
    'is_active' => ['type' => 'TINYINT(1)', 'default' => '1'],
    'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP']
]);

// Add foreign key for author
$generator->addForeignKey(
    'announcements',
    'fk_announcement_author',
    'author_id',
    'users',
    'id',
    'CASCADE',
    'CASCADE'
);

// Add the view file
$view_content = '<?php
// Announcements View
$announcements = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
?>
<h1>Announcements</h1>
<?php foreach ($announcements as $a): ?>
    <div class="announcement">
        <h3><?= htmlspecialchars($a["title"]) ?></h3>
        <p><?= nl2br(htmlspecialchars($a["content"])) ?></p>
    </div>
<?php endforeach; ?>';

$generator->addFile('views/announcements.php', $view_content);

// Add navigation
$generator->addNavigationItem(
    '?page=announcements',
    'views/announcements.php',
    'Announcements',
    'fas fa-bullhorn'
);

// Generate the package
$zip_path = $generator->generatePackage();
echo "Package created at: $zip_path\n";
```

## Troubleshooting

### Common Issues

1. **"Unknown migration type"** - Check that the migration type is one of the supported types
2. **"Table already exists"** - The migration is skipped (idempotent)
3. **"Version already installed"** - You're trying to install the same version again
4. **"Cannot downgrade"** - Lower versions cannot be installed after higher ones

### Debug Mode

Enable debug logging by checking the import log returned from `importFeature()`:

```php
$result = $importer->importFeature($zip_path);
foreach ($result['log'] as $entry) {
    echo "[{$entry['type']}] {$entry['message']}\n";
}
```

---

*Last updated: 2026-02-04*
