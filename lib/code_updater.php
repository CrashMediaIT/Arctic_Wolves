<?php
/**
 * Code Updater
 * Updates code references after database/file migrations
 */

class CodeUpdater {
    private $base_path;
    private $log = [];
    
    public function __construct($base_path) {
        $this->base_path = $base_path;
    }
    
    /**
     * Update code references for table rename
     */
    public function updateTableReferences($old_name, $new_name) {
        $files_updated = 0;
        $references_updated = 0;
        
        // Find all PHP files
        $php_files = $this->findPhpFiles();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $original = $content;
            
            // Update table references in various SQL patterns
            $patterns = [
                // FROM clause
                '/FROM\s+`?' . preg_quote($old_name) . '`?/i',
                // INTO clause
                '/INTO\s+`?' . preg_quote($old_name) . '`?/i',
                // UPDATE clause
                '/UPDATE\s+`?' . preg_quote($old_name) . '`?/i',
                // JOIN clause
                '/JOIN\s+`?' . preg_quote($old_name) . '`?/i',
                // Table name in string literals
                '/"' . preg_quote($old_name) . '"/i',
                "/'" . preg_quote($old_name) . "'/i",
            ];
            
            foreach ($patterns as $pattern) {
                $count = 0;
                $content = preg_replace_callback($pattern, function($matches) use ($new_name, &$count) {
                    $count++;
                    // Preserve the original case and backticks
                    if (strpos($matches[0], '`') !== false) {
                        return str_replace('`' . $old_name . '`', '`' . $new_name . '`', $matches[0]);
                    } elseif (strpos($matches[0], '"') !== false) {
                        return str_replace('"' . $old_name . '"', '"' . $new_name . '"', $matches[0]);
                    } elseif (strpos($matches[0], "'") !== false) {
                        return str_replace("'" . $old_name . "'", "'" . $new_name . "'", $matches[0]);
                    } else {
                        return str_ireplace($old_name, $new_name, $matches[0]);
                    }
                }, $content);
                
                $references_updated += $count;
            }
            
            if ($content !== $original) {
                file_put_contents($file, $content);
                $files_updated++;
                $this->log[] = "Updated: " . str_replace($this->base_path . '/', '', $file);
            }
        }
        
        return [
            'files_updated' => $files_updated,
            'references_updated' => $references_updated,
            'log' => $this->log
        ];
    }
    
    /**
     * Update code references for column rename
     */
    public function updateColumnReferences($table, $old_name, $new_name) {
        $files_updated = 0;
        $references_updated = 0;
        
        $php_files = $this->findPhpFiles();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $original = $content;
            
            // Update column references in SQL and array keys
            $patterns = [
                // Column in SELECT/WHERE/SET with backticks
                '/`' . preg_quote($old_name) . '`/i',
                // Column in array access
                '/\[[\'"]\s*' . preg_quote($old_name) . '\s*[\'"]\]/i',
                // Column in fetch
                '/fetch\w*\(\s*[\'"]' . preg_quote($old_name) . '[\'"]\s*\)/i',
            ];
            
            foreach ($patterns as $pattern) {
                $count = 0;
                $content = preg_replace_callback($pattern, function($matches) use ($new_name, &$count) {
                    $count++;
                    return str_replace($old_name, $new_name, $matches[0]);
                }, $content);
                
                $references_updated += $count;
            }
            
            if ($content !== $original) {
                file_put_contents($file, $content);
                $files_updated++;
                $this->log[] = "Updated: " . str_replace($this->base_path . '/', '', $file);
            }
        }
        
        return [
            'files_updated' => $files_updated,
            'references_updated' => $references_updated,
            'log' => $this->log
        ];
    }
    
    /**
     * Update file path references
     */
    public function updateFilePathReferences($old_path, $new_path) {
        $files_updated = 0;
        $references_updated = 0;
        
        $php_files = $this->findPhpFiles();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $original = $content;
            
            // Update file path references
            $patterns = [
                // require/include statements
                '/(require_once|require|include_once|include)\s*[\'"]' . preg_quote($old_path) . '[\'"]/i',
                // String references
                '/[\'"]' . preg_quote($old_path) . '[\'"]/i',
            ];
            
            foreach ($patterns as $pattern) {
                $count = 0;
                $content = preg_replace_callback($pattern, function($matches) use ($old_path, $new_path, &$count) {
                    $count++;
                    return str_replace($old_path, $new_path, $matches[0]);
                }, $content);
                
                $references_updated += $count;
            }
            
            if ($content !== $original) {
                file_put_contents($file, $content);
                $files_updated++;
                $this->log[] = "Updated: " . str_replace($this->base_path . '/', '', $file);
            }
        }
        
        return [
            'files_updated' => $files_updated,
            'references_updated' => $references_updated,
            'log' => $this->log
        ];
    }
    
    /**
     * Find all PHP files in the project
     */
    private function findPhpFiles() {
        $files = [];
        $exclude_dirs = ['vendor', 'tmp', 'cache', 'logs', 'uploads', 'sessions'];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->base_path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $path = $file->getPathname();
                
                // Skip excluded directories
                $skip = false;
                foreach ($exclude_dirs as $exclude) {
                    if (strpos($path, '/' . $exclude . '/') !== false) {
                        $skip = true;
                        break;
                    }
                }
                
                if (!$skip) {
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Update setup.php validation list
     */
    public function updateSetupValidation($view_file) {
        $setup_file = $this->base_path . '/setup.php';
        if (!file_exists($setup_file)) {
            return false;
        }
        
        $content = file_get_contents($setup_file);
        
        // Find the expected views array
        if (preg_match('/\$expected_views\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $views_array = $matches[1];
            
            // Check if view already exists
            if (strpos($views_array, "'$view_file'") === false) {
                // Add new view to array
                $new_views_array = rtrim($views_array) . ",\n    '$view_file'";
                $content = str_replace($views_array, $new_views_array, $content);
                
                file_put_contents($setup_file, $content);
                $this->log[] = "Added '$view_file' to setup.php validation";
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get update log
     */
    public function getLog() {
        return $this->log;
    }
    
    /**
     * Clear log
     */
    public function clearLog() {
        $this->log = [];
    }
}
