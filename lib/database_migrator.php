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
     */
    public function tableExists($table_name) {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$table_name'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Check if column exists in table
     */
    public function columnExists($table_name, $column_name) {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `$table_name` LIKE '$column_name'");
            return $stmt->rowCount() > 0;
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
                    $migration['column_definition']
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
                
            default:
                throw new Exception("Unknown migration type: $type");
        }
    }
    
    /**
     * Rename table
     */
    public function renameTable($old_name, $new_name) {
        if (!$this->tableExists($old_name)) {
            throw new Exception("Table '$old_name' does not exist");
        }
        
        if ($this->tableExists($new_name)) {
            throw new Exception("Table '$new_name' already exists");
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
        if (!$this->tableExists($table)) {
            throw new Exception("Table '$table' does not exist");
        }
        
        if (!$this->columnExists($table, $old_name)) {
            throw new Exception("Column '$old_name' does not exist in table '$table'");
        }
        
        if ($this->columnExists($table, $new_name)) {
            throw new Exception("Column '$new_name' already exists in table '$table'");
        }
        
        // Get current column definition if not provided
        if (!$definition) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `$table` LIKE '$old_name'");
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
    public function addColumn($table, $column_definition) {
        if (!$this->tableExists($table)) {
            throw new Exception("Table '$table' does not exist");
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
        if (!$this->tableExists($table)) {
            throw new Exception("Table '$table' does not exist");
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
        if (!$this->tableExists($table)) {
            throw new Exception("Table '$table' does not exist");
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
}
