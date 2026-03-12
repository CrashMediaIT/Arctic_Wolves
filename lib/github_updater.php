<?php
/**
 * GitHub Updater - Manages system updates from GitHub repository
 * Supports both public and private repositories with GitHub authentication
 */

class GitHubUpdater {
    private $pdo;
    private $repo_owner = 'CrashMediaIT';
    private $repo_name = 'Arctic_Wolves';
    private $base_path;
    private $github_token;
    
    // Files/directories to exclude from updates
    private $excluded_paths = [
        'db_config.php',
        'uploads/',
        '.git/',
        '.env',
        '.credential_key',
        'arctic_wolves.env',
        'config.php',
        'vendor/',
        'node_modules/',
        'stripe-php/',
        '.update_deferred.json',
    ];
    
    // Files that are part of the active update execution chain.
    // These cannot be safely overwritten while the update is running,
    // so they are deferred and applied after the response is sent.
    private $active_update_files = [
        'lib/github_updater.php',
        'process_settings.php',
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->base_path = realpath(__DIR__ . '/..');
        $this->loadGitHubToken();
    }
    
    /**
     * Load GitHub token from database settings
     */
    private function loadGitHubToken() {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'github_token'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $raw_token = $result['setting_value'] ?? '';
        $this->github_token = (function_exists('decryptCredential') && !empty($raw_token)) ? decryptCredential($raw_token) : $raw_token;
    }
    
    /**
     * Test GitHub connection and authentication
     */
    public function testGitHubConnection() {
        try {
            $url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}";
            $headers = ['User-Agent: Arctic-Wolves-Updater'];
            
            if (!empty($this->github_token)) {
                $headers[] = "Authorization: token {$this->github_token}";
            }
            
            $response = $this->makeGitHubRequest($url, $headers);
            
            if ($response === false) {
                return ['success' => false, 'message' => 'Failed to connect to GitHub'];
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['message']) && $data['message'] === 'Not Found') {
                return ['success' => false, 'message' => 'Repository not found or access denied'];
            }
            
            if (isset($data['private']) && $data['private'] && empty($this->github_token)) {
                return ['success' => false, 'message' => 'Repository is private. Please configure GitHub token.'];
            }
            
            return [
                'success' => true, 
                'message' => 'Successfully connected to GitHub repository',
                'repo_name' => $data['full_name'] ?? 'Unknown',
                'private' => $data['private'] ?? false
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check for available updates
     */
    public function checkForUpdates() {
        try {
            // Get latest commit from main/master branch
            $url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/commits/main";
            $headers = ['User-Agent: Arctic-Wolves-Updater'];
            
            if (!empty($this->github_token)) {
                $headers[] = "Authorization: token {$this->github_token}";
            }
            
            $response = $this->makeGitHubRequest($url, $headers);
            
            if ($response === false) {
                // Try master branch if main doesn't exist
                $url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/commits/master";
                $response = $this->makeGitHubRequest($url, $headers);
            }
            
            if ($response === false) {
                return ['success' => false, 'message' => 'Failed to fetch updates from GitHub'];
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['message'])) {
                return ['success' => false, 'message' => $data['message']];
            }
            
            $latest_commit = [
                'sha' => $data['sha'] ?? '',
                'message' => $data['commit']['message'] ?? '',
                'date' => $data['commit']['committer']['date'] ?? '',
                'author' => $data['commit']['author']['name'] ?? '',
            ];
            
            // Get current commit SHA if exists
            $current_sha = $this->getCurrentCommitSha();
            
            return [
                'success' => true,
                'has_updates' => ($current_sha !== $latest_commit['sha']),
                'latest_commit' => $latest_commit,
                'current_sha' => $current_sha
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Apply updates from GitHub using a staging approach.
     * All files are downloaded to a temporary directory first, then copied
     * to the live site only after all downloads succeed. This prevents
     * partial updates from crashing the running site.
     */
    public function applyUpdates() {
        $backup = [];
        $staging_dir = null;
        
        try {
            // Pre-flight connectivity check before starting destructive operations
            $connectivity = $this->testGitHubConnection();
            if (!$connectivity['success']) {
                return ['success' => false, 'message' => 'Cannot connect to GitHub: ' . ($connectivity['message'] ?? 'Network error')];
            }
            
            // Backup persistent files before update
            $backup = $this->backupPersistentFiles();
            
            // Get list of all files in the repository
            $url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/git/trees/main?recursive=1";
            $headers = ['User-Agent: Arctic-Wolves-Updater'];
            
            if (!empty($this->github_token)) {
                $headers[] = "Authorization: token {$this->github_token}";
            }
            
            $response = $this->makeGitHubRequest($url, $headers);
            
            if ($response === false) {
                // Try master branch
                $url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/git/trees/master?recursive=1";
                $response = $this->makeGitHubRequest($url, $headers);
            }
            
            if ($response === false) {
                $this->restorePersistentFiles($backup);
                return ['success' => false, 'message' => 'Failed to fetch repository tree. Network error or GitHub is unreachable.'];
            }
            
            $tree_data = json_decode($response, true);
            
            if (!isset($tree_data['tree']) || !is_array($tree_data['tree'])) {
                $this->restorePersistentFiles($backup);
                return ['success' => false, 'message' => 'Invalid repository structure'];
            }
            
            $repo_files = [];
            $total_files = 0;
            foreach ($tree_data['tree'] as $item) {
                if ($item['type'] === 'blob') {
                    $repo_files[] = $item['path'];
                    if (!$this->isExcludedPath($item['path'])) {
                        $total_files++;
                    }
                }
            }
            
            // Create staging directory for downloads
            $staging_dir = sys_get_temp_dir() . '/arctic_wolves_staging_' . time() . '_' . bin2hex(random_bytes(4));
            if (!mkdir($staging_dir, 0755, true)) {
                $this->restorePersistentFiles($backup);
                return ['success' => false, 'message' => 'Failed to create staging directory'];
            }
            
            // Phase 1: Download repository to staging directory
            $downloaded_count = 0;
            $failed_count = 0;
            $errors = [];
            
            // Try fast zipball download first (single request instead of per-file)
            $zipResult = $this->downloadAndExtractZipball($staging_dir);
            if ($zipResult['success']) {
                $downloaded_count = $zipResult['file_count'];
            } else {
                // Fall back to per-file downloads if zipball fails
                foreach ($tree_data['tree'] as $item) {
                    if ($item['type'] !== 'blob') continue;
                    
                    $file_path = $item['path'];
                    
                    if ($this->isExcludedPath($file_path)) {
                        continue;
                    }
                    
                    $result = $this->downloadFileToStaging($file_path, $staging_dir);
                    if ($result['success']) {
                        $downloaded_count++;
                    } else {
                        $failed_count++;
                        $errors[] = "Failed to download {$file_path}: {$result['message']}";
                    }
                    
                    // Abort if too many downloads are failing (network likely down)
                    if ($failed_count > 0 && $total_files > 0 && ($failed_count / ($downloaded_count + $failed_count)) > 0.5 && $failed_count >= 5) {
                        $this->cleanupDirectory($staging_dir);
                        $this->restorePersistentFiles($backup);
                        return [
                            'success' => false,
                            'message' => "Update aborted: too many download failures ({$failed_count} failed). Site files have been preserved.",
                            'errors' => $errors
                        ];
                    }
                }
                
                // If any downloads failed, abort — do not apply partial updates
                if ($failed_count > 0) {
                    $this->cleanupDirectory($staging_dir);
                    $this->restorePersistentFiles($backup);
                    return [
                        'success' => false,
                        'message' => "Update aborted: {$failed_count} file(s) failed to download. No files were changed.",
                        'errors' => $errors
                    ];
                }
            }
            
            // Phase 2: Copy staged files to live site, deferring active update files
            $updated_count = 0;
            $deferred_files = [];
            foreach ($tree_data['tree'] as $item) {
                if ($item['type'] !== 'blob') continue;
                
                $file_path = $item['path'];
                if ($this->isExcludedPath($file_path)) continue;
                
                $staged_path = $staging_dir . '/' . $file_path;
                $live_path = $this->base_path . '/' . $file_path;
                
                if (!file_exists($staged_path)) continue;
                
                // Defer files that are part of the running update process
                if ($this->isActiveUpdateFile($file_path)) {
                    $deferred_files[$file_path] = $staged_path;
                    continue;
                }
                
                $dir = dirname($live_path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                if (copy($staged_path, $live_path)) {
                    $updated_count++;
                } else {
                    $errors[] = "Failed to apply staged file: {$file_path}";
                }
            }
            
            // Phase 3: Delete files no longer in repo (only if all downloads succeeded)
            $local_files = $this->getLocalFiles();
            $files_to_delete = array_diff($local_files, $repo_files);
            $deleted_count = 0;
            
            foreach ($files_to_delete as $file_path) {
                if ($this->isExcludedPath($file_path)) continue;
                // Do not delete active update files during the running update
                if ($this->isActiveUpdateFile($file_path)) continue;
                
                $result = $this->deleteLocalFile($file_path);
                if ($result['success']) {
                    $deleted_count++;
                } else {
                    $errors[] = "Failed to delete {$file_path}: {$result['message']}";
                }
            }
            
            // Write deferred manifest for active update files before cleaning staging
            $has_deferred = !empty($deferred_files);
            if ($has_deferred) {
                $this->writeDeferredManifest($deferred_files);
            }
            
            // Clean up staging directory
            $this->cleanupDirectory($staging_dir);
            
            // Restore persistent files after update
            $this->restorePersistentFiles($backup);
            
            // Phase 4: Run database schema check to ensure tables match the updated code
            $schema_check = $this->runSchemaCheck();
            
            // Update current commit SHA
            $check_result = $this->checkForUpdates();
            if ($check_result['success']) {
                $this->updateCurrentCommitSha($check_result['latest_commit']['sha']);
            }
            
            $message = "Update completed: {$updated_count} files updated, {$deleted_count} files deleted";
            if ($has_deferred) {
                $message .= " (" . count($deferred_files) . " deferred)";
            }
            
            return [
                'success' => true,
                'message' => $message,
                'updated_count' => $updated_count,
                'deleted_count' => $deleted_count,
                'has_deferred' => $has_deferred,
                'schema_check' => $schema_check,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            if ($staging_dir && is_dir($staging_dir)) {
                $this->cleanupDirectory($staging_dir);
            }
            if (!empty($backup)) {
                $this->restorePersistentFiles($backup);
            }
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Download a single file from GitHub into the staging directory.
     * Files are NOT written to the live site until all downloads succeed.
     */
    private function downloadFileToStaging($file_path, $staging_dir) {
        try {
            $url = "https://raw.githubusercontent.com/{$this->repo_owner}/{$this->repo_name}/main/{$file_path}";
            $headers = ['User-Agent: Arctic-Wolves-Updater'];
            
            if (!empty($this->github_token)) {
                $headers[] = "Authorization: token {$this->github_token}";
            }
            
            $content = $this->makeGitHubRequest($url, $headers);
            
            if ($content === false) {
                return ['success' => false, 'message' => 'Failed to download file'];
            }
            
            $staged_path = $staging_dir . '/' . $file_path;
            $dir = dirname($staged_path);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            if (file_put_contents($staged_path, $content) === false) {
                return ['success' => false, 'message' => 'Failed to write staged file'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Download the entire repository as a zipball and extract to the staging directory.
     * This is much faster than downloading files individually since it uses a single HTTP request.
     *
     * @param string $staging_dir Path to the staging directory
     * @return array Result with success status and file count
     */
    private function downloadAndExtractZipball($staging_dir) {
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'ZipArchive extension not available'];
        }
        
        $zip_path = $staging_dir . '/repo.zip';
        $branches = ['main', 'master'];
        $downloaded = false;
        
        foreach ($branches as $branch) {
            $url = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/zipball/{$branch}";
            $headers = ['User-Agent: Arctic-Wolves-Updater'];
            
            if (!empty($this->github_token)) {
                $headers[] = "Authorization: token {$this->github_token}";
            }
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 120,
                    'follow_location' => true,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ]
            ]);
            
            $content = @file_get_contents($url, false, $context);
            
            if ($content !== false && strlen($content) > 0) {
                // Verify it's a valid ZIP (starts with PK magic bytes) and not an error response
                if (substr($content, 0, 2) === "PK") {
                    if (file_put_contents($zip_path, $content) !== false) {
                        $downloaded = true;
                        break;
                    }
                }
            }
        }
        
        if (!$downloaded) {
            return ['success' => false, 'message' => 'Failed to download repository archive'];
        }
        
        // Extract ZIP to staging
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            @unlink($zip_path);
            return ['success' => false, 'message' => 'Failed to open repository archive'];
        }
        
        // GitHub ZIP contains a root directory prefix (e.g., "Owner-Repo-SHA/")
        // Find and strip this prefix when extracting
        $prefix = '';
        if ($zip->numFiles > 0) {
            $first_entry = $zip->getNameIndex(0);
            $slash_pos = strpos($first_entry, '/');
            if ($slash_pos !== false) {
                $prefix = substr($first_entry, 0, $slash_pos + 1);
            }
        }
        
        $file_count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry_name = $zip->getNameIndex($i);
            
            // Skip directory entries
            if (substr($entry_name, -1) === '/') continue;
            
            // Strip the root prefix
            $relative_path = $entry_name;
            if (!empty($prefix) && strpos($entry_name, $prefix) === 0) {
                $relative_path = substr($entry_name, strlen($prefix));
            }
            
            if (empty($relative_path)) continue;
            
            $dest_path = $staging_dir . '/' . $relative_path;
            $dir = dirname($dest_path);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $entry_content = $zip->getFromIndex($i);
            if ($entry_content !== false) {
                file_put_contents($dest_path, $entry_content);
                $file_count++;
            }
        }
        
        $zip->close();
        @unlink($zip_path);
        
        return ['success' => true, 'file_count' => $file_count];
    }
    
    /**
     * Recursively remove a directory and all its contents
     */
    private function cleanupDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        
        @rmdir($dir);
    }
    
    /**
     * Delete a local file
     */
    private function deleteLocalFile($file_path) {
        try {
            $local_path = $this->base_path . '/' . $file_path;
            
            if (!file_exists($local_path)) {
                return ['success' => true]; // Already deleted
            }
            
            if (unlink($local_path)) {
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => 'Failed to delete file'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get list of local files (relative to base path)
     */
    private function getLocalFiles() {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->base_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                // Normalize path separators for consistency
                $relative_path = str_replace($this->base_path . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relative_path = str_replace('\\', '/', $relative_path); // Convert to forward slashes
                
                if (!$this->isExcludedPath($relative_path)) {
                    $files[] = $relative_path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Check if a path should be excluded from updates
     */
    private function isExcludedPath($path) {
        // Normalize path separators
        $path = str_replace('\\', '/', $path);
        
        foreach ($this->excluded_paths as $excluded) {
            // Exact match or starts with the excluded path
            if ($path === rtrim($excluded, '/') || strpos($path, $excluded) === 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if a file is part of the active update execution chain.
     * These files are deferred during updates to prevent replacing running code.
     */
    private function isActiveUpdateFile($path) {
        $path = str_replace('\\', '/', $path);
        return in_array($path, $this->active_update_files, true);
    }
    
    /**
     * Write deferred update manifest for files that cannot be replaced during the running update.
     * Copies deferred files from staging to .pending files alongside the live versions,
     * and writes a JSON manifest so they can be applied after the response is sent.
     *
     * @param array $deferred_files Map of relative_path => staged_path
     */
    private function writeDeferredManifest(array $deferred_files) {
        $manifest = [];
        
        foreach ($deferred_files as $relative_path => $staged_path) {
            $pending_path = $this->base_path . '/' . $relative_path . '.pending';
            $dir = dirname($pending_path);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            if (copy($staged_path, $pending_path)) {
                $manifest[] = $relative_path;
            }
        }
        
        if (!empty($manifest)) {
            $manifest_path = $this->base_path . '/.update_deferred.json';
            if (file_put_contents($manifest_path, json_encode($manifest, JSON_PRETTY_PRINT)) === false) {
                // Fallback: apply deferred files immediately if manifest write fails
                foreach ($deferred_files as $relative_path => $staged_path) {
                    $live_path = $this->base_path . '/' . $relative_path;
                    @copy($staged_path, $live_path);
                }
            }
        }
    }
    
    /**
     * Apply deferred update files that were skipped during the main update.
     * Uses rename() for atomic replacement to avoid leaving files in a half-written state.
     * Safe to call from a shutdown function after the HTTP response has been sent.
     *
     * @param string $base_path Application base path
     * @return array Result with applied count and any errors
     */
    public static function applyDeferredUpdates($base_path) {
        $manifest_path = $base_path . '/.update_deferred.json';
        
        if (!file_exists($manifest_path)) {
            return ['applied' => 0, 'errors' => []];
        }
        
        $raw = @file_get_contents($manifest_path);
        if ($raw === false) {
            @unlink($manifest_path);
            return ['applied' => 0, 'errors' => ['Failed to read deferred manifest']];
        }
        
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || empty($manifest)) {
            @unlink($manifest_path);
            return ['applied' => 0, 'errors' => []];
        }
        
        $applied = 0;
        $errors = [];
        
        foreach ($manifest as $relative_path) {
            $pending_path = $base_path . '/' . $relative_path . '.pending';
            $live_path = $base_path . '/' . $relative_path;
            
            if (!file_exists($pending_path)) {
                $errors[] = "Pending file not found: {$relative_path}";
                continue;
            }
            
            $dir = dirname($live_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Use rename() for atomic replacement on the same filesystem
            if (rename($pending_path, $live_path)) {
                $applied++;
            } else {
                // Fallback to copy + delete if rename fails (e.g. cross-device)
                if (copy($pending_path, $live_path)) {
                    @unlink($pending_path);
                    $applied++;
                } else {
                    $errors[] = "Failed to apply deferred file: {$relative_path}";
                }
            }
        }
        
        // Remove the manifest after all files are processed
        @unlink($manifest_path);
        
        return ['applied' => $applied, 'errors' => $errors];
    }
    
    /**
     * Make HTTP request to GitHub API with retry logic
     */
    private function makeGitHubRequest($url, $headers = [], $retries = 2) {
        $context_options = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ];
        
        $context = stream_context_create($context_options);
        
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $result = @file_get_contents($url, false, $context);
            if ($result !== false) {
                return $result;
            }
            if ($attempt < $retries) {
                sleep(1);
            }
        }
        
        return false;
    }
    
    /**
     * Get current commit SHA from settings
     */
    private function getCurrentCommitSha() {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'current_commit_sha'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['setting_value'] ?? '';
    }
    
    /**
     * Update current commit SHA in settings
     */
    private function updateCurrentCommitSha($sha) {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('current_commit_sha', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$sha, $sha]);
    }
    
    /**
     * Get GitHub OAuth authorization URL
     */
    public function getGitHubAuthUrl($client_id, $redirect_uri) {
        $state = bin2hex(random_bytes(16));
        $_SESSION['github_oauth_state'] = $state;
        
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => 'repo',
            'state' => $state
        ];
        
        return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
    }
    
    /**
     * Exchange OAuth code for access token
     */
    public function exchangeOAuthCode($code, $client_id, $client_secret) {
        $url = 'https://github.com/login/oauth/access_token';
        $data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code
        ];
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($data),
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return ['success' => false, 'message' => 'Failed to exchange OAuth code'];
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['access_token'])) {
            return ['success' => true, 'token' => $result['access_token']];
        }
        
        return ['success' => false, 'message' => $result['error_description'] ?? 'Unknown error'];
    }
    
    /**
     * Backup persistent files before applying updates.
     * These files contain encryption keys and configuration that must survive updates.
     * 
     * @return array Map of original path => backup path
     */
    private function backupPersistentFiles() {
        $persistent_files = [
            '.credential_key',
            'arctic_wolves.env',
            '.env',
            'db_config.php',
        ];
        
        $backup = [];
        $backup_dir = sys_get_temp_dir() . '/arctic_wolves_update_backup_' . time();
        
        foreach ($persistent_files as $file) {
            $full_path = $this->base_path . '/' . $file;
            if (file_exists($full_path)) {
                if (!is_dir($backup_dir)) {
                    mkdir($backup_dir, 0700, true);
                }
                $backup_path = $backup_dir . '/' . $file;
                if (copy($full_path, $backup_path)) {
                    $backup[$file] = $backup_path;
                }
            }
        }
        
        return $backup;
    }
    
    /**
     * Restore persistent files after applying updates.
     * 
     * @param array $backup Map of original path => backup path from backupPersistentFiles()
     */
    private function restorePersistentFiles($backup) {
        foreach ($backup as $file => $backup_path) {
            $full_path = $this->base_path . '/' . $file;
            if (file_exists($backup_path)) {
                // Only restore if the file was removed or changed during update
                if (!file_exists($full_path) || md5_file($full_path) !== md5_file($backup_path)) {
                    copy($backup_path, $full_path);
                    chmod($full_path, 0600);
                }
                @unlink($backup_path);
            }
        }
        
        // Clean up backup directory
        $backup_values = array_values($backup);
        $backup_dir = !empty($backup_values) ? dirname($backup_values[0]) : null;
        if ($backup_dir && is_dir($backup_dir)) {
            @rmdir($backup_dir);
        }
    }
    
    /**
     * Run database schema check after update to ensure tables match the updated code.
     * Uses DatabaseMigrator to compare the live database against database_schema.sql,
     * creates missing tables, adds missing columns, and runs inline migrations.
     *
     * @return array Schema check results with applied changes and any errors
     */
    public function runSchemaCheck() {
        $results = [];
        $errors = [];
        
        try {
            $schema_file = $this->base_path . '/database_schema.sql';
            if (!file_exists($schema_file)) {
                return ['success' => false, 'message' => 'database_schema.sql not found', 'results' => [], 'errors' => []];
            }
            
            $migrator_file = $this->base_path . '/lib/database_migrator.php';
            if (!file_exists($migrator_file)) {
                return ['success' => false, 'message' => 'DatabaseMigrator not found', 'results' => [], 'errors' => []];
            }
            
            require_once $migrator_file;
            $migrator = new \DatabaseMigrator($this->pdo, $this->base_path);
            
            // Parse expected schema from the updated database_schema.sql
            $expected_schema = $migrator->parseSchemaFile($schema_file);
            
            // Get current live database schema
            $current_schema = $migrator->getCurrentSchema();
            
            // Compare and generate migration steps
            $migrations = $migrator->compareSchemas($current_schema, $expected_schema);
            
            // Execute migrations: create missing tables and add missing columns
            $schema_sql = file_get_contents($schema_file);
            $create_table_pattern_tpl = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?%s`?\s*\(.*?\)\s*ENGINE[^;]*;/is';
            
            // Disable FK checks so tables can be created regardless of dependency order
            try { $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0'); } catch (\Exception $e) { error_log('Could not disable FK checks: ' . $e->getMessage()); }
            
            try {
                foreach ($migrations as $migration) {
                    try {
                        if ($migration['type'] === 'create_table') {
                            $table_name = $migration['table'];
                            if (preg_match(sprintf($create_table_pattern_tpl, preg_quote($table_name, '/')), $schema_sql, $match)) {
                                try {
                                    $this->pdo->exec($match[0]);
                                    $results[] = "Created missing table: $table_name";
                                } catch (\Exception $ce) {
                                    $isAlreadyExists = ($ce->getCode() === '42S01' || strpos($ce->getMessage(), '1050') !== false || strpos($ce->getMessage(), 'already exists') !== false);
                                    if ($isAlreadyExists) {
                                        $results[] = "Table already exists: $table_name";
                                    } else {
                                        $errors[] = "Could not create table $table_name: " . $ce->getMessage();
                                        error_log("Schema create table error for $table_name: " . $ce->getMessage());
                                    }
                                }
                            } else {
                                $errors[] = "Could not extract CREATE TABLE statement for $table_name from schema file";
                                error_log("Schema regex failed for table: $table_name");
                            }
                        } elseif ($migration['type'] === 'add_column') {
                            $result = $migrator->executeMigration($migration);
                            if (!empty($result['skipped']) && strpos($result['message'], 'does not exist') !== false) {
                                // Table missing — attempt to create it from schema file, then retry
                                $table_name = $migration['table'];
                                $created = false;
                                if (preg_match(sprintf($create_table_pattern_tpl, preg_quote($table_name, '/')), $schema_sql, $match)) {
                                    try {
                                        $this->pdo->exec($match[0]);
                                        $results[] = "Created missing table: $table_name";
                                        $created = true;
                                    } catch (\Exception $ce) {
                                        $errors[] = "Could not create table $table_name: " . $ce->getMessage();
                                    }
                                }
                                if ($created) {
                                    $result = $migrator->executeMigration($migration);
                                    if (!empty($result['skipped'])) {
                                        $results[] = $result['message'] . ' (skipped)';
                                    } else {
                                        $results[] = $result['message'];
                                    }
                                } else {
                                    $results[] = $result['message'] . ' (skipped)';
                                }
                            } elseif (!empty($result['skipped'])) {
                                $results[] = $result['message'] . ' (skipped)';
                            } else {
                                $results[] = $result['message'];
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore "Duplicate key name" errors (MySQL 1061 / SQLSTATE 42000) — index already exists
                        if (strpos($e->getMessage(), '1061') !== false || strpos($e->getMessage(), 'Duplicate key name') !== false) {
                            // Non-critical: index already exists on table
                        } else {
                            $errors[] = "Schema migration error: " . $e->getMessage();
                            error_log("Update schema check error: " . $e->getMessage());
                        }
                    }
                }
            } finally {
                try { $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (\Exception $e) { /* best-effort */ }
            }
            
            // Add foreign key constraints if missing
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND CONSTRAINT_NAME = 'fk_expense_payee'");
                $stmt->execute();
                if ($stmt->fetchColumn() == 0) {
                    $this->pdo->exec("ALTER TABLE expenses ADD CONSTRAINT fk_expense_payee FOREIGN KEY (payee_id) REFERENCES contacts(id) ON DELETE SET NULL ON UPDATE CASCADE");
                    $results[] = "Added foreign key: fk_expense_payee";
                }
            } catch (\PDOException $e) {
                // Non-critical — FK may already exist or tables may not be ready
            }
            
            // Verify schema after migration — retry any tables still missing
            try {
                $post_schema = $migrator->getCurrentSchema();
                $remaining = $migrator->compareSchemas($post_schema, $expected_schema);
                $remaining_tables = array_filter($remaining, function($m) { return $m['type'] === 'create_table'; });
                if (!empty($remaining_tables)) {
                    try { $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0'); } catch (\Exception $e) { /* best-effort */ }
                    try {
                        foreach ($remaining_tables as $rm) {
                            $table_name = $rm['table'];
                            if (preg_match(sprintf($create_table_pattern_tpl, preg_quote($table_name, '/')), $schema_sql, $match)) {
                                try {
                                    $this->pdo->exec($match[0]);
                                    $results[] = "Created missing table (retry): $table_name";
                                } catch (\Exception $ce) {
                                    $isAlreadyExists = ($ce->getCode() === '42S01' || strpos($ce->getMessage(), '1050') !== false || strpos($ce->getMessage(), 'already exists') !== false);
                                    if (!$isAlreadyExists) {
                                        $errors[] = "Retry: could not create table $table_name: " . $ce->getMessage();
                                        error_log("Schema retry create table error for $table_name: " . $ce->getMessage());
                                    }
                                }
                            }
                        }
                    } finally {
                        try { $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (\Exception $e) { /* best-effort */ }
                    }
                }
            } catch (\Exception $e) {
                error_log("Post-migration verification error: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'Schema check completed',
                'results' => $results,
                'errors' => $errors,
                'tables_checked' => count($expected_schema['tables'] ?? []),
                'changes_applied' => count($results)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Schema check failed: ' . $e->getMessage(),
                'results' => $results,
                'errors' => $errors
            ];
        }
    }
}
