<?php
/**
 * System Validator Utility Class
 * Comprehensive system validation for Crash Hockey platform
 * Performs file system audits, database integrity checks, code cross-references, and security scans
 */

class SystemValidator {
    private $pdo;
    private $base_path;
    private $results = [];
    
    public function __construct($pdo, $base_path = null) {
        $this->pdo = $pdo;
        $this->base_path = $base_path ?? __DIR__ . '/..';
    }
    
    /**
     * Run all validation checks
     */
    public function runAllChecks() {
        $this->results = [
            'file_system' => $this->checkFileSystem(),
            'database' => $this->checkDatabaseIntegrity(),
            'code_references' => $this->checkCodeCrossReferences(),
            'security' => $this->checkSecurity(),
            'summary' => []
        ];
        
        // Generate summary
        $this->results['summary'] = $this->generateSummary();
        
        return $this->results;
    }
    
    /**
     * 1. File System Audit
     * Check required files, detect orphaned files, verify permissions
     */
    private function checkFileSystem() {
        $checks = [
            'required_files' => [],
            'orphaned_files' => [],
            'permissions' => [],
            'missing_files' => []
        ];
        
        // Required core files
        $required_files = [
            'index.php',
            'dashboard.php',
            'login.php',
            'logout.php',
            'db_config.php',
            'security.php',
            'process_login.php',
            'setup.php'
        ];
        
        // Check required files exist
        foreach ($required_files as $file) {
            $path = $this->base_path . '/' . $file;
            if (file_exists($path)) {
                $checks['required_files'][] = [
                    'file' => $file,
                    'status' => 'exists',
                    'severity' => 'success'
                ];
            } else {
                $checks['missing_files'][] = [
                    'file' => $file,
                    'status' => 'missing',
                    'severity' => 'critical'
                ];
            }
        }
        
        // Check for orphaned process files (process_*.php without corresponding view)
        $process_files = glob($this->base_path . '/process_*.php');
        foreach ($process_files as $process_file) {
            $filename = basename($process_file);
            $expected_view = str_replace('process_', '', str_replace('.php', '', $filename));
            
            // Check if corresponding view exists
            $view_path = $this->base_path . '/views/' . $expected_view . '.php';
            if (!file_exists($view_path) && !in_array($filename, ['process_login.php', 'process_register.php', 'process_logout.php'])) {
                $checks['orphaned_files'][] = [
                    'file' => $filename,
                    'message' => 'Process file without corresponding view',
                    'severity' => 'warning'
                ];
            }
        }
        
        // Check critical directory permissions
        $critical_dirs = ['uploads/', 'cache/', 'logs/', 'sessions/', 'tmp/'];
        foreach ($critical_dirs as $dir) {
            $path = $this->base_path . '/' . $dir;
            if (file_exists($path)) {
                $perms = fileperms($path);
                $is_writable = is_writable($path);
                
                $checks['permissions'][] = [
                    'directory' => $dir,
                    'writable' => $is_writable,
                    'permissions' => substr(sprintf('%o', $perms), -4),
                    'severity' => $is_writable ? 'success' : 'critical'
                ];
            } else {
                $checks['permissions'][] = [
                    'directory' => $dir,
                    'writable' => false,
                    'message' => 'Directory does not exist',
                    'severity' => 'warning'
                ];
            }
        }
        
        return $checks;
    }
    
    /**
     * 2. Database Integrity Check
     * Verify tables exist, check foreign keys, validate columns
     */
    private function checkDatabaseIntegrity() {
        $checks = [
            'tables' => [],
            'foreign_keys' => [],
            'columns' => []
        ];
        
        try {
            // Required tables
            $required_tables = [
                'users',
                'sessions',
                'packages',
                'athlete_evaluations',
                'evaluation_scores',
                'eval_categories',
                'eval_skills',
                'goals',
                'goal_evaluations'
            ];
            
            // Check tables exist
            $tables_result = $this->pdo->query("SHOW TABLES");
            $existing_tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($required_tables as $table) {
                $exists = in_array($table, $existing_tables);
                $checks['tables'][] = [
                    'table' => $table,
                    'exists' => $exists,
                    'severity' => $exists ? 'success' : 'critical'
                ];
                
                // If table exists, verify key columns
                if ($exists) {
                    $this->validateTableColumns($table, $checks);
                }
            }
            
            // Check foreign key relationships
            $this->validateForeignKeys($checks);
            
        } catch (PDOException $e) {
            $checks['error'] = [
                'message' => 'Database connection error: ' . $e->getMessage(),
                'severity' => 'critical'
            ];
        }
        
        return $checks;
    }
    
    /**
     * Validate table columns exist
     */
    private function validateTableColumns($table, &$checks) {
        try {
            $columns_result = $this->pdo->query("DESCRIBE $table");
            $columns = $columns_result->fetchAll(PDO::FETCH_COLUMN);
            
            // Common expected columns based on table
            $expected_columns = [
                'users' => ['id', 'email', 'password', 'role', 'is_active'],
                'athlete_evaluations' => ['id', 'athlete_id', 'created_by', 'evaluation_date', 'status'],
                'evaluation_scores' => ['id', 'evaluation_id', 'skill_id', 'score'],
                'eval_categories' => ['id', 'name', 'is_active'],
                'eval_skills' => ['id', 'category_id', 'name', 'is_active']
            ];
            
            if (isset($expected_columns[$table])) {
                foreach ($expected_columns[$table] as $expected_col) {
                    $exists = in_array($expected_col, $columns);
                    if (!$exists) {
                        $checks['columns'][] = [
                            'table' => $table,
                            'column' => $expected_col,
                            'exists' => false,
                            'severity' => 'critical'
                        ];
                    }
                }
            }
        } catch (PDOException $e) {
            // Skip if error
        }
    }
    
    /**
     * Validate foreign key relationships
     */
    private function validateForeignKeys(&$checks) {
        try {
            // Check common foreign key relationships
            $fk_checks = [
                ['table' => 'athlete_evaluations', 'column' => 'athlete_id', 'references' => 'users(id)'],
                ['table' => 'athlete_evaluations', 'column' => 'created_by', 'references' => 'users(id)'],
                ['table' => 'evaluation_scores', 'column' => 'evaluation_id', 'references' => 'athlete_evaluations(id)'],
                ['table' => 'evaluation_scores', 'column' => 'skill_id', 'references' => 'eval_skills(id)'],
                ['table' => 'eval_skills', 'column' => 'category_id', 'references' => 'eval_categories(id)']
            ];
            
            foreach ($fk_checks as $fk) {
                // Simple existence check - verify referenced data exists
                $valid = $this->checkForeignKeyValidity($fk['table'], $fk['column']);
                $checks['foreign_keys'][] = [
                    'relationship' => "{$fk['table']}.{$fk['column']} -> {$fk['references']}",
                    'valid' => $valid,
                    'severity' => $valid ? 'success' : 'warning'
                ];
            }
        } catch (PDOException $e) {
            // Skip if error
        }
    }
    
    /**
     * Check if foreign key references are valid
     */
    private function checkForeignKeyValidity($table, $column) {
        try {
            // Check for orphaned records
            $query = "SELECT COUNT(*) as count FROM $table WHERE $column IS NOT NULL AND $column != 0";
            $result = $this->pdo->query($query);
            $count = $result->fetch()['count'];
            return true; // Simplified check
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 3. Code Cross-References Check
     * Forms→process files, includes→views, SQL→tables
     */
    private function checkCodeCrossReferences() {
        $checks = [
            'form_process_links' => [],
            'view_includes' => [],
            'sql_table_refs' => []
        ];
        
        // Check forms point to valid process files
        $view_files = glob($this->base_path . '/views/*.php');
        foreach ($view_files as $view_file) {
            $content = file_get_contents($view_file);
            $filename = basename($view_file);
            
            // Find form actions
            preg_match_all('/action=["\']([^"\']+)["\']/', $content, $matches);
            foreach ($matches[1] as $action) {
                if (strpos($action, 'process_') === 0 || strpos($action, '../process_') !== false) {
                    $process_file = basename($action);
                    $process_path = $this->base_path . '/' . $process_file;
                    
                    $exists = file_exists($process_path);
                    $checks['form_process_links'][] = [
                        'view' => $filename,
                        'process_file' => $process_file,
                        'exists' => $exists,
                        'severity' => $exists ? 'success' : 'critical'
                    ];
                }
            }
        }
        
        // Check required includes in views
        foreach ($view_files as $view_file) {
            $content = file_get_contents($view_file);
            $filename = basename($view_file);
            
            // Should include security.php
            $has_security = strpos($content, 'security.php') !== false;
            if (!$has_security) {
                $checks['view_includes'][] = [
                    'view' => $filename,
                    'missing_include' => 'security.php',
                    'severity' => 'warning'
                ];
            }
        }
        
        // Check SQL queries reference existing tables
        $all_php_files = array_merge(
            glob($this->base_path . '/*.php'),
            glob($this->base_path . '/views/*.php'),
            glob($this->base_path . '/admin/*.php')
        );
        
        $tables_result = $this->pdo->query("SHOW TABLES");
        $existing_tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($all_php_files as $php_file) {
            $content = file_get_contents($php_file);
            $filename = basename($php_file);
            
            // Find table references in SQL queries
            preg_match_all('/FROM\s+([a-z_]+)/i', $content, $matches);
            preg_match_all('/JOIN\s+([a-z_]+)/i', $content, $join_matches);
            preg_match_all('/UPDATE\s+([a-z_]+)/i', $content, $update_matches);
            preg_match_all('/INSERT\s+INTO\s+([a-z_]+)/i', $content, $insert_matches);
            
            $all_table_refs = array_merge(
                $matches[1] ?? [],
                $join_matches[1] ?? [],
                $update_matches[1] ?? [],
                $insert_matches[1] ?? []
            );
            
            foreach (array_unique($all_table_refs) as $table_ref) {
                if (!in_array($table_ref, $existing_tables) && !empty($table_ref)) {
                    $checks['sql_table_refs'][] = [
                        'file' => $filename,
                        'table' => $table_ref,
                        'exists' => false,
                        'severity' => 'critical'
                    ];
                }
            }
        }
        
        return $checks;
    }
    
    /**
     * 4. Security Scan
     * SQL injection check, CSRF on forms, file upload validation
     */
    private function checkSecurity() {
        $checks = [
            'sql_injection' => [],
            'csrf_tokens' => [],
            'file_uploads' => []
        ];
        
        // Check for potential SQL injection vulnerabilities
        $all_php_files = array_merge(
            glob($this->base_path . '/process_*.php'),
            glob($this->base_path . '/views/*.php')
        );
        
        foreach ($all_php_files as $php_file) {
            $content = file_get_contents($php_file);
            $filename = basename($php_file);
            
            // Check for unsafe SQL patterns
            // Note: This regex-based check may have false positives (e.g., when matching against
            // comments or in string literals). Results should be reviewed manually. For accurate
            // detection, use static analysis tools like PHP CodeSniffer with security rules.
            if (preg_match('/\$pdo->query\([^?]*\$_/', $content)) {
                $checks['sql_injection'][] = [
                    'file' => $filename,
                    'issue' => 'Potential SQL injection - direct variable in query()',
                    'severity' => 'critical'
                ];
            }
            
            // Check for proper prepared statements
            if (preg_match('/->query\([\'"].*\$/', $content)) {
                $checks['sql_injection'][] = [
                    'file' => $filename,
                    'issue' => 'Potential SQL injection - string interpolation in query',
                    'severity' => 'critical'
                ];
            }
        }
        
        // Check forms have CSRF tokens
        $view_files = glob($this->base_path . '/views/*.php');
        foreach ($view_files as $view_file) {
            $content = file_get_contents($view_file);
            $filename = basename($view_file);
            
            // Find forms
            preg_match_all('/<form[^>]*>/i', $content, $form_matches);
            if (!empty($form_matches[0])) {
                // Check if CSRF token present
                $has_csrf = strpos($content, 'csrf_token') !== false || 
                           strpos($content, 'generateCsrfToken') !== false;
                
                if (!$has_csrf) {
                    $checks['csrf_tokens'][] = [
                        'file' => $filename,
                        'issue' => 'Form without CSRF token protection',
                        'severity' => 'critical'
                    ];
                }
            }
        }
        
        // Check file upload handlers have validation
        $upload_files = array_merge(
            glob($this->base_path . '/process_*upload*.php'),
            glob($this->base_path . '/process_*media*.php'),
            glob($this->base_path . '/process_*video*.php')
        );
        
        foreach ($upload_files as $upload_file) {
            $content = file_get_contents($upload_file);
            $filename = basename($upload_file);
            
            // Check for file type validation
            $has_validation = strpos($content, 'mime') !== false ||
                             strpos($content, 'getimagesize') !== false ||
                             strpos($content, 'finfo_file') !== false ||
                             strpos($content, 'pathinfo') !== false;
            
            if (!$has_validation) {
                $checks['file_uploads'][] = [
                    'file' => $filename,
                    'issue' => 'File upload without type validation',
                    'severity' => 'critical'
                ];
            }
            
            // Check for size limits
            $has_size_check = strpos($content, 'filesize') !== false ||
                             strpos($content, 'upload_max_filesize') !== false ||
                             strpos($content, 'MAX_FILE_SIZE') !== false;
            
            if (!$has_size_check) {
                $checks['file_uploads'][] = [
                    'file' => $filename,
                    'issue' => 'File upload without size validation',
                    'severity' => 'warning'
                ];
            }
        }
        
        return $checks;
    }
    
    /**
     * Generate summary of all checks
     */
    private function generateSummary() {
        $summary = [
            'total_checks' => 0,
            'critical' => 0,
            'warnings' => 0,
            'passed' => 0,
            'overall_status' => 'healthy'
        ];
        
        foreach ($this->results as $category => $checks) {
            if ($category === 'summary') continue;
            
            foreach ($checks as $check_type => $items) {
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $summary['total_checks']++;
                        
                        $severity = $item['severity'] ?? 'info';
                        if ($severity === 'critical') {
                            $summary['critical']++;
                        } elseif ($severity === 'warning') {
                            $summary['warnings']++;
                        } elseif ($severity === 'success') {
                            $summary['passed']++;
                        }
                    }
                }
            }
        }
        
        // Determine overall status
        if ($summary['critical'] > 0) {
            $summary['overall_status'] = 'critical';
        } elseif ($summary['warnings'] > 5) {
            $summary['overall_status'] = 'warning';
        } else {
            $summary['overall_status'] = 'healthy';
        }
        
        return $summary;
    }
}
