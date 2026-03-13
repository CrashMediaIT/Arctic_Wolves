/**
 * Tests for Table Creation Fix and Rebuild Schema UI
 *
 * Verifies:
 * 1. DatabaseMigrator uses information_schema for reliable table/column detection
 *    (instead of SHOW TABLES LIKE ? which can fail with non-emulated prepared statements)
 * 2. runSchemaCheck uses two-pass processing (tables first, columns second)
 * 3. setup.php uses same two-pass processing
 * 4. process_settings.php deferred handler uses two-pass processing
 * 5. Rebuild Schema UI exists in database tools (desktop and PWA)
 * 6. process_database_backup.php has rebuild_schema action
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. DatabaseMigrator uses information_schema
// =====================================================

test.describe('DatabaseMigrator - information_schema for reliable detection', () => {

  test('tableExists uses information_schema.TABLES instead of SHOW TABLES LIKE', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function tableExists(');
    const fnEnd = content.indexOf('\n    }', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('information_schema.TABLES');
    expect(fn).toContain('TABLE_SCHEMA = DATABASE()');
    expect(fn).toContain('TABLE_NAME = ?');
    expect(fn).not.toContain('SHOW TABLES LIKE');
  });

  test('columnExists uses information_schema.COLUMNS instead of SHOW COLUMNS LIKE', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function columnExists(');
    const fnEnd = content.indexOf('\n    }', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('information_schema.COLUMNS');
    expect(fn).toContain('TABLE_SCHEMA = DATABASE()');
    expect(fn).toContain('TABLE_NAME = ?');
    expect(fn).toContain('COLUMN_NAME = ?');
    expect(fn).not.toContain('SHOW COLUMNS FROM');
  });

  test('tableExists still uses sanitizeIdentifier for safety', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function tableExists(');
    const fnEnd = content.indexOf('\n    }', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('columnExists still uses sanitizeIdentifier for safety', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function columnExists(');
    const fnEnd = content.indexOf('\n    }', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('tableExists uses prepared statement (not string interpolation)', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function tableExists(');
    const fnEnd = content.indexOf('\n    }', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('$this->pdo->prepare(');
    expect(fn).toContain('->execute(');
  });

  test('columnExists uses prepared statement with both params', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function columnExists(');
    const fnEnd = content.indexOf('\n    }', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('$this->pdo->prepare(');
    expect(fn).toContain('->execute(');
    // Should bind both table name and column name as parameters
    expect(fn).toContain('$table_name, $column_name');
  });
});

// =====================================================
// 2. runSchemaCheck two-pass processing
// =====================================================

test.describe('runSchemaCheck - two-pass processing', () => {

  test('github_updater.php separates create_table and add_column migrations', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should have two separate arrays
    expect(fn).toContain('$create_migrations');
    expect(fn).toContain('$column_migrations');
  });

  test('github_updater.php creates tables before adding columns', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // First pass should be for create_migrations
    const firstPassPos = fn.indexOf('First pass: create all missing tables');
    const secondPassPos = fn.indexOf('Second pass: add all missing columns');
    expect(firstPassPos).toBeGreaterThan(-1);
    expect(secondPassPos).toBeGreaterThan(-1);
    expect(firstPassPos).toBeLessThan(secondPassPos);
  });

  test('github_updater.php verifies table creation with tableExists', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should verify after exec
    expect(fn).toContain('tableExists($table_name)');
    expect(fn).toContain('possible driver issue');
  });

  test('github_updater.php still has fallback table creation in add_column path', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // The add_column section should still handle missing tables as a fallback
    expect(fn).toContain("'does not exist'");
    expect(fn).toContain('Created missing table');
    expect(fn).toContain('executeMigration');
  });
});

// =====================================================
// 3. setup.php two-pass processing
// =====================================================

test.describe('setup.php - two-pass processing', () => {

  test('setup.php separates create_table and add_column migrations', () => {
    const content = readFile('setup.php');
    expect(content).toContain('$create_migrations');
    expect(content).toContain('$column_migrations');
  });

  test('setup.php creates tables before adding columns', () => {
    const content = readFile('setup.php');
    const start = content.indexOf('compareSchemas($current_schema, $expected_schema)');
    const section = content.substring(start);
    const firstPassPos = section.indexOf('First pass: create all missing tables');
    const secondPassPos = section.indexOf('Second pass: add all missing columns');
    expect(firstPassPos).toBeGreaterThan(-1);
    expect(secondPassPos).toBeGreaterThan(-1);
    expect(firstPassPos).toBeLessThan(secondPassPos);
  });
});

// =====================================================
// 4. process_settings.php deferred handler two-pass
// =====================================================

test.describe('process_settings.php - deferred handler two-pass', () => {

  test('deferred handler separates create_table and add_column', () => {
    const content = readFile('process_settings.php');
    const shutdownStart = content.indexOf('register_shutdown_function');
    const shutdownSection = content.substring(shutdownStart);
    // Should have separate passes for create_table and add_column
    expect(shutdownSection).toContain("'create_table'");
    expect(shutdownSection).toContain("'add_column'");
    // First pass for tables, second for columns
    const createPassPos = shutdownSection.indexOf('First pass: create all missing tables');
    const columnPassPos = shutdownSection.indexOf('Second pass: add all missing columns');
    expect(createPassPos).toBeGreaterThan(-1);
    expect(columnPassPos).toBeGreaterThan(-1);
    expect(createPassPos).toBeLessThan(columnPassPos);
  });
});

// =====================================================
// 5. Rebuild Schema UI
// =====================================================

test.describe('Rebuild Schema - Desktop UI', () => {

  test('admin_system_tools.php has Rebuild Schema button in database tab', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Rebuild Schema');
    expect(content).toContain('btn-rebuild-schema');
    expect(content).toContain('runRebuildSchema');
  });

  test('admin_system_tools.php has runRebuildSchema JavaScript function', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('async function runRebuildSchema');
    expect(content).toContain('rebuild_schema');
    expect(content).toContain('process_database_backup.php');
  });

  test('admin_system_tools.php rebuild schema uses confirmation dialog', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function runRebuildSchema');
    const fnEnd = content.indexOf('\n}', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('showConfirmModal');
  });

  test('admin_system_tools.php rebuild schema uses CSRF token', () => {
    const content = readFile('views/admin_system_tools.php');
    const fnStart = content.indexOf('async function runRebuildSchema');
    const fnEnd = content.indexOf('\n}', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('csrf_token');
    expect(fn).toContain('getCsrfToken()');
  });

  test('admin_system_tools.php info box mentions Rebuild Schema', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Rebuild Schema');
    expect(content).toContain('missing tables or columns');
  });

  test('admin_database_tools.php has Rebuild Schema tool card', () => {
    const content = readFile('views/admin_database_tools.php');
    expect(content).toContain('Rebuild Schema');
    expect(content).toContain('rebuild_schema');
  });

  test('admin_database_tools.php handles rebuild_schema action', () => {
    const content = readFile('views/admin_database_tools.php');
    expect(content).toContain("case 'rebuild_schema':");
    expect(content).toContain('runSchemaCheck');
  });
});

test.describe('Rebuild Schema - PWA UI', () => {

  test('PWA admin_database_tools.php has Rebuild Schema button', () => {
    const content = readFile('views/pwa/admin_database_tools.php');
    expect(content).toContain('Rebuild Schema');
    expect(content).toContain('m-btn-rebuild-schema');
    expect(content).toContain('mRebuildSchema');
  });

  test('PWA rebuild schema has JavaScript handler', () => {
    const content = readFile('views/pwa/admin_database_tools.php');
    expect(content).toContain('async function mRebuildSchema');
    expect(content).toContain('rebuild_schema');
  });

  test('PWA rebuild schema uses CSRF token from meta tag', () => {
    const content = readFile('views/pwa/admin_database_tools.php');
    const fnStart = content.indexOf('async function mRebuildSchema');
    const fnEnd = content.indexOf('\n}', fnStart + 50);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('csrf-token');
    expect(fn).toContain('csrf_token');
  });

  test('PWA rebuild schema calls process_database_backup.php', () => {
    const content = readFile('views/pwa/admin_database_tools.php');
    expect(content).toContain('process_database_backup.php');
  });

  test('PWA rebuild schema shows status feedback', () => {
    const content = readFile('views/pwa/admin_database_tools.php');
    expect(content).toContain('m-schema-status');
    expect(content).toContain('m-ok');
    expect(content).toContain('m-err');
  });
});

// =====================================================
// 6. process_database_backup.php rebuild_schema action
// =====================================================

test.describe('process_database_backup.php - rebuild_schema action', () => {

  test('has rebuild_schema case in switch', () => {
    const content = readFile('process_database_backup.php');
    expect(content).toContain("case 'rebuild_schema':");
  });

  test('rebuild_schema requires GitHubUpdater', () => {
    const content = readFile('process_database_backup.php');
    const caseStart = content.indexOf("case 'rebuild_schema':");
    const caseEnd = content.indexOf('break;', caseStart);
    const caseBody = content.substring(caseStart, caseEnd);
    expect(caseBody).toContain('github_updater.php');
    expect(caseBody).toContain('GitHubUpdater');
  });

  test('rebuild_schema calls runSchemaCheck', () => {
    const content = readFile('process_database_backup.php');
    const caseStart = content.indexOf("case 'rebuild_schema':");
    const caseEnd = content.indexOf('break;', caseStart);
    const caseBody = content.substring(caseStart, caseEnd);
    expect(caseBody).toContain('runSchemaCheck');
  });

  test('rebuild_schema logs the action', () => {
    const content = readFile('process_database_backup.php');
    const caseStart = content.indexOf("case 'rebuild_schema':");
    const caseEnd = content.indexOf('break;', caseStart);
    const caseBody = content.substring(caseStart, caseEnd);
    expect(caseBody).toContain('logAction');
    expect(caseBody).toContain('schema_rebuild');
  });

  test('rebuild_schema returns JSON response with details', () => {
    const content = readFile('process_database_backup.php');
    const caseStart = content.indexOf("case 'rebuild_schema':");
    const caseEnd = content.indexOf('break;', caseStart);
    const caseBody = content.substring(caseStart, caseEnd);
    expect(caseBody).toContain('json_encode');
    expect(caseBody).toContain('changes_applied');
    expect(caseBody).toContain('details');
  });
});
