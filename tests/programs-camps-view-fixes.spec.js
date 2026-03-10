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

  test('process_purchase_package.php redirects back to booking page for already purchased', () => {
    const content = readFile('process_purchase_package.php');
    // Should redirect to booking page (programs_camps page was removed)
    expect(content).toContain('dashboard.php?page=booking');
    expect(content).not.toContain('dashboard.php?page=programs_camps');
  });

  test('programs_camps page is removed from dashboard routing', () => {
    const content = readFile('dashboard.php');
    expect(content).not.toContain("'programs_camps'");
  });

  test('sessions_booking.php shows Already Registered for purchased programs', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('booking_purchased_ids');
    expect(content).toContain('Already Registered');
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

  test('sessions_upcoming.php shows camp dates for all users not just athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    // Should not restrict to athlete role only
    expect(content).not.toContain("user_role === 'athlete' && !$show_history");
    // Should use a broader check
    expect(content).toContain('!$show_history');
    expect(content).toContain('camp_daily_schedules');
    expect(content).toContain('multiweek_program_dates');
  });

  test('sessions_upcoming.php does not link to programs_camps page', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).not.toContain('page=programs_camps');
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
    expect(content).toContain('view-program-registrations');
  });

  test('accounting_products.php has program registrations modal with email form', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('session-registrations-modal');
    expect(content).toContain('Registered Users');
    expect(content).toContain('viewProgramRegistrations');
    expect(content).toContain('sendRegisteredUsersEmail');
    expect(content).toContain('email_registered_users');
  });

  test('accounting_products.php has viewProgramRegistrations JavaScript function', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('function viewProgramRegistrations(packageId, packageName)');
  });

  test('accounting_products.php has view registered users button for sessions', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('view-session-registrations');
    expect(content).toContain('viewSessionRegistrations');
    expect(content).toContain('session-registrations-modal');
  });

  test('process_packages.php has get_session_registrations endpoint', () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('get_session_registrations');
    expect(content).toContain('session_template_id');
  });

  test('packages.php excludes camps and multi_week packages', () => {
    const content = readFile('views/packages.php');
    expect(content).toContain("NOT IN ('camp', 'multi_week')");
    // Should not have camp/multi_week filter buttons
    expect(content).not.toContain('data-type="camp"');
    expect(content).not.toContain('data-type="multi_week"');
  });
});

// =====================================================
// 4. Logger Timezone — no DB query, system TZ only
// =====================================================

test.describe('Logger Timezone — no DB override', () => {
  test('lib/logger.php has ensureTimezone method', () => {
    const content = readFile('lib/logger.php');
    expect(content).toContain('ensureTimezone');
  });

  test('lib/logger.php does not query DB for timezone', () => {
    const content = readFile('lib/logger.php');
    expect(content).not.toContain("setting_key = 'timezone'");
    expect(content).not.toContain('date_default_timezone_set');
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

  test('error_logger.php has ensureTimezone method', () => {
    const content = readFile('error_logger.php');
    expect(content).toContain('ensureTimezone');
  });

  test('error_logger.php does not query DB for timezone', () => {
    const content = readFile('error_logger.php');
    expect(content).not.toContain("setting_key = 'timezone'");
    expect(content).not.toContain('date_default_timezone_set');
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
