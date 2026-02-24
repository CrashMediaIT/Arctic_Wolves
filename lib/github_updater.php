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
        '.nextcloud_key',
        'arctic_wolves.env',
        'config.php',
        'vendor/',
        'node_modules/',
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
     * Apply updates from GitHub
     */
    public function applyUpdates() {
        try {
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
                return ['success' => false, 'message' => 'Failed to fetch repository tree'];
            }
            
            $tree_data = json_decode($response, true);
            
            if (!isset($tree_data['tree'])) {
                return ['success' => false, 'message' => 'Invalid repository structure'];
            }
            
            $repo_files = [];
            foreach ($tree_data['tree'] as $item) {
                if ($item['type'] === 'blob') { // Only files, not directories
                    $repo_files[] = $item['path'];
                }
            }
            
            // Get current local files
            $local_files = $this->getLocalFiles();
            
            // Determine files to delete (in local but not in repo)
            $files_to_delete = array_diff($local_files, $repo_files);
            
            // Update/download files from repository
            $updated_count = 0;
            $deleted_count = 0;
            $errors = [];
            
            foreach ($tree_data['tree'] as $item) {
                if ($item['type'] !== 'blob') continue;
                
                $file_path = $item['path'];
                
                // Skip excluded files
                if ($this->isExcludedPath($file_path)) {
                    continue;
                }
                
                $result = $this->downloadAndUpdateFile($file_path);
                if ($result['success']) {
                    $updated_count++;
                } else {
                    $errors[] = "Failed to update {$file_path}: {$result['message']}";
                }
            }
            
            // Delete files that no longer exist in repository
            foreach ($files_to_delete as $file_path) {
                if ($this->isExcludedPath($file_path)) {
                    continue;
                }
                
                $result = $this->deleteLocalFile($file_path);
                if ($result['success']) {
                    $deleted_count++;
                } else {
                    $errors[] = "Failed to delete {$file_path}: {$result['message']}";
                }
            }
            
            // Restore persistent files after update
            $this->restorePersistentFiles($backup);
            
            // Update current commit SHA
            $check_result = $this->checkForUpdates();
            if ($check_result['success']) {
                $this->updateCurrentCommitSha($check_result['latest_commit']['sha']);
            }
            
            return [
                'success' => true,
                'message' => "Update completed: {$updated_count} files updated, {$deleted_count} files deleted",
                'updated_count' => $updated_count,
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Download and update a single file from GitHub
     */
    private function downloadAndUpdateFile($file_path) {
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
            
            $local_path = $this->base_path . '/' . $file_path;
            $dir = dirname($local_path);
            
            // Create directory if it doesn't exist
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Write file
            if (file_put_contents($local_path, $content) === false) {
                return ['success' => false, 'message' => 'Failed to write file'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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
     * Make HTTP request to GitHub API
     */
    private function makeGitHubRequest($url, $headers = []) {
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
        return @file_get_contents($url, false, $context);
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
            '.nextcloud_key',
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
}
