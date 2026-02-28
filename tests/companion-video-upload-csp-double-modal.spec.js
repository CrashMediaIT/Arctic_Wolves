/**
 * Tests for Companion Video Upload CSP Fix and Double Delete Modal Fix
 *
 * Verifies:
 * 1. gameplan.php passes RustFS endpoint origin to CSP connect-src
 *    (matching dashboard.php / pwa.php pattern) so video uploads via
 *    the companion app are not blocked by Content Security Policy.
 * 2. app.js does NOT attach a global delete-video handler that would
 *    duplicate the page-level handler in video_coach_reviews.php and
 *    video_drill_review.php, preventing two confirmation modals.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. gameplan.php includes RustFS endpoint in CSP
// =====================================================

test.describe('gameplan.php includes RustFS endpoint in CSP connect-src', () => {
  test('should require cloud_config.php', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain("require_once __DIR__ . '/cloud_config.php'");
  });

  test('should get RustFS settings and extract endpoint origin', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('getRustFSSettings($pdo)');
    expect(content).toContain('isRustFSConfigured($rustfs)');
    expect(content).toContain("parse_url(rtrim($rustfs['rustfs_endpoint'], '/'))");
  });

  test('should build origin from parsed endpoint', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain("$parsedEndpoint['scheme']");
    expect(content).toContain("$parsedEndpoint['host']");
    expect(content).toContain("$parsedEndpoint['port']");
    expect(content).toContain('$extraConnectSrc[]');
  });

  test('should pass $extraConnectSrc to setSecurityHeaders', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain('setSecurityHeaders($extraConnectSrc)');
  });

  test('should wrap RustFS lookup in try-catch to avoid breaking on DB errors', () => {
    const content = readFile('gameplan.php');
    const csrfIdx = content.indexOf('$extraConnectSrc = []');
    const headerIdx = content.indexOf('setSecurityHeaders($extraConnectSrc)');
    const block = content.substring(csrfIdx, headerIdx);
    expect(block).toContain('try {');
    expect(block).toContain('} catch');
  });
});

// =====================================================
// 2. app.js does NOT have a global delete-video handler
// =====================================================

test.describe('No duplicate delete-video handler in app.js', () => {
  test('app.js should not attach addEventListener for delete-video buttons', () => {
    const content = readFile('js/app.js');
    // The global handler previously bound click listeners to [data-action="delete-video"]
    // and called showConfirmModal, duplicating the page-level handlers.
    const lines = content.split('\n');
    const deleteVideoBindings = lines.filter(line => {
      const trimmed = line.trim();
      if (trimmed.startsWith('//') || trimmed.startsWith('*') || trimmed.startsWith('/*')) return false;
      return trimmed.includes("'delete-video'") && trimmed.includes('addEventListener');
    });
    expect(deleteVideoBindings.length).toBe(0);
  });

  test('video_coach_reviews.php should still have its own delete handler', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain("data-action=\"delete-video\"");
    expect(content).toContain("getElementById('deleteModal')");
    expect(content).toContain("getElementById('confirmDeleteBtn')");
  });

  test('video_drill_review.php should still have its own delete handler', () => {
    const content = readFile('views/video_drill_review.php');
    expect(content).toContain("data-action=\"delete-video\"");
    expect(content).toContain('showConfirmModal');
  });
});
