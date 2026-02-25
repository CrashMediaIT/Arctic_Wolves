import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Registered Session Dates in Upcoming Sessions & Calendar
 * Tests for:
 * 1. Athletes see only template sessions they are registered for (via session_date_athletes)
 * 2. Coach/admin view still shows all active template sessions with show_on_landing
 * 3. Calendar view receives the same filtered session data
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Athlete Template Sessions - Registration Filter
// =====================================================

test.describe('Athlete Registered Template Sessions in Upcoming View', () => {
  test('sessions_upcoming.php joins session_date_athletes for athlete role', () => {
    const content = readFile('views/sessions_upcoming.php');
    // The template sessions query should INNER JOIN session_date_athletes for athletes
    expect(content).toContain('session_date_athletes sda');
    expect(content).toContain('sda.session_date_id = tsd.id');
    expect(content).toContain('sda.athlete_id');
  });

  test('sessions_upcoming.php conditionally joins session_date_athletes only for athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    // The join should be conditional on athlete role
    expect(content).toContain("user_role === 'athlete'");
    // The INNER JOIN ensures only registered sessions appear
    expect(content).toContain('INNER JOIN session_date_athletes sda');
  });

  test('sessions_upcoming.php passes athlete user_id as template param for registration filter', () => {
    const content = readFile('views/sessions_upcoming.php');
    // The athlete_id parameter should be added to template_params
    expect(content).toContain('$template_params[] = $user_id');
  });

  test('sessions_upcoming.php does not require show_on_landing for athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    // show_on_landing should only be applied for non-athlete roles
    expect(content).toContain("user_role !== 'athlete'");
    expect(content).toContain('show_on_landing = 1');
  });
});

// =====================================================
// 2. Coach/Admin Template Sessions - Existing Behavior
// =====================================================

test.describe('Coach/Admin Template Sessions in Upcoming View', () => {
  test('sessions_upcoming.php still shows show_on_landing sessions for non-athletes', () => {
    const content = readFile('views/sessions_upcoming.php');
    // The show_on_landing filter should still be applied for non-athletes
    expect(content).toContain('show_on_landing = 1');
  });

  test('sessions_upcoming.php merges template sessions with regular sessions', () => {
    const content = readFile('views/sessions_upcoming.php');
    expect(content).toContain('array_merge($sessions, $template_sessions)');
  });
});

// =====================================================
// 3. Calendar View - Same Filtered Data
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
});
