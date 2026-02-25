import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. Stripe booking status ENUM fix (process_booking.php, payment_success.php)
 * 2. Packages shown on landing page without show_on_landing requirement
 * 3. OCR scan uploads to Nextcloud and Paperless-NGX
 * 4. Nextcloud password decryption fix (cloud_config.php)
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Stripe Booking Status ENUM Fix
// =====================================================

test.describe('Stripe Booking Status ENUM Fix', () => {
  test('process_booking.php private session uses confirmed status (not pending)', () => {
    const content = readFile('process_booking.php');
    // The private session INSERT should use 'confirmed' for status column
    const privateSessionInsert = content.match(/INSERT INTO bookings \(session_id, user_id, stripe_session_id, amount_paid, payment_status, status, notes\)\s*VALUES\s*\(\?, \?, \?, \?, '[^']+', '[^']+', \?\)/s);
    expect(privateSessionInsert).not.toBeNull();
    expect(privateSessionInsert[0]).toContain("'confirmed'");
    // payment_status should be pending, status should be confirmed
    expect(privateSessionInsert[0]).toContain("'pending', 'confirmed'");
  });

  test('process_booking.php regular session uses confirmed status (not pending)', () => {
    const content = readFile('process_booking.php');
    // The regular session INSERT should use 'confirmed' for status and 'pending' for payment_status
    expect(content).toContain("'confirmed', 'pending'");
    expect(content).toContain("payment_status");
    // Should NOT insert 'pending' as the status value
    expect(content).not.toMatch(/VALUES\s*\([^)]*'pending'\s*\)\s*$/m);
  });

  test('payment_success.php updates payment_status not status', () => {
    const content = readFile('payment_success.php');
    // Should update payment_status column, not status column
    expect(content).toContain("UPDATE bookings SET payment_status = 'paid'");
    // Should NOT update status to 'paid' (invalid ENUM value)
    expect(content).not.toContain("SET status = 'paid'");
  });

  test('payment_success.php checks payment_status for idempotency', () => {
    const content = readFile('payment_success.php');
    // Should check payment_status !== 'paid' instead of status == 'pending'
    expect(content).toContain("payment_status");
    expect(content).toContain("!== 'paid'");
  });
});

// =====================================================
// 2. Packages on Landing Page
// =====================================================

test.describe('Packages on Landing Page', () => {
  test('sessions_public.php shows all active packages without requiring show_on_landing', () => {
    const content = readFile('sessions_public.php');
    // The packages query should NOT require show_on_landing = 1
    const packagesSection = content.substring(
      content.indexOf('Fetch active packages'),
      content.indexOf('fetchAll(PDO::FETCH_ASSOC)', content.indexOf('Fetch active packages'))
    );
    expect(packagesSection).not.toContain('show_on_landing');
  });

  test('sessions_public.php excludes camp and multi_week from packages section', () => {
    const content = readFile('sessions_public.php');
    // Packages query should still exclude camp and multi_week types
    expect(content).toContain("package_type NOT IN ('camp', 'multi_week')");
  });

  test('sessions_public.php still has packages section with training packages heading', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain('Training Packages');
    expect(content).toContain('packages-section');
    expect(content).toContain('packages-grid');
    expect(content).toContain('package-card');
  });

  test('sessions_public.php handles NULL package_type gracefully', () => {
    const content = readFile('sessions_public.php');
    // Query should handle packages with NULL package_type
    expect(content).toContain('package_type IS NULL OR package_type NOT IN');
  });
});

// =====================================================
// 3. OCR Scan Upload to Cloud Services
// =====================================================

test.describe('OCR Scan Upload to Cloud Services', () => {
  test('ocr_scan saves file permanently instead of temp', () => {
    const content = readFile('process_expenses.php');
    // Should save to uploads/receipts/ not sys_get_temp_dir()
    const ocrSection = content.substring(content.indexOf("case 'ocr_scan':"));
    expect(ocrSection).toContain("uploads/receipts/");
    expect(ocrSection).toContain("uniqid('receipt_')");
    // Should NOT use temp dir for OCR scan
    expect(ocrSection).not.toContain('sys_get_temp_dir');
    // Should NOT delete the file after OCR
    expect(ocrSection).not.toContain('unlink(');
  });

  test('ocr_scan uploads to Nextcloud after OCR', () => {
    const content = readFile('process_expenses.php');
    // Find the full ocr_scan case section - from 'case ocr_scan' to the next 'case '
    const startIdx = content.indexOf("case 'ocr_scan':");
    const nextCase = content.indexOf("case '", startIdx + 20);
    const ocrSection = content.substring(startIdx, nextCase > -1 ? nextCase : undefined);
    expect(ocrSection).toContain('persistUploadedFile');
    expect(ocrSection).toContain('nextcloud_path');
  });

  test('ocr_scan uploads to Paperless-NGX after OCR', () => {
    const content = readFile('process_expenses.php');
    const startIdx = content.indexOf("case 'ocr_scan':");
    const nextCase = content.indexOf("case '", startIdx + 20);
    const ocrSection = content.substring(startIdx, nextCase > -1 ? nextCase : undefined);
    expect(ocrSection).toContain('uploadToPaperless');
    expect(ocrSection).toContain('Receipt');
  });

  test('ocr_scan returns receipt_url and nextcloud_path in response', () => {
    const content = readFile('process_expenses.php');
    const startIdx = content.indexOf("case 'ocr_scan':");
    const nextCase = content.indexOf("case '", startIdx + 20);
    const ocrSection = content.substring(startIdx, nextCase > -1 ? nextCase : undefined);
    expect(ocrSection).toContain("'receipt_url'");
    expect(ocrSection).toContain("'nextcloud_path'");
  });

  test('frontend stores receipt_url and nextcloud_path from OCR response', () => {
    const content = readFile('views/accounting_expenses.php');
    expect(content).toContain('_receipt_url');
    expect(content).toContain('_nextcloud_path');
    expect(content).toContain('data.receipt_url');
    expect(content).toContain('data.nextcloud_path');
  });
});

// =====================================================
// 4. Nextcloud Password Decryption Fix
// =====================================================

test.describe('Nextcloud Password Decryption Fix', () => {
  test('connectNextcloud decrypts stored encrypted password', () => {
    const content = readFile('cloud_config.php');
    const connectFn = content.substring(
      content.indexOf('function connectNextcloud'),
      content.indexOf('}', content.indexOf("return [", content.indexOf('function connectNextcloud'))) + 1
    );
    // Should call decryptPassword on the password
    expect(connectFn).toContain('decryptPassword');
    expect(connectFn).toContain('function_exists');
  });

  test('connectNextcloud falls back to raw password if decryption returns empty', () => {
    const content = readFile('cloud_config.php');
    const connectFn = content.substring(
      content.indexOf('function connectNextcloud'),
      content.indexOf('}', content.indexOf("return [", content.indexOf('function connectNextcloud'))) + 1
    );
    // Should only use decrypted password if it's not empty
    expect(connectFn).toContain('!empty($decrypted)');
  });

  test('connectNextcloud still validates required settings', () => {
    const content = readFile('cloud_config.php');
    const connectFn = content.substring(
      content.indexOf('function connectNextcloud'),
      content.indexOf('}', content.indexOf("return [", content.indexOf('function connectNextcloud'))) + 1
    );
    expect(connectFn).toContain("empty($settings['nextcloud_url'])");
    expect(connectFn).toContain("empty($settings['nextcloud_username'])");
    expect(connectFn).toContain("empty($settings['nextcloud_password'])");
    expect(connectFn).toContain('Nextcloud settings are incomplete');
  });
});
