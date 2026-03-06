/**
 * Tests for the PHP-level catch-all in index.php that routes /api/ requests
 * to api/index.php.
 *
 * When nginx's `location /api/` block is missing or inactive, the main
 * site's `location /` fallback sends /api/* requests to index.php instead
 * of api/index.php, causing the companion callback (and any API caller) to
 * receive an HTML page instead of a JSON response.  The catch-all in
 * index.php prevents this by detecting /api/ in REQUEST_URI and including
 * api/index.php directly.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. index.php routes /api/ requests to api/index.php
// =====================================================

test.describe('index.php API catch-all guard', () => {
  const content = () => readFile('index.php');

  test('should detect /api/ prefix in REQUEST_URI', () => {
    const c = content();
    expect(c).toContain("REQUEST_URI");
    expect(c).toContain("/api/");
  });

  test('should include api/index.php for /api/ requests', () => {
    const c = content();
    expect(c).toContain("api/index.php");
  });

  test('should route /api/ requests BEFORE loading db_config', () => {
    const c = content();
    const apiGuardPos = c.indexOf("/api/");
    const dbConfigPos = c.indexOf("db_config.php");
    expect(apiGuardPos).toBeGreaterThan(0);
    expect(dbConfigPos).toBeGreaterThan(0);
    // The /api/ guard must come before db_config.php to avoid side effects
    expect(apiGuardPos).toBeLessThan(dbConfigPos);
  });

  test('should exit after including api/index.php', () => {
    const c = content();
    // Find the api/index.php require and check exit follows
    const requirePos = c.indexOf("require __DIR__ . '/api/index.php'");
    expect(requirePos).toBeGreaterThan(0);
    const afterRequire = c.substring(requirePos, requirePos + 200);
    expect(afterRequire).toContain('exit');
  });

  test('should use parse_url to extract path (ignore query string)', () => {
    const c = content();
    expect(c).toContain('parse_url');
    expect(c).toContain('PHP_URL_PATH');
  });
});

// =====================================================
// 2. nginx config still has location /api/ block
// =====================================================

test.describe('nginx config retains location /api/ block', () => {
  test('main site server block should still have location /api/ block', () => {
    const c = readFile('deployment/arctic_wolves.conf');
    const mainStart = c.indexOf('server_name arcticwolves.ca www.arcticwolves.ca;');
    expect(mainStart).toBeGreaterThan(0);
    const mainEnd = c.indexOf('# =====', mainStart);
    const mainBlock = c.substring(mainStart, mainEnd > -1 ? mainEnd : undefined);
    expect(mainBlock).toContain('location /api/');
    expect(mainBlock).toMatch(/location\s+\/api\/\s*\{[^}]*\/api\/index\.php/);
  });
});
