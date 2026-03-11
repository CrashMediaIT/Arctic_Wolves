/**
 * Tests for coach_calendar.php evaluation integration
 * 
 * Verifies that:
 * 1. Session evaluations are queried and displayed on the coach calendar
 * 2. The "Start Evaluation" modal exists with template and athlete selection
 * 3. The session detail modal has evaluation-aware buttons
 * 4. Evaluation data attributes are present on session elements
 * 5. process_session_evaluations.php has the start_evaluation action
 * 6. Navigation and routing no longer reference coach_session_evaluations
 * 7. session_evaluation_form.php back links point to coach_calendar
 */
const { test, expect } = require('@playwright/test');
const { readFileSync, existsSync } = require('fs');
const { join } = require('path');

const ROOT = join(__dirname, '..');
function readFile(rel) {
  return readFileSync(join(ROOT, rel), 'utf-8');
}

test.describe('Coach Calendar Evaluation Integration', () => {

  test('coach_calendar.php queries session_evaluations alongside sessions', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain('LEFT JOIN session_evaluations se ON se.session_id = s.id');
    expect(content).toContain('se.id as evaluation_id');
    expect(content).toContain('se.name as evaluation_name');
    expect(content).toContain('se.status as evaluation_status');
  });

  test('coach_calendar.php queries evaluation_templates for the modal', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain('evaluation_templates');
    expect(content).toContain('eval_templates');
    expect(content).toContain('evalTemplateSelect');
  });

  test('coach_calendar.php session cards include evaluation data attributes', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain('data-evaluation-id');
    expect(content).toContain('data-evaluation-name');
  });

  test('coach_calendar.php has eval-tag CSS class for evaluation indicators', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain('.eval-tag');
    expect(content).toContain('eval-tag');
  });

  test('coach_calendar.php shows evaluation status tag on session cards', () => {
    const content = readFile('views/coach_calendar.php');
    // Check that sessions with evaluations get the eval-tag
    expect(content).toContain("evaluation_id']");
    expect(content).toContain('eval-tag');
    expect(content).toContain('fa-clipboard-check');
  });

  test('coach_calendar.php has "Start Evaluation" modal with template and athlete selection', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain('startEvalModal');
    expect(content).toContain('Start Evaluation');
    expect(content).toContain('evalTemplateSelect');
    expect(content).toContain('evalAthleteGrid');
    expect(content).toContain('eval_athlete_ids');
  });

  test('coach_calendar.php "Continue Evaluation" links go to session_evaluation_form', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain("page=session_evaluation_form&evaluation_id=");
    expect(content).toContain('Continue Evaluation');
  });

  test('coach_calendar.php "Evaluate" button opens startEvalModal for sessions without evaluations', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain('openStartEvalModal');
  });

  test('coach_calendar.php submitStartEval posts to process_session_evaluations.php', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).toContain("fetch('process_session_evaluations.php'");
    expect(content).toContain("'start_evaluation'");
    expect(content).toContain('X-Requested-With');
    expect(content).toContain('XMLHttpRequest');
  });

  test('coach_calendar.php does not reference coach_session_evaluations', () => {
    const content = readFile('views/coach_calendar.php');
    expect(content).not.toContain('coach_session_evaluations');
  });
});

test.describe('Process Session Evaluations - start_evaluation action', () => {

  test('process_session_evaluations.php has start_evaluation action', () => {
    const content = readFile('process_session_evaluations.php');
    expect(content).toContain("case 'start_evaluation':");
  });

  test('start_evaluation action creates evaluation with template_id', () => {
    const content = readFile('process_session_evaluations.php');
    const startIdx = content.indexOf("case 'start_evaluation':");
    const endIdx = content.indexOf('break;', startIdx);
    const section = content.substring(startIdx, endIdx);
    expect(section).toContain('template_id');
    expect(section).toContain('INSERT INTO session_evaluations');
  });

  test('start_evaluation action adds selected athletes', () => {
    const content = readFile('process_session_evaluations.php');
    const startIdx = content.indexOf("case 'start_evaluation':");
    const endIdx = content.indexOf('break;', startIdx);
    const section = content.substring(startIdx, endIdx);
    expect(section).toContain('athlete_ids');
    expect(section).toContain('INSERT INTO session_evaluation_athletes');
  });

  test('start_evaluation action checks for existing evaluation', () => {
    const content = readFile('process_session_evaluations.php');
    const startIdx = content.indexOf("case 'start_evaluation':");
    const endIdx = content.indexOf('break;', startIdx);
    const section = content.substring(startIdx, endIdx);
    expect(section).toContain('SELECT id FROM session_evaluations WHERE session_id');
  });

  test('process_session_evaluations.php redirects to coach_calendar not coach_session_evaluations', () => {
    const content = readFile('process_session_evaluations.php');
    expect(content).toContain('page=coach_calendar');
    expect(content).not.toContain('page=coach_session_evaluations');
  });
});

test.describe('Session Evaluation Form back links', () => {

  test('session_evaluation_form.php back links point to coach_calendar', () => {
    const content = readFile('views/session_evaluation_form.php');
    expect(content).toContain('page=coach_calendar');
    expect(content).not.toContain('page=coach_session_evaluations');
  });

  test('session_evaluation_form.php back link says "Back to Calendar"', () => {
    const content = readFile('views/session_evaluation_form.php');
    expect(content).toContain('Back to Calendar');
  });
});

test.describe('Navigation no longer references coach_session_evaluations', () => {

  test('dashboard.php does not have coach_session_evaluations route', () => {
    const content = readFile('dashboard.php');
    expect(content).not.toContain("'coach_session_evaluations'");
  });

  test('dashboard.php does not have Session Evaluations nav link', () => {
    const content = readFile('dashboard.php');
    expect(content).not.toContain('page=coach_session_evaluations');
  });

  test('pwa_tablet.php does not have coach_session_evaluations route', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).not.toContain("'coach_session_evaluations'");
  });

  test('pwa.php does not have coach_session_evaluations route', () => {
    const content = readFile('pwa.php');
    expect(content).not.toContain("'coach_session_evaluations'");
  });

  test('pwa_more_menu.php does not have coach_session_evaluations link', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).not.toContain('coach_session_evaluations');
  });

  test('views/coach_session_evaluations.php no longer exists', () => {
    expect(existsSync(join(ROOT, 'views/coach_session_evaluations.php'))).toBe(false);
  });

  test('views/pwa/coach_session_evaluations.php no longer exists', () => {
    expect(existsSync(join(ROOT, 'views/pwa/coach_session_evaluations.php'))).toBe(false);
  });

  test('session_evaluation_form route still exists in dashboard.php', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain("'session_evaluation_form'");
  });
});
