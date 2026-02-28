/**
 * Tests for Companion Video Upload 504 Fix
 *
 * Verifies:
 * 1. Database schema uses correct gameplan_companion_* setting key names
 *    (not bare companion_url / companion_api_key) so settings entered in
 *    the gameplan system tools tab are actually read by triggerHlsTranscode.
 * 2. Videos table includes hls_master_url and hls_segments_path columns
 *    required by triggerHlsTranscode and companion callback endpoint.
 * 3. gameplan_app_url setting is seeded in the schema for companion callbacks.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Database schema companion setting keys match code
// =====================================================

test.describe('database_schema.sql companion setting keys', () => {
  const content = () => readFile('database_schema.sql');

  test('should seed gameplan_companion_url (not bare companion_url)', () => {
    const c = content();
    expect(c).toContain("'gameplan_companion_url'");
  });

  test('should seed gameplan_companion_api_key (not bare companion_api_key)', () => {
    const c = content();
    expect(c).toContain("'gameplan_companion_api_key'");
  });

  test('should seed gameplan_app_url for companion callbacks', () => {
    const c = content();
    expect(c).toContain("'gameplan_app_url'");
  });

  test('should NOT seed bare companion_url key', () => {
    const c = content();
    // Match the exact INSERT pattern for the wrong key name
    expect(c).not.toMatch(/INSERT.*INTO.*system_settings.*'companion_url'/);
  });

  test('should NOT seed bare companion_api_key key', () => {
    const c = content();
    expect(c).not.toMatch(/INSERT.*INTO.*system_settings.*'companion_api_key'/);
  });

  test('setting keys should match what triggerHlsTranscode reads', () => {
    const schema = content();
    const processVideo = readFile('process_video.php');
    const func = processVideo.substring(processVideo.indexOf('function triggerHlsTranscode'));

    // triggerHlsTranscode looks for these three keys
    expect(func).toContain("'gameplan_companion_url'");
    expect(func).toContain("'gameplan_companion_api_key'");
    expect(func).toContain("'gameplan_app_url'");

    // Schema must seed the same keys
    expect(schema).toContain("'gameplan_companion_url'");
    expect(schema).toContain("'gameplan_companion_api_key'");
    expect(schema).toContain("'gameplan_app_url'");
  });

  test('setting keys should match what process_gameplan_settings.php saves', () => {
    const schema = content();
    const settings = readFile('process_gameplan_settings.php');

    // process_gameplan_settings.php saves these keys via upsertGameplanSetting
    expect(settings).toContain("'gameplan_companion_url'");
    expect(settings).toContain("'gameplan_companion_api_key'");
    expect(settings).toContain("'gameplan_app_url'");

    // Schema seeds must match
    expect(schema).toContain("'gameplan_companion_url'");
    expect(schema).toContain("'gameplan_companion_api_key'");
    expect(schema).toContain("'gameplan_app_url'");
  });
});

// =====================================================
// 2. Videos table has required HLS columns
// =====================================================

test.describe('Videos table HLS columns in database_schema.sql', () => {
  const content = () => readFile('database_schema.sql');

  test('should have hls_master_url column', () => {
    const c = content();
    expect(c).toContain('hls_master_url');
  });

  test('should have hls_segments_path column', () => {
    const c = content();
    expect(c).toContain('hls_segments_path');
  });

  test('hls_master_url column should be in ALTER TABLE videos', () => {
    const c = content();
    // Find the ALTER TABLE videos section
    const alterStart = c.indexOf('ALTER TABLE `videos`');
    expect(alterStart).toBeGreaterThan(-1);
    const alterEnd = c.indexOf(';', alterStart);
    const alterBlock = c.substring(alterStart, alterEnd);
    expect(alterBlock).toContain('hls_master_url');
  });

  test('hls_segments_path column should be in ALTER TABLE videos', () => {
    const c = content();
    const alterStart = c.indexOf('ALTER TABLE `videos`');
    expect(alterStart).toBeGreaterThan(-1);
    const alterEnd = c.indexOf(';', alterStart);
    const alterBlock = c.substring(alterStart, alterEnd);
    expect(alterBlock).toContain('hls_segments_path');
  });
});

test.describe('Videos table HLS columns in deployment/schema.sql', () => {
  const content = () => readFile('deployment/schema.sql');

  test('should have hls_master_url column in CREATE TABLE', () => {
    const c = content();
    expect(c).toContain('hls_master_url');
  });

  test('should have hls_segments_path column in CREATE TABLE', () => {
    const c = content();
    expect(c).toContain('hls_segments_path');
  });
});

// =====================================================
// 3. triggerHlsTranscode uses columns that exist
// =====================================================

test.describe('triggerHlsTranscode column usage matches schema', () => {
  test('columns in triggerHlsTranscode UPDATE should exist in schema', () => {
    const processVideo = readFile('process_video.php');
    const func = processVideo.substring(processVideo.indexOf('function triggerHlsTranscode'));
    const schema = readFile('deployment/schema.sql');

    // The function updates these columns
    expect(func).toContain('hls_status');
    expect(func).toContain('hls_url');
    expect(func).toContain('hls_master_url');
    expect(func).toContain('hls_segments_path');

    // All must exist in the schema
    expect(schema).toContain('hls_status');
    expect(schema).toContain('hls_url');
    expect(schema).toContain('hls_master_url');
    expect(schema).toContain('hls_segments_path');
  });

  test('columns in companion callback UPDATE should exist in schema', () => {
    const callback = readFile('api/v1/companion.php');
    const schema = readFile('deployment/schema.sql');

    // Callback updates these columns
    expect(callback).toContain('hls_status');
    expect(callback).toContain('hls_master_url');
    expect(callback).toContain('hls_segments_path');
    expect(callback).toContain('hls_url');

    // All must exist in the schema
    expect(schema).toContain('hls_status');
    expect(schema).toContain('hls_master_url');
    expect(schema).toContain('hls_segments_path');
    expect(schema).toContain('hls_url');
  });
});
