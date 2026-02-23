import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for evaluation athlete add/import fix:
 * The fetch() API does not automatically send the X-Requested-With header
 * that the PHP isAjaxRequest() function checks. Without it, the server
 * returns a redirect instead of JSON, causing response.json() to fail
 * and triggering "Error adding athlete" / "Error importing CSV".
 *
 * Fix: Add headers: { 'X-Requested-With': 'XMLHttpRequest' } to all
 * POST fetch() calls in coach_session_evaluations.php.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Frontend fetch calls include XMLHttpRequest header
// =====================================================

test.describe('Evaluation athlete fetch calls include XMLHttpRequest header', () => {
  test('addExistingAthlete sends X-Requested-With header', () => {
    const content = readFile('views/coach_session_evaluations.php');
    // Find the addExistingAthlete function section
    const fnStart = content.indexOf('async function addExistingAthlete()');
    const fnEnd = content.indexOf('async function addManualAthlete()');
    const fnBody = content.substring(fnStart, fnEnd);

    expect(fnBody).toContain("headers: { 'X-Requested-With': 'XMLHttpRequest' }");
    expect(fnBody).toContain("method: 'POST'");
    expect(fnBody).toContain("process_session_evaluations.php");
  });

  test('addManualAthlete sends X-Requested-With header', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function addManualAthlete()');
    const fnEnd = content.indexOf('async function importCSV()');
    const fnBody = content.substring(fnStart, fnEnd);

    expect(fnBody).toContain("headers: { 'X-Requested-With': 'XMLHttpRequest' }");
    expect(fnBody).toContain("method: 'POST'");
    expect(fnBody).toContain("process_session_evaluations.php");
  });

  test('importCSV sends X-Requested-With header', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function importCSV()');
    const fnEnd = content.indexOf('async function removeAthlete(');
    const fnBody = content.substring(fnStart, fnEnd);

    expect(fnBody).toContain("headers: { 'X-Requested-With': 'XMLHttpRequest' }");
    expect(fnBody).toContain("method: 'POST'");
    expect(fnBody).toContain("process_session_evaluations.php");
  });

  test('removeAthlete sends X-Requested-With header', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function removeAthlete(');
    const fnBody = content.substring(fnStart, fnStart + 800);

    expect(fnBody).toContain("headers: { 'X-Requested-With': 'XMLHttpRequest' }");
    expect(fnBody).toContain("method: 'POST'");
    expect(fnBody).toContain("process_session_evaluations.php");
  });

  test('all POST fetch calls to process_session_evaluations.php have the header', () => {
    const content = readFile('views/coach_session_evaluations.php');
    // Count POST fetch blocks that target process_session_evaluations.php
    const postFetchPattern = /fetch\('process_session_evaluations\.php',\s*\{[^}]*method:\s*'POST'/g;
    const matches = content.match(postFetchPattern);
    expect(matches).not.toBeNull();
    expect(matches.length).toBe(4);

    // Each POST fetch should have the XMLHttpRequest header
    const headerPattern = /fetch\('process_session_evaluations\.php',\s*\{[^}]*headers:\s*\{\s*'X-Requested-With':\s*'XMLHttpRequest'\s*\}/g;
    const headerMatches = content.match(headerPattern);
    expect(headerMatches).not.toBeNull();
    expect(headerMatches.length).toBe(4);
  });
});

// =====================================================
// 2. Server-side isAjaxRequest check exists
// =====================================================

test.describe('Server-side AJAX detection in process_session_evaluations.php', () => {
  test('isAjaxRequest function checks X-Requested-With header', () => {
    const content = readFile('process_session_evaluations.php');
    expect(content).toContain('function isAjaxRequest()');
    expect(content).toContain('HTTP_X_REQUESTED_WITH');
    expect(content).toContain('xmlhttprequest');
  });

  test('sendResponse returns JSON for AJAX requests', () => {
    const content = readFile('process_session_evaluations.php');
    expect(content).toContain('function sendResponse');
    expect(content).toContain('isAjaxRequest()');
    expect(content).toContain('json_encode');
    expect(content).toContain("'Content-Type: application/json'");
  });

  test('add_athlete action returns response via sendResponse', () => {
    const content = readFile('process_session_evaluations.php');
    const addSection = content.substring(
      content.indexOf("case 'add_athlete':"),
      content.indexOf("case 'update_athlete':")
    );
    expect(addSection).toContain('sendResponse(true');
    expect(addSection).toContain('Athlete added successfully');
  });

  test('import_athletes_csv action returns response via sendResponse', () => {
    const content = readFile('process_session_evaluations.php');
    const importSection = content.substring(
      content.indexOf("case 'import_athletes_csv':"),
      content.indexOf("case 'save_evaluation_scores':")
    );
    expect(importSection).toContain('sendResponse(true');
    expect(importSection).toContain('imported');
  });
});

// =====================================================
// 3. Frontend functions preserve correct behavior
// =====================================================

test.describe('Frontend evaluation functions maintain correct behavior', () => {
  test('addExistingAthlete sends user_id and evaluation_id', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function addExistingAthlete()');
    const fnEnd = content.indexOf('async function addManualAthlete()');
    const fnBody = content.substring(fnStart, fnEnd);

    expect(fnBody).toContain("formData.append('action', 'add_athlete')");
    expect(fnBody).toContain("formData.append('evaluation_id', evaluationId)");
    expect(fnBody).toContain("formData.append('user_id', userId)");
    expect(fnBody).toContain("formData.append('csrf_token'");
  });

  test('addManualAthlete sends name, email, dob, notes fields', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function addManualAthlete()');
    const fnEnd = content.indexOf('async function importCSV()');
    const fnBody = content.substring(fnStart, fnEnd);

    expect(fnBody).toContain("formData.append('action', 'add_athlete')");
    expect(fnBody).toContain("formData.append('first_name', firstName)");
    expect(fnBody).toContain("formData.append('last_name', lastName)");
    expect(fnBody).toContain("formData.append('email', email)");
    expect(fnBody).toContain("formData.append('date_of_birth', dob)");
    expect(fnBody).toContain("formData.append('notes', notes)");
    expect(fnBody).toContain("formData.append('csrf_token'");
  });

  test('importCSV sends csv_file and evaluation_id', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function importCSV()');
    const fnEnd = content.indexOf('async function removeAthlete(');
    const fnBody = content.substring(fnStart, fnEnd);

    expect(fnBody).toContain("formData.append('action', 'import_athletes_csv')");
    expect(fnBody).toContain("formData.append('evaluation_id', evaluationId)");
    expect(fnBody).toContain("formData.append('csv_file', fileInput.files[0])");
    expect(fnBody).toContain("formData.append('csrf_token'");
  });

  test('removeAthlete sends athlete_id', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function removeAthlete(');
    const fnBody = content.substring(fnStart, fnStart + 800);

    expect(fnBody).toContain("formData.append('action', 'remove_athlete')");
    expect(fnBody).toContain("formData.append('athlete_id', athleteId)");
    expect(fnBody).toContain("formData.append('csrf_token'");
  });

  test('importCSV displays success and error messages', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('async function importCSV()');
    const fnEnd = content.indexOf('async function removeAthlete(');
    const fnBody = content.substring(fnStart, fnEnd);

    expect(fnBody).toContain('data.success');
    expect(fnBody).toContain('data.message');
    expect(fnBody).toContain('data.errors');
    expect(fnBody).toContain('text-success');
    expect(fnBody).toContain('text-danger');
    expect(fnBody).toContain('text-warning');
  });
});
