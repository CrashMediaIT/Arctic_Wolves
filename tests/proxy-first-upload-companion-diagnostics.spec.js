/**
 * Tests for Proxy-First Upload Strategy, Companion Diagnostics UI,
 * and Hardware Readiness Indicators
 *
 * Verifies:
 * 1. Upload views use proxy-first strategy (proxy before direct S3)
 * 2. Companion settings page has diagnostics test buttons
 * 3. Companion /api/test includes main app connectivity test
 * 4. Companion dashboard has hardware readiness indicators
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Upload views use proxy-first strategy
// =====================================================

test.describe('Proxy-first upload strategy', () => {

  const uploadViews = [
    'views/gameplan/film_room.php',
    'views/gameplan/gp_film_room.php',
    'views/video_record_athlete.php',
    'views/video_coach_reviews.php',
    'views/video_record_drill.php',
    'views/pwa/video_record_drill.php',
  ];

  for (const viewPath of uploadViews) {
    test(`${viewPath} should check proxyUploadUrl && proxyToken before direct S3`, () => {
      const content = readFile(viewPath);
      // The proxy-first check should appear in the upload flow
      expect(content).toContain('proxyUploadUrl && proxyToken');
    });

    test(`${viewPath} should NOT try direct S3 presigned URL before proxy`, () => {
      const content = readFile(viewPath);
      // The old pattern "Direct S3 upload failed" should not exist
      expect(content).not.toContain('Direct S3 upload failed');
    });
  }

  test('film_room.php should set X-Upload-Token header for proxy uploads', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain("'X-Upload-Token'");
  });

  test('gp_film_room.php should set X-Upload-Token header for proxy uploads', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain("'X-Upload-Token'");
  });
});

// =====================================================
// 2. Companion settings page has diagnostics test buttons
// =====================================================

test.describe('Companion diagnostics UI', () => {
  const content = () => readFile('companion/templates/settings.html');

  test('should have a Run All Tests button', () => {
    expect(content()).toContain('Run All Tests');
    expect(content()).toContain('runDiagnostics');
  });

  test('should have a diagnostics results container', () => {
    expect(content()).toContain('diagnostics-results');
  });

  test('should display Main App Connection test result', () => {
    expect(content()).toContain('Main App Connection');
    expect(content()).toContain('diag-main-app-badge');
  });

  test('should display RustFS Storage test result', () => {
    expect(content()).toContain('S3 / RustFS Storage');
    expect(content()).toContain('diag-rustfs-badge');
  });

  test('should display Hardware Transcoding test result', () => {
    expect(content()).toContain('Hardware Transcoding');
    expect(content()).toContain('diag-hw-badge');
  });

  test('should show overall pass/fail result', () => {
    expect(content()).toContain('diag-overall');
    expect(content()).toContain('all_passed');
  });

  test('should call /api/test endpoint', () => {
    expect(content()).toContain('/api/test');
  });
});

// =====================================================
// 3. Companion /api/test includes main app connectivity
// =====================================================

test.describe('Companion /api/test main app connectivity', () => {
  const content = () => readFile('companion/app.py');

  test('should test main app connectivity in run_diagnostics', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('main_app');
    expect(funcBody).toContain('MAIN_APP_URL');
  });

  test('should handle ConnectionError for main app test', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('ConnectionError');
  });

  test('should handle Timeout for main app test', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('Timeout');
  });

  test('should return main_app in test results', () => {
    const c = content();
    const funcStart = c.indexOf('def run_diagnostics');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(funcBody).toContain('"main_app"');
  });
});

// =====================================================
// 4. Companion dashboard has hardware readiness indicators
// =====================================================

test.describe('Companion dashboard hardware readiness', () => {
  const content = () => readFile('companion/templates/index.html');

  test('should have hardware readiness section', () => {
    expect(content()).toContain('hw-readiness');
    expect(content()).toContain('Hardware Readiness');
  });

  test('should have updateHwReadiness function', () => {
    expect(content()).toContain('function updateHwReadiness');
  });

  test('should check for encoders, decoders, and methods', () => {
    const c = content();
    expect(c).toContain('hasEncoders');
    expect(c).toContain('hasDecoders');
    expect(c).toContain('hasMethods');
  });

  test('should display ready state with green indicator for full hardware support', () => {
    expect(content()).toContain('Hardware Transcoding Ready');
  });

  test('should display warning for software-only mode', () => {
    expect(content()).toContain('Software Transcoding Mode');
  });

  test('should display error state when no hardware detected', () => {
    expect(content()).toContain('No Hardware Acceleration');
  });

  test('should call updateHwReadiness from refreshHealth', () => {
    expect(content()).toContain('updateHwReadiness(hw, d)');
  });

  test('should check S3 connectivity in readiness indicator', () => {
    expect(content()).toContain('s3_connected');
    expect(content()).toContain('S3/RustFS connected');
  });
});
