import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Coach Detection & Unified Session Tests
 * Tests for:
 * 1. Coach detection - sessions show "You're Coaching" button for coaches
 * 2. Unified session source_type - no 'template' distinction in user-facing code
 * 3. JS uses dateId presence instead of sourceType to route registration
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Coach Detection - Booking Page
// =====================================================

test.describe('Coach Detection - Booking Sessions', () => {
  test('sessions_booking.php queries include coach_id in SELECT for group sessions', () => {
    const content = readFile('views/sessions_booking.php');
    // The main sessions query should include s.coach_id
    const queryStart = content.indexOf('$available_sessions_query');
    const queryEnd = content.indexOf('$available_sessions = $pdo->query');
    const querySection = content.substring(queryStart, queryEnd);
    expect(querySection).toContain('s.coach_id');
  });

  test('sessions_booking.php queries include coach_id in SELECT for template sessions', () => {
    const content = readFile('views/sessions_booking.php');
    // The template sessions query should include t.coach_id in SELECT
    const queryStart = content.indexOf('$template_sessions_query');
    const queryEnd = content.indexOf('$template_sessions = $pdo->query');
    const querySection = content.substring(queryStart, queryEnd);
    expect(querySection).toContain('t.coach_id');
  });

  test('sessions_booking.php queries include coach_id in SELECT for private sessions', () => {
    const content = readFile('views/sessions_booking.php');
    const queryStart = content.indexOf('$private_sessions_query');
    const queryEnd = content.indexOf('$private_sessions = $pdo->query');
    const querySection = content.substring(queryStart, queryEnd);
    expect(querySection).toContain('s.coach_id');
  });

  test('sessions_booking.php computes is_session_coach for each session', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('$is_session_coach');
    expect(content).toContain("session['coach_id']");
    expect(content).toContain("$_SESSION['user_id']");
  });

  test('sessions_booking.php shows "You\'re Coaching" button for coach sessions', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("You're Coaching");
    expect(content).toContain('fa-user-shield');
  });

  test('sessions_booking.php coach check comes before already_booked check', () => {
    const content = readFile('views/sessions_booking.php');
    // In the action column, coach check should appear before already_booked
    const coachIdx = content.indexOf('$is_session_coach):');
    const bookedIdx = content.indexOf('$already_booked):');
    expect(coachIdx).toBeGreaterThan(-1);
    expect(bookedIdx).toBeGreaterThan(-1);
    expect(coachIdx).toBeLessThan(bookedIdx);
  });

  test('sessions_booking.php private sessions also detect coach', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('$ps_is_coach');
  });

  test('sessions_booking.php card has data-coach attribute', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('data-coach=');
  });
});

// =====================================================
// 2. Coach Detection - Calendar View
// =====================================================

test.describe('Coach Detection - Calendar View', () => {
  test('calendar sessionData includes isCoach field', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain("isCoach: card.dataset.coach === '1'");
  });

  test('calendar button rendering handles coach state', () => {
    const content = readFile('views/sessions_booking.php');
    expect(content).toContain('session.isCoach');
    expect(content).toContain("You\\'re Coaching");
  });
});

// =====================================================
// 3. Unified Session Terminology
// =====================================================

test.describe('Unified Session Terminology', () => {
  test('booking page template query uses session as source_type', () => {
    const content = readFile('views/sessions_booking.php');
    const templateQuery = content.substring(
      content.indexOf('$template_sessions_query'),
      content.indexOf('$template_sessions = $pdo->query')
    );
    // Template query now uses 'session' as source_type, not 'template'
    expect(templateQuery).toContain("'session' as source_type");
    expect(templateQuery).not.toContain("'template' as source_type");
  });

  test('public page template query uses session as source_type', () => {
    const content = readFile('sessions_public.php');
    // All queries should use 'session' as source_type
    const matches = content.match(/'session' as source_type/g);
    expect(matches).not.toBeNull();
    expect(matches.length).toBeGreaterThanOrEqual(2);
    // No 'template' as source_type should exist
    expect(content).not.toContain("'template' as source_type");
  });

  test('booking JS uses dateId presence to determine registration action', () => {
    const content = readFile('views/sessions_booking.php');
    // Should use dateId instead of sourceType === 'template'
    expect(content).toContain("dateId ? 'register_template_session' : 'register_session'");
    expect(content).not.toContain("sourceType === 'template'");
  });

  test('public page list view uses session_date_id for registration links', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain("session_date_id");
    // Should not use source_type to determine template_date type
    expect(content).not.toContain("source_type'] === 'template'");
  });

  test('public page calendar view uses session_date_id for registration links', () => {
    const content = readFile('sessions_public.php');
    expect(content).toContain("session.session_date_id ? 'template_date' : 'session'");
    expect(content).not.toContain("session.source_type === 'template'");
  });
});
