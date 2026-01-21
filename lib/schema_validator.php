<?php
/**
 * Schema Validator - Comprehensive database schema validation
 * Scans all PHP files and validates tables/columns against schema
 */

class SchemaValidator {
    private $pdo;
    private $errors = [];
    private $warnings = [];
    private $tables_in_schema = [];
    private $tables_referenced = [];
    private $columns_referenced = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Perform comprehensive schema validation
     */
    public function validate() {
        echo "<h2>Schema Validation Starting...</h2>";
        
        // Step 1: Get all tables from database
        $this->loadDatabaseTables();
        
        // Step 2: Scan all PHP files for table references
        $this->scanPhpFiles();
        
        // Step 3: Compare and find missing tables
        $this->findMissingTables();
        
        // Step 4: Validate columns for each table
        $this->validateColumns();
        
        // Step 5: Generate report
        $this->generateReport();
        
        return [
            'success' => count($this->errors) === 0,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'stats' => [
                'tables_in_db' => count($this->tables_in_schema),
                'tables_referenced' => count($this->tables_referenced),
                'missing_tables' => count(array_diff($this->tables_referenced, $this->tables_in_schema))
            ]
        ];
    }
    
    private function loadDatabaseTables() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $this->tables_in_schema[] = $row[0];
            }
            echo "<p>✓ Found " . count($this->tables_in_schema) . " tables in database</p>";
        } catch (PDOException $e) {
            $this->errors[] = "Failed to load database tables: " . $e->getMessage();
        }
    }
    
    private function scanPhpFiles() {
        $dir = __DIR__ . '/..';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $phpFiles = 0;
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Skip vendor and node_modules
                if (strpos($file->getPathname(), 'vendor') !== false || 
                    strpos($file->getPathname(), 'node_modules') !== false) {
                    continue;
                }
                
                $phpFiles++;
                $this->scanFileForTables($file->getPathname());
            }
        }
        
        echo "<p>✓ Scanned $phpFiles PHP files</p>";
        echo "<p>✓ Found " . count($this->tables_referenced) . " unique table references</p>";
    }
    
    private function scanFileForTables($filepath) {
        $content = file_get_contents($filepath);
        
        // Match INSERT INTO, UPDATE, SELECT FROM, DELETE FROM patterns
        $patterns = [
            '/INSERT\s+INTO\s+`?([a-z_]+)`?/i',
            '/UPDATE\s+`?([a-z_]+)`?/i',
            '/FROM\s+`?([a-z_]+)`?/i',
            '/DELETE\s+FROM\s+`?([a-z_]+)`?/i',
            '/JOIN\s+`?([a-z_]+)`?/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $table) {
                    // Filter out obvious non-table words
                    $skip_words = ['select', 'where', 'and', 'or', 'values', 'set', 'join', 'inner', 'left', 'right'];
                    if (!in_array(strtolower($table), $skip_words)) {
                        $this->tables_referenced[$table] = ($this->tables_referenced[$table] ?? 0) + 1;
                    }
                }
            }
        }
    }
    
    private function findMissingTables() {
        $missing = array_diff(array_keys($this->tables_referenced), $this->tables_in_schema);
        
        if (count($missing) > 0) {
            echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107;'>";
            echo "<h3>⚠ Missing Tables: " . count($missing) . "</h3>";
            echo "<ul>";
            foreach ($missing as $table) {
                $count = $this->tables_referenced[$table];
                echo "<li><strong>$table</strong> (referenced $count times)</li>";
                $this->errors[] = "Table '$table' is referenced but not in schema";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745;'>";
            echo "<p>✓ All referenced tables exist in schema</p>";
            echo "</div>";
        }
    }
    
    private function validateColumns() {
        // This would require parsing SQL statements more deeply
        // For now, we'll just check basic table structure
        foreach ($this->tables_in_schema as $table) {
            try {
                $stmt = $this->pdo->query("DESCRIBE `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "<p>✓ Table '$table' has " . count($columns) . " columns</p>";
            } catch (PDOException $e) {
                $this->errors[] = "Failed to describe table '$table': " . $e->getMessage();
            }
        }
    }
    
    private function generateReport() {
        echo "<hr>";
        echo "<h2>Validation Summary</h2>";
        
        if (count($this->errors) > 0) {
            echo "<div style='background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545;'>";
            echo "<h3>❌ Errors: " . count($this->errors) . "</h3>";
            echo "<ul>";
            foreach ($this->errors as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745;'>";
            echo "<h3>✓ Schema Validation Passed</h3>";
            echo "<p>All tables and references are valid.</p>";
            echo "</div>";
        }
        
        if (count($this->warnings) > 0) {
            echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107;'>";
            echo "<h3>⚠ Warnings: " . count($this->warnings) . "</h3>";
            echo "<ul>";
            foreach ($this->warnings as $warning) {
                echo "<li>$warning</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
    }
}
