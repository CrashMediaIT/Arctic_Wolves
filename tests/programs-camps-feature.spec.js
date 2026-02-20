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
// 2. Programs & Camps View Tests
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

  test('Dashboard routing includes programs_camps page', async () => {
    const dashboardContent = readFile('dashboard.php');
    expect(dashboardContent).toContain("'programs_camps'");
    expect(dashboardContent).toContain('views/programs_camps.php');
    expect(dashboardContent).toContain('page=programs_camps');
    expect(dashboardContent).toContain('fa-campground');
    expect(dashboardContent).toContain('Programs & Camps');
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
