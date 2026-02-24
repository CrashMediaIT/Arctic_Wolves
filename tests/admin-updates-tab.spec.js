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
    expect(content).toContain("'.nextcloud_key'");
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
    const fnEnd = content.indexOf('function downloadAndUpdateFile');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('testGitHubConnection');
    expect(fn).toContain('Cannot connect to GitHub');
  });

  test('applyUpdates should abort on excessive download failures', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadAndUpdateFile');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('$failed_count');
    expect(fn).toContain('Update aborted: too many download failures');
  });

  test('applyUpdates should skip file deletions when download errors occurred', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadAndUpdateFile');
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('$failed_count === 0');
    expect(fn).toContain('File deletions skipped due to download errors');
  });

  test('applyUpdates should restore persistent files on failure', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function applyUpdates()');
    const fnEnd = content.indexOf('function downloadAndUpdateFile');
    const fn = content.substring(fnStart, fnEnd);
    // Should restore backup on early returns and in catch block
    const restoreCalls = fn.match(/restorePersistentFiles/g);
    expect(restoreCalls).not.toBeNull();
    expect(restoreCalls.length).toBeGreaterThanOrEqual(4);
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
});
