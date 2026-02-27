/**
 * Tests for Practice Plan Import CSP Fix
 *
 * Verifies fixes for:
 * 1. CSP connect-src includes https://cdn.jsdelivr.net so HLS.js source map fetches are not blocked
 * 2. NGINX CSP fallback also includes cdn.jsdelivr.net in connect-src
 * 3. deployment/schema.sql practice_plans table includes columns needed by import
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. CSP connect-src includes cdn.jsdelivr.net in PHP
// =====================================================

test.describe('security.php CSP connect-src includes cdn.jsdelivr.net', () => {
  test('should include https://cdn.jsdelivr.net in connect-src base string', () => {
    const content = readFile('security.php');
    const funcStart = content.indexOf('function setSecurityHeaders(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const funcBody = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // connect-src base should include cdn.jsdelivr.net
    expect(funcBody).toContain('https://cdn.jsdelivr.net');
    // Specifically in the connectSrc variable assignment
    expect(funcBody).toMatch(/\$connectSrc\s*=.*https:\/\/cdn\.jsdelivr\.net/);
  });

  test('cdn.jsdelivr.net should be in connect-src, not just script-src', () => {
    const content = readFile('security.php');
    // The connectSrc variable is used in the connect-src directive
    const connectSrcMatch = content.match(/\$connectSrc\s*=\s*"([^"]+)"/);
    expect(connectSrcMatch).not.toBeNull();
    expect(connectSrcMatch[1]).toContain('https://cdn.jsdelivr.net');
  });
});

// =====================================================
// 2. NGINX CSP fallback includes cdn.jsdelivr.net
// =====================================================

test.describe('NGINX CSP fallback includes cdn.jsdelivr.net in connect-src', () => {
  test('should include cdn.jsdelivr.net in connect-src directive', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    // Extract the full CSP header line (the add_header directive)
    const cspLine = content.match(/add_header Content-Security-Policy "([^"]+)"/);
    expect(cspLine).not.toBeNull();
    // Extract connect-src from within the CSP header value
    const connectSrcMatch = cspLine[1].match(/connect-src\s+([^;]+);/);
    expect(connectSrcMatch).not.toBeNull();
    expect(connectSrcMatch[1]).toContain('https://cdn.jsdelivr.net');
  });
});

// =====================================================
// 3. deployment/schema.sql practice_plans has import columns
// =====================================================

test.describe('deployment/schema.sql practice_plans table has import columns', () => {
  test('should include focus_area column', () => {
    const content = readFile('deployment/schema.sql');
    const tableStart = content.indexOf('CREATE TABLE IF NOT EXISTS `practice_plans`');
    const tableEnd = content.indexOf(') ENGINE=InnoDB', tableStart);
    const tableBody = content.substring(tableStart, tableEnd);
    expect(tableBody).toContain('`focus_area`');
  });

  test('should include age_group column', () => {
    const content = readFile('deployment/schema.sql');
    const tableStart = content.indexOf('CREATE TABLE IF NOT EXISTS `practice_plans`');
    const tableEnd = content.indexOf(') ENGINE=InnoDB', tableStart);
    const tableBody = content.substring(tableStart, tableEnd);
    expect(tableBody).toContain('`age_group`');
  });

  test('should include duration_minutes column', () => {
    const content = readFile('deployment/schema.sql');
    const tableStart = content.indexOf('CREATE TABLE IF NOT EXISTS `practice_plans`');
    const tableEnd = content.indexOf(') ENGINE=InnoDB', tableStart);
    const tableBody = content.substring(tableStart, tableEnd);
    expect(tableBody).toContain('`duration_minutes`');
  });

  test('should match database_schema.sql practice_plans columns', () => {
    const deploySchema = readFile('deployment/schema.sql');
    const dbSchema = readFile('database_schema.sql');

    // Both should have the key columns
    const requiredColumns = ['focus_area', 'age_group', 'duration_minutes', 'share_token', 'title'];
    for (const col of requiredColumns) {
      const deployStart = deploySchema.indexOf('CREATE TABLE IF NOT EXISTS `practice_plans`');
      const deployEnd = deploySchema.indexOf(') ENGINE=InnoDB', deployStart);
      const deployTable = deploySchema.substring(deployStart, deployEnd);

      const dbStart = dbSchema.indexOf('CREATE TABLE IF NOT EXISTS `practice_plans`');
      const dbEnd = dbSchema.indexOf(') ENGINE=InnoDB', dbStart);
      const dbTable = dbSchema.substring(dbStart, dbEnd);

      expect(deployTable).toContain(`\`${col}\``);
      expect(dbTable).toContain(`\`${col}\``);
    }
  });
});

// =====================================================
// 4. process_practice_plans.php import uses correct columns
// =====================================================

test.describe('practice plan import SQL matches schema', () => {
  test('import_ihs_practice_plan INSERT should use columns from the schema', () => {
    const content = readFile('process_practice_plans.php');
    const importSection = content.substring(
      content.indexOf("if ($action === 'import_ihs_practice_plan')"),
      content.indexOf("function parseIHSPracticePlanPage")
    );

    // The INSERT should reference these columns which must exist in the schema
    expect(importSection).toContain('focus_area');
    expect(importSection).toContain('age_group');
    expect(importSection).toContain('duration_minutes');
  });
});
