import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { join } from 'path';

/**
 * Security Audit Tests
 * Validates fixes for:
 * 1. SQL injection in database_migrator.php — identifier sanitization + prepared statements
 * 2. SQL injection in schema_validator.php — identifier validation
 * 3. SQL injection in system_health_validator.php — prepared statements
 * 4. XSS via addslashes() — replaced with json_encode() in JS contexts
 * 5. API key query parameter deprecation warning in api_auth.php
 */

const ROOT = join(__dirname, '..');

function readFile(relativePath) {
  return readFileSync(join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. SQL Injection — database_migrator.php
// =====================================================

test.describe('database_migrator.php SQL injection prevention', () => {

  test('defines sanitizeIdentifier method', () => {
    const c = readFile('lib/database_migrator.php');
    expect(c).toContain('function sanitizeIdentifier($name)');
    expect(c).toContain("preg_match('/^[a-zA-Z0-9_]+$/'");
  });

  test('sanitizeIdentifier rejects disallowed characters', () => {
    const c = readFile('lib/database_migrator.php');
    expect(c).toContain('Invalid SQL identifier');
  });

  test('tableExists uses prepared statement instead of interpolation', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function tableExists(');
    const fnEnd = c.indexOf('}', c.indexOf('}', fnStart) + 1);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
    expect(fn).toContain("->prepare(");
    expect(fn).toContain("SHOW TABLES LIKE ?");
    expect(fn).not.toMatch(/query\([^)]*\$table_name/);
  });

  test('columnExists uses prepared statement instead of interpolation', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function columnExists(');
    const fnEnd = c.indexOf('}', c.indexOf('}', fnStart) + 1);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
    expect(fn).toContain("->prepare(");
    expect(fn).toContain("SHOW COLUMNS FROM");
    expect(fn).toContain("LIKE ?");
    expect(fn).not.toMatch(/query\([^)]*\$column_name/);
  });

  test('renameColumn uses prepared statement for SHOW COLUMNS', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function renameColumn(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
    expect(fn).toContain("->prepare(");
    expect(fn).not.toMatch(/query\([^)]*LIKE '\$/);
  });

  test('addIndex uses prepared statement for SHOW INDEX', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function addIndex(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
    expect(fn).toContain("->prepare(");
    expect(fn).toContain("Key_name = ?");
  });

  test('dropIndex uses prepared statement for SHOW INDEX', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function dropIndex(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
    expect(fn).toContain("->prepare(");
    expect(fn).toContain("Key_name = ?");
  });

  test('renameTable sanitizes identifiers', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function renameTable(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('createTable sanitizes identifiers', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function createTable(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('dropTable sanitizes identifiers', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function dropTable(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('addColumn sanitizes identifiers', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function addColumn(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('dropColumn sanitizes identifiers', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function dropColumn(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('modifyColumn sanitizes identifiers', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function modifyColumn(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('addForeignKey sanitizes identifiers and validates actions', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function addForeignKey(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
    expect(fn).toContain('valid_actions');
    expect(fn).toContain('CASCADE');
  });

  test('dropForeignKey sanitizes identifiers', () => {
    const c = readFile('lib/database_migrator.php');
    const fnStart = c.indexOf('function dropForeignKey(');
    const fnEnd = c.indexOf('\n    }', fnStart);
    const fn = c.substring(fnStart, fnEnd);
    expect(fn).toContain('sanitizeIdentifier');
  });

  test('no remaining unsanitized SHOW TABLES LIKE with string interpolation', () => {
    const c = readFile('lib/database_migrator.php');
    // Should not have SHOW TABLES LIKE '$variable' pattern (interpolation)
    expect(c).not.toMatch(/SHOW TABLES LIKE '\$/);
  });

  test('no remaining unsanitized SHOW COLUMNS with string interpolation in LIKE', () => {
    const c = readFile('lib/database_migrator.php');
    // Should not have LIKE '$variable' after SHOW COLUMNS
    expect(c).not.toMatch(/SHOW COLUMNS FROM[^)]*LIKE '\$/);
  });

  test('no remaining unsanitized SHOW INDEX with string interpolation', () => {
    const c = readFile('lib/database_migrator.php');
    // Should not have Key_name = '$variable' pattern
    expect(c).not.toMatch(/Key_name = '\$/);
  });
});

// =====================================================
// 2. SQL Injection — schema_validator.php
// =====================================================

test.describe('schema_validator.php SQL injection prevention', () => {

  test('validates table identifiers before DESCRIBE queries', () => {
    const c = readFile('lib/schema_validator.php');
    expect(c).toContain("preg_match('/^[a-zA-Z0-9_]+$/'");
    expect(c).toContain('Invalid table name');
  });
});

// =====================================================
// 3. SQL Injection — system_health_validator.php
// =====================================================

test.describe('system_health_validator.php SQL injection prevention', () => {

  test('uses prepared statement for SHOW COLUMNS query', () => {
    const c = readFile('system_health_validator.php');
    // Should use prepare + execute pattern instead of direct query with interpolation
    expect(c).toContain("->prepare(\"SHOW COLUMNS FROM");
    expect(c).toContain("->execute(['is_demo'])");
  });
});

// =====================================================
// 4. XSS Prevention — addslashes replaced with json_encode
// =====================================================

test.describe('XSS prevention: addslashes replaced with json_encode', () => {

  test('admin_plan_categories.php uses json_encode for JS context', () => {
    const c = readFile('views/admin_plan_categories.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($cat['name'])");
  });

  test('admin_packages.php uses json_encode for JS context', () => {
    const c = readFile('views/admin_packages.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($pkg['name'])");
  });

  test('library_nutrition.php uses json_encode for JS context', () => {
    const c = readFile('views/library_nutrition.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($meal['name'])");
  });

  test('library_workouts.php uses json_encode for JS context', () => {
    const c = readFile('views/library_workouts.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($exercise['name'])");
  });

  test('programs_camps.php uses json_encode for JS context', () => {
    const c = readFile('views/programs_camps.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($pkg['name'])");
    expect(c).toContain("json_encode($pkg['package_type'])");
  });

  test('financial_reports.php uses json_encode for JS context', () => {
    const c = readFile('views/financial_reports.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($schedule['report_name']");
  });

  test('pwa/hr_complaints.php uses json_encode for JS context', () => {
    const c = readFile('views/pwa/hr_complaints.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($comp['status']");
    expect(c).toContain("json_encode($comp['priority']");
  });

  test('pwa/pos_terminal.php uses json_encode for JS context', () => {
    const c = readFile('views/pwa/pos_terminal.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($cat)");
  });

  test('pos_time_tracking.php uses json_encode for JS context', () => {
    const c = readFile('views/pos_time_tracking.php');
    expect(c).not.toContain("addslashes");
    expect(c).toContain("json_encode($activeShift['clock_in']");
  });
});

// =====================================================
// 5. API Key Query Parameter Deprecation
// =====================================================

test.describe('API key query parameter deprecation', () => {

  test('api_auth.php logs deprecation warning for query parameter API keys', () => {
    const c = readFile('api/api_auth.php');
    expect(c).toContain('deprecated');
    expect(c).toContain('error_log');
    expect(c).toContain('query parameter');
  });

  test('api_auth.php still supports Authorization header', () => {
    const c = readFile('api/api_auth.php');
    expect(c).toContain('HTTP_AUTHORIZATION');
    expect(c).toContain('Bearer');
  });

  test('api_auth.php still supports X-API-Key header', () => {
    const c = readFile('api/api_auth.php');
    expect(c).toContain('HTTP_X_API_KEY');
  });
});
