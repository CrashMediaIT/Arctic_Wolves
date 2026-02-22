import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Camp Purchase & Upcoming Sessions Tests
 * Tests for:
 * 1. Payment success handling for camp/multi-week package purchases
 * 2. Upcoming sessions view includes camp/multi-week package sessions
 * 3. Stripe checkout includes customer email
 * 4. Database schema updates for user_packages
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Payment Success - Package Purchase Handling
// =====================================================

test.describe('Payment Success - Package Purchase Handling', () => {
  test('payment_success.php handles type=package parameter', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("purchase_type = \$_GET['type']");
    expect(content).toContain("purchase_type === 'package'");
    expect(content).toContain('package_purchase');
  });

  test('payment_success.php creates user_packages record for camp purchases', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('INSERT INTO user_packages');
    expect(content).toContain('stripe_session_id');
    expect(content).toContain("payment_status, amount_paid");
    expect(content).toContain("'paid'");
  });

  test('payment_success.php creates bookings for linked package sessions', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('package_sessions');
    expect(content).toContain('linked_session_ids');
    expect(content).toContain('INSERT INTO bookings');
    expect(content).toContain("'confirmed'");
  });

  test('payment_success.php handles add-ons for camp registrations', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('camp_registration_add_ons');
    expect(content).toContain('selected_addons');
    expect(content).toContain('add_on_id');
  });

  test('payment_success.php has idempotency check to prevent duplicate processing', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('already_processed');
    expect(content).toContain('stripe_session_id');
    expect(content).toContain('!empty($athlete_ids)');
  });

  test('payment_success.php uses database transaction for package processing', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('beginTransaction');
    expect(content).toContain('commit');
    expect(content).toContain('rollBack');
  });

  test('payment_success.php sends confirmation email for package purchases', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('sendEmail');
    expect(content).toContain('payment_receipt');
    // Should send email for both regular and package purchases
    const sendEmailCount = (content.match(/sendEmail/g) || []).length;
    expect(sendEmailCount).toBeGreaterThanOrEqual(2);
  });

  test('payment_success.php cleans up session data after package purchase', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("unset(\$_SESSION['package_purchase'])");
  });

  test('payment_success.php still handles regular session bookings', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('HANDLE REGULAR SESSION BOOKING');
    expect(content).toContain("booking['status'] == 'pending'");
    expect(content).toContain("UPDATE bookings SET status = 'paid'");
  });
});

// =====================================================
// 2. Upcoming Sessions - Camp/Multi-Week Package Support
// =====================================================

test.describe('Upcoming Sessions - Package Sessions Support', () => {
  test('Upcoming sessions query includes sessions from purchased packages', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('package_sessions');
    expect(content).toContain('user_packages');
    expect(content).toContain("up.payment_status = 'paid'");
  });

  test('Upcoming sessions uses subquery for package session lookup', () => {
    const content = readFile('views/sessions_upcoming.php');
    // Should check both bookings AND package_sessions
    expect(content).toContain('b.user_id IS NOT NULL OR s.id IN');
    expect(content).toContain('up.package_id = ps.package_id');
    expect(content).toContain('ps.session_id IS NOT NULL');
  });

  test('Upcoming sessions handles cancelled bookings correctly with package sessions', () => {
    const content = readFile('views/sessions_upcoming.php');
    // For upcoming (non-history) view, cancelled bookings should be excluded
    // but sessions from packages should still show even without a booking
    expect(content).toContain("b.id IS NULL OR b.status != 'cancelled'");
  });

  test('Upcoming sessions history view also includes package sessions', () => {
    const content = readFile('views/sessions_upcoming.php');
    // Both history and upcoming queries should have the package_sessions subquery
    const packageSessionsCount = (content.match(/package_sessions ps/g) || []).length;
    expect(packageSessionsCount).toBeGreaterThanOrEqual(2);
  });

  test('Upcoming sessions passes correct params for both queries', () => {
    const content = readFile('views/sessions_upcoming.php');
    // Need two user_id params for main query (one for bookings join, one for package subquery)
    expect(content).toContain('$params = [$user_id, $user_id]');
  });
});

// =====================================================
// 3. Stripe Checkout - Customer Email
// =====================================================

test.describe('Stripe Checkout - Customer Email', () => {
  test('process_purchase_package.php fetches user email for Stripe checkout', () => {
    const content = readFile('process_purchase_package.php');
    expect(content).toContain("SELECT email FROM users WHERE id = ?");
    expect(content).toContain('customer_email');
  });

  test('process_purchase_package.php passes customer_email to Stripe when available', () => {
    const content = readFile('process_purchase_package.php');
    expect(content).toContain("stripe_params['customer_email'] = \$customer_email");
    expect(content).toContain('!empty($customer_email)');
  });

  test('process_booking.php passes customer_email to Stripe for session bookings', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain('customer_email');
    expect(content).toContain("stripe_params['customer_email'] = \$customer_email");
    // Both private session and regular session booking should include email
    const emailQueryCount = (content.match(/SELECT email FROM users WHERE id/g) || []).length;
    expect(emailQueryCount).toBeGreaterThanOrEqual(2);
  });
});

// =====================================================
// 4. Database Schema - user_packages stripe_session_id
// =====================================================

test.describe('Database Schema - user_packages stripe_session_id', () => {
  test('Schema includes stripe_session_id in user_packages table', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('user_packages');
    expect(schema).toContain('stripe_session_id');
    // ALTER TABLE migration for existing databases
    expect(schema).toContain('ALTER TABLE `user_packages`');
    expect(schema).toContain("ADD COLUMN IF NOT EXISTS `stripe_session_id`");
  });

  test('Migration SQL file exists for user_packages stripe_session_id', () => {
    const migration = readFile('deployment/sql/add_user_packages_stripe_session.sql');
    expect(migration).toContain('ALTER TABLE `user_packages`');
    expect(migration).toContain('stripe_session_id');
    expect(migration).toContain('idx_stripe_session');
  });
});
