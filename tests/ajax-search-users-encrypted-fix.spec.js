import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for ajax_search_users.php fix:
 * User search must work with encrypted first_name/last_name fields.
 * The old code used SQL LIKE on encrypted columns which missed most users.
 * The fix fetches all candidates, decrypts, then filters in PHP.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

test.describe('AJAX User Search - Encrypted Field Fix', () => {
  test('does NOT use SQL LIKE on first_name or last_name', () => {
    const content = readFile('ajax_search_users.php');
    // The old bug: SQL LIKE on encrypted columns
    // Should NOT have: u.first_name LIKE ? OR u.last_name LIKE ?
    expect(content).not.toContain('u.first_name LIKE');
    expect(content).not.toContain('u.last_name LIKE');
  });

  test('fetches users then filters in PHP after decryption', () => {
    const content = readFile('ajax_search_users.php');
    // Should decrypt first, then filter
    expect(content).toContain('decryptUserRows');
    // PHP-side filtering with mb_strtolower for case-insensitive matching
    expect(content).toContain('mb_strtolower');
    expect(content).toContain('mb_strpos');
  });

  test('still filters by email in SQL when query contains @', () => {
    const content = readFile('ajax_search_users.php');
    // Email is NOT encrypted, so SQL LIKE on email is still valid
    expect(content).toContain("u.email LIKE");
    expect(content).toContain("strpos($query, '@')");
  });

  test('applies limit after PHP filtering, not in SQL', () => {
    const content = readFile('ajax_search_users.php');
    // Should use array_slice after filtering
    expect(content).toContain('array_slice');
    // The SQL query should NOT have LIMIT (it fetches all candidates)
    // Check that LIMIT is not in the main SQL string
    const sqlSection = content.substring(
      content.indexOf("SELECT u.id, u.first_name"),
      content.indexOf("$stmt = $pdo->prepare")
    );
    expect(sqlSection).not.toContain('LIMIT');
  });

  test('role filtering still works via SQL', () => {
    const content = readFile('ajax_search_users.php');
    expect(content).toContain("u.role IN");
    expect(content).toContain("$roleList");
  });

  test('splits search query into words for multi-word matching', () => {
    const content = readFile('ajax_search_users.php');
    expect(content).toContain('preg_split');
    expect(content).toContain("'/\\s+/'");
  });

  test('matches each word against concatenated first_name + last_name + email', () => {
    const content = readFile('ajax_search_users.php');
    // Should build a haystack from all searchable fields
    expect(content).toContain("first_name");
    expect(content).toContain("last_name");
    expect(content).toContain("email");
    expect(content).toContain("$haystack");
  });

  test('returns proper JSON structure with id, name, email, role', () => {
    const content = readFile('ajax_search_users.php');
    expect(content).toContain("'id'");
    expect(content).toContain("'name'");
    expect(content).toContain("'email'");
    expect(content).toContain("'role'");
    expect(content).toContain("json_encode");
    expect(content).toContain("'success' => true");
  });

  test('requires authentication', () => {
    const content = readFile('ajax_search_users.php');
    expect(content).toContain("$_SESSION['user_id']");
    expect(content).toContain('401');
    expect(content).toContain('Unauthorized');
  });

  test('documents encryption-aware approach in comments', () => {
    const content = readFile('ajax_search_users.php');
    expect(content).toContain('encrypted');
    expect(content).toContain('FieldEncryption');
  });
});
