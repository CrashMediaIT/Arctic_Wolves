import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Session Booking & Template Fix Tests
 * Tests for:
 * 1. Booking page only shows sessions from sessions table (no template UNION)
 * 2. Session update doesn't incorrectly write to session_coaches with template IDs
 * 3. Landing page shows all session dates from sessions table
 * 4. Registration intent uses session_id instead of template_id
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Booking Page - No Template UNION ALL
// =====================================================

test.describe('Booking Page - Session Query Fix', () => {
  test('sessions_booking.php does not query training_session_templates', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).not.toContain('training_session_templates');
    expect(content).not.toContain('UNION ALL');
  });

  test('sessions_booking.php queries only the sessions table for available sessions', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('FROM sessions s');
    expect(content).toContain("s.status = 'scheduled'");
    expect(content).toContain('s.session_date >= CURDATE()');
  });

  test('sessions_booking.php orders sessions by date', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('ORDER BY s.session_date ASC');
  });

  test('sessions_booking.php uses session id for register button', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('data-action="register-session"');
    expect(content).toContain('data-session-id');
  });
});

// =====================================================
// 2. Admin Action - No session_coaches for templates
// =====================================================

test.describe('Admin Action - Template Update Fix', () => {
  test('update_training_session does not write to session_coaches', () => {
    const content = readFile('process_admin_action.php');
    // Find the update_training_session section
    const updateSection = content.substring(
      content.indexOf("action == 'update_training_session'"),
      content.indexOf("action == 'update_training_session'") + 2000
    );
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
// 3. Landing Page - Shows all session dates
// =====================================================

test.describe('Landing Page - Session Dates Display', () => {
  test('sessions_public.php does not query training_session_templates for display', () => {
    const content = readFile('sessions_public.php');
    // The data query section should not reference training_session_templates
    // (it may still exist in the registration_intents table schema reference)
    const dataSection = content.substring(
      content.indexOf('Fetch public sessions'),
      content.indexOf('Fetch active packages')
    );
    expect(dataSection).not.toContain('training_session_templates');
  });

  test('sessions_public.php queries sessions table with show_on_landing', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain("s.show_on_landing = 1");
    expect(content).toContain("s.status = 'scheduled'");
    expect(content).toContain('FROM sessions s');
  });

  test('sessions_public.php does not artificially limit session count', () => {
    const content = readFile('sessions_public.php');
    // The main sessions query should not have LIMIT
    const dataSection = content.substring(
      content.indexOf('Fetch public sessions'),
      content.indexOf('Fetch active packages')
    );
    expect(dataSection).not.toContain('LIMIT');
  });

  test('sessions_public.php orders sessions by date and time', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain('ORDER BY s.session_date ASC, s.session_time ASC');
  });
});

// =====================================================
// 4. Registration Intent - Uses session_id
// =====================================================

test.describe('Registration Intent Fix', () => {
  test('sessions_public.php uses session_id for registration intent', () => {
    const content = readFile('sessions_public.php');
    const intentSection = content.substring(
      content.indexOf('Store the intent'),
      content.indexOf('Redirect to login with token')
    );
    expect(intentSection).toContain('session_id');
    expect(intentSection).not.toContain('template_id');
  });

  test('register links use session id', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain("register=1&type=session&id=<?= $session['id'] ?>");
  });
});
