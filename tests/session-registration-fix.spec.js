import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Session Registration Fix Tests
 * Tests for:
 * 1. CSRF token is present on the booking page for session registration
 * 2. app.js does not intercept register-session, join-waitlist, purchase-package clicks
 * 3. Template session bookings are checked against session_date_athletes table
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. CSRF Token Present on Booking Page
// =====================================================

test.describe('CSRF Token on Booking Page', () => {
  test('sessions_booking.php includes a CSRF token input for JS handlers', () => {
    const content = readFile('views/sessions_booking.php');
    // The page should output a CSRF token input outside of any form
    expect(content).toContain('csrfTokenInput()');
    // Verify it appears before the booking content (not just inside a form)
    const csrfIdx = content.indexOf('csrfTokenInput()');
    const bookingContentIdx = content.indexOf('booking-content');
    expect(csrfIdx).toBeLessThan(bookingContentIdx);
  });
});

// =====================================================
// 2. app.js Skip List Includes Booking Actions
// =====================================================

test.describe('app.js Action Handler Skip List', () => {
  test('app.js skips register-session action in generic handler', () => {
    const content = readFile('js/app.js');
    // Find the skip list in the generic action handler
    const skipListMatch = content.match(/if\s*\(\[([^\]]+)\]\.includes\(action\)\)/);
    expect(skipListMatch).not.toBeNull();
    const skipList = skipListMatch[1];
    expect(skipList).toContain('register-session');
  });

  test('app.js skips join-waitlist action in generic handler', () => {
    const content = readFile('js/app.js');
    const skipListMatch = content.match(/if\s*\(\[([^\]]+)\]\.includes\(action\)\)/);
    expect(skipListMatch).not.toBeNull();
    const skipList = skipListMatch[1];
    expect(skipList).toContain('join-waitlist');
  });

  test('app.js skips purchase-package action in generic handler', () => {
    const content = readFile('js/app.js');
    const skipListMatch = content.match(/if\s*\(\[([^\]]+)\]\.includes\(action\)\)/);
    expect(skipListMatch).not.toBeNull();
    const skipList = skipListMatch[1];
    expect(skipList).toContain('purchase-package');
  });
});

// =====================================================
// 3. Template Session Duplicate Check
// =====================================================

test.describe('Template Session Duplicate Detection', () => {
  test('sessions_booking.php queries session_date_athletes for booked template dates', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('session_date_athletes');
    expect(content).toContain('user_booked_template_dates');
  });

  test('sessions_booking.php checks template sessions against booked template dates', () => {
    const content = readFile('views/sessions_booking.php');
    // The already_booked check should handle both session and template source types
    expect(content).toContain("in_array($session['date_id']");
    expect(content).toContain('$user_booked_template_dates');
  });
});
