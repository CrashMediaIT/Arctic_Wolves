import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for PR 626 fixes:
 * 1. evaluations_skills.php - Direct SQL queries use correct columns (created_by, evaluation_id)
 * 2. workouts.php - Direct SQL query uses correct columns (coach_id, assigned_date)
 * 3. stats.php - $isAnyCoach used instead of $isCoach for button visibility
 * 4. evaluations_skills.php - $isAnyCoach used instead of $isCoach for coach actions
 * 5. athlete_goals.php - Add button JavaScript handler added
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. evaluations_skills.php - SQL queries use correct columns
// =====================================================

test.describe('evaluations_skills.php - SQL queries use correct schema columns', () => {
  test('evaluations list query uses created_by and evaluation_id directly', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalListSection = content.substring(
      content.indexOf('// Get all evaluations list'),
      content.indexOf('// Get historical evaluations')
    );
    expect(evalListSection).toContain('ae.created_by');
    expect(evalListSection).toContain('evaluation_id');
    expect(evalListSection).toContain('completed_scores');
    expect(evalListSection).toContain('total_scores');
  });

  test('evaluations list query has no PDO fallback', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalListSection = content.substring(
      content.indexOf('// Get all evaluations list'),
      content.indexOf('// Get historical evaluations')
    );
    expect(evalListSection).not.toContain('catch (PDOException');
    expect(evalListSection).not.toContain('fallback');
  });

  test('scores query uses evaluation_id directly', () => {
    const content = readFile('views/evaluations_skills.php');
    const scoresSection = content.substring(
      content.indexOf('// Load scores'),
      content.indexOf('// Group by category')
    );
    expect(scoresSection).toContain('es.evaluation_id');
    expect(scoresSection).not.toContain('catch (PDOException');
  });

  test('historical query uses evaluation_id directly', () => {
    const content = readFile('views/evaluations_skills.php');
    const histSection = content.substring(
      content.indexOf('// Get historical evaluations'),
      content.indexOf('?>')
    );
    expect(histSection).toContain('es.evaluation_id');
    expect(histSection).not.toContain('catch (PDOException');
  });

  test('eval load query uses created_by directly', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalLoadSection = content.substring(
      content.indexOf('// Load evaluation'),
      content.indexOf('if ($evaluation) {')
    );
    expect(evalLoadSection).toContain('ae.created_by');
    expect(evalLoadSection).not.toContain('catch (PDOException');
  });

  test('media query uses created_at directly', () => {
    const content = readFile('views/evaluations_skills.php');
    const mediaSection = content.substring(
      content.indexOf('// Load media for this evaluation'),
      content.indexOf('// Index media by score_id')
    );
    expect(mediaSection).toContain('ORDER BY created_at DESC');
    expect(mediaSection).not.toContain('catch (PDOException');
  });
});

// =====================================================
// 2. workouts.php - SQL query uses correct columns
// =====================================================

test.describe('workouts.php - SQL query uses correct schema columns', () => {
  test('workouts query uses coach_id and assigned_date directly', () => {
    const content = readFile('views/workouts.php');
    const workoutsSection = content.substring(
      content.indexOf('// Get workouts'),
      content.indexOf('// Get simple workouts')
    );
    expect(workoutsSection).toContain('uw.coach_id');
    expect(workoutsSection).toContain('uw.assigned_date');
  });

  test('workouts query has no PDO fallback', () => {
    const content = readFile('views/workouts.php');
    const workoutsSection = content.substring(
      content.indexOf('// Get workouts'),
      content.indexOf('// Get simple workouts')
    );
    expect(workoutsSection).not.toContain('catch (PDOException');
    expect(workoutsSection).not.toContain('fallback');
  });
});

// =====================================================
// 3. stats.php - $isAnyCoach for button visibility
// =====================================================

test.describe('stats.php - admin/coach access with $isAnyCoach', () => {
  test('athlete list query uses $isAnyCoach', () => {
    const content = readFile('views/stats.php');
    const athleteSection = content.substring(
      content.indexOf('// Get athlete list for coaches'),
      content.indexOf('try {')
    );
    expect(athleteSection).toContain('$isAnyCoach');
    expect(athleteSection).not.toMatch(/if \(\$isCoach\)/);
  });

  test('athlete selector dropdown uses $isAnyCoach', () => {
    const content = readFile('views/stats.php');
    expect(content).toContain('$isAnyCoach && count($athletes) > 0');
  });

  test('goal create button uses $isAnyCoach condition', () => {
    const content = readFile('views/stats.php');
    // Goal tab create button
    const goalsTab = content.substring(
      content.indexOf('<!-- TAB 1: Goal Tracker -->'),
      content.indexOf('<!-- TAB 2: Performance Stats -->')
    );
    expect(goalsTab).toContain('$isAnyCoach || $viewing_athlete_id == $user_id');
    expect(goalsTab).not.toMatch(/\$isCoach \|\| \$viewing_athlete_id/);
  });

  test('performance stats add buttons use $isAnyCoach condition', () => {
    const content = readFile('views/stats.php');
    // Performance tab buttons
    const perfTab = content.substring(
      content.indexOf('<!-- TAB 2: Performance Stats -->'),
      content.indexOf('</div><!-- end stats-content -->')
    );
    expect(perfTab).toContain('$isAnyCoach || $viewing_athlete_id == $user_id');
    expect(perfTab).not.toMatch(/\$isCoach \|\| \$viewing_athlete_id/);
  });

  test('JS isCoach variable uses $isAnyCoach', () => {
    const content = readFile('views/stats.php');
    expect(content).toContain('const isCoach = <?php echo json_encode($isAnyCoach); ?>');
  });

  test('no standalone $isCoach conditions remain for button visibility', () => {
    const content = readFile('views/stats.php');
    // The only remaining $isCoach should be on line 8 which already includes $isAdmin
    const matches = content.match(/\$isCoach \|\| \$viewing_athlete_id/g);
    expect(matches).toBeNull();
  });
});

// =====================================================
// 4. evaluations_skills.php - $isAnyCoach for coach actions
// =====================================================

test.describe('evaluations_skills.php - admin/coach access with $isAnyCoach', () => {
  test('team mode toggle uses $isAnyCoach', () => {
    const content = readFile('views/evaluations_skills.php');
    expect(content).toContain("$team_mode = isset($_GET['team_mode']) && $_GET['team_mode'] === '1' && $isAnyCoach");
  });

  test('viewing athlete selection uses $isAnyCoach', () => {
    const content = readFile('views/evaluations_skills.php');
    expect(content).toContain("if ($isAnyCoach && isset($_GET['athlete_id']))");
  });

  test('athlete list fetch uses $isAnyCoach', () => {
    const content = readFile('views/evaluations_skills.php');
    expect(content).toContain('if ($isAnyCoach) {');
  });

  test('no standalone $isCoach references remain in evaluations_skills.php', () => {
    const content = readFile('views/evaluations_skills.php');
    // $isCoach should be completely replaced with $isAnyCoach
    const matches = content.match(/\$isCoach(?!Any)/g);
    expect(matches).toBeNull();
  });
});

// =====================================================
// 5. athlete_goals.php - Add button click handler
// =====================================================

test.describe('athlete_goals.php - add goal button handler', () => {
  test('has JavaScript handler for add goal buttons', () => {
    const content = readFile('views/athlete_goals.php');
    expect(content).toContain('[data-action="add"][data-modal="add-goal-modal"]');
  });

  test('add handler calls openModal for add-goal-modal', () => {
    const content = readFile('views/athlete_goals.php');
    const addHandlerSection = content.substring(
      content.indexOf('[data-action="add"]'),
      content.indexOf('// Handle edit buttons')
    );
    expect(addHandlerSection).toContain("openModal('add-goal-modal')");
  });

  test('add handler resets form before opening', () => {
    const content = readFile('views/athlete_goals.php');
    const addHandlerSection = content.substring(
      content.indexOf('[data-action="add"]'),
      content.indexOf('// Handle edit buttons')
    );
    expect(addHandlerSection).toContain('form.reset()');
  });

  test('has handlers for all three button types: add, edit, progress', () => {
    const content = readFile('views/athlete_goals.php');
    expect(content).toContain('[data-action="add"]');
    expect(content).toContain('[data-action="edit"]');
    expect(content).toContain('[data-action="update-progress"]');
  });

  test('add-goal-modal exists in the HTML', () => {
    const content = readFile('views/athlete_goals.php');
    expect(content).toContain('id="add-goal-modal"');
    expect(content).toContain('id="add-goal-form"');
  });
});
