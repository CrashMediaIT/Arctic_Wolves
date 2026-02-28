/**
 * Tests for Companion Upload CSP and 504 Timeout Fix
 *
 * Verifies:
 * 1. Gameplan NGINX subdomain has CSP fallback header with cdn.jsdelivr.net
 *    in connect-src (prevents CSP errors on 504/error pages)
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
// 1. Gameplan NGINX subdomain CSP includes cdn.jsdelivr.net
// =====================================================

test.describe('Gameplan NGINX subdomain has CSP fallback', () => {
  test('should include Content-Security-Policy header in gameplan server block', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    // Find the gameplan server block
    const gameplanStart = content.indexOf('server_name gameplan.arcticwolves.ca;');
    expect(gameplanStart).toBeGreaterThan(0);
    // Find the end of the gameplan server block (next server { or end of file)
    const gameplanEnd = content.indexOf('# =====', gameplanStart);
    const gameplanBlock = content.substring(gameplanStart, gameplanEnd > -1 ? gameplanEnd : undefined);
    expect(gameplanBlock).toContain('Content-Security-Policy');
  });

  test('should include cdn.jsdelivr.net in gameplan CSP connect-src', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    const gameplanStart = content.indexOf('server_name gameplan.arcticwolves.ca;');
    const gameplanEnd = content.indexOf('# =====', gameplanStart);
    const gameplanBlock = content.substring(gameplanStart, gameplanEnd > -1 ? gameplanEnd : undefined);
    // Extract CSP from the gameplan block
    const cspMatch = gameplanBlock.match(/Content-Security-Policy "([^"]+)"/);
    expect(cspMatch).not.toBeNull();
    // Extract connect-src
    const connectSrcMatch = cspMatch[1].match(/connect-src\s+([^;]+);/);
    expect(connectSrcMatch).not.toBeNull();
    expect(connectSrcMatch[1]).toContain('https://cdn.jsdelivr.net');
  });

  test('should include www.google.com in gameplan CSP connect-src', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    const gameplanStart = content.indexOf('server_name gameplan.arcticwolves.ca;');
    const gameplanEnd = content.indexOf('# =====', gameplanStart);
    const gameplanBlock = content.substring(gameplanStart, gameplanEnd > -1 ? gameplanEnd : undefined);
    const cspMatch = gameplanBlock.match(/Content-Security-Policy "([^"]+)"/);
    expect(cspMatch).not.toBeNull();
    const connectSrcMatch = cspMatch[1].match(/connect-src\s+([^;]+);/);
    expect(connectSrcMatch).not.toBeNull();
    expect(connectSrcMatch[1]).toContain('https://www.google.com');
  });

  test('gameplan CSP should use always flag for error pages', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    const gameplanStart = content.indexOf('server_name gameplan.arcticwolves.ca;');
    const gameplanEnd = content.indexOf('# =====', gameplanStart);
    const gameplanBlock = content.substring(gameplanStart, gameplanEnd > -1 ? gameplanEnd : undefined);
    // The CSP header should end with 'always;' to apply to error pages (504, etc.)
    expect(gameplanBlock).toMatch(/Content-Security-Policy\s+"[^"]+"\s+always;/);
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
