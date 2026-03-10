/**
 * Tests for Schema Migration Graceful Handling
 *
 * Verifies that DatabaseMigrator methods gracefully skip operations
 * when target tables don't exist (returning skipped results instead of throwing),
 * and that inline migrations in github_updater.php and setup.php handle
 * missing table errors (SQLSTATE 42S02) alongside duplicate column errors.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. DatabaseMigrator - Graceful Handling of Missing Tables
// =====================================================

test.describe('DatabaseMigrator - Graceful Missing Table Handling', () => {

  test('addColumn returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function addColumn(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('modifyColumn returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function modifyColumn(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('dropColumn returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function dropColumn(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('renameColumn returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function renameColumn(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('renameTable returns skipped when old table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function renameTable(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('addIndex returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function addIndex(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('dropIndex returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function dropIndex(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('addForeignKey returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function addForeignKey(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('dropForeignKey returns skipped when table does not exist', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function dropForeignKey(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('dropTable already returns skipped when table does not exist (baseline pattern)', () => {
    const content = readFile('lib/database_migrator.php');
    const fnStart = content.indexOf('function dropTable(');
    const fnEnd = content.indexOf('\n    }', fnStart + 100);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('tableExists');
    expect(fn).toContain("'skipped' => true");
    expect(fn).not.toContain('throw new Exception("Table');
  });

  test('no methods throw exceptions for missing tables', () => {
    const content = readFile('lib/database_migrator.php');
    // Should have no throw statements with "Table ... does not exist"
    expect(content).not.toContain('throw new Exception("Table');
  });
});

// =====================================================
// 2. Inline Migrations - Missing Table Error Handling
// =====================================================

test.describe('Inline Migrations - Missing Table Error Handling', () => {

  test('github_updater.php inline migrations handle table-not-found errors', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should handle SQLSTATE 42S02 (Base table or view not found)
    expect(fn).toContain('42S02');
    // Should still handle duplicate column
    expect(fn).toContain('42S21');
    expect(fn).toContain('Duplicate column');
  });

  test('setup.php inline migrations handle table-not-found errors', () => {
    const content = readFile('setup.php');
    // Should handle SQLSTATE 42S02 (Base table or view not found)
    expect(content).toContain('42S02');
    // Should still handle duplicate column
    expect(content).toContain('42S21');
    expect(content).toContain('Duplicate column');
  });

  test('github_updater.php checks for "does not exist" and "doesn\'t exist" MySQL messages', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("doesn't exist");
    expect(fn).toContain('does not exist');
    // Error categorization should use readable helper variables
    expect(fn).toContain('isTableNotFound');
    expect(fn).toContain('isDuplicateColumn');
  });

  test('setup.php checks for "does not exist" and "doesn\'t exist" MySQL messages', () => {
    const content = readFile('setup.php');
    expect(content).toContain("doesn't exist");
    expect(content).toContain('does not exist');
    // Error categorization should use readable helper variables
    expect(content).toContain('isTableNotFound');
    expect(content).toContain('isDuplicateColumn');
  });
});

// =====================================================
// 3. Schema File - All Four Tables Exist
// =====================================================

test.describe('Database Schema - Affected Tables Exist', () => {

  test('game_schedules table exists in schema', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `game_schedules`');
  });

  test('agreement_templates table exists in schema', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `agreement_templates`');
  });

  test('personal_drills table exists in schema', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `personal_drills`');
  });

  test('development_program_videos table exists in schema', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `development_program_videos`');
  });
});
