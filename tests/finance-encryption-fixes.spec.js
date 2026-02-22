import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. Finance dashboard includes bookings and user_packages in revenue
 * 2. All credentials encrypted on save with encryptPassword()
 * 3. All credentials decrypted on read with decryptCredential()
 * 4. setup.php skips SMTP if already configured
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Finance Dashboard Revenue Sources
// =====================================================

test.describe('Finance Dashboard Revenue Sources', () => {
  test('finance_overview.php includes bookings in revenue cards', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('booking_revenue');
    expect(content).toContain("FROM bookings");
    expect(content).toContain("payment_status = 'paid'");
  });

  test('finance_overview.php includes user_packages in revenue cards', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('package_revenue');
    expect(content).toContain("FROM user_packages");
  });

  test('finance_overview.php adds bookings and packages to total revenue', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('$bookingRevenue');
    expect(content).toContain('$packageRevenue');
    expect(content).toContain('$revenue += $shopRevenue + $posRevenue + $bookingRevenue + $packageRevenue');
  });

  test('finance_overview.php chart data includes bookings and packages', () => {
    const content = readFile('views/finance_overview.php');
    // Chart query should have 5 UNION ALL parts
    const chartSection = content.substring(
      content.indexOf('Get revenue data for chart'),
      content.indexOf('Get expense data for chart')
    );
    expect(chartSection).toContain('FROM bookings');
    expect(chartSection).toContain('FROM user_packages');
    // Should pass 5 parameters (one per UNION clause)
    expect(chartSection).toContain('$chartDays, $chartDays, $chartDays, $chartDays, $chartDays');
  });

  test('finance_overview.php year-over-year includes bookings and packages', () => {
    const content = readFile('views/finance_overview.php');
    const yoySection = content.substring(
      content.indexOf('Year-over-year data'),
      content.indexOf('Calculate projection')
    );
    expect(yoySection).toContain('FROM bookings');
    expect(yoySection).toContain('FROM user_packages');
    // Should pass 5 year parameters
    expect(yoySection).toContain('$currentYear, $currentYear, $currentYear, $currentYear, $currentYear');
    expect(yoySection).toContain('$lastYear, $lastYear, $lastYear, $lastYear, $lastYear');
  });
});

// =====================================================
// 2. Credential Encryption on Save
// =====================================================

test.describe('Credential Encryption on Save', () => {
  test('process_settings.php encrypts SMTP password', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'smtp_pass', encryptPassword($smtp_pass))");
  });

  test('process_settings.php encrypts Stripe keys', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'stripe_secret_key', encryptPassword($stripe_secret_key))");
    expect(content).toContain("updateSetting($pdo, 'stripe_publishable_key', encryptPassword($stripe_publishable_key))");
  });

  test('process_settings.php encrypts DocuSeal API key', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'docuseal_api_key', encryptPassword($docuseal_api_key))");
  });

  test('process_settings.php encrypts Stallion Express keys', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'stallion_api_key', encryptPassword($stallion_api_key))");
    expect(content).toContain("updateSetting($pdo, 'stallion_api_secret', encryptPassword($stallion_api_secret))");
  });

  test('process_settings.php encrypts GitHub token', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'github_token', encryptPassword($github_token))");
  });

  test('process_settings.php encrypts Google Maps API key', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("updateSetting($pdo, 'google_maps_api_key', encryptPassword($api_key))");
  });

  test('process_admin_action.php encrypts Stripe keys in billing update', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('encryptPassword');
    expect(content).toContain("'stripe_publishable_key', 'stripe_secret_key'");
  });

  test('setup.php encrypts SMTP password', () => {
    const content = readFile('setup.php');
    expect(content).toContain('encryptPassword');
    expect(content).toContain('encrypted_smtp_pass');
  });
});

// =====================================================
// 3. Credential Decryption on Read
// =====================================================

test.describe('Credential Decryption on Read', () => {
  const stripFiles = [
    'views/finance_overview.php',
    'views/finance_billing.php',
    'dashboard_kiosk.php',
    'views/pos_terminal.php',
  ];

  for (const file of stripFiles) {
    test(`${file} decrypts Stripe keys`, () => {
      const content = readFile(file);
      expect(content).toContain('decryptCredential');
      expect(content).toContain("stripe_secret_key");
    });
  }

  test('mailer.php decrypts SMTP password', () => {
    const content = readFile('mailer.php');
    expect(content).toContain('decryptCredential');
    expect(content).toContain('smtp_pass');
  });

  test('lib/github_updater.php decrypts GitHub token', () => {
    const content = readFile('lib/github_updater.php');
    expect(content).toContain('decryptCredential');
  });

  test('lib/docuseal.php decrypts DocuSeal API key', () => {
    const content = readFile('lib/docuseal.php');
    expect(content).toContain('decryptCredential');
    expect(content).toContain('docuseal_api_key');
  });

  test('lib/stallion_express.php decrypts Stallion Express keys', () => {
    const content = readFile('lib/stallion_express.php');
    expect(content).toContain('decryptCredential');
    expect(content).toContain('stallion_api_key');
    expect(content).toContain('stallion_api_secret');
  });

  test('process_booking.php decrypts Stripe key', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain('decryptCredential');
  });

  test('payment_success.php decrypts Stripe key', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('decryptCredential');
  });

  test('process_refunds.php decrypts Stripe key', () => {
    const content = readFile('process_refunds.php');
    expect(content).toContain('decryptCredential');
  });

  test('process_reports.php decrypts Stripe key', () => {
    const content = readFile('process_reports.php');
    expect(content).toContain('decryptCredential');
  });
});

// =====================================================
// 4. security.php - decryptCredential helper
// =====================================================

test.describe('decryptCredential helper', () => {
  test('security.php defines decryptCredential function', () => {
    const content = readFile('security.php');
    expect(content).toContain('function decryptCredential($value)');
  });

  test('decryptCredential returns empty for empty value', () => {
    const content = readFile('security.php');
    const fnBody = content.substring(
      content.indexOf('function decryptCredential'),
      content.indexOf('}', content.indexOf("return $value;", content.indexOf('function decryptCredential'))) + 1
    );
    expect(fnBody).toContain("if (empty($value))");
    expect(fnBody).toContain("return ''");
  });

  test('decryptCredential falls back to raw value if decryption fails', () => {
    const content = readFile('security.php');
    const fnBody = content.substring(
      content.indexOf('function decryptCredential'),
      content.indexOf('}', content.indexOf("return $value;", content.indexOf('function decryptCredential'))) + 1
    );
    expect(fnBody).toContain("return $value");
    expect(fnBody).toContain("decryptPassword");
  });
});

// =====================================================
// 5. setup.php skip SMTP if already configured
// =====================================================

test.describe('Setup.php SMTP skip', () => {
  test('setup.php checks if SMTP already configured', () => {
    const content = readFile('setup.php');
    const smtpSection = content.substring(
      content.indexOf('SMTP Configuration'),
      content.indexOf('smtp_from_name')
    );
    expect(smtpSection).toContain("smtp_host");
    expect(smtpSection).toContain("existing_smtp");
    expect(smtpSection).toContain("Location: setup.php?step=5");
  });
});
