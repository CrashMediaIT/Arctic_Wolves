<?php
/**
 * Database Migrator
 * Handles schema parsing, comparison, and intelligent migrations
 */

class DatabaseMigrator {
    private $pdo;
    private $base_path;
    private $schema_cache = [];
    
    public function __construct($pdo, $base_path) {
        $this->pdo = $pdo;
        $this->base_path = $base_path;
    }
    
    /**
     * Sanitize a SQL identifier (table/column name) to prevent SQL injection.
     * Only allows alphanumeric characters and underscores.
     */
    private function sanitizeIdentifier($name) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new Exception("Invalid SQL identifier: contains disallowed characters");
        }
        return $name;
    }
    
    /**
     * Parse schema.sql file and extract table/column definitions
     */
    public function parseSchemaFile($schema_file_path) {
        if (!file_exists($schema_file_path)) {
            throw new Exception("Schema file not found: $schema_file_path");
        }
        
        $sql = file_get_contents($schema_file_path);
        $schema = [
            'tables' => [],
            'columns' => []
        ];
        
        // Extract CREATE TABLE statements
        preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.*?)\)\s*ENGINE/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        );
        
        foreach ($matches as $match) {
            $table_name = $match[1];
            $table_def = $match[2];
            
            $schema['tables'][$table_name] = [
                'name' => $table_name,
                'columns' => $this->parseTableColumns($table_def)
            ];
        }
        
        return $schema;
    }
    
    /**
     * Parse table column definitions
     */
    private function parseTableColumns($table_def) {
        $columns = [];
        $lines = explode("\n", $table_def);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === ',') continue;
            
            // Skip constraints
            if (preg_match('/^(PRIMARY KEY|FOREIGN KEY|UNIQUE KEY|INDEX|KEY|CONSTRAINT)/i', $line)) {
                continue;
            }
            
            // Extract column name
            if (preg_match('/^`?(\w+)`?\s+(\w+)/i', $line, $col_match)) {
                $col_name = $col_match[1];
                $col_type = $col_match[2];
                
                $columns[$col_name] = [
                    'name' => $col_name,
                    'type' => $col_type,
                    'definition' => rtrim($line, ',')
                ];
            }
        }
        
        return $columns;
    }
    
    /**
     * Get current database schema from live database
     */
    public function getCurrentSchema() {
        $schema = [
            'tables' => [],
            'columns' => []
        ];
        
        // Get all tables
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $schema['tables'][$table] = [
                'name' => $table,
                'columns' => $this->getTableColumns($table)
            ];
        }
        
        return $schema;
    }
    
    /**
     * Get columns for a specific table
     */
    private function getTableColumns($table) {
        $columns = [];
        $table = $this->sanitizeIdentifier($table);
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $columns[$row['Field']] = [
                'name' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
        
        return $columns;
    }
    
    /**
     * Check if table exists
     * Uses information_schema for reliable detection with server-side prepared statements
     */
    public function tableExists($table_name) {
        try {
            $table_name = $this->sanitizeIdentifier($table_name);
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            $stmt->execute([$table_name]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Check if column exists in table
     * Uses information_schema for reliable detection with server-side prepared statements
     */
    public function columnExists($table_name, $column_name) {
        try {
            $table_name = $this->sanitizeIdentifier($table_name);
            $column_name = $this->sanitizeIdentifier($column_name);
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table_name, $column_name]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Execute database migration from manifest
     */
    public function executeMigration($migration) {
        $type = $migration['type'] ?? '';
        
        switch ($type) {
            case 'rename_table':
                return $this->renameTable(
                    $migration['old_name'],
                    $migration['new_name']
                );
                
            case 'rename_column':
                return $this->renameColumn(
                    $migration['table'],
                    $migration['old_name'],
                    $migration['new_name'],
                    $migration['definition'] ?? null
                );
                
            case 'add_column':
                return $this->addColumn(
                    $migration['table'],
                    $migration['column_definition'],
                    $migration['after'] ?? null
                );
                
            case 'drop_column':
                return $this->dropColumn(
                    $migration['table'],
                    $migration['column_name']
                );
                
            case 'modify_column':
                return $this->modifyColumn(
                    $migration['table'],
                    $migration['column_name'],
                    $migration['new_definition']
                );
                
            case 'create_table':
                return $this->createTable(
                    $migration['table'],
                    $migration['columns'],
                    $migration['indexes'] ?? [],
                    $migration['foreign_keys'] ?? []
                );
                
            case 'drop_table':
                return $this->dropTable($migration['table']);
                
            case 'add_index':
                return $this->addIndex(
                    $migration['table'],
                    $migration['index_name'],
                    $migration['columns'],
                    $migration['unique'] ?? false
                );
                
            case 'drop_index':
                return $this->dropIndex(
                    $migration['table'],
                    $migration['index_name']
                );
                
            case 'add_foreign_key':
                return $this->addForeignKey(
                    $migration['table'],
                    $migration['constraint_name'],
                    $migration['column'],
                    $migration['ref_table'],
                    $migration['ref_column'],
                    $migration['on_delete'] ?? 'CASCADE',
                    $migration['on_update'] ?? 'CASCADE'
                );
                
            case 'drop_foreign_key':
                return $this->dropForeignKey(
                    $migration['table'],
                    $migration['constraint_name']
                );
                
            case 'data_migration':
                return $this->executeDataMigration(
                    $migration['sql'],
                    $migration['description'] ?? ''
                );
                
            default:
                throw new Exception("Unknown migration type: $type");
        }
    }
    
    /**
     * Rename table
     */
    public function renameTable($old_name, $new_name) {
        $old_name = $this->sanitizeIdentifier($old_name);
        $new_name = $this->sanitizeIdentifier($new_name);
        
        if (!$this->tableExists($old_name)) {
            return [
                'success' => true,
                'message' => "Table '$old_name' does not exist",
                'skipped' => true
            ];
        }
        
        if ($this->tableExists($new_name)) {
            return [
                'success' => true,
                'message' => "Table '$new_name' already exists",
                'skipped' => true
            ];
        }
        
        $sql = "RENAME TABLE `$old_name` TO `$new_name`";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Table renamed: $old_name → $new_name",
            'sql' => $sql
        ];
    }
    
    /**
     * Rename column
     */
    public function renameColumn($table, $old_name, $new_name, $definition = null) {
        $table = $this->sanitizeIdentifier($table);
        $old_name = $this->sanitizeIdentifier($old_name);
        $new_name = $this->sanitizeIdentifier($new_name);
        
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        if (!$this->columnExists($table, $old_name)) {
            throw new Exception("Column '$old_name' does not exist in table '$table'");
        }
        
        if ($this->columnExists($table, $new_name)) {
            throw new Exception("Column '$new_name' already exists in table '$table'");
        }
        
        // Get current column definition if not provided
        if (!$definition) {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$old_name]);
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $type = $col['Type'];
            $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $col['Default'] !== null ? "DEFAULT '{$col['Default']}'" : '';
            $extra = $col['Extra'];
            
            $definition = "$type $null $default $extra";
        }
        
        $sql = "ALTER TABLE `$table` CHANGE `$old_name` `$new_name` $definition";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Column renamed: $table.$old_name → $new_name",
            'sql' => $sql
        ];
    }
    
    /**
     * Add column to table
     */
    public function addColumn($table, $column_definition, $after_column = null) {
        $table = $this->sanitizeIdentifier($table);
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        // Extract column name from definition
        preg_match('/^`?(\w+)`?\s+/i', $column_definition, $matches);
        if (!$matches) {
            throw new Exception("Invalid column definition: $column_definition");
        }
        
        $column_name = $matches[1];
        
        if ($this->columnExists($table, $column_name)) {
            return [
                'success' => true,
                'message' => "Column '$column_name' already exists in table '$table'",
                'skipped' => true
            ];
        }
        
        $sql = "ALTER TABLE `$table` ADD $column_definition";
        if ($after_column) {
            $after_column = $this->sanitizeIdentifier($after_column);
            $sql .= " AFTER `$after_column`";
        }
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Column added: $table.$column_name",
            'sql' => $sql
        ];
    }
    
    /**
     * Drop column from table
     */
    public function dropColumn($table, $column_name) {
        $table = $this->sanitizeIdentifier($table);
        $column_name = $this->sanitizeIdentifier($column_name);
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        if (!$this->columnExists($table, $column_name)) {
            return [
                'success' => true,
                'message' => "Column '$column_name' does not exist in table '$table'",
                'skipped' => true
            ];
        }
        
        $sql = "ALTER TABLE `$table` DROP COLUMN `$column_name`";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Column dropped: $table.$column_name",
            'sql' => $sql
        ];
    }
    
    /**
     * Modify column definition
     */
    public function modifyColumn($table, $column_name, $new_definition) {
        $table = $this->sanitizeIdentifier($table);
        $column_name = $this->sanitizeIdentifier($column_name);
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        if (!$this->columnExists($table, $column_name)) {
            throw new Exception("Column '$column_name' does not exist in table '$table'");
        }
        
        $sql = "ALTER TABLE `$table` MODIFY `$column_name` $new_definition";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Column modified: $table.$column_name",
            'sql' => $sql
        ];
    }
    
    /**
     * Validate migration before execution
     */
    public function validateMigration($migration) {
        $type = $migration['type'] ?? '';
        $issues = [];
        
        switch ($type) {
            case 'rename_table':
                if (!$this->tableExists($migration['old_name'])) {
                    $issues[] = "Source table '{$migration['old_name']}' does not exist";
                }
                if ($this->tableExists($migration['new_name'])) {
                    $issues[] = "Target table '{$migration['new_name']}' already exists";
                }
                break;
                
            case 'rename_column':
                if (!$this->tableExists($migration['table'])) {
                    $issues[] = "Table '{$migration['table']}' does not exist";
                } else {
                    if (!$this->columnExists($migration['table'], $migration['old_name'])) {
                        $issues[] = "Column '{$migration['old_name']}' does not exist";
                    }
                    if ($this->columnExists($migration['table'], $migration['new_name'])) {
                        $issues[] = "Column '{$migration['new_name']}' already exists";
                    }
                }
                break;
                
            case 'add_column':
                if (!$this->tableExists($migration['table'])) {
                    $issues[] = "Table '{$migration['table']}' does not exist";
                }
                break;
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
    
    /**
     * Update schema.sql file with migration changes
     */
    public function updateSchemaFile($migrations) {
        $schema_file = $this->base_path . '/deployment/schema.sql';
        if (!file_exists($schema_file)) {
            throw new Exception("Schema file not found");
        }
        
        $sql = file_get_contents($schema_file);
        
        foreach ($migrations as $migration) {
            $type = $migration['type'] ?? '';
            
            switch ($type) {
                case 'rename_table':
                    $sql = $this->updateSchemaTableRename($sql, $migration['old_name'], $migration['new_name']);
                    break;
                    
                case 'rename_column':
                    $sql = $this->updateSchemaColumnRename(
                        $sql,
                        $migration['table'],
                        $migration['old_name'],
                        $migration['new_name']
                    );
                    break;
            }
        }
        
        file_put_contents($schema_file, $sql);
        
        return true;
    }
    
    /**
     * Update schema file for table rename
     */
    private function updateSchemaTableRename($sql, $old_name, $new_name) {
        // Replace table name in CREATE TABLE
        $sql = preg_replace(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($old_name) . '`?/i',
            "CREATE TABLE IF NOT EXISTS `$new_name`",
            $sql
        );
        
        // Replace in foreign key references
        $sql = preg_replace(
            '/REFERENCES\s+`?' . preg_quote($old_name) . '`?/i',
            "REFERENCES `$new_name`",
            $sql
        );
        
        return $sql;
    }
    
    /**
     * Update schema file for column rename
     */
    private function updateSchemaColumnRename($sql, $table, $old_name, $new_name) {
        // Find the table definition
        $pattern = '/(CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table) . '`?\s*\()(.*?)(\)\s*ENGINE)/is';
        
        if (preg_match($pattern, $sql, $matches)) {
            $table_def = $matches[2];
            
            // Replace column name
            $table_def = preg_replace(
                '/`?' . preg_quote($old_name) . '`?(\s+\w+)/i',
                "`$new_name`$1",
                $table_def,
                1
            );
            
            $sql = str_replace($matches[2], $table_def, $sql);
        }
        
        return $sql;
    }
    
    /**
     * Create a new table
     * 
     * @param string $table_name Table name
     * @param array $columns Array of column definitions
     * @param array $indexes Array of index definitions
     * @param array $foreign_keys Array of foreign key definitions
     * @return array Result with success status
     */
    public function createTable($table_name, array $columns, array $indexes = [], array $foreign_keys = []) {
        $table_name = $this->sanitizeIdentifier($table_name);
        if ($this->tableExists($table_name)) {
            return [
                'success' => true,
                'message' => "Table '$table_name' already exists",
                'skipped' => true
            ];
        }
        
        $column_defs = [];
        $primary_key = null;
        
        foreach ($columns as $col_name => $col_def) {
            if (is_array($col_def)) {
                // Structured column definition
                $def = "`$col_name` " . $col_def['type'];
                
                if (isset($col_def['nullable']) && !$col_def['nullable']) {
                    $def .= ' NOT NULL';
                }
                
                if (isset($col_def['default'])) {
                    $default = $col_def['default'];
                    if ($default === 'CURRENT_TIMESTAMP') {
                        $def .= " DEFAULT $default";
                    } else {
                        $def .= " DEFAULT '$default'";
                    }
                }
                
                if (isset($col_def['auto_increment']) && $col_def['auto_increment']) {
                    $def .= ' AUTO_INCREMENT';
                    $primary_key = $col_name;
                }
                
                if (isset($col_def['primary']) && $col_def['primary']) {
                    $primary_key = $col_name;
                }
                
                $column_defs[] = $def;
            } else {
                // String column definition
                $column_defs[] = "`$col_name` $col_def";
            }
        }
        
        // Add primary key
        if ($primary_key) {
            $column_defs[] = "PRIMARY KEY (`$primary_key`)";
        }
        
        // Add indexes
        foreach ($indexes as $index_name => $index_def) {
            if (is_array($index_def)) {
                $unique = !empty($index_def['unique']) ? 'UNIQUE ' : '';
                $cols = implode('`, `', $index_def['columns']);
                $column_defs[] = "{$unique}INDEX `$index_name` (`$cols`)";
            } else {
                $column_defs[] = "INDEX `$index_name` (`$index_def`)";
            }
        }
        
        // Add foreign keys
        foreach ($foreign_keys as $fk_name => $fk_def) {
            $column_defs[] = sprintf(
                "CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`) ON DELETE %s ON UPDATE %s",
                $fk_name,
                $fk_def['column'],
                $fk_def['ref_table'],
                $fk_def['ref_column'],
                $fk_def['on_delete'] ?? 'CASCADE',
                $fk_def['on_update'] ?? 'CASCADE'
            );
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (\n    " . 
               implode(",\n    ", $column_defs) . 
               "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Table created: $table_name",
            'sql' => $sql
        ];
    }
    
    /**
     * Drop a table
     * 
     * @param string $table_name Table name to drop
     * @return array Result with success status
     */
    public function dropTable($table_name) {
        $table_name = $this->sanitizeIdentifier($table_name);
        if (!$this->tableExists($table_name)) {
            return [
                'success' => true,
                'message' => "Table '$table_name' does not exist",
                'skipped' => true
            ];
        }
        
        $sql = "DROP TABLE `$table_name`";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Table dropped: $table_name",
            'sql' => $sql
        ];
    }
    
    /**
     * Add an index to a table
     * 
     * @param string $table Table name
     * @param string $index_name Index name
     * @param array $columns Columns to include in the index
     * @param bool $unique Whether the index should be unique
     * @return array Result with success status
     */
    public function addIndex($table, $index_name, array $columns, $unique = false) {
        $table = $this->sanitizeIdentifier($table);
        $index_name = $this->sanitizeIdentifier($index_name);
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        // Check if index already exists
        $stmt = $this->pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$index_name]);
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => "Index '$index_name' already exists on table '$table'",
                'skipped' => true
            ];
        }
        
        $unique_str = $unique ? 'UNIQUE ' : '';
        $sanitized_cols = array_map(function($c) { return $this->sanitizeIdentifier($c); }, $columns);
        $cols = '`' . implode('`, `', $sanitized_cols) . '`';
        $sql = "CREATE {$unique_str}INDEX `$index_name` ON `$table` ($cols)";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Index created: $index_name on $table",
            'sql' => $sql
        ];
    }
    
    /**
     * Drop an index from a table
     * 
     * @param string $table Table name
     * @param string $index_name Index name to drop
     * @return array Result with success status
     */
    public function dropIndex($table, $index_name) {
        $table = $this->sanitizeIdentifier($table);
        $index_name = $this->sanitizeIdentifier($index_name);
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        // Check if index exists
        $stmt = $this->pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$index_name]);
        if ($stmt->rowCount() == 0) {
            return [
                'success' => true,
                'message' => "Index '$index_name' does not exist on table '$table'",
                'skipped' => true
            ];
        }
        
        $sql = "DROP INDEX `$index_name` ON `$table`";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Index dropped: $index_name from $table",
            'sql' => $sql
        ];
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
     * @return array Result with success status
     */
    public function addForeignKey($table, $constraint_name, $column, $ref_table, $ref_column, $on_delete = 'CASCADE', $on_update = 'CASCADE') {
        $table = $this->sanitizeIdentifier($table);
        $constraint_name = $this->sanitizeIdentifier($constraint_name);
        $column = $this->sanitizeIdentifier($column);
        $ref_table = $this->sanitizeIdentifier($ref_table);
        $ref_column = $this->sanitizeIdentifier($ref_column);
        
        // Validate ON DELETE/ON UPDATE actions
        $valid_actions = ['CASCADE', 'SET NULL', 'NO ACTION', 'RESTRICT', 'SET DEFAULT'];
        $on_delete = strtoupper($on_delete);
        $on_update = strtoupper($on_update);
        if (!in_array($on_delete, $valid_actions)) {
            throw new Exception("Invalid ON DELETE action: $on_delete");
        }
        if (!in_array($on_update, $valid_actions)) {
            throw new Exception("Invalid ON UPDATE action: $on_update");
        }
        
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        if (!$this->tableExists($ref_table)) {
            return [
                'success' => true,
                'message' => "Referenced table '$ref_table' does not exist",
                'skipped' => true
            ];
        }
        
        // Check if constraint already exists
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ?
        ");
        $stmt->execute([$table, $constraint_name]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => true,
                'message' => "Foreign key '$constraint_name' already exists on table '$table'",
                'skipped' => true
            ];
        }
        
        $sql = sprintf(
            "ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`) ON DELETE %s ON UPDATE %s",
            $table,
            $constraint_name,
            $column,
            $ref_table,
            $ref_column,
            $on_delete,
            $on_update
        );
        
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Foreign key added: $constraint_name on $table",
            'sql' => $sql
        ];
    }
    
    /**
     * Drop a foreign key constraint
     * 
     * @param string $table Table name
     * @param string $constraint_name Constraint name to drop
     * @return array Result with success status
     */
    public function dropForeignKey($table, $constraint_name) {
        $table = $this->sanitizeIdentifier($table);
        $constraint_name = $this->sanitizeIdentifier($constraint_name);
        if (!$this->tableExists($table)) {
            return [
                'success' => true,
                'message' => "Table '$table' does not exist",
                'skipped' => true
            ];
        }
        
        // Check if constraint exists
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ?
        ");
        $stmt->execute([$table, $constraint_name]);
        if ($stmt->fetchColumn() == 0) {
            return [
                'success' => true,
                'message' => "Foreign key '$constraint_name' does not exist on table '$table'",
                'skipped' => true
            ];
        }
        
        $sql = "ALTER TABLE `$table` DROP FOREIGN KEY `$constraint_name`";
        $this->pdo->exec($sql);
        
        return [
            'success' => true,
            'message' => "Foreign key dropped: $constraint_name from $table",
            'sql' => $sql
        ];
    }
    
    /**
     * Execute a data migration (INSERT, UPDATE, DELETE)
     * 
     * @param string $sql_statement The SQL statement to execute
     * @param string $description Description of what the migration does
     * @return array Result with success status
     */
    public function executeDataMigration($sql_statement, $description = '') {
        // Execute the SQL statement
        $affected = $this->pdo->exec($sql_statement);
        
        $message = $description ? $description : "Data migration executed";
        if ($affected !== false) {
            $message .= " ({$affected} rows affected)";
        }
        
        return [
            'success' => true,
            'message' => $message,
            'sql' => $sql_statement,
            'affected_rows' => $affected
        ];
    }
    
    /**
     * Get the current schema version from the database
     * 
     * @return string|null The current schema version or null if not set
     */
    public function getSchemaVersion() {
        try {
            $stmt = $this->pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'schema_version'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Set the schema version in the database
     * 
     * @param string $version The version to set
     * @return bool Success status
     */
    public function setSchemaVersion($version) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES ('schema_version', ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            return $stmt->execute([$version, $version]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Compare two schemas and generate migration steps
     * 
     * @param array $source_schema Source schema (current)
     * @param array $target_schema Target schema (desired)
     * @return array Array of migration steps needed
     */
    public function compareSchemas($source_schema, $target_schema) {
        $migrations = [];
        
        // Find tables to create
        foreach ($target_schema['tables'] as $table_name => $table_def) {
            if (!isset($source_schema['tables'][$table_name])) {
                $migrations[] = [
                    'type' => 'create_table',
                    'table' => $table_name,
                    'definition' => $table_def
                ];
            } else {
                // Compare columns
                $source_cols = $source_schema['tables'][$table_name]['columns'] ?? [];
                $target_cols = $table_def['columns'] ?? [];
                
                // Find columns to add
                foreach ($target_cols as $col_name => $col_def) {
                    if (!isset($source_cols[$col_name])) {
                        $migrations[] = [
                            'type' => 'add_column',
                            'table' => $table_name,
                            'column_definition' => $col_def['definition'] ?? "$col_name {$col_def['type']}"
                        ];
                    }
                }
            }
        }
        
        // Find tables to drop (only if explicitly marked for removal)
        foreach ($source_schema['tables'] as $table_name => $table_def) {
            if (!isset($target_schema['tables'][$table_name]) && isset($target_schema['drop_tables']) && in_array($table_name, $target_schema['drop_tables'])) {
                $migrations[] = [
                    'type' => 'drop_table',
                    'table' => $table_name
                ];
            }
        }
        
        return $migrations;
    }
}
