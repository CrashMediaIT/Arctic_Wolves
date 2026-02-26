/**
 * Tests for RustFS S3 Backup Destination
 *
 * Verifies:
 * 1. Backup destination options use RustFS S3 instead of Nextcloud
 * 2. process_database_backup.php uses s3 as default destination
 * 3. Database backup UI offers RustFS options
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

test.describe('RustFS S3 backup destination', () => {
  test('process_database_backup.php should default destination to s3', () => {
    const content = readFile('process_database_backup.php');
    expect(content).toContain("'destination_type'] ?? 's3'");
  });

  test('process_database_backup.php should have force_rustfs action', () => {
    const content = readFile('process_database_backup.php');
    expect(content).toContain("case 'force_rustfs':");
  });

  test('process_database_backup.php should NOT have force_nextcloud action', () => {
    const content = readFile('process_database_backup.php');
    expect(content).not.toContain("case 'force_nextcloud':");
  });

  test('process_database_backup.php should NOT reference both_nextcloud destination', () => {
    const content = readFile('process_database_backup.php');
    expect(content).not.toContain("'both_nextcloud'");
  });

  test('performBackup should upload to RustFS for s3 destination', () => {
    const content = readFile('process_database_backup.php');
    expect(content).toContain("uploadContentToRustFS");
    expect(content).toContain("'Backups/' . $filename");
  });

  test('admin_database_backup.php should offer s3 destination option', () => {
    const content = readFile('views/admin_database_backup.php');
    expect(content).toContain('value="s3"');
    expect(content).toContain('RustFS S3 Storage');
  });

  test('admin_database_backup.php should NOT offer nextcloud destination', () => {
    const content = readFile('views/admin_database_backup.php');
    expect(content).not.toContain('value="nextcloud"');
    expect(content).not.toContain('value="both_nextcloud"');
  });

  test('admin_database_backup.php should have forceRustFSBackup function', () => {
    const content = readFile('views/admin_database_backup.php');
    expect(content).toContain('function forceRustFSBackup()');
  });

  test('cron_database_backup.php should check for s3 destination', () => {
    const content = readFile('cron_database_backup.php');
    expect(content).toContain("'s3'");
    expect(content).not.toContain("'nextcloud'");
    expect(content).not.toContain("'both_nextcloud'");
  });
});
