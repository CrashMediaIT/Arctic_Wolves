/**
 * Tests for Direct S3 Upload Fix — Public Endpoint & Cancel Upload
 *
 * Verifies:
 * 1. rustfs_public_endpoint is fetched from system_settings
 * 2. generatePresignedUploadUrl accepts optional public_endpoint parameter
 * 3. process_video.php passes public endpoint to presigned URL generation
 * 4. CSP connect-src includes public endpoint origin in all entry points
 * 5. Cancel upload button exists in all upload views
 * 6. XHR abort logic is wired for cancel support
 * 7. Admin UI has the Public Endpoint URL field in RustFS settings
 * 8. process_settings.php saves rustfs_public_endpoint
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. rustfs_public_endpoint in getRustFSSettings
// =====================================================

test.describe('rustfs_public_endpoint setting', () => {
  test('should be included in getRustFSSettings keys', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain("'rustfs_public_endpoint'");
  });

  test('should be fetched alongside other RustFS settings', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function getRustFSSettings(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'rustfs_public_endpoint'");
  });
});

// =====================================================
// 2. generatePresignedUploadUrl public_endpoint param
// =====================================================

test.describe('generatePresignedUploadUrl public_endpoint support', () => {
  test('should accept optional public_endpoint parameter', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toMatch(/function generatePresignedUploadUrl\(\$settings,\s*\$object_key.*\$public_endpoint/);
  });

  test('should use public endpoint scheme and host when provided', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('public_endpoint');
    expect(funcBody).toContain("parse_url");
  });

  test('should fall back to internal endpoint when public_endpoint is empty', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // Should have an else branch for when public_endpoint is not set
    expect(funcBody).toContain('} else {');
    expect(funcBody).toContain("parsed['host']");
  });
});

// =====================================================
// 3. process_video.php passes public endpoint
// =====================================================

test.describe('process_video.php presigned URL handlers use public endpoint', () => {
  test('handleGetAthleteUploadUrl should pass rustfs_public_endpoint', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetAthleteUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("rustfs_public_endpoint");
    expect(funcBody).toContain('generatePresignedUploadUrl(');
  });

  test('handleGetVideoUploadUrl should pass rustfs_public_endpoint', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleGetVideoUploadUrl()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("rustfs_public_endpoint");
    expect(funcBody).toContain('generatePresignedUploadUrl(');
  });
});

// =====================================================
// 4. CSP connect-src includes public endpoint origin
// =====================================================

test.describe('CSP connect-src includes public endpoint', () => {
  const entryPoints = ['dashboard.php', 'gameplan.php', 'pwa.php', 'pwa_tablet.php'];

  for (const file of entryPoints) {
    test(`${file} should include rustfs_public_endpoint in extraConnectSrc`, () => {
      const content = readFile(file);
      expect(content).toContain('rustfs_public_endpoint');
      expect(content).toContain('$extraConnectSrc');
      expect(content).toContain('$pubOrigin');
    });
  }
});

// =====================================================
// 5. Cancel upload button in all upload views
// =====================================================

test.describe('Cancel upload button exists in upload views', () => {
  test('video_record_athlete.php should have cancel upload button', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('cancelUploadBtn');
    expect(content).toContain('Cancel Upload');
  });

  test('video_record_drill.php should have cancel upload button', () => {
    const content = readFile('views/video_record_drill.php');
    expect(content).toContain('cancelDrillUploadBtn');
    expect(content).toContain('Cancel Upload');
  });

  test('gameplan/film_room.php should have cancel upload button', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('vrCancelUploadBtn');
    expect(content).toContain('Cancel Upload');
  });

  test('gameplan/gp_film_room.php should have cancel upload button', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('vrCancelUploadBtn');
    expect(content).toContain('Cancel Upload');
  });
});

// =====================================================
// 6. XHR abort logic for cancel support
// =====================================================

test.describe('XHR abort logic for cancel support', () => {
  test('video_record_athlete.php should track XHR and abort on cancel', () => {
    const content = readFile('views/video_record_athlete.php');
    expect(content).toContain('currentUploadXhr');
    expect(content).toContain('.abort()');
  });

  test('video_record_drill.php should track XHR and abort on cancel', () => {
    const content = readFile('views/video_record_drill.php');
    expect(content).toContain('drillUploadXhr');
    expect(content).toContain('.abort()');
  });

  test('gameplan/film_room.php should track XHR and abort on cancel', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('vrCurrentUploadXhr');
    expect(content).toContain('.abort()');
  });

  test('gameplan/gp_film_room.php should track XHR and abort on cancel', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('vrCurrentUploadXhr');
    expect(content).toContain('.abort()');
  });
});

// =====================================================
// 7. Admin UI has Public Endpoint URL field
// =====================================================

test.describe('Admin UI RustFS settings', () => {
  test('should have Public Endpoint URL field', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('rustfs_public_endpoint');
    expect(content).toContain('Public Endpoint URL');
  });

  test('should display stored public endpoint value', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("settings['rustfs_public_endpoint']");
  });
});

// =====================================================
// 8. process_settings.php saves rustfs_public_endpoint
// =====================================================

test.describe('process_settings.php saves public endpoint', () => {
  test('should save rustfs_public_endpoint on update_rustfs action', () => {
    const content = readFile('process_settings.php');
    const caseStart = content.indexOf("case 'update_rustfs':");
    const caseEnd = content.indexOf("case '", caseStart + 25);
    const caseBody = content.substring(caseStart, caseEnd > -1 ? caseEnd : undefined);

    expect(caseBody).toContain('rustfs_public_endpoint');
    expect(caseBody).toContain("updateSetting($pdo, 'rustfs_public_endpoint'");
  });
});
