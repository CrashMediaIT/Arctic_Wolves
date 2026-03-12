/**
 * Tests for Schema Migration Graceful Handling
 *
 * Verifies that DatabaseMigrator methods gracefully skip operations
 * when target tables don't exist (returning skipped results instead of throwing),
 * and that the update process (github_updater.php and setup.php) relies on
 * compareSchemas for column detection rather than inline ALTER TABLE migrations.
 * Also verifies that database_schema.sql has all columns in CREATE TABLE
 * definitions with no redundant ALTER TABLE ADD COLUMN IF NOT EXISTS statements.
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
// 2. Schema Comparison - No Inline Migrations Needed
// =====================================================

test.describe('Schema Comparison - No Inline Migrations', () => {

  test('github_updater.php runSchemaCheck uses compareSchemas instead of inline migrations', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should use compareSchemas to detect missing columns/tables
    expect(fn).toContain('compareSchemas');
    expect(fn).toContain('executeMigration');
    // Should NOT have inline ALTER TABLE migrations
    expect(fn).not.toContain('inline_migrations');
  });

  test('setup.php uses compareSchemas instead of inline migrations', () => {
    const content = readFile('setup.php');
    // Should use compareSchemas
    expect(content).toContain('compareSchemas');
    expect(content).toContain('executeMigration');
    // Should NOT have inline ALTER TABLE migrations
    expect(content).not.toContain('inline_migrations');
  });

  test('github_updater.php handles migration errors in schema comparison flow', () => {
    const content = readFile('lib/github_updater.php');
    const fnStart = content.indexOf('function runSchemaCheck()');
    const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
    const fn = content.substring(fnStart, fnEnd);
    // Should still handle table-already-exists and missing table errors in the comparison flow
    expect(fn).toContain('already exists');
    expect(fn).toContain('does not exist');
  });

  test('setup.php handles migration errors in schema comparison flow', () => {
    const content = readFile('setup.php');
    // Should still handle table-already-exists and missing table errors in the comparison flow
    expect(content).toContain('already exists');
    expect(content).toContain('does not exist');
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
    // Should handle 'already exists' errors gracefully using SQLSTATE and message
    expect(fn).toContain("'42S01'");
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
    expect(migSection).toContain("'42S01'");
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
// 8. SVG Icon CSS - inline data URLs for cross-browser reliability
// =====================================================

test.describe('SVG Icon CSS - inline data URLs', () => {

  test('style-guide.css uses inline SVG data URLs for hockey icons (not external files)', () => {
    const content = readFile('css/style-guide.css');
    // Find the shared hockey icon rule block (the comma-separated selector)
    const ruleStart = content.indexOf('.icon-hockey-player,');
    expect(ruleStart).toBeGreaterThan(-1);
    // Get the rule block from selector to closing brace
    const ruleEnd = content.indexOf('}', ruleStart);
    const iconRule = content.substring(ruleStart, ruleEnd);
    // Should NOT use mask-mode (was incorrectly added for Firefox; Chrome/Edge ignore it)
    expect(iconRule).not.toContain('mask-mode');
    // Should use background-color: currentColor for coloring
    expect(iconRule).toContain('background-color: currentColor');
  });

  test('hockey player icon uses inline SVG data URL', () => {
    const content = readFile('css/style-guide.css');
    const playerRuleStart = content.indexOf('.icon-hockey-player {');
    expect(playerRuleStart).toBeGreaterThan(-1);
    const playerRuleEnd = content.indexOf('}', playerRuleStart);
    const playerRule = content.substring(playerRuleStart, playerRuleEnd);
    // Should use data URL, not external file reference
    expect(playerRule).toContain("mask-image: url(\"data:image/svg+xml,");
    expect(playerRule).toContain("-webkit-mask-image: url(\"data:image/svg+xml,");
    // Should NOT reference external SVG file
    expect(playerRule).not.toContain('hockey-player.svg');
  });

  test('hockey goalie icon uses inline SVG data URL', () => {
    const content = readFile('css/style-guide.css');
    // Find the standalone .icon-hockey-goalie rule (skip the shared comma-separated selector)
    const playerRuleStart = content.indexOf('.icon-hockey-player {');
    const goalieRuleStart = content.indexOf('.icon-hockey-goalie {', playerRuleStart);
    expect(goalieRuleStart).toBeGreaterThan(playerRuleStart);
    const goalieRuleEnd = content.indexOf('}', goalieRuleStart);
    const goalieRule = content.substring(goalieRuleStart, goalieRuleEnd);
    // Should use data URL, not external file reference
    expect(goalieRule).toContain("mask-image: url(\"data:image/svg+xml,");
    expect(goalieRule).toContain("-webkit-mask-image: url(\"data:image/svg+xml,");
    // Should NOT reference external SVG file
    expect(goalieRule).not.toContain('hockey-goalie.svg');
  });

  test('SVG data URLs use explicit fill (not currentColor) for reliable mask rendering', () => {
    const content = readFile('css/style-guide.css');
    // Find the player and goalie mask-image data URLs
    const playerStart = content.indexOf('.icon-hockey-player {');
    const playerEnd = content.indexOf('}', playerStart);
    const playerBlock = content.substring(playerStart, playerEnd);
    // The SVG fill should be #000 (black) — encoded as %23000 in the data URL — not currentColor
    expect(playerBlock).toContain('%23000');
    expect(playerBlock).not.toContain('currentColor');
  });

  test('hockey SVG source files exist with explicit fill and no external URLs', () => {
    const playerSvg = readFile('assets/svg/hockey-player.svg');
    expect(playerSvg).toContain('<svg');
    expect(playerSvg).toContain('</svg>');
    expect(playerSvg).toContain('viewBox');
    // SVG files should use fill="black" for reliable mask rendering
    expect(playerSvg).toContain('fill="black"');
    // Should not contain external URL references (comments linking to source websites)
    expect(playerSvg).not.toContain('freesvg.org');
    expect(playerSvg).not.toContain('Source:');
    
    const goalieSvg = readFile('assets/svg/hockey-goalie.svg');
    expect(goalieSvg).toContain('<svg');
    expect(goalieSvg).toContain('</svg>');
    expect(goalieSvg).toContain('viewBox');
    expect(goalieSvg).toContain('fill="black"');
    expect(goalieSvg).not.toContain('freesvg.org');
    expect(goalieSvg).not.toContain('Source:');
  });
});

// =====================================================
// 8. Schema Consolidation - No Redundant ALTER TABLE ADD COLUMN
// =====================================================

test.describe('Schema Consolidation - All Columns in CREATE TABLE', () => {

  test('database_schema.sql has no active ALTER TABLE ADD COLUMN IF NOT EXISTS', () => {
    const schema = readFile('database_schema.sql');
    // Only commented-out ALTER TABLE ADD COLUMN should remain
    const lines = schema.split('\n');
    const activeAddColumn = lines.filter(l => {
      const stripped = l.trim();
      return stripped.includes('ADD COLUMN IF NOT EXISTS') && !stripped.startsWith('--');
    });
    expect(activeAddColumn).toHaveLength(0);
  });

  test('key columns are in CREATE TABLE definitions, not ALTER TABLE', () => {
    const schema = readFile('database_schema.sql');
    
    // evaluation_scores columns should be in CREATE TABLE
    const evalScoresCreate = schema.substring(
      schema.indexOf('CREATE TABLE IF NOT EXISTS `evaluation_scores`'),
      schema.indexOf('ENGINE=', schema.indexOf('CREATE TABLE IF NOT EXISTS `evaluation_scores`'))
    );
    expect(evalScoresCreate).toContain('`evaluation_id`');
    expect(evalScoresCreate).toContain('`public_notes`');
    expect(evalScoresCreate).toContain('`private_notes`');
    expect(evalScoresCreate).toContain('`updated_at`');

    // evaluation_media columns should be in CREATE TABLE
    const evalMediaCreate = schema.substring(
      schema.indexOf('CREATE TABLE IF NOT EXISTS `evaluation_media`'),
      schema.indexOf('ENGINE=', schema.indexOf('CREATE TABLE IF NOT EXISTS `evaluation_media`'))
    );
    expect(evalMediaCreate).toContain('`score_id`');
    expect(evalMediaCreate).toContain('`media_url`');
    expect(evalMediaCreate).toContain('`created_at`');
    expect(evalMediaCreate).toContain('`nextcloud_path`');

    // user_workouts columns should be in CREATE TABLE
    const userWorkoutsCreate = schema.substring(
      schema.indexOf('CREATE TABLE IF NOT EXISTS `user_workouts`'),
      schema.indexOf('ENGINE=', schema.indexOf('CREATE TABLE IF NOT EXISTS `user_workouts`'))
    );
    expect(userWorkoutsCreate).toContain('`coach_id`');
    expect(userWorkoutsCreate).toContain('`assigned_date`');
  });

  test('sessions table has all required columns in CREATE TABLE', () => {
    const schema = readFile('database_schema.sql');
    const sessionsCreate = schema.substring(
      schema.indexOf('CREATE TABLE IF NOT EXISTS `sessions`'),
      schema.indexOf('ENGINE=', schema.indexOf('CREATE TABLE IF NOT EXISTS `sessions`'))
    );
    expect(sessionsCreate).toContain('`enable_child_checkin`');
    expect(sessionsCreate).toContain('`is_private`');
    expect(sessionsCreate).toContain('`is_semi_private`');
    expect(sessionsCreate).toContain('`show_on_landing`');
    expect(sessionsCreate).toContain('`session_type_category`');
  });

  test('teams table has all required columns in CREATE TABLE', () => {
    const schema = readFile('database_schema.sql');
    const teamsCreate = schema.substring(
      schema.indexOf('CREATE TABLE IF NOT EXISTS `teams`'),
      schema.indexOf('ENGINE=', schema.indexOf('CREATE TABLE IF NOT EXISTS `teams`'))
    );
    expect(teamsCreate).toContain('`is_managed`');
    expect(teamsCreate).toContain('`ical_url`');
    expect(teamsCreate).toContain('`nextcloud_logo_path`');
    expect(teamsCreate).toContain('`logo_url`');
  });

  test('users table has all required columns in CREATE TABLE', () => {
    const schema = readFile('database_schema.sql');
    const usersCreate = schema.substring(
      schema.indexOf('CREATE TABLE IF NOT EXISTS `users`'),
      schema.indexOf('ENGINE=', schema.indexOf('CREATE TABLE IF NOT EXISTS `users`'))
    );
    expect(usersCreate).toContain('`sip_wss_port`');
    expect(usersCreate).toContain('`two_factor_required`');
    expect(usersCreate).toContain('`nextcloud_image_path`');
  });

  test('no inline_migrations array in update process files', () => {
    const updater = readFile('lib/github_updater.php');
    const setup = readFile('setup.php');
    expect(updater).not.toContain('inline_migrations');
    expect(setup).not.toContain('inline_migrations');
  });
});
