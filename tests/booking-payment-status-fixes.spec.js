import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Booking Payment Status & Duplicate Charge Prevention Tests
 * Tests for:
 * 1. Sessions not missing due to unpaid bookings inflating registered_count
 * 2. Booking page shows "Registered" only when payment_status is 'paid'
 * 3. Button text shows "Registered" instead of "Already Registered"
 * 4. Duplicate charge prevention while allowing parents to pay for multiple kids
 * 5. Calendar view respects booking/full status instead of always showing Register
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Missing Sessions Fix - Only count paid bookings
// =====================================================

test.describe('Missing Sessions Fix - Paid Bookings Only', () => {
  test('registered_count LEFT JOIN only counts paid bookings', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("LEFT JOIN bookings b ON b.session_id = s.id AND b.status IN ('confirmed', 'waitlisted') AND b.payment_status = 'paid'");
  });

  test('capacity check in process_booking only counts paid bookings', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain("SELECT COUNT(*) FROM bookings WHERE session_id = ? AND status = 'confirmed' AND payment_status = 'paid'");
  });
});

// =====================================================
// 2. Show Registered Only When Paid
// =====================================================

test.describe('Registered Status Requires Payment', () => {
  test('user booked sessions query requires payment_status = paid', () => {
    const content = readFile('views/sessions_booking.php');
    // The booked sessions check should only look for paid bookings
    expect(content).toContain("SELECT session_id FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'waitlisted') AND payment_status = 'paid'");
  });

  test('parent child booked sessions query requires payment_status = paid', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("AND bk.payment_status = 'paid'");
  });

  test('package purchased check requires payment_status = paid', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("AND payment_status = 'paid'");
    // Should NOT contain the old pending+paid check for booked sessions
    expect(content).not.toContain("payment_status IN ('pending', 'paid')");
  });
});

// =====================================================
// 3. Button Text Shows "Registered" Not "Already Registered"
// =====================================================

test.describe('Registered Button Text', () => {
  test('session list button shows Registered not Already Registered', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).not.toContain('Already Registered');
    // Verify "Registered" text still exists (in buttons)
    const registeredMatches = content.match(/<i class="fas fa-check-circle"><\/i> Registered/g);
    expect(registeredMatches).not.toBeNull();
    expect(registeredMatches.length).toBeGreaterThanOrEqual(2);
  });
});

// =====================================================
// 4. Duplicate Charge Prevention
// =====================================================

test.describe('Duplicate Charge Prevention', () => {
  test('process_booking checks payment_status to distinguish paid vs pending bookings', () => {
    const content = readFile('process_booking.php');
    // Should fetch payment_status to check if booking is paid vs pending
    expect(content).toContain("SELECT id, payment_status FROM bookings WHERE session_id = ? AND user_id = ? AND status IN ('confirmed', 'waitlisted')");
  });

  test('process_booking only blocks duplicate if already paid', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain("payment_status'] === 'paid'");
    // Should redirect only for paid bookings
    expect(content).toContain("error=already_booked");
  });

  test('process_booking updates existing pending booking instead of creating duplicate', () => {
    const content = readFile('process_booking.php');
    // Should update existing pending booking with new stripe session
    expect(content).toContain("payment_status'] === 'pending'");
    expect(content).toContain("UPDATE bookings SET stripe_session_id = ?");
  });

  test('payment_success uses WHERE condition for idempotent update', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("UPDATE bookings SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'");
  });

  test('payment_success checks rowCount before sending receipt email', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('rowCount()');
    expect(content).toContain('prevents duplicate emails');
  });
});

// =====================================================
// 5. Calendar View Respects Booking/Full Status
// =====================================================

test.describe('Calendar View Status Awareness', () => {
  test('session cards include booking status data attributes', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('data-booked=');
    expect(content).toContain('data-full=');
    expect(content).toContain('data-spots=');
  });

  test('calendar JavaScript reads booking status from session data', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("booked: card.dataset.booked === '1'");
    expect(content).toContain("full: card.dataset.full === '1'");
  });

  test('calendar panel shows Registered button for already-booked sessions', () => {
    const content = readFile('views/sessions_booking.php');
    // Calendar should check session.booked and show disabled Registered button
    expect(content).toContain('if (session.booked)');
    expect(content).toContain("registerBtn.innerHTML = '<i class=\"fas fa-check-circle\"></i> Registered'");
  });

  test('calendar panel shows Join Waitlist for full sessions', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('} else if (session.full)');
    expect(content).toContain("registerBtn.innerHTML = '<i class=\"fas fa-clock\"></i> Join Waitlist'");
  });
});

// =====================================================
// 6. Private & Semi-Private Sessions from Products
// =====================================================

test.describe('Private & Semi-Private Sessions', () => {
  test('auto-creates Sessions category and Private/Semi-Private products', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("INSERT IGNORE INTO merchandise_categories (name, description, is_active) VALUES (?, ?, 1)");
    expect(content).toContain("INSERT IGNORE INTO merchandise_products");
    expect(content).toContain("'SESSION-PRIVATE'");
    expect(content).toContain("'SESSION-SEMI-PRIVATE'");
  });

  test('queries pricing from merchandise_products by SKU', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("SELECT price FROM merchandise_products WHERE sku = 'SESSION-PRIVATE'");
    expect(content).toContain("SELECT price FROM merchandise_products WHERE sku = 'SESSION-SEMI-PRIVATE'");
  });

  test('queries available private and semi-private sessions from sessions table', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("(s.is_private = 1 OR s.is_semi_private = 1)");
    expect(content).toContain("s.status = 'scheduled'");
  });

  test('group sessions query excludes private and semi-private sessions', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('(s.is_private = 0 OR s.is_private IS NULL)');
    expect(content).toContain('(s.is_semi_private = 0 OR s.is_semi_private IS NULL)');
  });

  test('displays private/semi-private label on session cards', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("PRIVATE & SEMI-PRIVATE SESSIONS");
    expect(content).toContain("is_private") ;
    expect(content).toContain("is_semi_private");
  });

  test('no longer has free-form private session booking form', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).not.toContain('book_private_session');
    expect(content).not.toContain('coach-typeahead-container');
    expect(content).not.toContain('ArcticTypeahead');
  });

  test('database schema includes is_private and is_semi_private columns for sessions', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain("`is_private` TINYINT(1) DEFAULT 0");
    expect(content).toContain("`is_semi_private` TINYINT(1) DEFAULT 0");
  });
});
