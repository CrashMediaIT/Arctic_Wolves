import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Multi-Coach Assignment Feature Tests
 * Tests for:
 * 1. Package-level coach assignment (package_coaches table)
 * 2. Per-date coach assignment for camps and multi-week programs
 * 3. Edit functionality for coach assignment
 * 4. Database schema changes
 * 5. Backend processing
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Database Schema - Package Coaches
// =====================================================

test.describe('Database Schema - Package Coaches', () => {
  test('Schema includes package_coaches table', async () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('package_coaches');
    expect(schema).toContain('unique_package_coach');
  });

  test('Schema includes coach_ids column in camp_daily_schedules', async () => {
    const schema = readFile('database_schema.sql');
    // camp_daily_schedules should have coach_ids column
    const campTable = schema.substring(schema.indexOf('camp_daily_schedules'));
    expect(campTable).toContain('coach_ids');
  });

  test('Schema includes coach_ids column in multiweek_program_dates', async () => {
    const schema = readFile('database_schema.sql');
    // multiweek_program_dates should have coach_ids column
    const mwTable = schema.substring(schema.indexOf('multiweek_program_dates'));
    expect(mwTable).toContain('coach_ids');
  });

  test('Migration SQL file exists for package coaches', async () => {
    const migration = readFile('deployment/sql/add_package_coaches.sql');
    expect(migration).toContain('package_coaches');
    expect(migration).toContain('coach_ids');
    expect(migration).toContain('camp_daily_schedules');
    expect(migration).toContain('multiweek_program_dates');
  });
});

// =====================================================
// 2. Admin Packages View - Coach Assignment
// =====================================================

test.describe('Admin Packages View - Coach Assignment', () => {
  test('Admin packages fetches coaches from database', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain("role IN ('coach', 'health_coach', 'admin')");
    expect(content).toContain('pkg_coaches');
  });

  test('Admin packages has package-level coach section', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain('packageCoachesSection');
    expect(content).toContain('Assign Coaches');
    expect(content).toContain('coach_ids[]');
    expect(content).toContain('pkg-coach-cb');
  });

  test('Coach section is shown for camp and multi_week types', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain("coachesSection.style.display = 'block'");
  });

  test('ArcticCalendar date entries include per-date coach checkboxes', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain('buildDateCoachCheckboxes');
    expect(content).toContain('pkgCoachesData');
    expect(content).toContain('[coach_ids][]');
  });

  test('Edit function loads package coaches via AJAX', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain('loadPackageCoaches');
    expect(content).toContain('get_package_coaches');
  });

  test('Existing dates load with coach assignments when editing', async () => {
    const content = readFile('views/admin_packages.php');
    // addExistingDate should accept coachIds parameter
    expect(content).toContain('addExistingDate = function(dateStr, startTime, endTime, location, coachIds)');
    expect(content).toContain('s.coach_ids');
    expect(content).toContain('d.coach_ids');
  });

  test('Coach checkboxes are cleared when opening create modal', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain("querySelectorAll('.pkg-coach-cb').forEach");
  });

  test('Package type dropdown includes camp and multi_week options', async () => {
    const content = readFile('views/admin_packages.php');
    expect(content).toContain('value="camp"');
    expect(content).toContain('value="multi_week"');
  });
});

// =====================================================
// 3. Products Page - Coach Assignment
// =====================================================

test.describe('Products Page - Coach Assignment', () => {
  test('Products create program modal has coach assignment section', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('Assign Coaches');
    expect(content).toContain('program-coach-cb');
    expect(content).toContain('coach_ids[]');
  });

  test('Products page has per-date coach checkboxes in calendar entries', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('buildCalDateCoachCheckboxes');
    expect(content).toContain('sessionCalCoaches');
    expect(content).toContain('showCoaches');
  });

  test('Products edit package modal includes coach assignment for camps/programs', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('pkgCoachCheckboxes');
    expect(content).toContain('coach_ids_list');
    expect(content).toContain('isCampOrProgram');
  });

  test('Edit package modal includes camp and multi_week in package type options', async () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain("value=\"camp\"");
    expect(content).toContain("value=\"multi_week\"");
  });
});

// =====================================================
// 4. Backend Processing - Coach Assignment
// =====================================================

test.describe('Backend Processing - Coach Assignment', () => {
  test('Process packages saves package-level coaches on create', async () => {
    const content = readFile('process_packages.php');
    expect(content).toContain("INSERT INTO package_coaches");
    expect(content).toContain("coach_ids");
  });

  test('Process packages saves per-date coach_ids for camp schedules', async () => {
    const content = readFile('process_packages.php');
    // Camp daily schedules INSERT should include coach_ids
    expect(content).toContain("camp_daily_schedules (package_id, schedule_date, start_time, end_time, title, description, location, coach_ids)");
  });

  test('Process packages saves per-date coach_ids for multi-week dates', async () => {
    const content = readFile('process_packages.php');
    // Multi-week program dates INSERT should include coach_ids
    expect(content).toContain("multiweek_program_dates (package_id, session_date, start_time, end_time, title, individual_price, auto_session_id, location, coach_ids)");
  });

  test('Process packages has AJAX endpoint for getting package coaches', async () => {
    const content = readFile('process_packages.php');
    expect(content).toContain("get_package_coaches");
    expect(content).toContain("SELECT coach_id FROM package_coaches");
  });

  test('Process packages updates coaches on update action', async () => {
    const content = readFile('process_packages.php');
    expect(content).toContain("DELETE FROM package_coaches WHERE package_id");
  });

  test('Auto-created sessions get coach assignments', async () => {
    const content = readFile('process_packages.php');
    expect(content).toContain("INSERT INTO session_coaches (session_id, coach_id)");
  });

  test('Process admin action fetches package coaches for edit', async () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain("SELECT coach_id FROM package_coaches WHERE package_id");
    expect(content).toContain("coach_ids_list");
  });

  test('Process admin action saves coaches on package update', async () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain("DELETE FROM package_coaches WHERE package_id");
    expect(content).toContain("INSERT INTO package_coaches (package_id, coach_id)");
  });
});
