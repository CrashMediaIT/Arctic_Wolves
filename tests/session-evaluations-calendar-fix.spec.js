import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for coach_session_evaluations calendar view fix:
 *
 * 1. Calendar view container uses 'card' class (not 'content-card' which is PWA-only)
 * 2. Calendar view has conditional 'active' class based on $activeView
 * 3. Calendar initializes on page load when calendar view is active
 * 4. Date comparison uses string formatting instead of toISOString() to avoid
 *    timezone-related date shifts
 * 5. JSON encoding uses JSON_HEX_TAG to prevent XSS via </script> in session titles
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Calendar view card uses correct CSS class
// =====================================================

test.describe('Calendar view card class', () => {
  test('calendar view uses card class not content-card', () => {
    const content = readFile('views/coach_session_evaluations.php');
    // The calendar view section
    const calStart = content.indexOf('<!-- Calendar View -->');
    const calEnd = content.indexOf('</div>', content.indexOf('id="calendar-body"') + 50);
    const calSection = content.substring(calStart, calEnd);

    expect(calSection).toContain('<div class="card">');
    expect(calSection).not.toContain('content-card');
  });
});

// =====================================================
// 2. Calendar view has conditional active class
// =====================================================

test.describe('Calendar view active class', () => {
  test('calendar-view div has conditional active class like list-view', () => {
    const content = readFile('views/coach_session_evaluations.php');

    // list-view has conditional active
    expect(content).toContain('id="list-view" class="view-container <?= $activeView === \'list\' ? \'active\' : \'\' ?>"');
    // calendar-view must also have conditional active
    expect(content).toContain('id="calendar-view" class="view-container <?= $activeView === \'calendar\' ? \'active\' : \'\' ?>"');
  });
});

// =====================================================
// 3. Calendar initialization on page load
// =====================================================

test.describe('Calendar initialization', () => {
  test('initCalendar is called on page load when calendar view is active', () => {
    const content = readFile('views/coach_session_evaluations.php');

    // There should be code that checks if calendar-view is active and calls initCalendar
    expect(content).toContain("getElementById('calendar-view')");
    expect(content).toContain('initCalendar()');
    // The auto-init block should check for .active class
    expect(content).toMatch(/calendar-view.*classList.*contains.*active.*\).*\{?\s*\n?\s*initCalendar/s);
  });
});

// =====================================================
// 4. Date comparison uses string formatting
// =====================================================

test.describe('Calendar date comparison', () => {
  test('renderCalendar does not use toISOString for date comparison', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('function renderCalendar()');
    const fnEnd = content.indexOf("document.getElementById('calendar-body').innerHTML = html;", fnStart);
    const fnBody = content.substring(fnStart, fnEnd);

    // Should NOT use toISOString() for date comparison (timezone-fragile)
    expect(fnBody).not.toContain('toISOString()');
    // Should use string-based date formatting like coach_calendar.php
    expect(fnBody).toContain("padStart(2, '0')");
  });

  test('renderCalendar builds date strings using year-month-day formatting', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('function renderCalendar()');
    const fnEnd = content.indexOf("document.getElementById('calendar-body').innerHTML = html;", fnStart);
    const fnBody = content.substring(fnStart, fnEnd);

    // Should build todayStr from local date components
    expect(fnBody).toContain('todayStr');
    expect(fnBody).toContain('today.getFullYear()');
    expect(fnBody).toContain('today.getMonth()');
    expect(fnBody).toContain('today.getDate()');
  });

  test('renderCalendar uses substring to extract date from session_date', () => {
    const content = readFile('views/coach_session_evaluations.php');
    const fnStart = content.indexOf('function renderCalendar()');
    const fnEnd = content.indexOf("document.getElementById('calendar-body').innerHTML = html;", fnStart);
    const fnBody = content.substring(fnStart, fnEnd);

    // Should extract date portion via substring rather than Date parsing
    expect(fnBody).toContain('substring(0, 10)');
  });
});

// =====================================================
// 5. JSON encoding uses JSON_HEX_TAG
// =====================================================

test.describe('Calendar data JSON encoding', () => {
  test('calendarSessions JSON uses JSON_HEX_TAG for XSS safety', () => {
    const content = readFile('views/coach_session_evaluations.php');
    expect(content).toContain('json_encode(array_values($calendar_sessions), JSON_HEX_TAG)');
  });
});
