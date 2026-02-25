import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Session Booking & Template Fix Tests
 * Tests for:
 * 1. Booking page only shows sessions from sessions table (no template UNION)
 * 2. Session update doesn't incorrectly write to session_coaches with template IDs
 * 3. Public sessions page shows sessions from both sessions table AND training_session_templates
 * 4. Registration intent supports both session_id and template-based sessions
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Booking Page - No Template UNION ALL
// =====================================================

test.describe('Booking Page - Session Query Fix', () => {
  test('sessions_booking.php queries the sessions table for available sessions', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('FROM sessions s');
    expect(content).toContain("s.status = 'scheduled'");
    expect(content).toContain('s.session_date >= CURDATE()');
  });

  test('sessions_booking.php also queries training_session_templates with dates', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('FROM training_session_templates t');
    expect(content).toContain('INNER JOIN training_session_dates td ON td.template_id = t.id');
    expect(content).toContain('t.is_active = 1');
    expect(content).toContain('td.is_active = 1');
  });

  test('sessions_booking.php merges and sorts both session sources by date', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('array_merge');
    expect(content).toContain('usort');
  });

  test('sessions_booking.php orders sessions from sessions table by date', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('ORDER BY s.session_date ASC');
  });

  test('sessions_booking.php uses session id and source type for register button', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('data-action="register-session"');
    expect(content).toContain('data-session-id');
    expect(content).toContain('data-source-type');
    expect(content).toContain('data-date-id');
  });

  test('sessions_booking.php JS sends correct action for template sessions', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("register_template_session");
    expect(content).toContain("session_date_id");
  });
});

// =====================================================
// 2. Admin Action - No session_coaches for templates
// =====================================================

test.describe('Admin Action - Template Update Fix', () => {
  test('update_training_session does not write to session_coaches', () => {
    const content = readFile('process_admin_action.php');
    // Find the update_training_session section up to the next major action or commit
    const startIdx = content.indexOf("action == 'update_training_session'");
    const sectionAfter = content.substring(startIdx);
    const commitIdx = sectionAfter.indexOf('$pdo->commit()');
    const updateSection = sectionAfter.substring(0, commitIdx);
    // Should NOT contain INSERT INTO session_coaches in the template update section
    expect(updateSection).not.toContain('INSERT INTO session_coaches');
  });

  test('update_training_session still updates training_session_templates table', () => {
    const content = readFile('process_admin_action.php');
    expect(content).toContain('UPDATE training_session_templates');
    expect(content).toContain('coach_id = ?');
  });
});

// =====================================================
// 3. Public Sessions Page - Shows sessions from both sources
// =====================================================

test.describe('Public Sessions Page - Dual Source Display', () => {
  test('sessions_public.php queries the sessions table', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain('FROM sessions s');
    expect(content).toContain("s.status = 'scheduled'");
  });

  test('sessions_public.php also queries training_session_templates with dates', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain('FROM training_session_templates t');
    expect(content).toContain('INNER JOIN training_session_dates td ON td.template_id = t.id');
    expect(content).toContain('t.is_active = 1');
    expect(content).toContain('td.is_active = 1');
  });

  test('sessions_public.php merges and sorts both session sources by date', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain('array_merge');
    expect(content).toContain('usort');
    expect(content).toContain("strtotime(\$a['next_date'])");
  });

  test('sessions_public.php does not artificially limit session count', () => {
    const content = readFile('sessions_public.php');
    // The main sessions query between FROM sessions and the catch block should not have LIMIT
    const startIdx = content.indexOf('FROM sessions s');
    const endIdx = content.indexOf('} catch (PDOException $e)');
    const dataSection = content.substring(startIdx, endIdx);
    expect(dataSection).not.toContain('LIMIT');
  });

  test('sessions_public.php uses unified session source_type for all sessions', () => {
    const content = readFile('sessions_public.php');
    // Both queries now use 'session' as source_type (unified terminology)
    expect(content).toContain("'session' as source_type");
    // Template sessions also use 'session' source_type - no 'template' distinction
    // Routing is based on session_date_id presence instead
    expect(content).toContain('session_date_id');
  });
});

// =====================================================
// 4. Registration Intent - Supports both session and template types
// =====================================================

test.describe('Registration Intent Fix', () => {
  test('sessions_public.php supports template_date registration type', () => {
    const content = readFile('sessions_public.php');
    const intentSection = content.substring(
      content.indexOf('INSERT INTO session_registration_intents'),
      content.indexOf('Redirect to login with token')
    );
    expect(intentSection).toContain('session_id');
    expect(intentSection).toContain('template_id');
    expect(intentSection).toContain('session_date_id');
  });

  test('register links use session_date_id to determine registration type', () => {
    const content = readFile('sessions_public.php');
    // Registration type is now determined by session_date_id presence, not source_type
    expect(content).toContain("session_date_id") ;
    expect(content).toContain("template_date");
  });

  test('calendar view register links use session_date_id to determine type', () => {
    const content = readFile('sessions_public.php');
    // Calendar JS now uses session.session_date_id instead of source_type
    expect(content).toContain("session.session_date_id ? 'template_date' : 'session'");
  });
});

// =====================================================
// 6. Process Booking - Template Session Registration
// =====================================================

test.describe('Process Booking - Template Session Support', () => {
  test('process_booking.php has register_template_session handler', () => {
    const content = readFile('process_booking.php');
    expect(content).toContain("register_template_session");
    expect(content).toContain('session_date_athletes');
    expect(content).toContain('session_date_id');
  });

  test('register_template_session validates session_date_id', () => {
    const content = readFile('process_booking.php');
    const startIdx = content.indexOf("register_template_session");
    const section = content.substring(startIdx, content.indexOf('JOIN WAITLIST'));
    expect(section).toContain('session_date_id');
    expect(section).toContain('training_session_dates');
    expect(section).toContain('training_session_templates');
  });

  test('register_template_session checks for duplicate registration', () => {
    const content = readFile('process_booking.php');
    const startIdx = content.indexOf("register_template_session");
    const section = content.substring(startIdx, content.indexOf('JOIN WAITLIST'));
    expect(section).toContain('session_date_athletes');
    expect(section).toContain('already_booked');
  });
});
