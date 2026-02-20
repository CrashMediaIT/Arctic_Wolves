/**
 * Tests for Database Backup Nextcloud Destination
 *
 * Verifies:
 * 1. Quick Backup (manual_backup without job ID) uses both_nextcloud destination
 * 2. Force Nextcloud Backup uses both_nextcloud destination
 * 3. performBackup in process_database_backup.php handles both_nextcloud destination
 * 4. performBackup uploads to secondary Nextcloud when both_nextcloud is selected
 * 5. Cron backup also handles both_nextcloud destination (existing behaviour)
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('Backup Nextcloud Destination - process_database_backup.php', () => {
  let content;

  test.beforeAll(() => {
    const filePath = path.join(__dirname, '..', 'process_database_backup.php');
    content = fs.readFileSync(filePath, 'utf-8');
  });

  test('Quick Backup should use both_nextcloud destination type', () => {
    // The Quick Backup temporary job config should target both Nextcloud instances
    const quickBackupMatch = content.match(
      /['"]name['"]\s*=>\s*['"]Quick Backup['"][\s\S]*?['"]destination_type['"]\s*=>\s*['"]([^'"]+)['"]/
    );
    expect(quickBackupMatch).not.toBeNull();
    expect(quickBackupMatch[1]).toBe('both_nextcloud');
  });

  test('Force Nextcloud Backup should use both_nextcloud destination type', () => {
    // The Force Nextcloud Backup temporary job config should target both instances
    const forceMatch = content.match(
      /['"]name['"]\s*=>\s*['"]Force Nextcloud Backup['"][\s\S]*?['"]destination_type['"]\s*=>\s*['"]([^'"]+)['"]/
    );
    expect(forceMatch).not.toBeNull();
    expect(forceMatch[1]).toBe('both_nextcloud');
  });

  test('performBackup should handle both_nextcloud for primary Nextcloud upload', () => {
    // The primary Nextcloud upload condition should include both_nextcloud
    expect(content).toContain("destination_type'] === 'both_nextcloud'");
    // Check that primary upload condition covers both_nextcloud alongside nextcloud and both
    const primaryUploadPattern = /Upload to primary Nextcloud[\s\S]*?destination_type.*?===.*?'both_nextcloud'/;
    expect(content).toMatch(primaryUploadPattern);
  });

  test('performBackup should upload to secondary Nextcloud for both_nextcloud destination', () => {
    // Should call getSecondaryNextcloudSettings for the secondary instance
    expect(content).toContain('getSecondaryNextcloudSettings');
    // Should have a block that uploads to secondary Nextcloud
    const secondaryUploadPattern = /Upload to secondary Nextcloud.*both_nextcloud/;
    expect(content).toMatch(secondaryUploadPattern);
  });

  test('performBackup should track secondary Nextcloud destination in success list', () => {
    // Should append 'Nextcloud2:' to the success destinations
    expect(content).toContain("'Nextcloud2: '");
  });
});

test.describe('Backup Nextcloud Destination - cron_database_backup.php', () => {
  let content;

  test.beforeAll(() => {
    const filePath = path.join(__dirname, '..', 'cron_database_backup.php');
    content = fs.readFileSync(filePath, 'utf-8');
  });

  test('Cron backup should handle both_nextcloud for primary Nextcloud upload', () => {
    const primaryUploadPattern = /destination_type.*?===.*?'both_nextcloud'/;
    expect(content).toMatch(primaryUploadPattern);
  });

  test('Cron backup should upload to secondary Nextcloud for both_nextcloud destination', () => {
    expect(content).toContain('getSecondaryNextcloudSettings');
    const secondaryUploadPattern = /Upload to secondary Nextcloud.*both_nextcloud/;
    expect(content).toMatch(secondaryUploadPattern);
  });
});
