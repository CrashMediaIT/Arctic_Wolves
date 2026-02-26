import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. Drill delete button in desktop library view (views/drills_library.php)
 * 2. Drill delete JavaScript handler in desktop library view
 * 3. DELETE endpoint in API v1 drills (api/v1/drills.php)
 * 4. decryptCredential returns empty string for encrypted values when key is lost
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Desktop drill library has delete button
// =====================================================

test.describe('Drill delete button in desktop library', () => {
  test('drills_library.php contains a delete button with trash icon', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('data-action="delete"');
    expect(content).toContain('fa-trash');
    expect(content).toContain('title="Delete"');
  });

  test('delete button is gated by same permission check as edit button', () => {
    const content = readFile('views/drills_library.php');
    // Both edit and delete buttons should be inside the same permission block
    const permBlock = content.indexOf("data-action=\"edit\"");
    const deleteBlock = content.indexOf("data-action=\"delete\"");
    // Delete button should appear after edit button within the same conditional
    expect(deleteBlock).toBeGreaterThan(permBlock);
    // Both should be within the created_by / admin / coach check
    const permCheckBefore = content.lastIndexOf("created_by", deleteBlock);
    expect(permCheckBefore).toBeGreaterThan(-1);
  });

  test('delete button has btn-icon-danger CSS class', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('btn-icon-danger');
    // CSS definition should exist
    expect(content).toContain('.btn-icon-danger:hover');
    expect(content).toContain('#EF4444');
  });
});

// =====================================================
// 2. Desktop drill library has delete JavaScript handler
// =====================================================

test.describe('Drill delete JavaScript handler', () => {
  test('drills_library.php defines deleteDrill function', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('function deleteDrill(');
  });

  test('deleteDrill sends delete_drill action to process_drills.php', () => {
    const content = readFile('views/drills_library.php');
    const fnStart = content.indexOf('function deleteDrill(');
    const fnEnd = content.indexOf('}', content.indexOf('.catch', fnStart)) + 1;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'delete_drill'");
    expect(fnBody).toContain('process_drills.php');
    expect(fnBody).toContain('csrf_token');
  });

  test('deleteDrill sends Accept: application/json header', () => {
    const content = readFile('views/drills_library.php');
    const fnStart = content.indexOf('function deleteDrill(');
    const fnEnd = content.indexOf('}', content.indexOf('.catch', fnStart)) + 1;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'Accept': 'application/json'");
  });

  test('deleteDrill shows confirmation before deleting', () => {
    const content = readFile('views/drills_library.php');
    const fnStart = content.indexOf('function deleteDrill(');
    const fnEnd = content.indexOf('}', content.indexOf('showDeleteConfirm', fnStart)) + 1;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('showDeleteConfirm(');
  });

  test('delete action buttons are wired up in DOMContentLoaded', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain("data-action=\"delete\"");
    expect(content).toContain('deleteDrill(drillId)');
  });
});

// =====================================================
// 2b. Drill delete returns JSON for AJAX
// =====================================================

test.describe('Drill delete returns JSON for AJAX requests', () => {
  test('process_drills.php delete_drill returns JSON when Accept header is application/json', () => {
    const content = readFile('process_drills.php');
    const fnStart = content.indexOf("if ($action === 'delete_drill')");
    const fnEnd = content.indexOf("if ($action === 'bulk_delete_drills')");
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("HTTP_ACCEPT");
    expect(fn).toContain("application/json");
    expect(fn).toContain("json_encode");
    expect(fn).toContain("'success' => true");
  });
});

// =====================================================
// 2c. Multi-select drills UI and bulk actions
// =====================================================

test.describe('Drill multi-select and bulk actions', () => {
  test('drill cards have selection checkboxes', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('drill-select-checkbox');
    expect(content).toContain('drill-select-overlay');
    expect(content).toContain('onchange="updateBulkSelection()"');
  });

  test('bulk actions bar exists with Create Practice Plan and Delete Selected buttons', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('id="bulkActionsBar"');
    expect(content).toContain('id="bulkCreatePlanBtn"');
    expect(content).toContain('id="bulkDeleteBtn"');
    expect(content).toContain('Create Practice Plan');
    expect(content).toContain('Delete Selected');
  });

  test('select all checkbox exists', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('id="selectAllDrills"');
    expect(content).toContain('toggleSelectAllDrills');
  });

  test('updateBulkSelection function exists and manages bulk actions bar', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('function updateBulkSelection()');
    expect(content).toContain('bulkActionsBar');
    expect(content).toContain('bulkSelectedCount');
  });

  test('bulkCreatePracticePlan function stores drill IDs and navigates to practice_create', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('function bulkCreatePracticePlan()');
    expect(content).toContain('drillsToAdd');
    expect(content).toContain('sessionStorage.setItem');
    expect(content).toContain('practice_create');
  });

  test('bulkDeleteDrills function sends bulk_delete_drills action', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('function bulkDeleteDrills()');
    expect(content).toContain("'bulk_delete_drills'");
    expect(content).toContain('process_drills.php');
    expect(content).toContain('drill_ids[]');
  });

  test('process_drills.php has bulk_delete_drills action', () => {
    const content = readFile('process_drills.php');
    expect(content).toContain("if ($action === 'bulk_delete_drills')");
    expect(content).toContain("'delete_drills'");
    expect(content).toContain("drill_ids");
  });

  test('drill cards have data-drill-id attribute for DOM manipulation', () => {
    const content = readFile('views/drills_library.php');
    expect(content).toContain('data-drill-id=');
  });
});

// =====================================================
// 3. API v1 drills DELETE endpoint
// =====================================================

test.describe('API v1 drill DELETE endpoint', () => {
  test('api/v1/drills.php documents DELETE endpoint', () => {
    const content = readFile('api/v1/drills.php');
    expect(content).toContain('DELETE /v1/drills/{id}');
  });

  test('api/v1/drills.php routes DELETE method to handler', () => {
    const content = readFile('api/v1/drills.php');
    expect(content).toContain("'DELETE'");
    expect(content).toContain('handleDeleteDrill');
  });

  test('handleDeleteDrill requires write_drills permission', () => {
    const content = readFile('api/v1/drills.php');
    const fnStart = content.indexOf('function handleDeleteDrill');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'write_drills'");
    expect(fnBody).toContain('403');
  });

  test('handleDeleteDrill checks drill exists before deleting', () => {
    const content = readFile('api/v1/drills.php');
    const fnStart = content.indexOf('function handleDeleteDrill');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('SELECT id FROM drills WHERE id = ?');
    expect(fnBody).toContain('404');
    expect(fnBody).toContain('Drill not found');
  });

  test('handleDeleteDrill performs DELETE and logs access', () => {
    const content = readFile('api/v1/drills.php');
    const fnStart = content.indexOf('function handleDeleteDrill');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('DELETE FROM drills WHERE id = ?');
    expect(fnBody).toContain('logApiAccess');
    expect(fnBody).toContain('delete_drill');
    expect(fnBody).toContain('Drill deleted successfully');
  });
});

// =====================================================
// 4. decryptCredential returns empty for encrypted values when key is lost
// =====================================================

test.describe('decryptCredential handles lost encryption key', () => {
  test('decryptCredential checks isValueEncrypted when decryption fails', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptCredential');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('isValueEncrypted');
  });

  test('decryptCredential returns empty string for encrypted values that cannot be decrypted', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptCredential');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    // After isValueEncrypted check, should return empty string
    const encryptedCheck = fnBody.indexOf('isValueEncrypted($value)');
    const returnEmpty = fnBody.indexOf("return ''", encryptedCheck);
    expect(encryptedCheck).toBeGreaterThan(-1);
    expect(returnEmpty).toBeGreaterThan(encryptedCheck);
  });

  test('decryptCredential logs specific message about missing encryption key', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptCredential');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('encryption key may be missing');
    expect(fnBody).toContain('re-entered');
  });

  test('decryptCredential still returns plaintext value for backward compatibility', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function decryptCredential');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    // At the very end, for non-encrypted values, still returns $value
    expect(fnBody).toContain('return $value');
  });
});
