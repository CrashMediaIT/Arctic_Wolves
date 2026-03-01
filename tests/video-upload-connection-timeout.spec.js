/**
 * Tests for Video Upload Connection Timeout
 *
 * Verifies that all direct-to-RustFS upload views include a connection
 * timeout so the browser falls back to the legacy server-side upload
 * when the cloud storage endpoint is unreachable.
 *
 * Before this fix, the XHR PUT to the presigned URL could hang
 * indefinitely if the S3/RustFS endpoint was unreachable from the
 * browser (e.g., internal-only hostname, firewall). Previously the
 * CSP header blocked the request instantly, triggering the fallback.
 * With CSP corrected, the request must now time out on its own.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

/**
 * Extract the Step 2 XHR block (the Promise that performs the PUT)
 * from a view file's inline JS.
 */
function getStep2Block(content) {
  // Look for the direct upload promise block — either the legacy inline
  // pattern or the refactored xhrPut helper pattern
  let start = content.indexOf("xhr.open('PUT', presignedUrl");
  if (start === -1) {
    // Refactored pattern: uses a helper function with setTimeout inside
    start = content.indexOf("xhr.open('PUT', url, true)");
  }
  if (start === -1) return null;
  // Go back to the enclosing "new Promise" or "function xhrPut"
  let promiseStart = content.lastIndexOf('new Promise', start);
  if (promiseStart === -1) {
    promiseStart = content.lastIndexOf('function xhrPut', start);
  }
  // Go forward to the matching closing of the promise
  const promiseEnd = content.indexOf('xhr.send(', start);
  if (promiseStart === -1 || promiseEnd === -1) return null;
  return content.substring(promiseStart, promiseEnd + 40);
}

// =====================================================
// 1. Connection timeout present in all upload views
// =====================================================

const VIEWS = [
  { name: 'video_record_athlete',  path: 'views/video_record_athlete.php' },
  { name: 'video_coach_reviews',   path: 'views/video_coach_reviews.php' },
  { name: 'film_room',             path: 'views/gameplan/film_room.php' },
  { name: 'gp_film_room',          path: 'views/gameplan/gp_film_room.php' },
  { name: 'video_record_drill',    path: 'views/pwa/video_record_drill.php' },
];

test.describe('Direct upload XHR has connection timeout in all views', () => {
  for (const view of VIEWS) {
    test(`${view.name} should have a connection timeout timer`, () => {
      const content = readFile(view.path);
      const block = getStep2Block(content);
      expect(block).not.toBeNull();

      // Must declare a timer with setTimeout
      expect(block).toContain('setTimeout');
      // Must abort the XHR when the timer fires
      expect(block).toContain('xhr.abort()');
      // Must track whether upload progress has started
      expect(block).toContain('uploadStarted');
    });

    test(`${view.name} should clear the timer on upload progress`, () => {
      const content = readFile(view.path);
      const block = getStep2Block(content);
      expect(block).not.toBeNull();

      // The onprogress handler must clear the connection timer
      expect(block).toContain('clearTimeout(connTimer)');
    });

    test(`${view.name} should clear the timer on XHR load and error`, () => {
      const content = readFile(view.path);
      const block = getStep2Block(content);
      expect(block).not.toBeNull();

      // onload and onerror must also clear the timer to prevent
      // stale timer firing after the request has already settled
      const onloadMatch = block.match(/xhr\.onload\s*=\s*function/);
      expect(onloadMatch).not.toBeNull();
      const onerrorMatch = block.match(/xhr\.onerror\s*=\s*function/);
      expect(onerrorMatch).not.toBeNull();

      // Count clearTimeout calls — at least 3: onprogress, onload, onerror
      const clearTimeoutCount = (block.match(/clearTimeout\(connTimer\)/g) || []).length;
      expect(clearTimeoutCount).toBeGreaterThanOrEqual(3);
    });

    test(`${view.name} should reject the promise on connection timeout`, () => {
      const content = readFile(view.path);
      const block = getStep2Block(content);
      expect(block).not.toBeNull();

      // The timeout handler must reject the promise so the catch/fallback fires
      expect(block).toMatch(/reject\(new Error\(.+connection timed out/);
    });
  }
});

// =====================================================
// 2. Legacy fallback still present in all views
// =====================================================

test.describe('Legacy fallback upload is preserved in all views', () => {
  for (const view of VIEWS) {
    test(`${view.name} should have a .catch handler with legacy fallback`, () => {
      const content = readFile(view.path);
      // Every upload view must still have the catch → legacy XHR fallback
      expect(content).toContain('falling back');
      expect(content).toMatch(/legacyXhr\.open\('POST'/);
      expect(content).toMatch(/legacyXhr\.send\(/);
    });
  }
});

// =====================================================
// 3. Presigned URL signing uses only 'host' (not content-type)
//    to match AWS SDK behaviour and avoid RustFS
//    SignatureDoesNotMatch errors (rustfs/rustfs#700).
// =====================================================

test.describe('Presigned URL signs only host header', () => {
  test('X-Amz-SignedHeaders should be host only', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // Must sign only 'host', NOT 'content-type;host'
    expect(funcBody).toContain("'X-Amz-SignedHeaders'  => 'host'");
    expect(funcBody).not.toContain("'X-Amz-SignedHeaders'  => 'content-type;host'");
  });

  test('canonical headers should include only host', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function generatePresignedUploadUrl(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // Canonical headers must NOT include content-type for presigned URLs
    expect(funcBody).toContain("$canonical_headers = 'host:' . $host");
    expect(funcBody).toContain("$signed_headers = 'host'");
    expect(funcBody).not.toMatch(/\$canonical_headers\s*=\s*'content-type:/);
  });
});
