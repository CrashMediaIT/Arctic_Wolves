import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * Arctic Wolves - Admin Updates Tab Tests
 * Tests to verify the GitHub-based single-button update utility in System Tools Updates tab
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Updates tab UI structure
// =====================================================

test.describe('System Tools - Updates Tab - GitHub Updater UI', () => {
  
  test('Updates tab should have GitHub updater card', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="updates-tab"');
    expect(content).toContain('System Updates');
    expect(content).toContain('fa-github');
  });

  test('Updates tab should show repository name', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('CrashMediaIT/Arctic_Wolves');
  });

  test('Updates tab should have Check for Updates button', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="githubCheckBtn"');
    expect(content).toContain('Check for Updates');
    expect(content).toContain('githubCheckForUpdates()');
  });

  test('Updates tab should have Update Now button (initially disabled)', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="githubUpdateBtn"');
    expect(content).toContain('Update Now');
    expect(content).toContain('githubApplyUpdate()');
    // Button should be disabled initially
    const btnMatch = content.match(/id="githubUpdateBtn"[^>]*disabled/);
    expect(btnMatch).not.toBeNull();
  });

  test('Updates tab should have status area for current version', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="githubUpdateStatus"');
    expect(content).toContain('id="githubCurrentVersion"');
    expect(content).toContain('id="githubUpdateBadge"');
  });

  test('Updates tab should have update details section', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="githubUpdateDetails"');
    expect(content).toContain('id="githubLatestSha"');
    expect(content).toContain('id="githubLatestMessage"');
    expect(content).toContain('id="githubLatestAuthor"');
    expect(content).toContain('id="githubLatestDate"');
  });

  test('Updates tab should have progress section', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="githubProgressSection"');
    expect(content).toContain('id="githubProgressBar"');
    expect(content).toContain('id="githubLogContainer"');
  });

  test('Updates tab should have result banner area', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="githubResultBanner"');
  });

  test('Updates tab should still have Stripe library updater', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Stripe PHP Library');
    expect(content).toContain('checkStripeUpdates');
    expect(content).toContain('updateStripeLibrary');
  });
});

// =====================================================
// 2. JavaScript functions
// =====================================================

test.describe('GitHub updater JavaScript functions', () => {

  test('githubCheckForUpdates function should call check_updates action', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('async function githubCheckForUpdates()');
    // Should POST to process_settings.php with check_updates action
    const fnStart = content.indexOf('async function githubCheckForUpdates()');
    const fnEnd = content.indexOf('async function githubApplyUpdate()');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("action=check_updates");
    expect(fn).toContain("process_settings.php");
  });

  test('githubApplyUpdate function should call apply_updates action', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('async function githubApplyUpdate()');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("action=apply_updates");
    expect(fn).toContain("process_settings.php");
  });

  test('githubApplyUpdate should show confirmation dialog', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('confirm(');
  });

  test('githubAddLogEntry function should exist', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function githubAddLogEntry(');
  });

  test('githubCheckForUpdates should display has_updates badge', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubCheckForUpdates()');
    const fnEnd = content.indexOf('async function githubApplyUpdate()');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('has_updates');
    expect(fn).toContain('Update Available');
    expect(fn).toContain('Up to Date');
  });

  test('githubApplyUpdate should show reload button on success', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('window.location.reload()');
    expect(fn).toContain('Reload Page');
  });
});

// =====================================================
// 3. Backend handlers exist
// =====================================================

test.describe('Backend update handlers in process_settings.php', () => {
  
  test('process_settings.php should handle check_updates action', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'check_updates':");
    expect(content).toContain('GitHubUpdater');
    expect(content).toContain('checkForUpdates');
  });

  test('process_settings.php should handle apply_updates action', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'apply_updates':");
    expect(content).toContain('GitHubUpdater');
    expect(content).toContain('applyUpdates');
  });

  test('check_updates and apply_updates should be JSON actions', () => {
    const content = readFile('process_settings.php');
    // These should be in the json_actions array
    expect(content).toContain("'check_updates'");
    expect(content).toContain("'apply_updates'");
  });
});

// =====================================================
// 4. GitHub updater class
// =====================================================

test.describe('GitHub updater class capabilities', () => {
  
  test('GitHubUpdater class should exist', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('class GitHubUpdater');
  });

  test('GitHubUpdater should have checkForUpdates method', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function checkForUpdates()');
  });

  test('GitHubUpdater should have applyUpdates method', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function applyUpdates()');
  });

  test('GitHubUpdater should backup persistent files during updates', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('backupPersistentFiles');
    expect(content).toContain('restorePersistentFiles');
  });

  test('GitHubUpdater should exclude sensitive files from updates', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain("'db_config.php'");
    expect(content).toContain("'uploads/'");
    expect(content).toContain("'.credential_key'");
    expect(content).toContain("'.env'");
  });
});

// =====================================================
// 5. No ZIP upload UI remnants
// =====================================================

test.describe('ZIP feature importer UI removed from updates tab', () => {
  
  test('Updates tab should not have ZIP upload section', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).not.toContain('id="uploadSection"');
    expect(content).not.toContain('id="updateFileInput"');
    expect(content).not.toContain('Upload Update Package');
  });

  test('Updates tab should not have import button', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).not.toContain('id="importUpdateBtn"');
    expect(content).not.toContain('Import Update Package');
  });

  test('Old feature importer JS functions should be removed', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).not.toContain('function handleUpdateFileSelect(');
    expect(content).not.toContain('function handleUpdateFile(');
    expect(content).not.toContain('function removeUpdateFile(');
    expect(content).not.toContain('function startUpdateImport(');
    expect(content).not.toContain('let selectedUpdateFile');
  });
});

// =====================================================
// 6. Network error handling
// =====================================================

test.describe('GitHub updater network error handling', () => {

  test('makeGitHubRequest should have retry logic', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('$retries');
    expect(content).toContain('$attempt');
    expect(content).toContain('sleep(1)');
  });

  test('applyUpdates should pre-check connectivity before destructive operations', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('testGitHubConnection');
    expect(fn).toContain('Cannot connect to GitHub');
  });

  test('applyUpdates should use staging directory for downloads', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('staging_dir');
    expect(fn).toContain('arctic_wolves_staging_');
    expect(fn).toContain('downloadFileToStaging');
  });

  test('applyUpdates should copy from staging to live only after all downloads succeed', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    // Phase 2: copy staged files to live
    expect(fn).toContain('Copy staged files to live site');
    expect(fn).toContain('copy($staged_path, $live_path)');
  });

  test('applyUpdates should abort without modifying site if downloads fail', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('No files were changed');
    expect(fn).toContain('cleanupDirectory($staging_dir)');
  });

  test('applyUpdates should abort on excessive download failures', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('$failed_count');
    expect(fn).toContain('Update aborted: too many download failures');
  });

  test('applyUpdates should restore persistent files on failure', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    const restoreCalls = fn.match(/restorePersistentFiles/g);
    expect(restoreCalls).not.toBeNull();
    expect(restoreCalls.length).toBeGreaterThanOrEqual(4);
  });

  test('downloadFileToStaging method should exist and write to staging dir', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function downloadFileToStaging($file_path, $staging_dir)');
    const fnStart = content.indexOf('function downloadFileToStaging');
    const fnEnd = content.indexOf('function cleanupDirectory');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('$staged_path = $staging_dir');
    expect(fn).toContain('file_put_contents($staged_path');
  });

  test('cleanupDirectory method should exist for staging cleanup', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function cleanupDirectory($dir)');
  });

  test('githubCheckForUpdates JS should check response.ok', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubCheckForUpdates()');
    const fnEnd = content.indexOf('async function githubApplyUpdate()');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('response.ok');
  });

  test('githubApplyUpdate JS should check response.ok', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('response.ok');
  });

  test('githubApplyUpdate JS should handle JSON parse errors gracefully', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('parseError');
    expect(fn).toContain('Invalid response from server');
  });

  test('githubApplyUpdate JS catch block should display error message', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('error.message');
  });

  test('applyUpdates should try zipball download before per-file downloads', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadAndExtractZipball');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('downloadAndExtractZipball($staging_dir)');
    expect(fn).toContain('downloadFileToStaging');
  });

  test('downloadAndExtractZipball method should exist and use zipball API', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function downloadAndExtractZipball($staging_dir)');
    const fnStart = content.indexOf('function downloadAndExtractZipball');
    const fnEnd = content.indexOf('function cleanupDirectory');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('zipball');
    expect(fn).toContain('ZipArchive');
    expect(fn).toContain('file_count');
  });

  test('downloadAndExtractZipball should strip GitHub root directory prefix', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function downloadAndExtractZipball');
    const fnEnd = content.indexOf('function cleanupDirectory');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('$prefix');
    expect(fn).toContain('getNameIndex');
  });

  test('downloadAndExtractZipball should fall back to master branch', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function downloadAndExtractZipball');
    const fnEnd = content.indexOf('function cleanupDirectory');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'main'");
    expect(fn).toContain("'master'");
  });

  test('githubApplyUpdate JS should have retry logic for network errors', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('maxRetries');
    expect(fn).toContain('attempt');
    expect(fn).toContain('Will retry');
  });

  test('githubApplyUpdate JS should retry on 502/503/504 status codes', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('502');
    expect(fn).toContain('503');
    expect(fn).toContain('504');
    expect(fn).toContain('isRetryableStatus');
  });

  test('githubApplyUpdate JS should use AbortController with timeout', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('AbortController');
    expect(fn).toContain('controller.abort()');
    expect(fn).toContain('signal: controller.signal');
    expect(fn).toContain('clearTimeout(timeoutId)');
  });
});

// =====================================================
// 7. Deferred update mechanism for active files
// =====================================================

test.describe('Deferred update mechanism to prevent self-replacement', () => {

  test('GitHubUpdater should define active_update_files that are deferred during update', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('active_update_files');
    expect(content).toContain("'lib/github_updater.php'");
    expect(content).toContain("'process_settings.php'");
  });

  test('GitHubUpdater should have isActiveUpdateFile method', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function isActiveUpdateFile(');
  });

  test('applyUpdates should defer active update files instead of overwriting them', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('isActiveUpdateFile($file_path)');
    expect(fn).toContain('$deferred_files');
    expect(fn).toContain('writeDeferredManifest');
    expect(fn).toContain('has_deferred');
  });

  test('GitHubUpdater should have writeDeferredManifest method', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function writeDeferredManifest(');
    expect(content).toContain('.update_deferred.json');
    expect(content).toContain('.pending');
  });

  test('GitHubUpdater should have static applyDeferredUpdates method', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('static function applyDeferredUpdates($base_path)');
    // Should use rename() for atomic replacement
    expect(content).toContain('rename($pending_path, $live_path)');
  });

  test('applyDeferredUpdates should clean up manifest after processing', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('static function applyDeferredUpdates');
    const fnEnd = content.indexOf('Make HTTP request to GitHub API');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('@unlink($manifest_path)');
  });

  test('.update_deferred.json should be in excluded paths', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain("'.update_deferred.json'");
    // Should be in the excluded_paths array
    const excludeStart = content.indexOf('$excluded_paths');
    const excludeEnd = content.indexOf('];', excludeStart);
    const excludeBlock = content.substring(excludeStart, excludeEnd);
    expect(excludeBlock).toContain('.update_deferred.json');
  });

  test('process_settings apply_updates should apply leftover deferred files first', () => {
    const content = readFile('process_settings.php');
    const fnStart = content.indexOf("case 'apply_updates':");
    const fnEnd = content.indexOf("case 'update_nextcloud_backup':");
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('applyDeferredUpdates(__DIR__)');
  });

  test('process_settings apply_updates should use ignore_user_abort', () => {
    const content = readFile('process_settings.php');
    const fnStart = content.indexOf("case 'apply_updates':");
    const fnEnd = content.indexOf("case 'update_nextcloud_backup':");
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('ignore_user_abort(true)');
    expect(fn).toContain('set_time_limit(300)');
  });

  test('process_settings apply_updates should schedule deferred file application via shutdown function', () => {
    const content = readFile('process_settings.php');
    const fnStart = content.indexOf("case 'apply_updates':");
    const fnEnd = content.indexOf("case 'update_nextcloud_backup':");
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('register_shutdown_function');
    expect(fn).toContain('has_deferred');
    expect(fn).toContain('applyDeferredUpdates');
  });

  test('applyUpdates Phase 3 should skip deletion of active update files', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('Phase 3: Delete files');
    const fnEnd = content.indexOf('Write deferred manifest');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('isActiveUpdateFile($file_path)');
  });

  test('applyDeferredUpdates should have copy fallback if rename fails', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('static function applyDeferredUpdates');
    const fnEnd = content.indexOf('Make HTTP request to GitHub API');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('copy($pending_path, $live_path)');
    expect(fn).toContain('@unlink($pending_path)');
  });
});

// =====================================================
// 8. Database schema check during updates
// =====================================================

test.describe('Database schema check during update process', () => {

  test('GitHubUpdater should have runSchemaCheck method', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('function runSchemaCheck()');
  });

  test('runSchemaCheck should use DatabaseMigrator to compare schemas', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('DatabaseMigrator');
    expect(fn).toContain('parseSchemaFile');
    expect(fn).toContain('getCurrentSchema');
    expect(fn).toContain('compareSchemas');
  });

  test('runSchemaCheck should load database_schema.sql', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('database_schema.sql');
  });

  test('runSchemaCheck should create missing tables from schema file', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('create_table');
    expect(fn).toContain('Created missing table');
  });

  test('runSchemaCheck should add missing columns', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('add_column');
    expect(fn).toContain('executeMigration');
  });

  test('runSchemaCheck should run inline migrations matching setup.php', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('inline_migrations');
    expect(fn).toContain('eval_categories');
    expect(fn).toContain('eval_skills');
    expect(fn).toContain('vr_game_plan_lines');
    expect(fn).toContain('teams');
    expect(fn).toContain('game_schedules');
    expect(fn).toContain('users');
    expect(fn).toContain('sip_wss_port');
  });

  test('runSchemaCheck should handle duplicate column errors gracefully', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('Duplicate column');
    expect(fn).toContain('42S21');
  });

  test('runSchemaCheck should return structured results', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'tables_checked'");
    expect(fn).toContain("'changes_applied'");
    expect(fn).toContain("'results'");
    expect(fn).toContain("'errors'");
  });

  test('applyUpdates should call runSchemaCheck after file update', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('runSchemaCheck()');
    expect(fn).toContain("'schema_check'");
  });

  test('applyUpdates return value should include schema_check field', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadFileToStaging');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("'schema_check' => $schema_check");
  });

  test('githubApplyUpdate JS should display schema check results', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function githubApplyUpdate()');
    const fnEnd = content.indexOf('// Stripe Library Update functions');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('schema_check');
    expect(fn).toContain('Running database schema check');
    expect(fn).toContain('Schema check complete');
    expect(fn).toContain('changes_applied');
  });

  test('runSchemaCheck should handle fk_expense_payee foreign key', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('fk_expense_payee');
  });
});
