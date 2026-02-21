import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Programs & Camps Feature Tests
 * Tests for:
 * 1. Email export "Table Not Found" bug fix
 * 2. Programs & Camps dedicated view
 * 3. Landing page camps/programs display
 * 4. Marketing email campaigns
 * 5. Database schema
 * 6. Registration flow
 * 7. Admin package management
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Email Export Bug Fix Tests
// =====================================================

test.describe('Email Export - Table Not Found Fix', () => {
  test('Export Emails link should have data-action=link attribute', async () => {
    const content = readFile('views/reports_user.php');
    expect(content).toContain('data-action="link"');
    expect(content).toContain('process_users_email_export.php');
  });

  test('JS app.js should not intercept links with data-action != export', async () => {
    const jsContent = readFile('js/app.js');
    expect(jsContent).toContain("this.tagName === 'A'");
    expect(jsContent).toContain('data-action');
    expect(jsContent).toContain('function exportTable');
  });
});

// =====================================================
// 2. Programs & Camps Integration Tests
// =====================================================

test.describe('Programs & Camps View', () => {
  test('programs_camps.php view file exists and has correct structure', async () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('Programs & Camps');
    expect(content).toContain("package_type IN ('camp', 'multi_week')");
    expect(content).toContain('process_purchase_package.php');
    expect(content).toContain('program-filter');
    expect(content).toContain('Register for Camp');
    expect(content).toContain('Enroll in Program');
    expect(content).toContain('camp_daily_schedules');
    expect(content).toContain('multiweek_program_dates');
    expect(content).toContain('camp_add_ons');
    expect(content).toContain('location');
    expect(content).toContain('fa-map-marker-alt');
    expect(content).toContain('Child Pickup Enabled');
    expect(content).toContain('enable_child_checkin');
  });

  test('Dashboard routing includes programs_camps page for backward compatibility', async () => {
    const dashboardContent = readFile('dashboard.php');
    // Route mapping should still exist for backward compatibility
    expect(dashboardContent).toContain("'programs_camps'");
    expect(dashboardContent).toContain('views/programs_camps.php');
  });

  test('Programs & Camps is NOT a main navigation item', async () => {
    const dashboardContent = readFile('dashboard.php');
    // The nav link should not exist - programs are accessed via booking page and products
    expect(dashboardContent).not.toContain('<a href="?page=programs_camps"');
  });
});

// =====================================================
// 2b. Booking Page - Programs & Camps Section
// =====================================================

test.describe('Booking Page - Programs & Camps Section', () => {
  test('Booking page separates regular packages from camp/program packages', async () => {
    const content = readFile('views/sessions_booking.php');
    // Regular packages exclude camps/programs
    expect(content).toContain("package_type NOT IN ('camp', 'multi_week')");
    // Camp/program packages fetched separately
    expect(content).toContain("package_type IN ('camp', 'multi_week')");
  });

  test('Booking page has Programs & Camps section heading', async () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('Programs & Camps');
    expect(content).toContain('programs-section');
    expect(content).toContain('fa-campground');
    expect(content).toContain('Register for Camp');
    expect(content).toContain('Enroll in Program');
  });

  test('Booking page shows camp daily schedules and multi-week dates', async () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('camp_daily_schedules');
    expect(content).toContain('multiweek_program_dates');
    expect(content).toContain('program-schedule-preview');
    expect(content).toContain('schedule-date-badge');
  });

  test('Booking page shows program details including pricing and tax', async () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('program-pricing');
    expect(content).toContain('program-price');
    expect(content).toContain('program-tax');
    expect(content).toContain('tax_rate');
    expect(content).toContain('process_purchase_package.php');
  });

  test('Booking page shows child pickup badge for enabled programs', async () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('enable_child_checkin');
    expect(content).toContain('Child Pickup Enabled');
  });
});

// =====================================================
// 2c. Products Page - Programs & Camps Tab
// =====================================================

test.describe('Products Page - Programs & Camps Tab', () => {
  test('Products page has Programs & Camps tab', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('programs_camps');
    expect(content).toContain('fa-campground');
    expect(content).toContain('Programs & Camps');
    expect(content).toContain('programs_camps-tab');
  });

  test('Products page fetches camp/multi_week packages separately', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain("package_type IN ('camp', 'multi_week')");
    expect(content).toContain('programPackages');
  });

  test('Products create package modal includes camp and multi_week types', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain("value=\"camp\"");
    expect(content).toContain("value=\"multi_week\"");
    expect(content).toContain('camp_start_date');
    expect(content).toContain('camp_end_date');
    expect(content).toContain('daily_start_time');
    expect(content).toContain('daily_end_time');
    expect(content).toContain('enable_child_checkin');
    expect(content).toContain('allow_individual_sessions');
  });

  test('Products page togglePackageTypeFields handles camp and multi_week', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('camp-fields-row');
    expect(content).toContain('multi-week-fields-row');
  });
});

// =====================================================
// 3. Landing Page Camps/Programs Display Tests
// =====================================================

test.describe('Landing Page - Camps & Programs Section', () => {
  test('sessions_public.php fetches camps and programs separately', async () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain('camps_programs');
    expect(content).toContain("package_type IN ('camp', 'multi_week')");
    expect(content).toContain("package_type NOT IN ('camp', 'multi_week')");
    expect(content).toContain('Camps & Programs');
    expect(content).toContain('Register for Camp');
    expect(content).toContain('Enroll Now');
    expect(content).toContain('Child pickup enabled');
    expect(content).toContain('Individual sessions available');
  });

  test('Landing page shows all active camps without requiring show_on_landing flag', async () => {
    const content = readFile('sessions_public.php');
    // Camps query should NOT require show_on_landing = 1 (all active camps show)
    const campsQuery = content.substring(content.indexOf('Fetch camps and multi-week programs'));
    const campsQueryEnd = campsQuery.substring(0, campsQuery.indexOf('fetchAll'));
    expect(campsQueryEnd).not.toContain('show_on_landing');
  });

  test('Landing page has distinct camps section with its own CSS classes', async () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain('camps-section');
    expect(content).toContain('camps-grid');
    expect(content).toContain('camp-card');
    expect(content).toContain('camp-badge');
    expect(content).toContain('camp-name');
    expect(content).toContain('camp-price');
    expect(content).toContain('camp-details');
    expect(content).toContain('camp-register-btn');
  });
});

// =====================================================
// 4. Marketing Email Campaign Tests
// =====================================================

test.describe('Marketing Email Campaigns', () => {
  test('process_send_marketing_email.php exists and has correct structure', async () => {
    const content = readFile('process_send_marketing_email.php');
    expect(content).toContain('checkCsrfToken');
    expect(content).toContain("user_role'] !== 'admin'");
    expect(content).toContain('send_campaign');
    expect(content).toContain('get_campaigns');
    expect(content).toContain('buildMarketingEmailBody');
    expect(content).toContain('include_child_pickup');
    expect(content).toContain('enable_child_checkin');
    expect(content).toContain('logEmailAttempt');
    expect(content).toContain('marketing_email_campaigns');
    expect(content).toContain('marketing_emails');
    expect(content).toContain('opted_in');
    expect(content).toContain('parents');
    expect(content).toContain('athletes');
  });

  test('Marketing page includes Email Campaigns tab', async () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('tab-email-campaigns');
    expect(content).toContain('Email Campaigns');
    expect(content).toContain('section-email-campaigns');
    expect(content).toContain('campaignForm');
    expect(content).toContain('sendCampaign');
    expect(content).toContain('campaignHistory');
    expect(content).toContain('loadCampaignHistory');
  });
});

// =====================================================
// 5. Database Schema Tests
// =====================================================

test.describe('Database Schema Updates', () => {
  test('Schema includes location field and marketing campaigns table', async () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('camp_daily_schedules');
    expect(schema).toContain('multiweek_program_dates');
    expect(schema).toContain('marketing_email_campaigns');
    expect(schema).toContain('include_child_pickup');
    expect(schema).toContain('show_on_landing');
  });

  test('Migration SQL file exists', async () => {
    const migration = readFile('deployment/sql/add_programs_camps_enhancements.sql');
    expect(migration).toContain('camp_daily_schedules');
    expect(migration).toContain('location');
    expect(migration).toContain('marketing_email_campaigns');
    expect(migration).toContain('show_on_landing');
  });
});

// =====================================================
// 6. Registration Flow Tests
// =====================================================

test.describe('Registration Intent Flow', () => {
  test('Login page redirects camp/program intents to programs_camps page', async () => {
    const loginContent = readFile('login.php');
    expect(loginContent).toContain('programs_camps');
    expect(loginContent).toContain("'camp', 'multi_week'");
    expect(loginContent).toContain('package_type');
  });

  test('Register page redirects camp/program intents to programs_camps page', async () => {
    const registerContent = readFile('register.php');
    expect(registerContent).toContain('programs_camps');
    expect(registerContent).toContain("'camp', 'multi_week'");
  });
});

// =====================================================
// 7. Admin Package Management Tests
// =====================================================

test.describe('Admin Package Management - Location Field', () => {
  test('Admin packages view includes location field for camps', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain('schedule_locations[]');
    expect(content).toContain('Arena / Venue');
    expect(content).toContain('program_locations[]');
    expect(content).toContain('s.location');
    expect(content).toContain('d.location');
  });

  test('Process packages handles location field', async () => {
    const content = readFile('process_packages.php');
    expect(content).toContain('schedule_locations');
    expect(content).toContain('program_locations');
    expect(content).toContain(', location)');
  });
});
