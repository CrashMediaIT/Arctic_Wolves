<?php
/**
 * Update Package Generator
 * Creates update/feature packages with proper JSON manifest, database migrations, and documentation.
 * 
 * This class generates standardized update packages that can be used by the FeatureImporter
 * to add new features, update existing functionality, or migrate database schemas.
 * 
 * @version 1.0.0
 */

class UpdatePackageGenerator {
    private $base_path;
    private $output_dir;
    private $manifest = [];
    private $files = [];
    private $migrations = [];
    
    /**
     * Constructor
     * 
     * @param string $base_path Base path of the application
     * @param string $output_dir Directory where update packages will be created
     */
    public function __construct($base_path = null, $output_dir = null) {
        $this->base_path = $base_path ?? dirname(__DIR__);
        $this->output_dir = $output_dir ?? $this->base_path . '/tmp/update_packages';
        
        if (!file_exists($this->output_dir)) {
            mkdir($this->output_dir, 0755, true);
        }
    }
    
    /**
     * Initialize a new update package
     * 
     * @param string $name Feature/update name (alphanumeric with underscores)
     * @param string $version Semantic version (e.g., "1.0.0")
     * @param string $description Description of the update
     * @param string|null $requires_version Required base version (optional)
     * @return self
     */
    public function initPackage($name, $version, $description, $requires_version = null) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Package name must start with a letter and contain only alphanumeric characters and underscores');
        }
        
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException('Version must be in semantic versioning format (e.g., 1.0.0)');
        }
        
        $this->manifest = [
            'name' => $name,
            'version' => $version,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'author' => 'Arctic Wolves System',
            'requires_validation' => true,
            'database_migrations' => [],
            'file_migrations' => [],
            'files' => [
                'create' => [],
                'update' => [],
                'delete' => []
            ],
            'directories' => [],
            'navigation' => [
                'add' => [],
                'remove' => []
            ]
        ];
        
        if ($requires_version) {
            $this->manifest['requires_version'] = $requires_version;
        }
        
        $this->files = [];
        $this->migrations = [];
        
        return $this;
    }
    
    /**
     * Add a database table creation migration
     * 
     * @param string $table_name Name of the table to create
     * @param array $columns Array of column definitions
     * @param array $indexes Optional array of index definitions
     * @param array $foreign_keys Optional array of foreign key definitions
     * @return self
     */
    public function addTableCreate($table_name, array $columns, array $indexes = [], array $foreign_keys = []) {
        $this->manifest['database_migrations'][] = [
            'type' => 'create_table',
            'table' => $table_name,
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreign_keys
        ];
        
        return $this;
    }
    
    /**
     * Add a column to an existing table
     * 
     * @param string $table Table name
     * @param string $column_definition Full column definition (e.g., "status VARCHAR(50) DEFAULT 'active'")
     * @param string|null $after_column Column to add after (optional)
     * @return self
     */
    public function addColumn($table, $column_definition, $after_column = null) {
        $migration = [
            'type' => 'add_column',
            'table' => $table,
            'column_definition' => $column_definition
        ];
        
        if ($after_column) {
            $migration['after'] = $after_column;
        }
        
        $this->manifest['database_migrations'][] = $migration;
        
        return $this;
    }
    
    /**
     * Drop a column from a table
     * 
     * @param string $table Table name
     * @param string $column_name Column to drop
     * @return self
     */
    public function dropColumn($table, $column_name) {
        $this->manifest['database_migrations'][] = [
            'type' => 'drop_column',
            'table' => $table,
            'column_name' => $column_name
        ];
        
        return $this;
    }
    
    /**
     * Rename a column
     * 
     * @param string $table Table name
     * @param string $old_name Current column name
     * @param string $new_name New column name
     * @param string|null $definition New column definition (optional, will be auto-detected if not provided)
     * @return self
     */
    public function renameColumn($table, $old_name, $new_name, $definition = null) {
        $migration = [
            'type' => 'rename_column',
            'table' => $table,
            'old_name' => $old_name,
            'new_name' => $new_name
        ];
        
        if ($definition) {
            $migration['definition'] = $definition;
        }
        
        $this->manifest['database_migrations'][] = $migration;
        
        return $this;
    }
    
    /**
     * Rename a table
     * 
     * @param string $old_name Current table name
     * @param string $new_name New table name
     * @return self
     */
    public function renameTable($old_name, $new_name) {
        $this->manifest['database_migrations'][] = [
            'type' => 'rename_table',
            'old_name' => $old_name,
            'new_name' => $new_name
        ];
        
        return $this;
    }
    
    /**
     * Modify a column definition
     * 
     * @param string $table Table name
     * @param string $column_name Column name
     * @param string $new_definition New column definition
     * @return self
     */
    public function modifyColumn($table, $column_name, $new_definition) {
        $this->manifest['database_migrations'][] = [
            'type' => 'modify_column',
            'table' => $table,
            'column_name' => $column_name,
            'new_definition' => $new_definition
        ];
        
        return $this;
    }
    
    /**
     * Add a data migration (INSERT, UPDATE, DELETE)
     * 
     * @param string $sql_statement The SQL statement to execute
     * @param string $description Description of what the migration does
     * @return self
     */
    public function addDataMigration($sql_statement, $description = '') {
        $this->manifest['database_migrations'][] = [
            'type' => 'data_migration',
            'sql' => $sql_statement,
            'description' => $description
        ];
        
        return $this;
    }
    
    /**
     * Add an index to a table
     * 
     * @param string $table Table name
     * @param string $index_name Index name
     * @param array $columns Columns to include in the index
     * @param bool $unique Whether the index should be unique
     * @return self
     */
    public function addIndex($table, $index_name, array $columns, $unique = false) {
        $this->manifest['database_migrations'][] = [
            'type' => 'add_index',
            'table' => $table,
            'index_name' => $index_name,
            'columns' => $columns,
            'unique' => $unique
        ];
        
        return $this;
    }
    
    /**
     * Drop an index from a table
     * 
     * @param string $table Table name
     * @param string $index_name Index name to drop
     * @return self
     */
    public function dropIndex($table, $index_name) {
        $this->manifest['database_migrations'][] = [
            'type' => 'drop_index',
            'table' => $table,
            'index_name' => $index_name
        ];
        
        return $this;
    }
    
    /**
     * Add a foreign key constraint
     * 
     * @param string $table Table name
     * @param string $constraint_name Constraint name
     * @param string $column Local column name
     * @param string $ref_table Referenced table
     * @param string $ref_column Referenced column
     * @param string $on_delete ON DELETE action
     * @param string $on_update ON UPDATE action
     * @return self
     */
    public function addForeignKey($table, $constraint_name, $column, $ref_table, $ref_column, $on_delete = 'CASCADE', $on_update = 'CASCADE') {
        $this->manifest['database_migrations'][] = [
            'type' => 'add_foreign_key',
            'table' => $table,
            'constraint_name' => $constraint_name,
            'column' => $column,
            'ref_table' => $ref_table,
            'ref_column' => $ref_column,
            'on_delete' => $on_delete,
            'on_update' => $on_update
        ];
        
        return $this;
    }
    
    /**
     * Drop a foreign key constraint
     * 
     * @param string $table Table name
     * @param string $constraint_name Constraint name to drop
     * @return self
     */
    public function dropForeignKey($table, $constraint_name) {
        $this->manifest['database_migrations'][] = [
            'type' => 'drop_foreign_key',
            'table' => $table,
            'constraint_name' => $constraint_name
        ];
        
        return $this;
    }
    
    /**
     * Add a file to be created in the package
     * 
     * @param string $relative_path Relative path where the file will be created
     * @param string $content File content
     * @return self
     */
    public function addFile($relative_path, $content) {
        $this->files[$relative_path] = $content;
        $this->manifest['files']['create'][] = $relative_path;
        
        return $this;
    }
    
    /**
     * Add a file to be updated
     * 
     * @param string $relative_path Relative path of the file to update
     * @param string $content New file content
     * @return self
     */
    public function updateFile($relative_path, $content) {
        $this->files[$relative_path] = $content;
        $this->manifest['files']['update'][] = $relative_path;
        
        return $this;
    }
    
    /**
     * Mark a file for deletion
     * 
     * @param string $relative_path Relative path of the file to delete
     * @return self
     */
    public function deleteFile($relative_path) {
        $this->manifest['files']['delete'][] = $relative_path;
        
        return $this;
    }
    
    /**
     * Add a file migration (move/rename file)
     * 
     * @param string $old_path Current file path
     * @param string $new_path New file path
     * @return self
     */
    public function moveFile($old_path, $new_path) {
        $this->manifest['file_migrations'][] = [
            'type' => 'move',
            'old_path' => $old_path,
            'new_path' => $new_path
        ];
        
        return $this;
    }
    
    /**
     * Add a directory to be created
     * 
     * @param string $relative_path Relative path of the directory
     * @return self
     */
    public function addDirectory($relative_path) {
        $this->manifest['directories'][] = $relative_path;
        
        return $this;
    }
    
    /**
     * Add a navigation item
     * 
     * @param string $url URL/route for the navigation item
     * @param string $view View file path
     * @param string $label Display label
     * @param string|null $icon FontAwesome icon class (optional)
     * @param string|null $parent_menu Parent menu key (optional)
     * @param array $roles Allowed roles (optional)
     * @return self
     */
    public function addNavigationItem($url, $view, $label, $icon = null, $parent_menu = null, array $roles = []) {
        $nav_item = [
            'url' => $url,
            'view' => $view,
            'label' => $label
        ];
        
        if ($icon) {
            $nav_item['icon'] = $icon;
        }
        
        if ($parent_menu) {
            $nav_item['parent'] = $parent_menu;
        }
        
        if (!empty($roles)) {
            $nav_item['roles'] = $roles;
        }
        
        $this->manifest['navigation']['add'][] = $nav_item;
        
        return $this;
    }
    
    /**
     * Set whether to skip system validation
     * 
     * @param bool $skip Whether to skip validation
     * @return self
     */
    public function skipValidation($skip = true) {
        $this->manifest['requires_validation'] = !$skip;
        
        return $this;
    }
    
    /**
     * Generate the update package as a ZIP file
     * 
     * @param string|null $output_filename Optional custom filename
     * @return string Path to the generated ZIP file
     */
    public function generatePackage($output_filename = null) {
        if (empty($this->manifest['name'])) {
            throw new RuntimeException('Package not initialized. Call initPackage() first.');
        }
        
        // Generate filename
        $filename = $output_filename ?? sprintf(
            '%s_v%s_%s.zip',
            $this->manifest['name'],
            $this->manifest['version'],
            date('Ymd_His')
        );
        
        $zip_path = $this->output_dir . '/' . $filename;
        
        // Create ZIP
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create ZIP file');
        }
        
        // Add manifest.json
        $zip->addFromString('manifest.json', json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        // Add files
        foreach ($this->files as $path => $content) {
            $zip->addFromString('files/' . $path, $content);
        }
        
        // Add README.md with documentation
        $readme = $this->generateReadme();
        $zip->addFromString('README.md', $readme);
        
        // Add CHANGELOG.md
        $changelog = $this->generateChangelog();
        $zip->addFromString('CHANGELOG.md', $changelog);
        
        $zip->close();
        
        return $zip_path;
    }
    
    /**
     * Generate a README for the update package
     * 
     * @return string Markdown content
     */
    private function generateReadme() {
        $name = $this->manifest['name'];
        $version = $this->manifest['version'];
        $description = $this->manifest['description'];
        
        $readme = "# {$name} v{$version}\n\n";
        $readme .= "{$description}\n\n";
        $readme .= "## Installation\n\n";
        $readme .= "1. Navigate to Admin → System Tools → Updates\n";
        $readme .= "2. Upload this package ZIP file\n";
        $readme .= "3. Click 'Import Feature' to apply the update\n\n";
        
        // Database changes section
        if (!empty($this->manifest['database_migrations'])) {
            $readme .= "## Database Changes\n\n";
            foreach ($this->manifest['database_migrations'] as $migration) {
                $type = $migration['type'] ?? 'unknown';
                switch ($type) {
                    case 'create_table':
                        $readme .= "- **Create Table:** `{$migration['table']}`\n";
                        break;
                    case 'add_column':
                        $readme .= "- **Add Column:** `{$migration['table']}.{$migration['column_definition']}`\n";
                        break;
                    case 'drop_column':
                        $readme .= "- **Drop Column:** `{$migration['table']}.{$migration['column_name']}`\n";
                        break;
                    case 'rename_column':
                        $readme .= "- **Rename Column:** `{$migration['table']}.{$migration['old_name']}` → `{$migration['new_name']}`\n";
                        break;
                    case 'rename_table':
                        $readme .= "- **Rename Table:** `{$migration['old_name']}` → `{$migration['new_name']}`\n";
                        break;
                    case 'modify_column':
                        $readme .= "- **Modify Column:** `{$migration['table']}.{$migration['column_name']}`\n";
                        break;
                    case 'data_migration':
                        $desc = $migration['description'] ?: 'Data migration';
                        $readme .= "- **Data Migration:** {$desc}\n";
                        break;
                    case 'add_index':
                        $readme .= "- **Add Index:** `{$migration['index_name']}` on `{$migration['table']}`\n";
                        break;
                    case 'drop_index':
                        $readme .= "- **Drop Index:** `{$migration['index_name']}` from `{$migration['table']}`\n";
                        break;
                    case 'add_foreign_key':
                        $readme .= "- **Add Foreign Key:** `{$migration['constraint_name']}` on `{$migration['table']}`\n";
                        break;
                    case 'drop_foreign_key':
                        $readme .= "- **Drop Foreign Key:** `{$migration['constraint_name']}` from `{$migration['table']}`\n";
                        break;
                }
            }
            $readme .= "\n";
        }
        
        // File changes section
        $has_file_changes = !empty($this->manifest['files']['create']) || 
                            !empty($this->manifest['files']['update']) || 
                            !empty($this->manifest['files']['delete']) ||
                            !empty($this->manifest['file_migrations']);
                            
        if ($has_file_changes) {
            $readme .= "## File Changes\n\n";
            
            if (!empty($this->manifest['files']['create'])) {
                $readme .= "### New Files\n";
                foreach ($this->manifest['files']['create'] as $file) {
                    $readme .= "- `{$file}`\n";
                }
                $readme .= "\n";
            }
            
            if (!empty($this->manifest['files']['update'])) {
                $readme .= "### Updated Files\n";
                foreach ($this->manifest['files']['update'] as $file) {
                    $readme .= "- `{$file}`\n";
                }
                $readme .= "\n";
            }
            
            if (!empty($this->manifest['files']['delete'])) {
                $readme .= "### Deleted Files\n";
                foreach ($this->manifest['files']['delete'] as $file) {
                    $readme .= "- `{$file}`\n";
                }
                $readme .= "\n";
            }
            
            if (!empty($this->manifest['file_migrations'])) {
                $readme .= "### Moved/Renamed Files\n";
                foreach ($this->manifest['file_migrations'] as $migration) {
                    $readme .= "- `{$migration['old_path']}` → `{$migration['new_path']}`\n";
                }
                $readme .= "\n";
            }
        }
        
        // Navigation section
        if (!empty($this->manifest['navigation']['add'])) {
            $readme .= "## New Navigation Items\n\n";
            foreach ($this->manifest['navigation']['add'] as $nav) {
                $readme .= "- **{$nav['label']}:** `{$nav['url']}`\n";
            }
            $readme .= "\n";
        }
        
        $readme .= "## Rollback\n\n";
        $readme .= "If issues occur, contact your system administrator. Automatic rollback is ";
        $readme .= "performed if the import fails.\n\n";
        
        $readme .= "---\n";
        $readme .= "*Generated on " . date('Y-m-d H:i:s') . "*\n";
        
        return $readme;
    }
    
    /**
     * Generate a CHANGELOG for the update package
     * 
     * @return string Markdown content
     */
    private function generateChangelog() {
        $name = $this->manifest['name'];
        $version = $this->manifest['version'];
        $description = $this->manifest['description'];
        
        $changelog = "# Changelog - {$name}\n\n";
        $changelog .= "## [{$version}] - " . date('Y-m-d') . "\n\n";
        $changelog .= "### Description\n";
        $changelog .= "{$description}\n\n";
        
        if (!empty($this->manifest['database_migrations'])) {
            $changelog .= "### Database Changes\n";
            foreach ($this->manifest['database_migrations'] as $migration) {
                $type = ucfirst(str_replace('_', ' ', $migration['type'] ?? 'unknown'));
                $changelog .= "- {$type}\n";
            }
            $changelog .= "\n";
        }
        
        $changelog .= "### Requirements\n";
        $changelog .= "- Arctic Wolves CRM System\n";
        if (!empty($this->manifest['requires_version'])) {
            $changelog .= "- Base version: {$this->manifest['requires_version']}\n";
        }
        $changelog .= "\n";
        
        return $changelog;
    }
    
    /**
     * Get the current manifest
     * 
     * @return array The manifest array
     */
    public function getManifest() {
        return $this->manifest;
    }
    
    /**
     * Get the JSON schema for update package manifests
     * 
     * @return array JSON schema definition
     */
    public static function getManifestSchema() {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'Arctic Wolves Update Package Manifest',
            'description' => 'Schema for update package manifest.json files',
            'type' => 'object',
            'required' => ['name', 'version'],
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'pattern' => '^[a-zA-Z][a-zA-Z0-9_]*$',
                    'description' => 'Package name (alphanumeric with underscores)'
                ],
                'version' => [
                    'type' => 'string',
                    'pattern' => '^\d+\.\d+\.\d+$',
                    'description' => 'Semantic version (e.g., 1.0.0)'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Description of the update'
                ],
                'requires_version' => [
                    'type' => 'string',
                    'pattern' => '^\d+\.\d+\.\d+$',
                    'description' => 'Required base version for upgrade'
                ],
                'requires_validation' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Whether to run system validation before import'
                ],
                'database_migrations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['type'],
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'enum' => [
                                    'create_table',
                                    'add_column',
                                    'drop_column',
                                    'rename_column',
                                    'rename_table',
                                    'modify_column',
                                    'data_migration',
                                    'add_index',
                                    'drop_index',
                                    'add_foreign_key',
                                    'drop_foreign_key'
                                ]
                            ]
                        ]
                    ]
                ],
                'file_migrations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['type', 'old_path', 'new_path'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['move', 'rename']],
                            'old_path' => ['type' => 'string'],
                            'new_path' => ['type' => 'string']
                        ]
                    ]
                ],
                'files' => [
                    'type' => 'object',
                    'properties' => [
                        'create' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'update' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'delete' => ['type' => 'array', 'items' => ['type' => 'string']]
                    ]
                ],
                'directories' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'navigation' => [
                    'type' => 'object',
                    'properties' => [
                        'add' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['url', 'view', 'label'],
                                'properties' => [
                                    'url' => ['type' => 'string'],
                                    'view' => ['type' => 'string'],
                                    'label' => ['type' => 'string'],
                                    'icon' => ['type' => 'string'],
                                    'parent' => ['type' => 'string'],
                                    'roles' => ['type' => 'array', 'items' => ['type' => 'string']]
                                ]
                            ]
                        ],
                        'remove' => ['type' => 'array', 'items' => ['type' => 'string']]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Validate a manifest against the schema
     * 
     * @param array $manifest The manifest to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public static function validateManifest(array $manifest) {
        $errors = [];
        
        // Check required fields
        if (empty($manifest['name'])) {
            $errors[] = 'Missing required field: name';
        } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $manifest['name'])) {
            $errors[] = 'Invalid name format. Must start with a letter and contain only alphanumeric characters and underscores';
        }
        
        if (empty($manifest['version'])) {
            $errors[] = 'Missing required field: version';
        } elseif (!preg_match('/^\d+\.\d+\.\d+$/', $manifest['version'])) {
            $errors[] = 'Invalid version format. Must be semantic version (e.g., 1.0.0)';
        }
        
        // Validate database migrations
        if (!empty($manifest['database_migrations'])) {
            $valid_types = [
                'create_table', 'add_column', 'drop_column', 'rename_column',
                'rename_table', 'modify_column', 'data_migration',
                'add_index', 'drop_index', 'add_foreign_key', 'drop_foreign_key'
            ];
            
            foreach ($manifest['database_migrations'] as $i => $migration) {
                if (empty($migration['type'])) {
                    $errors[] = "Database migration {$i}: missing type";
                } elseif (!in_array($migration['type'], $valid_types)) {
                    $errors[] = "Database migration {$i}: invalid type '{$migration['type']}'";
                }
            }
        }
        
        // Validate file migrations
        if (!empty($manifest['file_migrations'])) {
            foreach ($manifest['file_migrations'] as $i => $migration) {
                if (empty($migration['old_path'])) {
                    $errors[] = "File migration {$i}: missing old_path";
                }
                if (empty($migration['new_path'])) {
                    $errors[] = "File migration {$i}: missing new_path";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
