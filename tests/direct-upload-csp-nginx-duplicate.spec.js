/**
 * Tests for Direct Upload CSP Fix — NGINX Duplicate Header Prevention
 *
 * Verifies:
 * 1. NGINX PHP location blocks include their own security headers (add_header)
 *    so that the server-level static CSP is NOT inherited for PHP responses.
 *    PHP sets CSP dynamically via security.php (with RustFS/S3 endpoint origin),
 *    so having a second static CSP from NGINX would block direct S3 uploads.
 * 2. Server-level static CSP is still present as a fallback for non-PHP assets.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

/**
 * Extract a specific location block from an NGINX server block string.
 * Returns the content between the opening { and closing } of the location.
 */
function extractLocationBlock(serverBlock, locationPattern) {
  const idx = serverBlock.indexOf(locationPattern);
  if (idx === -1) return null;
  let braceCount = 0;
  let start = -1;
  for (let i = idx; i < serverBlock.length; i++) {
    if (serverBlock[i] === '{') {
      if (start === -1) start = i;
      braceCount++;
    } else if (serverBlock[i] === '}') {
      braceCount--;
      if (braceCount === 0) {
        return serverBlock.substring(start + 1, i);
      }
    }
  }
  return null;
}

// =====================================================
// Helper: extract main-site and gameplan server blocks
// =====================================================

function getMainSiteServerBlock() {
  const content = readFile('deployment/arctic_wolves.conf');
  const mainStart = content.indexOf('server_name arcticwolves.ca www.arcticwolves.ca;');
  const mainEnd = content.indexOf('server_name gameplan.arcticwolves.ca;');
  return content.substring(mainStart, mainEnd > -1 ? mainEnd : undefined);
}

function getGameplanServerBlock() {
  const content = readFile('deployment/arctic_wolves.conf');
  const gpStart = content.indexOf('server_name gameplan.arcticwolves.ca;');
  // Gameplan server block ends at the next section separator or SSL block
  const gpEnd = content.indexOf('# =====', gpStart);
  return content.substring(gpStart, gpEnd > -1 ? gpEnd : undefined);
}

// =====================================================
// 1. Main-site PHP location block prevents CSP inheritance
// =====================================================

test.describe('Main-site NGINX PHP location block prevents CSP inheritance', () => {
  test('should have add_header directives in PHP location block', () => {
    const serverBlock = getMainSiteServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).not.toBeNull();
    expect(phpBlock).toContain('add_header');
  });

  test('should include X-Frame-Options in PHP location block', () => {
    const serverBlock = getMainSiteServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).toContain('X-Frame-Options');
    expect(phpBlock).toContain('SAMEORIGIN');
  });

  test('should include X-Content-Type-Options in PHP location block', () => {
    const serverBlock = getMainSiteServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).toContain('X-Content-Type-Options');
    expect(phpBlock).toContain('nosniff');
  });

  test('should include Referrer-Policy in PHP location block', () => {
    const serverBlock = getMainSiteServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).toContain('Referrer-Policy');
  });

  test('should NOT include Content-Security-Policy in PHP location block', () => {
    const serverBlock = getMainSiteServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).not.toContain('Content-Security-Policy');
  });
});

// =====================================================
// 2. Gameplan PHP location block prevents CSP inheritance
// =====================================================

test.describe('Gameplan NGINX PHP location block prevents CSP inheritance', () => {
  test('should have add_header directives in PHP location block', () => {
    const serverBlock = getGameplanServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).not.toBeNull();
    expect(phpBlock).toContain('add_header');
  });

  test('should include X-Frame-Options in PHP location block', () => {
    const serverBlock = getGameplanServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).toContain('X-Frame-Options');
    expect(phpBlock).toContain('SAMEORIGIN');
  });

  test('should include X-Content-Type-Options in PHP location block', () => {
    const serverBlock = getGameplanServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).toContain('X-Content-Type-Options');
    expect(phpBlock).toContain('nosniff');
  });

  test('should NOT include Content-Security-Policy in PHP location block', () => {
    const serverBlock = getGameplanServerBlock();
    const phpBlock = extractLocationBlock(serverBlock, 'location ~ \\.php$');
    expect(phpBlock).not.toContain('Content-Security-Policy');
  });
});

// =====================================================
// 3. Server-level static CSP still present as fallback
// =====================================================

test.describe('Server-level static CSP remains as fallback for non-PHP assets', () => {
  test('main site should still have server-level Content-Security-Policy', () => {
    const serverBlock = getMainSiteServerBlock();
    // The CSP should exist at server level (outside the PHP location block)
    expect(serverBlock).toContain('Content-Security-Policy');
  });

  test('gameplan should still have server-level Content-Security-Policy', () => {
    const serverBlock = getGameplanServerBlock();
    expect(serverBlock).toContain('Content-Security-Policy');
  });
});
