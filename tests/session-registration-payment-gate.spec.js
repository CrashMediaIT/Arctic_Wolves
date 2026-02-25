import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Session Registration Payment Gate Tests
 * Tests that paid template sessions are NOT marked as registered until
 * payment is confirmed via Stripe. Prevents the bug where clicking Register
 * and then hitting the back button would still show the session as registered.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Paid template sessions must NOT insert into
//    session_date_athletes before Stripe payment
// =====================================================

test.describe('Template Session Registration Requires Payment', () => {
  test('process_booking does NOT insert into session_date_athletes before Stripe redirect for paid sessions', () => {
    const content = readFile('process_booking.php');
    // Find the register_template_session handler
    const handlerStart = content.indexOf("action === 'register_template_session'");
    expect(handlerStart).toBeGreaterThan(-1);

    // Find the Stripe checkout creation and redirect section (paid path)
    const stripeCreateIdx = content.indexOf('Checkout\\Session::create($stripe_params)', handlerStart);
    expect(stripeCreateIdx).toBeGreaterThan(-1);

    // Find the redirect to Stripe (end of paid path)
    const redirectIdx = content.indexOf('header("Location: " . $checkout_session->url)', stripeCreateIdx);
    expect(redirectIdx).toBeGreaterThan(-1);

    // The code between Stripe create and redirect should NOT contain an INSERT into session_date_athletes
    const betweenCode = content.substring(stripeCreateIdx, redirectIdx);
    expect(betweenCode).not.toContain("INSERT INTO session_date_athletes");
  });

  test('process_booking still inserts into session_date_athletes for FREE sessions', () => {
    const content = readFile('process_booking.php');
    // The free session path should still directly register
    const freeIdx = content.indexOf('// Free session - register directly');
    expect(freeIdx).toBeGreaterThan(-1);

    // After the free session comment, there should be an INSERT
    const afterFree = content.substring(freeIdx, freeIdx + 300);
    expect(afterFree).toContain("INSERT INTO session_date_athletes");
  });
});

// =====================================================
// 2. payment_success.php handles template_session type
//    from Stripe metadata after payment is confirmed
// =====================================================

test.describe('Payment Success Handles Template Sessions', () => {
  test('payment_success.php checks Stripe metadata for template_session type', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("metadata->type");
    expect(content).toContain("template_session");
  });

  test('payment_success.php inserts into session_date_athletes after payment confirmed', () => {
    const content = readFile('payment_success.php');
    // The INSERT should appear in the template_session handler
    const templateIdx = content.indexOf("template_session");
    expect(templateIdx).toBeGreaterThan(-1);

    const afterTemplate = content.substring(templateIdx, templateIdx + 1500);
    expect(afterTemplate).toContain("INSERT INTO session_date_athletes");
  });

  test('payment_success.php has idempotency check for template session registration', () => {
    const content = readFile('payment_success.php');
    // Should check for existing registration before inserting
    const templateIdx = content.indexOf("template_session");
    const afterTemplate = content.substring(templateIdx, templateIdx + 1500);
    expect(afterTemplate).toContain("SELECT id FROM session_date_athletes WHERE session_date_id = ? AND athlete_id = ?");
  });

  test('payment_success.php reads session_date_id and athlete_id from Stripe metadata', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("metadata->session_date_id");
    expect(content).toContain("metadata->athlete_id");
  });

  test('payment_success.php includes auditor for logging', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("require_once __DIR__ . '/lib/auditor.php'");
  });

  test('payment_success.php logs audit entry for template session registration', () => {
    const content = readFile('payment_success.php');
    const templateIdx = content.indexOf("template_session");
    const afterTemplate = content.substring(templateIdx, templateIdx + 1500);
    expect(afterTemplate).toContain("Auditor::log");
    expect(afterTemplate).toContain("register_template_session");
  });
});
