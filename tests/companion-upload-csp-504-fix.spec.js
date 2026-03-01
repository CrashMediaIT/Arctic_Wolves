/**
 * Tests for Companion Upload CSP and 504 Timeout Fix
 *
 * Verifies:
 * 1. Gameplan NGINX server block has NO server-level CSP (PHP manages CSP exclusively
 *    to prevent duplicate headers that block direct S3 uploads). CSP domains verified
 *    in security.php instead.
 * 2. Film room upload forms use AJAX with progress to avoid 504 timeouts
 * 3. handleUploadVideoSource returns JSON for AJAX requests
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Gameplan NGINX server block has NO server-level CSP
//    (CSP managed exclusively by PHP — verified in security.php)
// =====================================================

test.describe('Gameplan NGINX server block CSP removed', () => {
  test('should NOT have a server-level add_header Content-Security-Policy', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    const gameplanStart = content.indexOf('server_name gameplan.arcticwolves.ca;');
    expect(gameplanStart).toBeGreaterThan(0);
    const gameplanEnd = content.indexOf('# =====', gameplanStart);
    const gameplanBlock = content.substring(gameplanStart, gameplanEnd > -1 ? gameplanEnd : undefined);
    // Extract PHP location block boundary to check only server-level directives
    const phpBlockStart = gameplanBlock.indexOf('location ~ \\.php$');
    const serverLevelBlock = gameplanBlock.substring(0, phpBlockStart > -1 ? phpBlockStart : undefined);
    const lines = serverLevelBlock.split('\n');
    const cspAddHeaderLines = lines.filter(l => {
      const trimmed = l.trim();
      return trimmed.startsWith('add_header') && trimmed.includes('Content-Security-Policy');
    });
    expect(cspAddHeaderLines).toHaveLength(0);
  });

  test('security.php CSP connect-src should include cdn.jsdelivr.net', () => {
    const content = readFile('security.php');
    const connectSrcMatch = content.match(/\$connectSrc\s*=\s*"([^"]+)"/);
    expect(connectSrcMatch).not.toBeNull();
    expect(connectSrcMatch[1]).toContain('https://cdn.jsdelivr.net');
  });

  test('security.php CSP connect-src should include www.google.com', () => {
    const content = readFile('security.php');
    const connectSrcMatch = content.match(/\$connectSrc\s*=\s*"([^"]+)"/);
    expect(connectSrcMatch).not.toBeNull();
    expect(connectSrcMatch[1]).toContain('https://www.google.com');
  });
});

// =====================================================
// 2. Film room upload uses AJAX with progress
// =====================================================

test.describe('gp_film_room.php upload uses AJAX with progress', () => {
  test('should have an upload progress overlay element', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('vrUploadProgressOverlay');
    expect(content).toContain('vrUploadProgressBar');
    expect(content).toContain('vrUploadProgressPercent');
    expect(content).toContain('vrUploadProgressStatus');
  });

  test('should intercept form submission with preventDefault', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain("e.preventDefault()");
    expect(content).toContain("uploadForm.addEventListener('submit'");
  });

  test('should use XMLHttpRequest with X-Requested-With header', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('new XMLHttpRequest()');
    expect(content).toContain("'X-Requested-With'");
    expect(content).toContain("'XMLHttpRequest'");
  });

  test('should track upload progress via onprogress', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('xhr.upload.onprogress');
    expect(content).toContain('ev.lengthComputable');
  });

  test('should handle upload errors gracefully', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('xhr.onerror');
    expect(content).toContain('xhr.ontimeout');
  });
});

test.describe('film_room.php upload uses AJAX with progress', () => {
  test('should have an upload progress overlay element', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('vrUploadProgressOverlay');
    expect(content).toContain('vrUploadProgressBar');
  });

  test('should intercept form submission with AJAX', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain("e.preventDefault()");
    expect(content).toContain('new XMLHttpRequest()');
    expect(content).toContain("'X-Requested-With'");
  });
});

// =====================================================
// 3. handleUploadVideoSource returns JSON for AJAX
// =====================================================

test.describe('handleUploadVideoSource supports AJAX responses', () => {
  test('should check for X-Requested-With XMLHttpRequest header', () => {
    const content = readFile('process_video.php');
    // Find the handleUploadVideoSource function
    const funcStart = content.indexOf('function handleUploadVideoSource()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('HTTP_X_REQUESTED_WITH');
    expect(funcBody).toContain('xmlhttprequest');
  });

  test('should return JSON with success and redirect for AJAX', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleUploadVideoSource()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("'Content-Type: application/json'");
    expect(funcBody).toContain("'success' => true");
    expect(funcBody).toContain("'redirect'");
  });

  test('should still redirect for standard form submissions', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleUploadVideoSource()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain("Location: /gameplan.php?page=film_room");
  });
});
