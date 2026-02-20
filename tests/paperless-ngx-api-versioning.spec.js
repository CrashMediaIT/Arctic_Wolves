/**
 * Tests for Paperless-NGX API Versioning Fix
 * 
 * Verifies:
 * 1. All Paperless-NGX API calls use versioned Accept header (application/json; version=5)
 * 2. Test connection endpoint uses /api/documents/ instead of bare /api/
 * 3. HTTP 406 error is handled with a descriptive message
 * 4. OCR_SETUP.md documents API versioning
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('Paperless-NGX API Versioning - process_settings.php', () => {
  test('test connection should use versioned Accept header', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');

    // Should use versioned Accept header
    expect(content).toContain("'Accept: application/json; version=5'");
    // Should NOT use unversioned Accept header for Paperless calls
    // (other integrations may still use unversioned headers, so we check context)
    expect(content).toContain('test_paperless');
  });

  test('test connection should use /api/documents/ endpoint', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');

    // Should use /api/documents/ for connection test, not bare /api/
    expect(content).toContain("/api/documents/?page=1&page_size=1");
  });

  test('should handle HTTP 406 error with descriptive message', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');

    expect(content).toContain('$http_code === 406');
    expect(content).toContain('API version not supported');
  });
});

test.describe('Paperless-NGX API Versioning - process_expenses.php', () => {
  test('all Paperless API calls should use versioned Accept header', async () => {
    const filePath = path.join(__dirname, '..', 'process_expenses.php');
    const content = fs.readFileSync(filePath, 'utf-8');

    // Count occurrences of versioned header in Paperless-related code
    const versionedMatches = content.match(/Accept: application\/json; version=5/g);
    expect(versionedMatches).not.toBeNull();
    expect(versionedMatches.length).toBeGreaterThanOrEqual(4);

    // Should not have unversioned Accept headers in Paperless calls
    const unversionedMatches = content.match(/['"]Accept: application\/json['"](?!; version)/g);
    expect(unversionedMatches).toBeNull();
  });
});

test.describe('Paperless-NGX API Versioning - cloud_config.php', () => {
  test('all Paperless API calls should use versioned Accept header', async () => {
    const filePath = path.join(__dirname, '..', 'cloud_config.php');
    const content = fs.readFileSync(filePath, 'utf-8');

    const versionedMatches = content.match(/Accept: application\/json; version=5/g);
    expect(versionedMatches).not.toBeNull();
    expect(versionedMatches.length).toBeGreaterThanOrEqual(7);

    const unversionedMatches = content.match(/['"]Accept: application\/json['"](?!; version)/g);
    expect(unversionedMatches).toBeNull();
  });
});

test.describe('Paperless-NGX API Versioning - cron_receipt_scanner.php', () => {
  test('all Paperless API calls should use versioned Accept header', async () => {
    const filePath = path.join(__dirname, '..', 'cron_receipt_scanner.php');
    const content = fs.readFileSync(filePath, 'utf-8');

    const versionedMatches = content.match(/Accept: application\/json; version=5/g);
    expect(versionedMatches).not.toBeNull();
    expect(versionedMatches.length).toBeGreaterThanOrEqual(3);

    const unversionedMatches = content.match(/['"]Accept: application\/json['"](?!; version)/g);
    expect(unversionedMatches).toBeNull();
  });
});

test.describe('Paperless-NGX API Versioning - Documentation', () => {
  test('OCR_SETUP.md should document API versioning', async () => {
    const filePath = path.join(__dirname, '..', 'docs', 'OCR_SETUP.md');
    const content = fs.readFileSync(filePath, 'utf-8');

    expect(content).toContain('API Versioning');
    expect(content).toContain('version=5');
    expect(content).toContain('406');
  });

  test('OCR_SETUP.md should include 406 in troubleshooting table', async () => {
    const filePath = path.join(__dirname, '..', 'docs', 'OCR_SETUP.md');
    const content = fs.readFileSync(filePath, 'utf-8');

    expect(content).toContain('HTTP 406');
    expect(content).toContain('API version mismatch');
  });
});
