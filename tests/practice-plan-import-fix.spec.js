/**
 * Tests for Practice Plan Import Fixes
 *
 * Verifies:
 * 1. Image downloads happen BEFORE the DB transaction (not inside it)
 * 2. practice_plans INSERT sets both name and title columns
 * 3. Drill existence check uses a simple query (no expressions in ORDER BY)
 * 4. Error handling catches Throwable (not just Exception)
 * 5. nextcloud_image_path UPDATE is wrapped in try/catch
 * 6. import_json maps drill categories correctly
 * 7. Drill INSERT does not include explicit created_at (uses DEFAULT)
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// Helper: extract the import_ihs_practice_plan action block
function getIHSImportBlock() {
  const content = readFile('process_practice_plans.php');
  const start = content.indexOf("if ($action === 'import_ihs_practice_plan')");
  const end = content.indexOf('function parseIHSPracticePlanPage');
  return content.substring(start, end);
}

// Helper: extract the import_json action block
function getJSONImportBlock() {
  const content = readFile('process_practice_plans.php');
  const start = content.indexOf("if ($action === 'import_json')");
  // Find the next top-level block or end of file
  const end = content.indexOf('\n// Fallback', start);
  return content.substring(start, end > start ? end : undefined);
}

// =====================================================
// 1. Image downloads before transaction
// =====================================================

test.describe('IHS import: image downloads outside transaction', () => {
  test('downloadAndSaveDrillImage should be called before beginTransaction', () => {
    const block = getIHSImportBlock();
    const downloadPos = block.indexOf('downloadAndSaveDrillImage');
    const beginTxPos = block.indexOf('beginTransaction');

    expect(downloadPos).toBeGreaterThan(-1);
    expect(beginTxPos).toBeGreaterThan(-1);
    // The first call to downloadAndSaveDrillImage must appear before beginTransaction
    expect(downloadPos).toBeLessThan(beginTxPos);
  });

  test('downloadAndSaveDrillImage should NOT appear after beginTransaction', () => {
    const block = getIHSImportBlock();
    const beginTxPos = block.indexOf('beginTransaction');
    const afterTx = block.substring(beginTxPos);
    // After the transaction starts, there should be no more image download calls
    expect(afterTx).not.toContain('downloadAndSaveDrillImage');
  });
});

// =====================================================
// 2. practice_plans INSERT sets both name and title
// =====================================================

test.describe('IHS import: practice_plans INSERT sets name and title', () => {
  test('INSERT INTO practice_plans should include title column', () => {
    const block = getIHSImportBlock();
    // Find the practice_plans INSERT
    const insertMatch = block.match(/INSERT INTO practice_plans\s*\(([^)]+)\)/);
    expect(insertMatch).not.toBeNull();
    const columns = insertMatch[1];
    expect(columns).toContain('name');
    expect(columns).toContain('title');
  });

  test('INSERT INTO practice_plans should include total_duration', () => {
    const block = getIHSImportBlock();
    const insertMatch = block.match(/INSERT INTO practice_plans\s*\(([^)]+)\)/);
    expect(insertMatch).not.toBeNull();
    expect(insertMatch[1]).toContain('total_duration');
  });
});

// =====================================================
// 3. Drill existence check uses simple query
// =====================================================

test.describe('IHS import: drill existence check is simple', () => {
  test('drill SELECT should not use ORDER BY expression with parameter', () => {
    const block = getIHSImportBlock();
    // The old problematic pattern: ORDER BY (created_by = ?)
    expect(block).not.toMatch(/ORDER BY\s*\(created_by\s*=\s*\?\)/);
  });

  test('drill SELECT should use simple LIMIT 1', () => {
    const block = getIHSImportBlock();
    expect(block).toMatch(/SELECT id FROM drills WHERE title = \? LIMIT 1/);
  });
});

// =====================================================
// 4. Error handling catches Throwable
// =====================================================

test.describe('Import error handling catches Throwable', () => {
  test('IHS import catch block should catch Throwable', () => {
    const block = getIHSImportBlock();
    expect(block).toContain('catch (\\Throwable');
  });

  test('IHS import should check inTransaction before rollBack', () => {
    const block = getIHSImportBlock();
    expect(block).toContain('inTransaction');
  });

  test('process_ihs_import import_plans should catch Throwable', () => {
    const content = readFile('process_ihs_import.php');
    const importBlock = content.substring(
      content.indexOf("if ($action === 'import_plans')"),
    );
    // Should catch Throwable, not just Exception
    expect(importBlock).toContain('catch (\\Throwable');
  });
});

// =====================================================
// 5. nextcloud_image_path UPDATE is safe
// =====================================================

test.describe('IHS import: nextcloud_image_path UPDATE is wrapped in try/catch', () => {
  test('nextcloud_image_path UPDATE should have its own catch block', () => {
    const block = getIHSImportBlock();
    // Find the nextcloud_image_path UPDATE
    const ncUpdatePos = block.indexOf('nextcloud_image_path');
    expect(ncUpdatePos).toBeGreaterThan(-1);

    // There should be a try/catch around it (look for catch near the UPDATE)
    const surrounding = block.substring(ncUpdatePos - 200, ncUpdatePos + 300);
    expect(surrounding).toContain('try');
    expect(surrounding).toContain('catch');
  });
});

// =====================================================
// 6. import_json maps drill categories
// =====================================================

test.describe('JSON import: drill categories are mapped', () => {
  test('import_json drill INSERT should include category_id', () => {
    const block = getJSONImportBlock();
    // Find the drill INSERT within the import_json block (multiline with s flag)
    const drillInsert = block.match(/INSERT INTO drills\s*\(([^)]+)\)\s*VALUES/gs);
    expect(drillInsert).not.toBeNull();
    // At least one drill INSERT should include category_id
    const hasCategory = drillInsert.some(ins => ins.includes('category_id'));
    expect(hasCategory).toBe(true);
  });
});

// =====================================================
// 7. Drill INSERT uses schema defaults for created_at
// =====================================================

test.describe('IHS import: drill INSERT lets DB handle created_at', () => {
  test('drill INSERT should not include explicit created_at with NOW()', () => {
    const block = getIHSImportBlock();
    // After beginTransaction, find the drill INSERT
    const afterTx = block.substring(block.indexOf('beginTransaction'));
    const drillInserts = afterTx.match(/INSERT INTO drills\s*\([^)]+\)\s*VALUES\s*\([^)]+\)/g);
    expect(drillInserts).not.toBeNull();
    // The drill INSERT should not have NOW() — let MySQL DEFAULT handle it
    for (const ins of drillInserts) {
      expect(ins).not.toContain('NOW()');
    }
  });
});
