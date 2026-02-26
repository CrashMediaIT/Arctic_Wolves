/**
 * Tests for Redirect Fixes, Encryption Key Persistence, Drill Image Restoration,
 * and Update Mechanism Improvements
 *
 * Verifies:
 * 1. process_settings.php catch block redirects to system_tools (not admin_settings)
 * 2. process_settings.php action redirects all use system_tools with correct tab
 * 3. loadCredentialKey() persists file-based key to database via INSERT IGNORE
 * 4. image_helper.php defines resolveDrillImage() function
 * 5. view_drill.php uses resolveDrillImage to restore missing images
 * 6. view_practice_plan.php uses resolveDrillImage to restore missing images
 * 7. GitHub updater excludes .nextcloud_key and arctic_wolves.env
 * 8. GitHub updater backs up and restores persistent files during updates
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Settings redirect fixes
// =====================================================

test.describe('Settings redirect fixes - no admin_settings redirects', () => {
  test('process_settings.php should not redirect to admin_settings', () => {
    const content = readFile('process_settings.php');
    expect(content).not.toContain("page=admin_settings");
  });

  test('process_settings.php catch block should redirect to system_tools with tab', () => {
    const content = readFile('process_settings.php');
    const catchBlock = content.substring(
      content.lastIndexOf('} catch (Exception $e)'),
      content.indexOf('}', content.lastIndexOf('} catch (Exception $e)') + 200) + 1
    );
    expect(catchBlock).toContain('page=system_tools&tab=');
  });

  test('process_settings.php catch block should include action-to-tab mapping for payments', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("'update_payments'    => 'payments'");
  });

  test('process_settings.php catch block should include action-to-tab mapping for landing', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("'update_landing'     => 'landing'");
  });

  test('update_payments should redirect to system_tools with payments tab on success', () => {
    const content = readFile('process_settings.php');
    const paymentsSection = content.substring(
      content.indexOf("case 'update_payments':"),
      content.indexOf("case 'update_security':")
    );
    expect(paymentsSection).toContain("page=system_tools&tab=payments&success=1");
  });

  test('update_security should redirect to system_tools on success', () => {
    const content = readFile('process_settings.php');
    const securitySection = content.substring(
      content.indexOf("case 'update_security':"),
      content.indexOf("case 'update_advanced':")
    );
    expect(securitySection).toContain("page=system_tools&tab=settings&success=1");
  });

  test('update_advanced should redirect to system_tools on success', () => {
    const content = readFile('process_settings.php');
    const advancedSection = content.substring(
      content.indexOf("case 'update_advanced':"),
      content.indexOf("case 'update_settings':")
    );
    expect(advancedSection).toContain("page=system_tools&tab=settings&success=1");
  });
});

// =====================================================
// 2. Encryption key database persistence
// =====================================================

test.describe('Encryption key persistence to database', () => {
  test('loadCredentialKey should persist key to database with INSERT IGNORE', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function loadCredentialKey()');
    const fnSection = content.substring(fnStart, fnStart + 1500);
    expect(fnSection).toContain('INSERT IGNORE INTO system_settings');
    expect(fnSection).toContain('_credential_encryption_key');
  });

  test('loadCredentialKey should use static variable to avoid repeated DB writes', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function loadCredentialKey()');
    const fnSection = content.substring(fnStart, fnStart + 500);
    expect(fnSection).toContain('static $synced_to_db');
  });

  test('decryptPassword should still restore key from database when file is missing', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptPassword(');
    const fnSection = content.substring(fnStart, fnStart + 1500);
    expect(fnSection).toContain('_credential_encryption_key');
    expect(fnSection).toContain('writeKeyFile');
  });
});

// =====================================================
// 3. Drill image restoration from Nextcloud
// =====================================================

test.describe('Drill image restoration from Nextcloud', () => {
  test('image_helper.php should define resolveDrillImage function', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('function resolveDrillImage(');
  });

  test('resolveDrillImage should validate URLs', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart, content.indexOf('\nfunction ', fnStart + 10) || undefined);
    expect(fn).toContain('https?://');
  });

  test('resolveDrillImage should return null for empty paths', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart);
    expect(fn).toContain('return null');
  });

  test('resolveDrillImage should return URL for valid http paths', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart);
    expect(fn).toContain('return $path');
  });

  test('resolveDrillImage no longer queries drills table directly', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart, content.indexOf('\nfunction ', fnStart + 10) || undefined);
    expect(fn).not.toContain('FROM drills');
  });

  test('resolveDrillImage no longer updates drills table', () => {
    const content = readFile('lib/image_helper.php');
    const fnStart = content.indexOf('function resolveDrillImage(');
    const fn = content.substring(fnStart, content.indexOf('\nfunction ', fnStart + 10) || undefined);
    expect(fn).not.toContain('UPDATE drills SET custom_image');
  });
});

// =====================================================
// 4. View files use drill image restoration
// =====================================================

test.describe('View files render drill images from URLs', () => {
  test('view_drill.php renders drill images without resolveDrillImage', () => {
    const content = readFile('views/view_drill.php');
    // After RustFS migration, images are URLs; resolveDrillImage is no longer needed in views
    expect(content).not.toContain('resolveDrillImage');
  });

  test('view_practice_plan.php renders drill images without resolveDrillImage', () => {
    const content = readFile('views/view_practice_plan.php');
    // After RustFS migration, images are URLs; resolveDrillImage is no longer needed in views
    expect(content).not.toContain('resolveDrillImage');
  });
});

// =====================================================
// 5. GitHub updater improvements
// =====================================================

test.describe('GitHub updater preserves persistent files during updates', () => {
  test('excluded_paths should include .nextcloud_key', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain("'.nextcloud_key'");
  });

  test('excluded_paths should include arctic_wolves.env', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain("'arctic_wolves.env'");
  });

  test('applyUpdates should call backupPersistentFiles before updating', () => {
    const content = readFile('lib/github_updater.php');
    const applyFn = content.substring(
      content.indexOf('function applyUpdates()'),
      content.indexOf('function downloadFileToStaging')
    );
    expect(applyFn).toContain('backupPersistentFiles');
  });

  test('applyUpdates should call restorePersistentFiles after updating', () => {
    const content = readFile('lib/github_updater.php');
    const applyFn = content.substring(
      content.indexOf('function applyUpdates()'),
      content.indexOf('function downloadFileToStaging')
    );
    expect(applyFn).toContain('restorePersistentFiles');
  });

  test('backupPersistentFiles should backup .nextcloud_key', () => {
    const content = readFile('lib/github_updater.php');
    const fn = content.substring(
      content.indexOf('function backupPersistentFiles()'),
      content.indexOf('function restorePersistentFiles')
    );
    expect(fn).toContain('.nextcloud_key');
  });

  test('restorePersistentFiles should restore files with restricted permissions', () => {
    const content = readFile('lib/github_updater.php');
    const fn = content.substring(
      content.indexOf('function restorePersistentFiles(')
    );
    expect(fn).toContain('chmod');
    expect(fn).toContain('0600');
  });
});
