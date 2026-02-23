import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Programs/Camps View Fixes Tests
 * Tests for:
 * 1. Already registered redirect no longer creates error view URL
 * 2. Upcoming sessions display camp/program day titles
 * 3. Products view shows registered users for programs/camps
 * 4. Logger timezone loaded from system settings
 * 5. Nextcloud folder paths use configured settings (no hardcoded /Arctic_Wolves/)
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Already Registered - No Error View URL
// =====================================================

test.describe('Already Registered - Inline Display', () => {
  test('process_purchase_package.php does not redirect to error=already_purchased URL', () => {
    const content = readFile('process_purchase_package.php');
    // Should NOT have the error=already_purchased redirect
    expect(content).not.toContain('error=already_purchased');
  });

  test('process_purchase_package.php redirects back to programs_camps page with package_id only', () => {
    const content = readFile('process_purchase_package.php');
    // Should redirect to programs_camps with just the package_id
    expect(content).toContain('dashboard.php?page=programs_camps&package_id=');
    expect(content).toContain('Already Registered');
  });

  test('programs_camps.php already shows inline Already Registered button', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('is_already_purchased');
    expect(content).toContain('Already Registered');
    expect(content).toContain('fa-check-circle');
  });
});

// =====================================================
// 2. Upcoming Sessions - Camp/Program Day Titles
// =====================================================

test.describe('Upcoming Sessions - Camp/Program Day Titles', () => {
  test('sessions_upcoming.php displays camp_day_title when available', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('camp_day_title');
    // The camp_day_title should be shown in a tag
    expect(content).toContain("session['camp_day_title']");
  });

  test('sessions_upcoming.php displays program_day_title when available', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('program_day_title');
    // The program_day_title should be shown in a tag
    expect(content).toContain("session['program_day_title']");
  });

  test('sessions_upcoming.php still shows Camp and Program type badges', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('fa-campground');
    expect(content).toContain('fa-calendar-alt');
    expect(content).toContain('camp_schedule');
    expect(content).toContain('program_schedule');
  });
});

// =====================================================
// 3. Products View - Registered Users
// =====================================================

test.describe('Products View - Registered Users', () => {
  test('accounting_products.php fetches registered user count for programs', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('registered_count');
    expect(content).toContain("up.payment_status = 'paid'");
  });

  test('accounting_products.php fetches registered user details for programs', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('programRegistrations');
    expect(content).toContain('user_packages up');
    expect(content).toContain('JOIN users u ON up.user_id = u.id');
  });

  test('accounting_products.php has Registered column in programs table', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('<th>Registered</th>');
    expect(content).toContain('toggleRegistrationList');
  });

  test('accounting_products.php has expandable registration list rows', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('registration-list-row');
    expect(content).toContain('reg-list-');
    expect(content).toContain('Registered Users');
  });

  test('accounting_products.php has toggleRegistrationList JavaScript function', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('function toggleRegistrationList(packageId)');
  });
});

// =====================================================
// 4. Logger Timezone from System Settings
// =====================================================

test.describe('Logger Timezone from System Settings', () => {
  test('lib/logger.php loads timezone from system_settings', () => {
    const content = readFile('lib/logger.php');
    expect(content).toContain('ensureTimezone');
    expect(content).toContain('system_settings');
    expect(content).toContain('timezone');
    expect(content).toContain('date_default_timezone_set');
  });

  test('lib/logger.php calls ensureTimezone before logging', () => {
    const content = readFile('lib/logger.php');
    // The core log function should call ensureTimezone
    const logFn = content.substring(
      content.indexOf('private static function log('),
      content.indexOf('return $result !== false;')
    );
    expect(logFn).toContain('ensureTimezone');
  });

  test('error_logger.php loads timezone from system_settings', () => {
    const content = readFile('error_logger.php');
    expect(content).toContain('ensureTimezone');
    expect(content).toContain('system_settings');
    expect(content).toContain('timezone');
    expect(content).toContain('date_default_timezone_set');
  });

  test('error_logger.php sets timezone when database connection is set', () => {
    const content = readFile('error_logger.php');
    // setDatabase should trigger timezone loading
    const setDbFn = content.substring(
      content.indexOf('function setDatabase('),
      content.indexOf('}', content.indexOf('function setDatabase(') + 50)
    );
    expect(setDbFn).toContain('ensureTimezone');
  });
});

// =====================================================
// 5. Nextcloud Folder Paths - No Hardcoded /Arctic_Wolves/
// =====================================================

test.describe('Nextcloud Folder Paths - Configurable', () => {
  test('cloud_config.php does not hardcode /Arctic_Wolves/ in fallback paths', () => {
    const content = readFile('cloud_config.php');
    // Should not contain /Arctic_Wolves/ as fallback defaults
    expect(content).not.toContain("'/Arctic_Wolves/");
  });

  test('process_settings.php does not hardcode /Arctic_Wolves/ in fallback paths', () => {
    const content = readFile('process_settings.php');
    // Should not contain /Arctic_Wolves/ as fallback defaults
    expect(content).not.toContain("'/Arctic_Wolves/");
  });

  test('process_payroll.php does not hardcode /Arctic_Wolves/ in fallback paths', () => {
    const content = readFile('process_payroll.php');
    expect(content).not.toContain("'/Arctic_Wolves/");
  });

  test('process_expenses.php does not hardcode /Arctic_Wolves/ in fallback paths', () => {
    const content = readFile('process_expenses.php');
    expect(content).not.toContain("'/Arctic_Wolves/");
  });

  test('process_onboarding.php does not hardcode /Arctic_Wolves/ in fallback paths', () => {
    const content = readFile('process_onboarding.php');
    expect(content).not.toContain("'/Arctic_Wolves/");
  });

  test('process_database_backup.php does not hardcode /Arctic_Wolves/ in fallback paths', () => {
    const content = readFile('process_database_backup.php');
    expect(content).not.toContain("'/Arctic_Wolves/");
  });

  test('admin_system_tools.php does not hardcode /Arctic_Wolves/ in form defaults', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).not.toContain("'/Arctic_Wolves/");
    expect(content).not.toContain('"/Arctic_Wolves/');
  });

  test('cloud_config.php uses generic fallback paths', () => {
    const content = readFile('cloud_config.php');
    // Should use generic fallbacks
    expect(content).toContain("'/HR/Terminations'");
    expect(content).toContain("'/DrillVideos'");
  });
});
