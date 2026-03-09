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
    expect(schema).toContain('notification_email');
    expect(schema).toContain('program_duration_weeks');
  });

  test('session types for Long Term Goalie and Player Development are inserted', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('Long Term Goalie Development');
    expect(schema).toContain('Long Term Player Development');
  });

  test('default notification templates are inserted', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain("INSERT IGNORE INTO `development_notification_templates`");
    expect(schema).toContain('Welcome to the Goalie Development Program!');
    expect(schema).toContain('Welcome to the Player Development Program!');
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

  test('views/personal_development_programs.php shows dynamic dev program products', () => {
    const content = readFile('views/personal_development_programs.php');
    expect(content).toContain('is_dev_program');
    expect(content).toContain('training_session_templates');
    expect(content).toContain('page=booking');
    expect(content).toContain('goalie_dev');
    expect(content).toContain('player_dev');
    expect(content).toContain('dev_products');
    expect(content).toContain('Completed Programs');
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
    expect(content).toContain('Active Programs');
    expect(content).toContain('Program History');
    expect(content).toContain('drill-select');
  });

  test('views/drills_personal.php exists with personal drill creation', () => {
    const content = readFile('views/drills_personal.php');
    expect(content).toContain('Personal Drill');
    expect(content).toContain('personal_drills');
    expect(content).toContain('video_file');
    expect(content).toContain('video_upload_path');
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

  test('dashboard.php Personal Development link is conditional on canAccessDevPrograms', () => {
    const content = readFile('dashboard.php');
    // The Personal Development menu link should be wrapped in canAccessDevPrograms check
    const devLinkIdx = content.indexOf('page=personal_development');
    const conditionBefore = content.lastIndexOf('canAccessDevPrograms', devLinkIdx);
    expect(conditionBefore).toBeGreaterThan(-1);
    expect(devLinkIdx - conditionBefore).toBeLessThan(200);
  });

  test('pwa_tablet.php Personal Development link is conditional on canAccessDevPrograms', () => {
    const content = readFile('pwa_tablet.php');
    const devLinkIdx = content.indexOf('page=personal_development');
    const conditionBefore = content.lastIndexOf('canAccessDevPrograms', devLinkIdx);
    expect(conditionBefore).toBeGreaterThan(-1);
    expect(devLinkIdx - conditionBefore).toBeLessThan(200);
  });

  test('pwa_more_menu.php Personal Development link is conditional on canAccessDevPrograms', () => {
    const content = readFile('pwa_more_menu.php');
    const devLinkIdx = content.indexOf('page=personal_development');
    const conditionBefore = content.lastIndexOf('canAccessDevPrograms', devLinkIdx);
    expect(conditionBefore).toBeGreaterThan(-1);
    expect(devLinkIdx - conditionBefore).toBeLessThan(200);
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

  test('development_programs.php shows active programs and history tabs', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('development_program_enrollments');
    expect(content).toContain('Active Programs');
    expect(content).toContain('Program History');
    expect(content).toContain('dev-active-cards');
    expect(content).toContain('dev-history-table');
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

  test('marketing view auto-creates default notification templates if empty', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('INSERT IGNORE INTO `development_notification_templates`');
    expect(content).toContain('Welcome to the Goalie Development Program!');
    expect(content).toContain('Welcome to the Player Development Program!');
    // Should not reference a non-existent migration process
    expect(content).not.toContain('Run the database migration');
  });

  test('marketing view has notification email field for development programs', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('dev-tmpl-email-');
    expect(content).toContain('notification_email');
    expect(content).toContain('Coach/admin gets a separate alert at this address');
  });

  test('marketing view has program duration field for development programs', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('dev-tmpl-duration-');
    expect(content).toContain('program_duration_weeks');
    expect(content).toContain('How long the program runs');
  });

  test('notification template save includes email and duration', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('notification_email');
    expect(content).toContain('program_duration_weeks');
    // JS send function includes new fields
    const jsContent = content.substring(content.indexOf('saveDevNotificationTemplate'));
    expect(jsContent).toContain('dev-tmpl-email-');
    expect(jsContent).toContain('dev-tmpl-duration-');
  });
});

// =====================================================
// 11. New Feature Tests - Drill Details, Videos, Appointments
// =====================================================

test.describe('Database Schema - Development Videos & Appointments', () => {

  test('development_program_videos table exists with correct structure', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `development_program_videos`');
    expect(schema).toContain('enrollment_id');
    expect(schema).toContain('athlete_id');
    expect(schema).toContain('drill_assignment_id');
    expect(schema).toContain('video_url');
    expect(schema).toContain('video_upload_path');
    expect(schema).toContain("'pending_review', 'reviewed', 'feedback_given'");
    expect(schema).toContain('coach_feedback');
    expect(schema).toContain('reviewed_by');
  });

  test('development_appointments table exists with correct structure', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `development_appointments`');
    expect(schema).toContain('enrollment_id');
    expect(schema).toContain('coach_id');
    expect(schema).toContain('athlete_id');
    expect(schema).toContain("'call', 'video_call', 'in_person'");
    expect(schema).toContain('appointment_date');
    expect(schema).toContain('appointment_time');
    expect(schema).toContain('duration_minutes');
    expect(schema).toContain('location');
    expect(schema).toContain('meeting_url');
    expect(schema).toContain('phone_number');
    expect(schema).toContain("'scheduled', 'completed', 'cancelled'");
  });
});

test.describe('Athlete Drill Detail View', () => {

  test('my program view has clickable drill cards linking to full detail page', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('drill-card');
    expect(content).toContain('dev_drill_detail');
    expect(content).toContain('enrollment_id');
    expect(content).toContain('View Full Details');
  });

  test('full drill detail page exists with complete drill information', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('dev-drill-page');
    expect(content).toContain('drill_coaching_points');
    expect(content).toContain('drill_setup');
    expect(content).toContain('drill_progression');
    expect(content).toContain('coach_notes');
    expect(content).toContain('Back to My Program');
    expect(content).toContain('personal_development_my_program');
  });

  test('full drill detail page has video upload and status actions', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('Upload Video');
    expect(content).toContain('Mark In Progress');
    expect(content).toContain('Mark Completed');
    expect(content).toContain('submitDrillVideo');
    expect(content).toContain('updateDrillStatus');
  });

  test('dev_drill_detail route is registered in all routing files', () => {
    const dashboard = readFile('dashboard.php');
    const pwa = readFile('pwa.php');
    const pwaTablet = readFile('pwa_tablet.php');
    expect(dashboard).toContain("'dev_drill_detail'");
    expect(pwa).toContain("'dev_drill_detail'");
    expect(pwaTablet).toContain("'dev_drill_detail'");
  });
});

test.describe('Athlete Development Video Upload', () => {

  test('my program view has video upload/record options', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('Upload Development Video');
    expect(content).toContain('dev-upload-section');
    expect(content).toContain('dev-upload-options');
    expect(content).toContain('Record Video');
    expect(content).toContain('Upload Video');
    expect(content).toContain('submitDevVideo');
  });

  test('my program view shows previously submitted videos', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('development_program_videos');
    expect(content).toContain('Your Submitted Videos');
    expect(content).toContain('video-status');
    expect(content).toContain('pending_review');
    expect(content).toContain('coach_feedback');
  });

  test('process handler supports upload_dev_video action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'upload_dev_video'");
    expect(content).toContain('handleUploadDevVideo');
    expect(content).toContain('development_program_videos');
    expect(content).toContain('dev_video_upload');
    expect(content).toContain('notifications');
  });
});

test.describe('Coach Appointment Scheduling', () => {

  test('development programs view has appointment scheduling form', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('Schedule Appointment');
    expect(content).toContain('appt-type');
    expect(content).toContain('appt-title');
    expect(content).toContain('appt-date');
    expect(content).toContain('appt-time');
    expect(content).toContain('appt-duration');
    expect(content).toContain('appt-location');
    expect(content).toContain('appt-meeting-url');
    expect(content).toContain('appt-phone');
    expect(content).toContain('createAppointment');
  });

  test('development programs view shows athlete uploaded videos', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('Athlete Videos');
    expect(content).toContain('development_program_videos');
    expect(content).toContain('pending_video_count');
    expect(content).toContain('dev-video-review-list');
    expect(content).toContain('coach-video-status');
  });

  test('development programs view shows existing appointments', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('development_appointments');
    expect(content).toContain('appt-type-badge');
    expect(content).toContain('cancelAppointment');
    expect(content).toContain('cancel_appointment');
  });

  test('process handler supports create_appointment action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'create_appointment'");
    expect(content).toContain('handleCreateAppointment');
    expect(content).toContain('development_appointments');
    expect(content).toContain('dev_appointment');
    expect(content).toContain('notifications');
  });

  test('process handler supports cancel_appointment action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'cancel_appointment'");
    expect(content).toContain('handleCancelAppointment');
  });

  test('process handler supports get_drill_details action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'get_drill_details'");
    expect(content).toContain('handleGetDrillDetails');
    expect(content).toContain('coaching_points');
    expect(content).toContain('setup');
  });
});

test.describe('Development Appointments in Upcoming Sessions', () => {

  test('sessions_upcoming.php fetches development appointments', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('development_appointments');
    expect(content).toContain('dev_appointments');
    expect(content).toContain('Development Appointments');
  });

  test('sessions_upcoming.php displays appointment type badges', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('appointment_type');
    expect(content).toContain('program_type');
    expect(content).toContain('Goalie Dev');
    expect(content).toContain('Player Dev');
  });
});

test.describe('Development Appointments in Coach Calendar', () => {

  test('coach calendar fetches development appointments', () => {
    const content = readFile('views/pwa/coach_calendar.php');
    expect(content).toContain('development_appointments');
    expect(content).toContain('is_dev_appointment');
    expect(content).toContain('program_type');
  });

  test('coach calendar shows DEV badge for development appointments', () => {
    const content = readFile('views/pwa/coach_calendar.php');
    expect(content).toContain('DEV');
    expect(content).toContain('appointment_type');
    expect(content).toContain('athlete_first');
  });
});

test.describe('Auto-create Development Program Products', () => {

  test('sessions_booking.php auto-creates Goalie Development Program template', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('Goalie Development Program');
    expect(content).toContain("'Player Development Program'");
    expect(content).toContain('training_session_templates');
    expect(content).toContain('show_on_landing');
  });

  test('development programs are displayed in programs_camps.php and sessions_booking.php', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('Development Programs');
    expect(content).toContain('dev-programs-section');
    expect(content).toContain('Long Term Goalie Development');
    expect(content).toContain('Long Term Player Development');
    expect(content).toContain('dev_enrolled_types');
    // Verify dev programs ALSO show on booking page (below Programs & Camps)
    const bookingContent = readFile('views/sessions_booking.php');
    expect(bookingContent).toContain('dev-programs-section');
    expect(bookingContent).toContain('Long Term Development Programs');
    expect(bookingContent).toContain('dev_products');
  });

  test('programs_camps.php shows program duration when configured', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('program_duration_weeks');
    expect(content).toContain('week program');
    expect(content).toContain('goalie_dev_duration_weeks');
    expect(content).toContain('player_dev_duration_weeks');
  });

  test('process_development_programs.php sends email to configured notification_email on enrollment', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('notification_email');
    expect(content).toContain('sendEmail');
    expect(content).toContain('filter_var');
    expect(content).toContain('FILTER_VALIDATE_EMAIL');
  });

  test('payment_success.php sends email to configured notification_email on paid enrollment', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('notification_email');
    expect(content).toContain('development_notification_templates');
  });

  test('process_development_programs.php handles notification_email and duration in update', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('notification_email');
    expect(content).toContain('program_duration_weeks');
    expect(content).toContain("FILTER_VALIDATE_EMAIL");
  });

  test('my program view shows upcoming appointments', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('development_appointments');
    expect(content).toContain('Upcoming Sessions');
    expect(content).toContain('appt-date-box');
    expect(content).toContain('appointment_type');
  });

  test('programs_camps.php states dev programs are tailored to each athlete', () => {
    const content = readFile('views/programs_camps.php');
    expect(content).toContain('Tailored to each athlete');
    expect(content).toContain('Personalized drill programs');
  });

  test('process_booking.php handles register_dev_program action with Stripe payment', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain('register_dev_program');
    expect(content).toContain('development_program_enrollments');
    expect(content).toContain('dev_program');
    expect(content).toContain('program_type');
    expect(content).toContain('template_id');
  });

  test('payment_success.php handles dev_program enrollment after payment', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('dev_program');
    expect(content).toContain('development_program_enrollments');
    expect(content).toContain('register_dev_program');
    expect(content).toContain('program_type');
  });

  test('process_development_programs.php restricts direct registration to admins/coaches only', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('Registration requires payment');
    expect(content).toContain('canManageDevPrograms');
  });

  test('personal_development_programs.php no longer allows direct free enrollment', () => {
    const content = readFile('views/personal_development_programs.php');
    // Should NOT contain the old direct registration function
    expect(content).not.toContain('registerDevProgram');
    expect(content).not.toContain('process_development_programs.php');
    // Should link to booking page instead
    expect(content).toContain('page=booking');
    expect(content).toContain('Enroll');
  });
});

test.describe('Presigned URL Video Upload Flow', () => {

  test('dev_drill_detail.php uses presigned URL upload flow via process_video.php', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('get_video_upload_url');
    expect(content).toContain('presigned_url');
    expect(content).toContain('upload_nonce');
    expect(content).toContain('xhrUploadWithProgress');
    expect(content).toContain('confirm_dev_video_upload');
    expect(content).toContain('falling back to legacy upload');
  });

  test('my program view uses presigned URL upload flow', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('get_video_upload_url');
    expect(content).toContain('process_video.php');
    expect(content).toContain('presigned_url');
    expect(content).toContain('confirm_dev_video_upload');
    expect(content).toContain('falling back to legacy upload');
  });

  test('process handler supports confirm_dev_video_upload action', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'confirm_dev_video_upload'");
    expect(content).toContain('handleConfirmDevVideoUpload');
    expect(content).toContain('hash_equals');
    expect(content).toContain('upload_nonce');
    expect(content).toContain('pending_video_upload');
    expect(content).toContain('development_program_videos');
  });

  test('dev_drill_detail.php falls back to legacy upload on presigned URL failure', () => {
    const content = readFile('views/dev_drill_detail.php');
    // Legacy fallback should use upload_dev_video action
    expect(content).toContain("'action', 'upload_dev_video'");
    expect(content).toContain('process_development_programs.php');
    expect(content).toContain('video_file');
  });

  test('dev_drill_detail.php has drill navigation between drills', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('dev-drill-nav');
    expect(content).toContain('prev_drill');
    expect(content).toContain('next_drill');
    expect(content).toContain('fa-arrow-left');
    expect(content).toContain('fa-arrow-right');
  });
});

// =====================================================
// Long Term Development Tab in Products
// =====================================================

test.describe('Long Term Development Tab in Products', () => {

  test('accounting_products.php has Long Term Development tab', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('Long Term Development');
    expect(content).toContain('long_term_dev');
    expect(content).toContain('long_term_dev-tab');
    expect(content).toContain('fa-chart-line');
  });

  test('Long Term Development tab has create button and table structure', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('Create Dev Program');
    expect(content).toContain('openAddDevProgramModal');
    expect(content).toContain('Duration');
    expect(content).toContain('duration_weeks');
  });

  test('Long Term Development tab has add and edit modals', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('add-dev-program-modal');
    expect(content).toContain('edit-dev-program-modal');
    expect(content).toContain('dev-duration-weeks');
    expect(content).toContain('saveDevProgram');
  });

  test('development programs auto-create with is_dev_program flag', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('is_dev_program');
    expect(content).toContain('Goalie Development Program');
    expect(content).toContain('Player Development Program');
    expect(content).toContain('Auto-add is_dev_program');
  });

  test('development programs use time frame (duration_weeks) not specific dates', () => {
    const content = readFile('views/accounting_products.php');
    expect(content).toContain('duration_weeks');
    expect(content).toContain('Time frame for the program');
    expect(content).toContain('weeks');
  });

  test('process_admin_action.php has dev program CRUD handlers', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('create_dev_program');
    expect(content).toContain('update_dev_program');
    expect(content).toContain('is_dev_program');
    expect(content).toContain('duration_weeks');
  });
});

// =====================================================
// Database Schema - Dev Program Enhancements
// =====================================================

test.describe('Database Schema - Dev Program Enhancements', () => {

  test('training_session_templates has is_dev_program and duration_weeks columns', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('is_dev_program');
    expect(schema).toContain('duration_weeks');
    expect(schema).toContain('idx_dev_program');
  });

  test('development_program_enrollments has start_date and end_date columns', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('start_date');
    expect(schema).toContain('end_date');
    expect(schema).toContain('Auto-calculated program start date');
    expect(schema).toContain('Auto-calculated from start_date + duration_weeks');
  });

  test('email_templates table exists in schema', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('CREATE TABLE IF NOT EXISTS `email_templates`');
    expect(schema).toContain('template_type');
    expect(schema).toContain('body_text');
    expect(schema).toContain('body_html');
    expect(schema).toContain('is_custom');
  });
});

// =====================================================
// Enrollment Date Tracking
// =====================================================

test.describe('Enrollment Date Tracking', () => {

  test('process_development_programs.php calculates start_date and end_date on enrollment', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('start_date');
    expect(content).toContain('end_date');
    expect(content).toContain('duration_weeks');
  });

  test('process_booking.php calculates dates for free enrollment', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain('start_date');
    expect(content).toContain('end_date');
    expect(content).toContain('duration_weeks');
  });

  test('payment_success.php calculates dates for paid enrollment', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('start_date');
    expect(content).toContain('end_date');
    expect(content).toContain('duration_weeks');
  });
});

// =====================================================
// Dev Notification Templates → Athlete Emails
// =====================================================

test.describe('Dev Notification Templates - Athlete Emails', () => {

  test('notification templates are sent to athletes, not coaches', () => {
    const content = readFile('process_development_programs.php');
    // Template used for athlete email
    expect(content).toContain('Send welcome email to the ATHLETE using the template');
    expect(content).toContain('athlete_email');
    // Coach gets hardcoded notification, not template
    expect(content).toContain('Coach/admin notification (hardcoded text');
  });

  test('marketing labels clarify templates are for athletes', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('Athlete Email Subject');
    expect(content).toContain('Athlete Email Body');
    expect(content).toContain('sent to the athlete when they enroll');
    expect(content).toContain('Athlete Email Templates');
  });

  test('default templates are athlete-facing welcome messages', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('Welcome to the Goalie Development Program!');
    expect(schema).toContain('Welcome to the Player Development Program!');
    expect(schema).toContain('Your coach will be in touch shortly');
  });

  test('payment_success.php sends athlete welcome email from template', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('athlete welcome email');
    expect(content).toContain('Welcome to Your Development Program!');
  });
});

// =====================================================
// Email Templates Tab in Marketing
// =====================================================

test.describe('Email Templates Tab in Marketing', () => {

  test('marketing page has Email Templates tab', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('email-templates');
    expect(content).toContain('tab-email-templates');
    expect(content).toContain('section-email-templates');
    expect(content).toContain('Email Templates');
  });

  test('email templates section lists all system template types', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('verification');
    expect(content).toContain('manual_welcome');
    expect(content).toContain('payment_receipt');
    expect(content).toContain('password_reset');
    expect(content).toContain('notification');
    expect(content).toContain('system_notification');
    expect(content).toContain('email_change_confirmation');
    expect(content).toContain('esignature_request');
    expect(content).toContain('contract_signed');
  });

  test('email templates have standard text and advanced HTML/CSS editing', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('etpl-body-');
    expect(content).toContain('etpl-html-');
    expect(content).toContain('Advanced HTML/CSS');
    expect(content).toContain('toggleAdvancedEditor');
  });

  test('advanced HTML/CSS editing is hidden by default', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain("id=\"etpl-advanced-");
    expect(content).toContain("style=\"display:none;");
  });

  test('email templates have live auto-updating preview', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('etpl-preview-');
    expect(content).toContain('updateEmailTemplatePreview');
    expect(content).toContain('oninput="updateEmailTemplatePreview');
  });

  test('email templates can be saved and reset', () => {
    const content = readFile('views/admin_business_cards.php');
    expect(content).toContain('saveEmailTemplate');
    expect(content).toContain('resetEmailTemplate');
    expect(content).toContain('save_email_template');
    expect(content).toContain('reset_email_template');
  });

  test('process_development_programs.php has email template save/reset handlers', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("case 'save_email_template'");
    expect(content).toContain("case 'reset_email_template'");
    expect(content).toContain('handleSaveEmailTemplate');
    expect(content).toContain('handleResetEmailTemplate');
  });

  test('email template save handler validates and whitelists template types', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain('allowed_types');
    expect(content).toContain('ON DUPLICATE KEY UPDATE');
    expect(content).toContain('is_custom');
  });

  test('mailer.php checks for custom email templates', () => {
    const content = readFile('mailer.php');
    expect(content).toContain('email_templates');
    expect(content).toContain('is_custom');
    expect(content).toContain('body_html');
    expect(content).toContain('body_text');
    expect(content).toContain('customTemplate');
  });
});

// =====================================================
// Multi-Enrollment & Program Differentiation
// =====================================================

test.describe('Multi-Enrollment & Program Differentiation', () => {

  test('enrollment table allows multiple enrollments (no UNIQUE constraint on athlete+type)', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain('development_program_enrollments');
    expect(schema).toContain('program_name');
    expect(schema).toContain('template_id');
    expect(schema).toContain('idx_template');
    // The enrollments table should NOT have a unique constraint on athlete_id+program_type
    const enrollSection = schema.split('development_program_enrollments')[1].split('ENGINE=InnoDB')[0];
    expect(enrollSection).not.toContain('UNIQUE KEY');
  });

  test('enrollment stores program_name and template_id', () => {
    const schema = readFile('database_schema.sql');
    expect(schema).toContain("program_name");
    expect(schema).toContain("template_id");
    expect(schema).toContain('idx_template');
  });

  test('process_development_programs.php checks only active enrollments for duplicates', () => {
    const content = readFile('process_development_programs.php');
    expect(content).toContain("status = 'active'");
    expect(content).toContain('template_id');
    expect(content).toContain('program_name');
  });

  test('process_booking.php checks only active enrollments with template_id', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain("status = 'active'");
    expect(content).toContain('template_id');
    expect(content).toContain('program_name');
  });

  test('payment_success.php stores program_name and template_id', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('program_name');
    expect(content).toContain('template_id');
    expect(content).toContain("status = 'active'");
  });
});

// =====================================================
// Coach View Redesign - Active Programs & History
// =====================================================

test.describe('Coach View Redesign', () => {

  test('coach view has Active Programs and Program History tabs', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('dev-coach-tabs');
    expect(content).toContain('Active Programs');
    expect(content).toContain('Program History');
    expect(content).toContain('switchCoachTab');
  });

  test('active programs shown as full-size cards with athlete info', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('dev-active-cards');
    expect(content).toContain('card-athlete-name');
    expect(content).toContain('card-program-name');
    expect(content).toContain('weeks-left');
    expect(content).toContain('program_name');
  });

  test('detail view has back button to overview', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('dev-back-btn');
    expect(content).toContain('Back to All Programs');
    expect(content).toContain('fa-arrow-left');
  });

  test('program history has filterable table', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('dev-history-table');
    expect(content).toContain('history-filter-name');
    expect(content).toContain('history-filter-position');
    expect(content).toContain('history-filter-program');
    expect(content).toContain('filterHistory');
  });

  test('coach view queries both active and completed enrollments', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain("dpe.status = 'active'");
    expect(content).toContain("'completed', 'paused', 'cancelled'");
    expect(content).toContain('history_athletes');
  });
});

// =====================================================
// Athlete View - Program History & Dynamic Products
// =====================================================

test.describe('Athlete View Updates', () => {

  test('my_program.php shows program name and weeks left on each enrollment', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('program_name');
    expect(content).toContain('weeks_left');
    expect(content).toContain('end_date');
    expect(content).toContain('program_display');
  });

  test('my_program.php shows completed programs history', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('completed_programs');
    expect(content).toContain('Previous Programs');
    expect(content).toContain('fa-history');
  });

  test('personal_development_programs.php loads products dynamically from DB', () => {
    const content = readFile('views/personal_development_programs.php');
    expect(content).toContain('is_dev_program');
    expect(content).toContain('dev_products');
    expect(content).toContain('training_session_templates');
    expect(content).toContain('duration_weeks');
  });

  test('personal_development_programs.php shows completed enrollment history', () => {
    const content = readFile('views/personal_development_programs.php');
    expect(content).toContain('completed_enrollments');
    expect(content).toContain('Completed Programs');
    expect(content).toContain('fa-history');
  });
});

// =====================================================
// Sessions Booking - Dev Program Cards
// =====================================================

test.describe('Booking Page Dev Program Cards', () => {

  test('sessions_booking.php has Long Term Development Programs section', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('Long Term Development Programs');
    expect(content).toContain('dev-programs-section');
    expect(content).toContain('dev-product-card');
    expect(content).toContain('fa-chart-line');
  });

  test('booking page fetches all dev products from training_session_templates', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('is_dev_program');
    expect(content).toContain('dev_products');
    expect(content).toContain('dev_active_template_ids');
  });

  test('booking page dev cards show enroll form with template_id', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('register_dev_program');
    expect(content).toContain('template_id');
    expect(content).toContain('program_type');
    expect(content).toContain('Enroll in Program');
  });
});

// =====================================================
// Navigation Rename: Sessions → Training
// =====================================================

test.describe('Navigation Rename Sessions to Training', () => {

  test('dashboard.php sidebar shows Training instead of Sessions', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('Training');
    // The link still goes to page=sessions for backward compat
    expect(content).toContain("page=sessions");
  });

  test('pwa.php tab shows Training instead of Sessions', () => {
    const content = readFile('pwa.php');
    expect(content).toContain('>Training<');
  });

  test('pwa_tablet.php sidebar shows Training instead of Sessions', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).toContain('Training');
  });

  test('sessions.php page title shows Training', () => {
    const content = readFile('views/sessions.php');
    expect(content).toContain('Training');
  });
});
