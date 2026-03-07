import { test, expect } from '@playwright/test';
import { readFileSync, existsSync } from 'fs';
import { join } from 'path';

/**
 * Personal Development Feature Tests
 * Tests for:
 * 1. Database schema - new roles, tables, session types
 * 2. Navigation - Personal Development in Main Menu, Development Programs in Coaches Corner
 * 3. Route parity across dashboard.php, pwa.php, pwa_tablet.php
 * 4. View files existence and structure
 * 5. Role definitions and access control
 * 6. Personal Drills tab in Drills view
 * 7. AJAX processor
 * 8. Admin Users role management
 */

const ROOT = join(__dirname, '..');

function readFile(relativePath) {
  return readFileSync(join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Database Schema Tests
// =====================================================

test.describe('Database Schema - Personal Development', () => {

  test('user_roles ENUM includes goalie_dev and player_dev', () => {
    const schema = readFile('database_schema.sql');
    // Check user_roles table specifically
    expect(schema).toContain("'goalie_dev'");
    expect(schema).toContain("'player_dev'");
  });

  test('development_program_enrollments table exists', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `development_program_enrollments`');
    expect(schema).toContain("'goalie_dev', 'player_dev'");
    expect(schema).toContain('athlete_id');
    expect(schema).toContain('program_type');
  });

  test('development_program_drills table exists', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `development_program_drills`');
    expect(schema).toContain('enrollment_id');
    expect(schema).toContain('drill_id');
    expect(schema).toContain('assigned_by');
    expect(schema).toContain('coach_notes');
  });

  test('development_program_messages table exists', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `development_program_messages`');
    expect(schema).toContain('sender_id');
    expect(schema).toContain('video_url');
  });

  test('personal_drills table exists', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `personal_drills`');
    expect(schema).toContain('video_url');
  });

  test('development_notification_templates table exists', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `development_notification_templates`');
    expect(schema).toContain('program_type');
    expect(schema).toContain('subject');
    expect(schema).toContain('body');
  });

  test('session types for Long Term Goalie and Player Development are inserted', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('Long Term Goalie Development');
    expect(schema).toContain('Long Term Player Development');
  });

  test('default notification templates are inserted', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain("INSERT IGNORE INTO `development_notification_templates`");
    expect(schema).toContain('New Goalie Development Program Registration');
    expect(schema).toContain('New Player Development Program Registration');
  });
});

// =====================================================
// 2. View Files Tests
// =====================================================

test.describe('Personal Development View Files', () => {

  test('views/personal_development.php exists and has correct structure', () => {
    const content = readFile('views/personal_development.php');
    expect(content).toContain('Personal Development');
    expect(content).toContain('personal_development_programs');
    expect(content).toContain('personal_development_my_program');
    expect(content).toContain('page-tabs');
    expect(content).toContain('Development Programs');
    expect(content).toContain('My Program');
  });

  test('views/personal_development_programs.php exists with registration functionality', () => {
    const content = readFile('views/personal_development_programs.php');
    expect(content).toContain('Long Term Goalie Development');
    expect(content).toContain('Long Term Player Development');
    expect(content).toContain('registerDevProgram');
    expect(content).toContain('process_development_programs.php');
    expect(content).toContain('goalie_dev');
    expect(content).toContain('player_dev');
  });

  test('views/personal_development_my_program.php exists with drill and chat features', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('development_program_enrollments');
    expect(content).toContain('development_program_drills');
    expect(content).toContain('development_program_messages');
    expect(content).toContain('sendDevMessage');
    expect(content).toContain('Program Chat');
  });

  test('views/development_programs.php exists with coach management features', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('Development Programs');
    expect(content).toContain('goalie_dev');
    expect(content).toContain('player_dev');
    expect(content).toContain('addDrill');
    expect(content).toContain('removeDrill');
    expect(content).toContain('sendCoachMessage');
    expect(content).toContain('sendCoachVideo');
    expect(content).toContain('Enrolled Athletes');
    expect(content).toContain('drill-select');
  });

  test('views/drills_personal.php exists with personal drill creation', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('Personal Drill');
    expect(content).toContain('personal_drills');
    expect(content).toContain('video_url');
    expect(content).toContain('create_personal_drill');
    expect(content).toContain('process_development_programs.php');
  });

  test('views/drills.php includes Personal Drills tab', () => {
    const content = readFile('views/drills.php');
    expect(content).toContain('personal_drills');
    expect(content).toContain('Personal Drills');
    expect(content).toContain('drills_personal.php');
  });
});

// =====================================================
// 3. PWA View Wrappers
// =====================================================

test.describe('PWA View Wrappers', () => {

  test('PWA wrapper exists for personal_development', () => {
    expect(existsSync(join(ROOT, 'views/pwa/personal_development.php'))).toBe(true);
  });

  test('PWA wrapper exists for personal_development_programs', () => {
    expect(existsSync(join(ROOT, 'views/pwa/personal_development_programs.php'))).toBe(true);
  });

  test('PWA wrapper exists for personal_development_my_program', () => {
    expect(existsSync(join(ROOT, 'views/pwa/personal_development_my_program.php'))).toBe(true);
  });

  test('PWA wrapper exists for development_programs', () => {
    expect(existsSync(join(ROOT, 'views/pwa/development_programs.php'))).toBe(true);
  });

  test('PWA wrapper exists for personal_drills', () => {
    expect(existsSync(join(ROOT, 'views/pwa/personal_drills.php'))).toBe(true);
  });
});

// =====================================================
// 4. Route Parity Tests
// =====================================================

test.describe('Route Parity - Personal Development', () => {

  const devRoutes = [
    'personal_development',
    'personal_development_programs',
    'personal_development_my_program',
    'development_programs',
    'personal_drills'
  ];

  test('dashboard.php has all personal development routes', () => {
    const content = readFile('dashboard.php');
    for (const route of devRoutes) {
      expect(content, `dashboard.php missing route: ${route}`).toContain(`'${route}'`);
    }
  });

  test('pwa.php has all personal development routes', () => {
    const content = readFile('pwa.php');
    for (const route of devRoutes) {
      expect(content, `pwa.php missing route: ${route}`).toContain(`'${route}'`);
    }
  });

  test('pwa_tablet.php has all personal development routes', () => {
    const content = readFile('pwa_tablet.php');
    for (const route of devRoutes) {
      expect(content, `pwa_tablet.php missing route: ${route}`).toContain(`'${route}'`);
    }
  });
});

// =====================================================
// 5. Role Variable Tests
// =====================================================

test.describe('Role Variables - Personal Development', () => {

  test('dashboard.php defines isGoalieDev, isPlayerDev, canAccessDevPrograms', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('$isGoalieDev');
    expect(content).toContain('$isPlayerDev');
    expect(content).toContain('$canAccessDevPrograms');
  });

  test('pwa.php defines isGoalieDev, isPlayerDev, canAccessDevPrograms', () => {
    const content = readFile('pwa.php');
    expect(content).toContain('$isGoalieDev');
    expect(content).toContain('$isPlayerDev');
    expect(content).toContain('$canAccessDevPrograms');
  });

  test('pwa_tablet.php defines isGoalieDev, isPlayerDev, canAccessDevPrograms', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain('$isGoalieDev');
    expect(content).toContain('$isPlayerDev');
    expect(content).toContain('$canAccessDevPrograms');
  });
});

// =====================================================
// 6. Navigation Tests
// =====================================================

test.describe('Navigation - Personal Development', () => {

  test('dashboard.php main menu has Personal Development link', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('page=personal_development');
    expect(content).toContain('Personal Development');
    expect(content).toContain('fa-hockey-puck');
  });

  test('pwa_tablet.php main menu has Personal Development link', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain('page=personal_development');
    expect(content).toContain('Personal Development');
  });

  test('pwa_more_menu.php has Personal Development link', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toContain('page=personal_development');
    expect(content).toContain('Personal Development');
  });

  test('dashboard.php Coaches Corner has Development Programs link (conditional)', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('page=development_programs');
    expect(content).toContain('canAccessDevPrograms');
  });

  test('pwa_tablet.php Coaches Corner has Development Programs link (conditional)', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain('page=development_programs');
    expect(content).toContain('canAccessDevPrograms');
  });

  test('pwa_more_menu.php Coaches Corner has Development Programs link (conditional)', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).toContain('page=development_programs');
    expect(content).toContain('canAccessDevPrograms');
  });

  test('dashboard.php drills active state includes personal_drills', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain("'personal_drills'");
    // Verify it's in the drills active check
    const drillsNavMatch = content.match(/drills.*?drill_library.*?create_drill.*?personal_drills/s);
    expect(drillsNavMatch).not.toBeNull();
  });

  test('pwa_tablet.php drills active state includes personal_drills', () => {
    const content = readFile('pwa_tablet.php');
    const drillsNavMatch = content.match(/drills.*?drill_library.*?create_drill.*?personal_drills/s);
    expect(drillsNavMatch).not.toBeNull();
  });
});

// =====================================================
// 7. AJAX Processor Tests
// =====================================================

test.describe('AJAX Processor - Development Programs', () => {

  test('process_development_programs.php exists and has correct structure', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('session_start');
    expect(content).toContain('db_config.php');
    expect(content).toContain('security.php');
    expect(content).toContain('X_REQUESTED_WITH');
    expect(content).toContain('application/json');
  });

  test('process_development_programs.php handles register action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'register'");
    expect(content).toContain('handleRegister');
    expect(content).toContain('development_program_enrollments');
    expect(content).toContain('development_notification_templates');
  });

  test('process_development_programs.php handles add_drill action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'add_drill'");
    expect(content).toContain('handleAddDrill');
    expect(content).toContain('development_program_drills');
  });

  test('process_development_programs.php handles remove_drill action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'remove_drill'");
    expect(content).toContain('handleRemoveDrill');
  });

  test('process_development_programs.php handles send_message action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'send_message'");
    expect(content).toContain('handleSendMessage');
    expect(content).toContain('development_program_messages');
  });

  test('process_development_programs.php handles create_personal_drill action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'create_personal_drill'");
    expect(content).toContain('handleCreatePersonalDrill');
    expect(content).toContain('personal_drills');
    // Also adds to main drill library
    expect(content).toContain("INSERT INTO drills");
  });

  test('process_development_programs.php sends notifications on registration', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('notifications');
    expect(content).toContain('dev_program_registration');
    expect(content).toContain('development_notification_templates');
  });

  test('process_development_programs.php has access control for coach actions', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('canManageDevPrograms');
    expect(content).toContain('goalie_dev');
    expect(content).toContain('player_dev');
    expect(content).toContain('Access denied');
  });
});

// =====================================================
// 8. Admin Users - Role Management Tests
// =====================================================

test.describe('Admin Users - Goalie Dev / Player Dev Roles', () => {

  test('admin_users.php role filter includes goalie_dev and player_dev', () => {
    const content = readFile('views/admin_users.php');
    expect(content).toContain("value=\"goalie_dev\"");
    expect(content).toContain("value=\"player_dev\"");
    expect(content).toContain('Goalie Dev');
    expect(content).toContain('Player Dev');
  });

  test('admin_users.php extra roles checkboxes include goalie_dev and player_dev', () => {
    const content = readFile('views/admin_users.php');
    expect(content).toContain('edit-role-goalie_dev');
    expect(content).toContain('edit-role-player_dev');
    expect(content).toContain('Goalie development programs');
    expect(content).toContain('Player development programs');
  });

  test('admin_users.php JavaScript allRoleCheckboxes includes new roles', () => {
    const content = readFile('views/admin_users.php');
    expect(content).toContain("'goalie_dev'");
    expect(content).toContain("'player_dev'");
  });

  test('admin_users.php role badge styling includes new roles', () => {
    const content = readFile('views/admin_users.php');
    expect(content).toContain('.role-badge.goalie_dev');
    expect(content).toContain('.role-badge.player_dev');
  });

  test('admin_users.php roleLabel switch includes new roles', () => {
    const content = readFile('views/admin_users.php');
    expect(content).toContain("case 'goalie_dev'");
    expect(content).toContain("case 'player_dev'");
  });
});

// =====================================================
// 9. Development Programs Access Control
// =====================================================

test.describe('Development Programs Access Control', () => {

  test('development_programs.php restricts access to dev roles and admin', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('goalie_dev');
    expect(content).toContain('player_dev');
    expect(content).toContain('admin');
    expect(content).toContain('Access Denied');
  });

  test('development_programs.php shows enrolled athletes', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('development_program_enrollments');
    expect(content).toContain('Enrolled Athletes');
  });

  test('development_programs.php allows drill management from library', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('drill-select');
    expect(content).toContain('Add Drill from Library');
    expect(content).toContain('Assigned Drills');
  });

  test('development_programs.php has communication features', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('Communication');
    expect(content).toContain('coach-msg-input');
    expect(content).toContain('coach-video-url');
    expect(content).toContain('Send Video');
  });
});

// =====================================================
// 10. Marketing View - Notification Templates
// =====================================================

test.describe('Marketing View - Development Notifications', () => {

  test('marketing view has Development Notifications tab', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('dev-notifications');
    expect(content).toContain('Development Notifications');
  });

  test('marketing view has notification template editing form', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('development_notification_templates');
    expect(content).toContain('saveDevNotificationTemplate');
    expect(content).toContain('dev-tmpl-subject-');
    expect(content).toContain('dev-tmpl-body-');
  });

  test('process_development_programs.php handles update_notification_template action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'update_notification_template'");
    expect(content).toContain('handleUpdateNotificationTemplate');
    expect(content).toContain('UPDATE development_notification_templates');
  });
});
