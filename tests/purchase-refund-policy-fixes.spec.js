import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Purchase & Refund Policy Fixes Tests
 * Tests for:
 * 1. Duplicate booking prevention for sessions
 * 2. Duplicate purchase prevention for camps/programs
 * 3. "Already Registered" UI state
 * 4. Calendar integration for camps/programs
 * 5. Credits/Refunds typeahead (all users, not just athletes)
 * 6. Staff registration management
 * 7. Cancellation/refund policies (48hr sessions, 14-day camps, per-session programs)
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Duplicate Booking Prevention - Sessions
// =====================================================

test.describe('Duplicate Booking Prevention - Sessions', () => {
  test('process_booking.php checks for existing bookings before creating new one', () => {
    const content = readFile('process_booking.php');
    // Should check for duplicate bookings
    expect(content).toContain('duplicate booking');
    expect(content).toContain("status IN ('confirmed', 'waitlisted')");
    expect(content).toContain("payment_status IN ('pending', 'paid')");
    expect(content).toContain('already_booked');
  });

  test('sessions_booking.php fetches user existing bookings for duplicate check', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('user_booked_sessions');
    expect(content).toContain("status IN ('confirmed', 'waitlisted')");
    expect(content).toContain("payment_status IN ('pending', 'paid')");
  });

  test('sessions_booking.php checks parent managed athletes bookings', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('managed_athletes');
    expect(content).toContain('parent');
    expect(content).toContain('child_booked_stmt');
  });

  test('sessions_booking.php shows Already Registered button for booked sessions', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('already_booked');
    expect(content).toContain('Already Registered');
    expect(content).toContain('fa-check-circle');
    expect(content).toContain('disabled');
  });

  test('sessions_booking.php counts only active bookings for registered count', () => {
    const content = readFile('views/sessions_booking.php');
    // The LEFT JOIN on bookings should filter by active statuses
    expect(content).toContain("b.status IN ('confirmed', 'waitlisted')");
  });
});

// =====================================================
// 2. Duplicate Purchase Prevention - Camps/Programs
// =====================================================

test.describe('Duplicate Purchase Prevention - Camps/Programs', () => {
  test('process_purchase_package.php checks for existing purchases before Stripe checkout', () => {
    const content = readFile('process_purchase_package.php');
    expect(content).toContain('duplicate');
    expect(content).toContain('user_packages');
    expect(content).toContain("payment_status IN ('pending', 'paid')");
    expect(content).toContain('already_purchased');
  });

  test('programs_camps.php fetches user purchased packages', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('purchased_package_ids');
    expect(content).toContain('user_packages');
    expect(content).toContain("payment_status IN ('pending', 'paid')");
  });

  test('programs_camps.php checks parent athletes packages too', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('check_user_ids');
    // Should include both user and their athletes
    expect(content).toContain('array_column($athletes');
  });

  test('programs_camps.php shows Already Registered for purchased packages', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('is_already_purchased');
    expect(content).toContain('Already Registered');
    expect(content).toContain('fa-check-circle');
  });
});

// =====================================================
// 3. Calendar Integration - Camps/Programs on Schedule
// =====================================================

test.describe('Calendar Integration - Camps/Programs', () => {
  test('sessions_upcoming.php fetches camp daily schedules for athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('camp_daily_schedules');
    expect(content).toContain("'camp_schedule' as source_type");
    expect(content).toContain("p.package_type = 'camp'");
  });

  test('sessions_upcoming.php fetches multi-week program dates for athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('multiweek_program_dates');
    expect(content).toContain("'program_schedule' as source_type");
    expect(content).toContain("p.package_type = 'multi_week'");
  });

  test('sessions_upcoming.php merges and sorts camp/program entries chronologically', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('camp_schedules');
    expect(content).toContain('mw_dates');
    expect(content).toContain('array_merge');
    expect(content).toContain('usort');
  });

  test('sessions_upcoming.php shows Camp/Program type badges', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain("source_type'] ?? ''");
    expect(content).toContain('camp_schedule');
    expect(content).toContain('program_schedule');
    expect(content).toContain('fa-campground');
    expect(content).toContain('Camp');
    expect(content).toContain('Program');
  });

  test('sessions_upcoming.php handles session_time for proper time display', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('session_time');
    expect(content).toContain('session_date_str');
  });

  test('sessions_upcoming.php shows Manage link for camp/program schedule entries', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('programs_camps');
    expect(content).toContain('package_id');
    expect(content).toContain('Manage');
  });

  test('payment_success.php distributes amount across session bookings', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('per_session_amount');
    expect(content).toContain('amount_per_athlete');
    expect(content).toContain('count($linked_session_ids)');
  });
});

// =====================================================
// 4. Credits/Refunds Typeahead - All Users
// =====================================================

test.describe('Credits/Refunds Typeahead', () => {
  test('accounting_credits.php uses typeahead input instead of static dropdown', () => {
    const content = readFile('views/accounting_credits.php');
    expect(content).toContain('credit-user-search');
    expect(content).toContain('credit-user-id');
    expect(content).toContain('credit-user-results');
    expect(content).toContain('ajax_search_users.php');
  });

  test('accounting_credits.php no longer restricts to athlete/parent roles', () => {
    const content = readFile('views/accounting_credits.php');
    // Should NOT contain the old role-restricted query
    expect(content).not.toContain("role IN ('athlete', 'parent') ORDER BY first_name");
  });

  test('accounting_credits.php has debounced typeahead with 300ms delay', () => {
    const content = readFile('views/accounting_credits.php');
    expect(content).toContain('setTimeout');
    expect(content).toContain('300');
    expect(content).toContain('searchTimeout');
  });

  test('accounting_credits.php clears typeahead state on modal close', () => {
    const content = readFile('views/accounting_credits.php');
    expect(content).toContain('credit-user-id');
    expect(content).toContain('credit-user-results');
    expect(content).toContain('closeModal');
  });

  test('PWA accounting_credits.php also uses typeahead', () => {
    const content = readFile('views/pwa/accounting_credits.php');
    expect(content).toContain('mCreditUser');
    expect(content).toContain('mCreditUserId');
    expect(content).toContain('mCreditUserResults');
    expect(content).toContain('ajax_search_users.php');
  });

  test('PWA accounting_credits.php no longer fetches limited user list', () => {
    const content = readFile('views/pwa/accounting_credits.php');
    expect(content).not.toContain("role IN ('athlete', 'parent') ORDER BY first_name, last_name LIMIT 500");
  });
});

// =====================================================
// 5. Staff Registration Management
// =====================================================

test.describe('Staff Registration Management', () => {
  test('process_packages.php has get_registrations endpoint', () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('get_registrations');
    expect(content).toContain('registered');
    expect(content).toContain('waitlisted');
  });

  test('process_packages.php has cancel_registration endpoint for staff', () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('cancel_registration');
    expect(content).toContain('staff_cancel_registration');
    expect(content).toContain('Stripe');
  });

  test('process_packages.php allows staff roles for registration management', () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('staff_roles');
    expect(content).toContain('coach');
    expect(content).toContain('front_desk_staff');
  });

  test('programs_camps.php shows staff registration management button', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('is_staff');
    expect(content).toContain('View Registrations');
    expect(content).toContain('viewRegistrations');
  });

  test('programs_camps.php has registration management modal for staff', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('registrations-modal');
    expect(content).toContain('reg-list-container');
    expect(content).toContain('loadRegistrations');
  });

  test('programs_camps.php supports email all registered users', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('Email All Registered Users');
    expect(content).toContain('mailto:');
  });

  test('programs_camps.php supports individual cancel and refund', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('cancelRegistration');
    expect(content).toContain('Cancel & Refund');
  });
});

// =====================================================
// 6. Cancellation/Refund Policies
// =====================================================

test.describe('Cancellation/Refund Policies', () => {
  test('Sessions: 48-hour cancellation policy', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain('48');
    expect(content).toContain('min_cancellation_hours');
    expect(content).toContain('refund_eligible');
  });

  test('Sessions: cancel button shows with 48-hour threshold', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain("+48 hours");
  });

  test('Sessions: cancellation confirm message mentions 48-hour policy', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('48 hours');
  });

  test('Camps: 14-day cancellation policy', () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('14');
    expect(content).toContain('days_until_camp');
    expect(content).toContain('camp_start_date');
    expect(content).toContain("Camp cancellations");
  });

  test('Programs: per-session refund with 48-hour cutoff', () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('refundable_sessions');
    expect(content).toContain('+48 hours');
    expect(content).toContain('per_session');
    expect(content).toContain('amount_paid');
  });

  test('process_packages.php has user self-service cancellation endpoint', () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('user_cancel_package');
    expect(content).toContain('ownership');
    expect(content).toContain('managed_athletes');
  });

  test('programs_camps.php shows cancellation policy info and cancel button', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('can_cancel');
    expect(content).toContain('cancel_note');
    expect(content).toContain('cancelPackageRegistration');
    expect(content).toContain('Cancel Registration');
  });

  test('programs_camps.php shows camp 14-day policy message', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('14 days');
    expect(content).toContain('cancellation deadline');
  });

  test('programs_camps.php shows program per-session refund message', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('48 hours');
    expect(content).toContain('refunded');
  });

  test('programs_camps.php cancelPackageRegistration JS function exists', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('function cancelPackageRegistration');
    expect(content).toContain('user_cancel_package');
    expect(content).toContain('process_packages.php');
  });
});
