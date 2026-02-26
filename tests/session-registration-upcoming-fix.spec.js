import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Session Registration Upcoming Sessions Fix
 * Tests for:
 * 1. Login intent handling redirects to booking page for regular session_id intents
 * 2. Register intent handling redirects to booking page for regular session_id intents
 * 3. Upcoming sessions query uses CURDATE() instead of NOW() for DATE column comparison
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Login Intent - session_id Handling
// =====================================================

test.describe('Login Intent - session_id Redirect', () => {
  test('login.php handles session_id intent for already-logged-in users', () => {
    const content = readFile('login.php');
    // Should redirect to booking page with session_id when intent has session_id
    expect(content).toContain("intent['session_id']");
    expect(content).toContain("dashboard.php?page=booking&session_id=");
  });

  test('login.php handles session_id intent after successful login', () => {
    const content = readFile('login.php');
    // Both already-logged-in and login-success blocks should handle session_id
    const sessionIdMatches = content.match(/intent\['session_id'\]/g) || [];
    expect(sessionIdMatches.length).toBeGreaterThanOrEqual(2);
  });

  test('login.php uses !empty() and intval() to safely check and sanitize session_id in redirect', () => {
    const content = readFile('login.php');
    expect(content).toContain("!empty($intent['session_id'])");
    expect(content).toContain("intval($intent['session_id'])");
  });

  test('login.php handles all three intent types: template_id, package_id, session_id', () => {
    const content = readFile('login.php');
    expect(content).toContain("intent['template_id']");
    expect(content).toContain("intent['package_id']");
    expect(content).toContain("intent['session_id']");
  });
});

// =====================================================
// 2. Register Intent - session_id Handling
// =====================================================

test.describe('Register Intent - session_id Redirect', () => {
  test('register.php handles session_id intent for already-logged-in users', () => {
    const content = readFile('register.php');
    expect(content).toContain("intent['session_id']");
    expect(content).toContain("dashboard.php?page=booking&session_id=");
  });

  test('register.php uses !empty() and intval() to safely check and sanitize session_id in redirect', () => {
    const content = readFile('register.php');
    expect(content).toContain("!empty($intent['session_id'])");
    expect(content).toContain("intval($intent['session_id'])");
  });

  test('register.php handles all three intent types: template_id, package_id, session_id', () => {
    const content = readFile('register.php');
    expect(content).toContain("intent['template_id']");
    expect(content).toContain("intent['package_id']");
    expect(content).toContain("intent['session_id']");
  });
});

// =====================================================
// 3. Upcoming Sessions - CURDATE() for DATE column
// =====================================================

test.describe('Upcoming Sessions - Correct Date Comparison', () => {
  test('sessions_upcoming.php uses CURDATE() for upcoming session_date comparison', () => {
    const content = readFile('views/sessions_upcoming.php');
    // The upcoming sessions query should use CURDATE() not NOW() for DATE column
    expect(content).toContain('s.session_date >= CURDATE()');
  });

  test('sessions_upcoming.php uses CURDATE() for history session_date comparison', () => {
    const content = readFile('views/sessions_upcoming.php');
    // The history query should use CURDATE() not NOW() for DATE column
    expect(content).toContain('s.session_date < CURDATE()');
  });

  test('sessions_upcoming.php does not use NOW() for session_date comparisons on DATE column', () => {
    const content = readFile('views/sessions_upcoming.php');
    // NOW() should only be used for DATETIME columns (like tsd.session_date), not s.session_date
    const lines = content.split('\n');
    for (const line of lines) {
      if (line.includes('s.session_date') && (line.includes('>= NOW()') || line.includes('< NOW()'))) {
        // This should not exist - fail the test
        expect(line).not.toContain('NOW()');
      }
    }
  });

  test('sessions_upcoming.php is consistent with home.php date comparisons', () => {
    const upcoming = readFile('views/sessions_upcoming.php');
    const home = readFile('views/home.php');
    // home.php uses CURDATE() for session_date - sessions_upcoming should too
    expect(home).toContain('s.session_date >= CURDATE()');
    expect(upcoming).toContain('s.session_date >= CURDATE()');
  });
});
