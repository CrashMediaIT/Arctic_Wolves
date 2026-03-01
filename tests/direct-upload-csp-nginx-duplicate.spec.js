/**
 * Tests for Direct Upload CSP Fix — NGINX Duplicate Header Prevention
 *
 * Verifies:
 * 1. NGINX PHP location blocks include their own security headers (add_header)
 *    so that any accidental server-level headers are NOT inherited.
 * 2. Server-level CSP add_header is NOT present (prevents duplicate CSP headers
 *    from NGINX and PHP that would block direct S3/RustFS uploads).
 * 3. PHP's security.php is the single source of truth for CSP.
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
// 3. Server-level CSP is NOT present (managed by PHP only)
//    Prevents duplicate CSP headers that block direct uploads
// =====================================================

test.describe('Server-level CSP removed to prevent duplicate headers', () => {
  test('main site should NOT have a server-level add_header Content-Security-Policy', () => {
    const serverBlock = getMainSiteServerBlock();
    // The PHP location block mentions CSP in comments, but should not have an add_header CSP
    // at the server level. Check that no add_header Content-Security-Policy line exists
    // outside the PHP location block.
    const phpBlockStart = serverBlock.indexOf('location ~ \\.php$');
    const serverLevelBlock = serverBlock.substring(0, phpBlockStart > -1 ? phpBlockStart : undefined);
    // Should not have an active add_header Content-Security-Policy directive
    const lines = serverLevelBlock.split('\n');
    const cspAddHeaderLines = lines.filter(l => {
      const trimmed = l.trim();
      return trimmed.startsWith('add_header') && trimmed.includes('Content-Security-Policy');
    });
    expect(cspAddHeaderLines).toHaveLength(0);
  });

  test('gameplan should NOT have a server-level add_header Content-Security-Policy', () => {
    const serverBlock = getGameplanServerBlock();
    const phpBlockStart = serverBlock.indexOf('location ~ \\.php$');
    const serverLevelBlock = serverBlock.substring(0, phpBlockStart > -1 ? phpBlockStart : undefined);
    const lines = serverLevelBlock.split('\n');
    const cspAddHeaderLines = lines.filter(l => {
      const trimmed = l.trim();
      return trimmed.startsWith('add_header') && trimmed.includes('Content-Security-Policy');
    });
    expect(cspAddHeaderLines).toHaveLength(0);
  });
});

// =====================================================
// 4. PHP CSP connect-src includes *.arcticwolves.ca
//    so S3/RustFS direct uploads are never blocked
// =====================================================

test.describe('PHP CSP connect-src includes wildcard subdomain for S3 uploads', () => {
  test('security.php base connect-src should include https://*.arcticwolves.ca', () => {
    const content = readFile('security.php');
    expect(content).toContain("'self' wss: https://*.arcticwolves.ca");
  });
});
