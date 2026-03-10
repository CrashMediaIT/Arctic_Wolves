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

// =====================================================
// 4. FK Checks Disabled During Table Creation
// =====================================================

test.describe('Migration Handlers - FK Checks Disabled During Table Creation', () => {

  test('github_updater.php disables FK checks before migration loop', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain('FOREIGN_KEY_CHECKS = 0');
    expect(fn).toContain('FOREIGN_KEY_CHECKS = 1');
  });

  test('github_updater.php re-enables FK checks in finally block', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // FOREIGN_KEY_CHECKS = 1 should be inside a finally block
    expect(fn).toContain('finally');
    const finallyIdx = fn.indexOf('finally');
    const afterFinally = fn.substring(finallyIdx);
    expect(afterFinally).toContain('FOREIGN_KEY_CHECKS = 1');
  });

  test('setup.php disables FK checks before migration loop', () => {
    const content = readFile('setup.php');
    // Look for FK check disable in the migration section
    expect(content).toContain('FOREIGN_KEY_CHECKS = 0');
    expect(content).toContain('FOREIGN_KEY_CHECKS = 1');
  });

  test('setup.php disables FK checks during fresh schema import', () => {
    const content = readFile('setup.php');
    // The full schema import for fresh databases should also disable FK checks
    const freshIdx = content.indexOf('Fresh database');
    expect(freshIdx).toBeGreaterThan(-1);
    const freshSection = content.substring(freshIdx, freshIdx + 500);
    expect(freshSection).toContain('FOREIGN_KEY_CHECKS = 0');
    expect(freshSection).toContain('FOREIGN_KEY_CHECKS = 1');
  });
});

// =====================================================
// 5. Missing Table Fallback - Create Table Before Add Column
// =====================================================

test.describe('Migration Handlers - Missing Table Fallback', () => {

  test('github_updater.php creates missing table when add_column finds table does not exist', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // When add_column is skipped because table doesn't exist, should try to create the table
    expect(fn).toContain("'does not exist'");
    expect(fn).toContain('Created missing table');
    // Should retry the column addition after creating the table
    expect(fn).toContain('executeMigration');
  });

  test('setup.php creates missing table when add_column finds table does not exist', () => {
    const content = readFile('setup.php');
    // When add_column is skipped because table doesn't exist, should try to create the table
    const migSection = content.substring(content.indexOf('compareSchemas'));
    expect(migSection).toContain("'does not exist'");
    expect(migSection).toContain('Created missing table');
    // Should retry the column addition after creating the table
    expect(migSection).toContain('executeMigration');
  });

  test('github_updater.php uses shared CREATE TABLE regex pattern template', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should use a shared pattern template for consistency
    expect(fn).toContain('create_table_pattern_tpl');
    // The same pattern should be used for both create_table and the add_column fallback
    const patternCount = (fn.match(/create_table_pattern_tpl/g) || []).length;
    expect(patternCount).toBeGreaterThanOrEqual(3);
  });

  test('setup.php uses shared CREATE TABLE regex pattern template', () => {
    const content = readFile('setup.php');
    const migSection = content.substring(content.indexOf('compareSchemas'));
    expect(migSection).toContain('create_table_pattern_tpl');
    const patternCount = (migSection.match(/create_table_pattern_tpl/g) || []).length;
    expect(patternCount).toBeGreaterThanOrEqual(3);
  });
});

// =====================================================
// 6. Create Table Error Handling
// =====================================================

test.describe('Migration Handlers - Create Table Error Handling', () => {

  test('github_updater.php has try-catch around create_table exec', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // The create_table handler should have its own try-catch
    expect(fn).toContain("Could not create table $table_name:");
    // Should handle 'already exists' errors gracefully
    expect(fn).toContain("'1050'");
    expect(fn).toContain("'already exists'");
  });

  test('github_updater.php logs error when regex fails for create_table', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("Could not extract CREATE TABLE statement for");
    expect(fn).toContain("Schema regex failed for table:");
  });

  test('setup.php has try-catch around create_table exec', () => {
    const content = readFile('setup.php');
    const migSection = content.substring(content.indexOf('compareSchemas'));
    expect(migSection).toContain("Could not create table $table_name:");
    expect(migSection).toContain("'1050'");
    expect(migSection).toContain("'already exists'");
  });

  test('setup.php logs error when regex fails for create_table', () => {
    const content = readFile('setup.php');
    const migSection = content.substring(content.indexOf('compareSchemas'));
    expect(migSection).toContain("Could not extract CREATE TABLE statement for");
    expect(migSection).toContain("Setup schema regex failed for table:");
  });
});

// =====================================================
// 7. Post-Migration Verification & Retry
// =====================================================

test.describe('Migration Handlers - Post-Migration Verification', () => {

  test('github_updater.php verifies schema after migration and retries missing tables', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should verify schema after migration
    expect(fn).toContain('getCurrentSchema()');
    // Should compare post-migration schema
    const compareCount = (fn.match(/compareSchemas/g) || []).length;
    expect(compareCount).toBeGreaterThanOrEqual(2);
    // Should retry missing tables
    expect(fn).toContain("Created missing table (retry):");
  });

  test('setup.php verifies schema after migration and retries missing tables', () => {
    const content = readFile('setup.php');
    const migSection = content.substring(content.indexOf('compareSchemas'));
    // Should retry missing tables
    expect(migSection).toContain("Created missing table (retry):");
    // Should re-verify after retry
    const getCurrentSchemaCount = (migSection.match(/getCurrentSchema/g) || []).length;
    expect(getCurrentSchemaCount).toBeGreaterThanOrEqual(2);
  });

  test('github_updater.php disables FK checks during retry', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should have FK checks disabled during both initial and retry loops
    const fkDisableCount = (fn.match(/FOREIGN_KEY_CHECKS\s*=\s*0/g) || []).length;
    expect(fkDisableCount).toBeGreaterThanOrEqual(2);
    const fkEnableCount = (fn.match(/FOREIGN_KEY_CHECKS\s*=\s*1/g) || []).length;
    expect(fkEnableCount).toBeGreaterThanOrEqual(2);
  });
});

// =====================================================
// 8. SVG Icon CSS Mask Mode
// =====================================================

test.describe('SVG Icon CSS - mask-mode alpha', () => {

  test('style-guide.css sets mask-mode: alpha for hockey icons', () => {
    const content = readFile('css/style-guide.css');
    // Find the hockey icon section
    const iconSection = content.substring(
      content.indexOf('.icon-hockey-player'),
      content.indexOf('.icon-hockey-player {', content.indexOf('.icon-hockey-player') + 1)
    );
    expect(iconSection).toContain('mask-mode: alpha');
    expect(iconSection).toContain('-webkit-mask-mode: alpha');
  });

  test('hockey SVG files exist and are valid', () => {
    const playerSvg = readFile('assets/svg/hockey-player.svg');
    expect(playerSvg).toContain('<svg');
    expect(playerSvg).toContain('</svg>');
    expect(playerSvg).toContain('viewBox');
    
    const goalieSvg = readFile('assets/svg/hockey-goalie.svg');
    expect(goalieSvg).toContain('<svg');
    expect(goalieSvg).toContain('</svg>');
    expect(goalieSvg).toContain('viewBox');
  });
});
