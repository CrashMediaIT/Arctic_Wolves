import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Registered Session Dates in Upcoming Sessions & Calendar
 * Tests for:
 * 1. Desktop upcoming view: athletes see only registered template sessions
 * 2. Desktop dashboard: athletes see registered template sessions
 * 3. PWA sessions view: includes template sessions with athlete registration filtering
 * 4. PWA home dashboard: includes registered template sessions
 * 5. Calendar view receives the same filtered session data
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Desktop Upcoming Sessions - Registration Filter
// =====================================================

test.describe('Desktop Upcoming Sessions - Athlete Registration Filter', () => {
  test('sessions_upcoming.php joins session_date_athletes for athlete role', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('session_date_athletes sda');
    expect(content).toContain('sda.session_date_id = tsd.id');
    expect(content).toContain('sda.athlete_id');
  });

  test('sessions_upcoming.php conditionally joins session_date_athletes only for athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain("user_role === 'athlete'");
    expect(content).toContain('LEFT JOIN session_date_athletes sda');
  });

  test('sessions_upcoming.php also checks package_sessions with template_id for athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('sda.id IS NOT NULL OR tst.id IN');
    expect(content).toContain('ps.template_id FROM package_sessions ps');
    expect(content).toContain('ps.template_id IS NOT NULL');
  });

  test('sessions_upcoming.php passes athlete user_id as template param for registration filter', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('$template_params[] = $user_id');
  });

  test('sessions_upcoming.php does not require show_on_landing for athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain("user_role !== 'athlete'");
    expect(content).toContain('show_on_landing = 1');
  });

  test('sessions_upcoming.php still shows show_on_landing sessions for non-athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('show_on_landing = 1');
  });

  test('sessions_upcoming.php merges template sessions with regular sessions', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('array_merge($sessions, $template_sessions)');
  });
});

// =====================================================
// 2. Desktop Dashboard - Registration Filter
// =====================================================

test.describe('Desktop Dashboard - Athlete Registered Sessions', () => {
  test('home.php filters regular sessions by bookings for athletes', () => {
    const content = readFile('views/home.php');
    expect(content).toContain('INNER JOIN bookings b ON b.session_id = s.id AND b.user_id = ?');
  });

  test('home.php filters template sessions by session_date_athletes for athletes', () => {
    const content = readFile('views/home.php');
    expect(content).toContain('INNER JOIN session_date_athletes sda ON sda.session_date_id = tsd.id AND sda.athlete_id = ?');
  });

  test('home.php passes user_id for both regular and template session filtering', () => {
    const content = readFile('views/home.php');
    expect(content).toContain('$stmt->execute([$user_id, $user_id])');
  });
});

// =====================================================
// 3. PWA Sessions View - Template Session Support
// =====================================================

test.describe('PWA Sessions View - Template Session Support', () => {
  test('pwa/sessions.php fetches training session templates', () => {
    const content = readFile('views/pwa/sessions.php');
    expect(content).toContain('training_session_templates tst');
    expect(content).toContain('training_session_dates tsd');
  });

  test('pwa/sessions.php joins session_date_athletes for non-coach users', () => {
    const content = readFile('views/pwa/sessions.php');
    expect(content).toContain('session_date_athletes sda ON sda.session_date_id = tsd.id AND sda.athlete_id');
  });

  test('pwa/sessions.php provides booking_id for registered template sessions', () => {
    const content = readFile('views/pwa/sessions.php');
    expect(content).toContain("sda.id as booking_id");
    expect(content).toContain("'confirmed' as booking_status");
  });

  test('pwa/sessions.php merges template sessions into upcoming sessions', () => {
    const content = readFile('views/pwa/sessions.php');
    expect(content).toContain('array_merge($upcomingSessions, $tplSessions)');
  });

  test('pwa/sessions.php applies period filters to template sessions', () => {
    const content = readFile('views/pwa/sessions.php');
    expect(content).toContain("tsd.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 WEEK)");
    expect(content).toContain("tsd.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)");
  });

  test('pwa/sessions.php skips template query for history mode', () => {
    const content = readFile('views/pwa/sessions.php');
    expect(content).toContain('if (!$showHistory)');
  });
});

// =====================================================
// 4. PWA Home Dashboard - Template Session Support
// =====================================================

test.describe('PWA Home Dashboard - Template Session Support', () => {
  test('pwa/home.php fetches training session templates', () => {
    const content = readFile('views/pwa/home.php');
    expect(content).toContain('training_session_templates tst');
    expect(content).toContain('training_session_dates tsd');
  });

  test('pwa/home.php joins session_date_athletes for athlete users', () => {
    const content = readFile('views/pwa/home.php');
    expect(content).toContain('session_date_athletes sda ON sda.session_date_id = tsd.id AND sda.athlete_id');
  });

  test('pwa/home.php merges template sessions into upcoming sessions', () => {
    const content = readFile('views/pwa/home.php');
    expect(content).toContain('array_merge($upcomingSessions, $tplSessions)');
  });

  test('pwa/home.php sorts merged sessions by date', () => {
    const content = readFile('views/pwa/home.php');
    // Check that usort is used after merge
    expect(content).toContain('usort($upcomingSessions');
  });
});

// =====================================================
// 5. Calendar View - Same Filtered Data
// =====================================================

test.describe('Calendar View Receives Registered Session Data', () => {
  test('sessions_upcoming.php provides session data to calendar via hidden div', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('id="sessionsData"');
    expect(content).toContain('class="session-data"');
    expect(content).toContain('data-date=');
  });

  test('calendar.js reads hidden session data for calendar rendering', () => {
    const content = readFile('js/calendar.js');
    expect(content).toContain('#sessionsData .session-data');
    expect(content).toContain('dataset.date');
  });

  test('pwa/sessions.php calendar date map includes template sessions', () => {
    const content = readFile('views/pwa/sessions.php');
    // The calendar date map is built AFTER template sessions are merged
    const mergeIdx = content.indexOf('array_merge($upcomingSessions, $tplSessions)');
    const calMapIdx = content.indexOf('calendarDates');
    expect(mergeIdx).toBeGreaterThan(0);
    expect(calMapIdx).toBeGreaterThan(mergeIdx);
  });
});

