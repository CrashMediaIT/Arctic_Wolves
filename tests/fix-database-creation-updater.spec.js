/**
 * Tests for Database Creation Issues Fix
 *
 * Verifies that the post-migration verification step in both github_updater.php
 * and setup.php retries BOTH create_table AND add_column migrations (not just
 * create_table), that ALTER TABLE statements from the schema file are executed,
 * and that the deferred update handler re-runs schema checks.
 *
 * Root cause: The verification step only retried create_table migrations,
 * silently ignoring failed add_column migrations. This left columns like
 * evaluation_scores.evaluation_id and user_workouts.coach_id missing.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// Helper to extract runSchemaCheck function body
function getRunSchemaCheckBody() {
  const content = readFile('lib/github_updater.php');
  const fnStart = content.indexOf('function runSchemaCheck()');
  const fnEnd = content.indexOf('}', content.indexOf("'Schema check failed:", fnStart));
  return content.substring(fnStart, fnEnd);
}

// Helper to extract setup.php migration section
function getSetupMigrationSection() {
  const content = readFile('setup.php');
  const start = content.indexOf('compareSchemas($current_schema, $expected_schema)');
  const end = content.indexOf("$_SESSION['setup']['schema_migrated']");
  return content.substring(start, end);
}

// =====================================================
// 1. Verification step retries add_column (not just create_table)
// =====================================================

test.describe('Post-migration verification retries add_column migrations', () => {

  test('github_updater.php verification does NOT filter only create_table', () => {
    const fn = getRunSchemaCheckBody();
    // The old buggy code filtered: array_filter($remaining, function($m) { return $m['type'] === 'create_table'; })
    // The fix should NOT have this filter in the verification section
    const verifySection = fn.substring(fn.indexOf('Verify schema after migration'));
    expect(verifySection).not.toContain("array_filter($remaining, function($m) { return $m['type'] === 'create_table'; })");
  });

  test('github_updater.php verification retries add_column migrations', () => {
    const fn = getRunSchemaCheckBody();
    const verifySection = fn.substring(fn.indexOf('Verify schema after migration'));
    // Should have add_column retry logic
    expect(verifySection).toContain("'add_column'");
    expect(verifySection).toContain('executeMigration');
    expect(verifySection).toContain('(retry)');
  });

  test('github_updater.php verification creates tables first, then adds columns', () => {
    const fn = getRunSchemaCheckBody();
    const verifySection = fn.substring(fn.indexOf('Verify schema after migration'));
    // First pass: create tables
    const createTablePos = verifySection.indexOf('create_table');
    // Second pass: add columns
    const addColumnPos = verifySection.indexOf('add_column');
    expect(createTablePos).toBeGreaterThan(-1);
    expect(addColumnPos).toBeGreaterThan(-1);
    // Tables should be created before columns are added
    expect(createTablePos).toBeLessThan(addColumnPos);
  });

  test('setup.php verification retries add_column migrations', () => {
    const section = getSetupMigrationSection();
    const verifySection = section.substring(section.indexOf('Verify schema after migration'));
    // Should have add_column retry logic
    expect(verifySection).toContain("'add_column'");
    expect(verifySection).toContain('executeMigration');
    expect(verifySection).toContain('(retry)');
  });

  test('setup.php verification creates tables first, then adds columns', () => {
    const section = getSetupMigrationSection();
    const verifySection = section.substring(section.indexOf('Verify schema after migration'));
    const createTablePos = verifySection.indexOf('create_table');
    const addColumnPos = verifySection.indexOf('add_column');
    expect(createTablePos).toBeGreaterThan(-1);
    expect(addColumnPos).toBeGreaterThan(-1);
    expect(createTablePos).toBeLessThan(addColumnPos);
  });

  test('setup.php remaining_issues includes both create_table and add_column', () => {
    const section = getSetupMigrationSection();
    // The re-verify should check for both types
    expect(section).toContain("'create_table'");
    expect(section).toContain("'add_column'");
    expect(section).toContain('remaining_issues');
  });
});

// =====================================================
// 2. ALTER TABLE statements from schema file are executed
// =====================================================

test.describe('ALTER TABLE MODIFY/ADD INDEX execution', () => {

  test('github_updater.php executes ALTER TABLE statements from schema file', () => {
    const fn = getRunSchemaCheckBody();
    // Should extract and execute ALTER TABLE statements
    expect(fn).toContain('ALTER\\s+TABLE');
    expect(fn).toContain('preg_match_all');
    expect(fn).toContain('alter_sql');
  });

  test('github_updater.php ALTER TABLE execution has error handling', () => {
    const fn = getRunSchemaCheckBody();
    const alterSection = fn.substring(fn.indexOf('ALTER TABLE MODIFY'));
    expect(alterSection).toContain('try');
    expect(alterSection).toContain('catch');
    // Non-critical errors should be logged, not thrown
    expect(alterSection).toContain('error_log');
  });

  test('setup.php executes ALTER TABLE statements from schema file', () => {
    const section = getSetupMigrationSection();
    expect(section).toContain('ALTER\\s+TABLE');
    expect(section).toContain('preg_match_all');
    expect(section).toContain('alter_sql');
  });

  test('database_schema.sql has ALTER TABLE for evaluation_scores MODIFY', () => {
    const schema = readFile('database_schema.sql');
    // The evaluation_scores table needs NOT NULL relaxed for new-style inserts
    expect(schema).toContain('ALTER TABLE `evaluation_scores`');
    expect(schema).toContain('MODIFY COLUMN `athlete_id` INT DEFAULT NULL');
    expect(schema).toContain('MODIFY COLUMN `evaluator_id` INT DEFAULT NULL');
  });

  test('database_schema.sql has evaluation_scores.evaluation_id column', () => {
    const schema = readFile('database_schema.sql');
    // The evaluation_id column must be in the CREATE TABLE definition
    const tableStart = schema.indexOf('CREATE TABLE IF NOT EXISTS `evaluation_scores`');
    const tableEnd = schema.indexOf('ENGINE=InnoDB', tableStart);
    const tableDef = schema.substring(tableStart, tableEnd);
    expect(tableDef).toContain('`evaluation_id`');
  });

  test('database_schema.sql has user_workouts.coach_id column', () => {
    const schema = readFile('database_schema.sql');
    // The coach_id column must be in the CREATE TABLE definition
    const tableStart = schema.indexOf('CREATE TABLE IF NOT EXISTS `user_workouts`');
    const tableEnd = schema.indexOf('ENGINE=InnoDB', tableStart);
    const tableDef = schema.substring(tableStart, tableEnd);
    expect(tableDef).toContain('`coach_id`');
  });
});

// =====================================================
// 3. Deferred update handler re-runs schema check
// =====================================================

test.describe('Post-deferred schema check in process_settings.php', () => {

  test('shutdown function includes $pdo in closure scope', () => {
    const content = readFile('process_settings.php');
    const applySection = content.substring(
      content.indexOf("case 'apply_updates':"),
      content.indexOf('echo json_encode($result)', content.indexOf("case 'apply_updates':"))
    );
    // The shutdown function closure should capture $pdo
    expect(applySection).toContain('use ($base_dir, $pdo)');
  });

  test('shutdown function runs schema check after deferred files are applied', () => {
    const content = readFile('process_settings.php');
    const applySection = content.substring(
      content.indexOf("case 'apply_updates':"),
      content.indexOf('echo json_encode($result)', content.indexOf("case 'apply_updates':"))
    );
    // Should run schema check after deferred updates
    expect(applySection).toContain('applyDeferredUpdates');
    expect(applySection).toContain('DatabaseMigrator');
    expect(applySection).toContain('parseSchemaFile');
    expect(applySection).toContain('getCurrentSchema');
    expect(applySection).toContain('compareSchemas');
  });

  test('shutdown function handles both create_table and add_column', () => {
    const content = readFile('process_settings.php');
    const applySection = content.substring(
      content.indexOf("case 'apply_updates':"),
      content.indexOf('echo json_encode($result)', content.indexOf("case 'apply_updates':"))
    );
    expect(applySection).toContain("'create_table'");
    expect(applySection).toContain("'add_column'");
    expect(applySection).toContain('executeMigration');
  });

  test('shutdown function disables FK checks during schema fix', () => {
    const content = readFile('process_settings.php');
    const applySection = content.substring(
      content.indexOf("case 'apply_updates':"),
      content.indexOf('echo json_encode($result)', content.indexOf("case 'apply_updates':"))
    );
    expect(applySection).toContain('FOREIGN_KEY_CHECKS = 0');
    expect(applySection).toContain('FOREIGN_KEY_CHECKS = 1');
  });

  test('shutdown function has comprehensive error handling', () => {
    const content = readFile('process_settings.php');
    const applySection = content.substring(
      content.indexOf("case 'apply_updates':"),
      content.indexOf('echo json_encode($result)', content.indexOf("case 'apply_updates':"))
    );
    expect(applySection).toContain('Post-deferred schema check error');
    expect(applySection).toContain('Deferred schema fix');
  });
});

// =====================================================
// 4. Schema file correctness for the affected tables
// =====================================================

test.describe('Schema correctness for evaluation_scores and user_workouts', () => {

  test('evaluation_scores CREATE TABLE has all required columns', () => {
    const schema = readFile('database_schema.sql');
    const tableStart = schema.indexOf('CREATE TABLE IF NOT EXISTS `evaluation_scores`');
    expect(tableStart).toBeGreaterThan(-1);
    const tableEnd = schema.indexOf('ENGINE=InnoDB', tableStart);
    const tableDef = schema.substring(tableStart, tableEnd);
    
    expect(tableDef).toContain('`id` INT AUTO_INCREMENT PRIMARY KEY');
    expect(tableDef).toContain('`evaluation_id`');
    expect(tableDef).toContain('`athlete_id`');
    expect(tableDef).toContain('`evaluator_id`');
    expect(tableDef).toContain('`skill_id`');
    expect(tableDef).toContain('`score`');
    expect(tableDef).toContain('`evaluation_date`');
    expect(tableDef).toContain('`public_notes`');
    expect(tableDef).toContain('`private_notes`');
  });

  test('user_workouts CREATE TABLE has all required columns', () => {
    const schema = readFile('database_schema.sql');
    const tableStart = schema.indexOf('CREATE TABLE IF NOT EXISTS `user_workouts`');
    expect(tableStart).toBeGreaterThan(-1);
    const tableEnd = schema.indexOf('ENGINE=InnoDB', tableStart);
    const tableDef = schema.substring(tableStart, tableEnd);
    
    expect(tableDef).toContain('`id` INT AUTO_INCREMENT PRIMARY KEY');
    expect(tableDef).toContain('`user_id`');
    expect(tableDef).toContain('`coach_id`');
    expect(tableDef).toContain('`title`');
    expect(tableDef).toContain('`assigned_date`');
    expect(tableDef).toContain('`workout_date`');
    expect(tableDef).toContain('`status`');
    expect(tableDef).toContain('`duration_minutes`');
    expect(tableDef).toContain('`completed_at`');
  });

  test('evaluations_skills.php query references evaluation_scores.evaluation_id', () => {
    const content = readFile('views/evaluations_skills.php');
    // The query at line 145 that was causing the PDO error
    expect(content).toContain('FROM evaluation_scores WHERE evaluation_id = ae.id');
  });

  test('workouts.php query references user_workouts.coach_id', () => {
    const content = readFile('views/workouts.php');
    // The query at line 20 that was causing the PDO error
    expect(content).toContain('uw.coach_id');
    expect(content).toContain('LEFT JOIN users coach ON uw.coach_id = coach.id');
  });
});

// =====================================================
// 5. DatabaseMigrator parseSchemaFile correctly parses tables
// =====================================================

test.describe('DatabaseMigrator parseSchemaFile regex captures all tables', () => {

  test('parseSchemaFile regex can capture evaluation_scores table', () => {
    const schema = readFile('database_schema.sql');
    // Use the same regex as parseSchemaFile
    const regex = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.*?)\)\s*ENGINE/gis;
    const tables = [];
    let match;
    while ((match = regex.exec(schema)) !== null) {
      tables.push(match[1]);
    }
    expect(tables).toContain('evaluation_scores');
    expect(tables).toContain('user_workouts');
    expect(tables).toContain('user_workout_items');
    expect(tables).toContain('athlete_evaluations');
  });

  test('parseSchemaFile regex captures evaluation_id column from evaluation_scores', () => {
    const schema = readFile('database_schema.sql');
    // Use the same regex as parseSchemaFile for the specific table
    const regex = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?evaluation_scores`?\s*\((.*?)\)\s*ENGINE/is;
    const match = regex.exec(schema);
    expect(match).not.toBeNull();
    
    // Check that the captured body contains the evaluation_id column
    const body = match[1];
    expect(body).toContain('`evaluation_id`');
    
    // Simulate parseTableColumns: split by newlines, find column definitions
    const lines = body.split('\n');
    const columns = [];
    for (const rawLine of lines) {
      const line = rawLine.trim();
      if (!line || line === ',') continue;
      if (/^(PRIMARY KEY|FOREIGN KEY|UNIQUE KEY|INDEX|KEY|CONSTRAINT)/i.test(line)) continue;
      const colMatch = line.match(/^`?(\w+)`?\s+(\w+)/i);
      if (colMatch) {
        columns.push(colMatch[1]);
      }
    }
    expect(columns).toContain('evaluation_id');
  });

  test('parseSchemaFile regex captures coach_id column from user_workouts', () => {
    const schema = readFile('database_schema.sql');
    const regex = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?user_workouts`?\s*\((.*?)\)\s*ENGINE/is;
    const match = regex.exec(schema);
    expect(match).not.toBeNull();
    
    const body = match[1];
    expect(body).toContain('`coach_id`');
    
    const lines = body.split('\n');
    const columns = [];
    for (const rawLine of lines) {
      const line = rawLine.trim();
      if (!line || line === ',') continue;
      if (/^(PRIMARY KEY|FOREIGN KEY|UNIQUE KEY|INDEX|KEY|CONSTRAINT)/i.test(line)) continue;
      const colMatch = line.match(/^`?(\w+)`?\s+(\w+)/i);
      if (colMatch) {
        columns.push(colMatch[1]);
      }
    }
    expect(columns).toContain('coach_id');
  });
});
