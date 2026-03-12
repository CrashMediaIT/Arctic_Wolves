import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for PR 626 fixes:
 * 1. evaluations_skills.php - SQL error with evaluation_id wrapped in try-catch
 * 2. workouts.php - SQL error with coach_id wrapped in try-catch
 * 3. stats.php - $isAnyCoach used instead of $isCoach for button visibility
 * 4. evaluations_skills.php - $isAnyCoach used instead of $isCoach for coach actions
 * 5. athlete_goals.php - Add button JavaScript handler added
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. evaluations_skills.php - SQL error with evaluation_id
// =====================================================

test.describe('evaluations_skills.php - evaluation_id SQL fix', () => {
  test('evaluations list query is wrapped in try-catch', () => {
    const content = readFile('views/evaluations_skills.php');
    // Find the section with the evaluations list query
    const evalListSection = content.substring(
      content.indexOf('// Get all evaluations list'),
      content.indexOf('// Get historical evaluations')
    );
    expect(evalListSection).toContain('try {');
    expect(evalListSection).toContain('} catch (PDOException $e) {');
  });

  test('evaluations list fallback query does not reference evaluation_id', () => {
    const content = readFile('views/evaluations_skills.php');
    const evalListSection = content.substring(
      content.indexOf('// Get all evaluations list'),
      content.indexOf('// Get historical evaluations')
    );
    // Should have a fallback with 0 as completed_scores and total_scores
    expect(evalListSection).toContain('0 as completed_scores');
    expect(evalListSection).toContain('0 as total_scores');
  });

  test('scores query is wrapped in try-catch', () => {
    const content = readFile('views/evaluations_skills.php');
    const scoresSection = content.substring(
      content.indexOf('// Load scores'),
      content.indexOf('// Group by category')
    );
    expect(scoresSection).toContain('try {');
    expect(scoresSection).toContain('} catch (PDOException $e) {');
  });

  test('historical query is wrapped in try-catch', () => {
    const content = readFile('views/evaluations_skills.php');
    const histSection = content.substring(
      content.indexOf('// Get historical evaluations'),
      content.indexOf('?>')
    );
    expect(histSection).toContain('try {');
    expect(histSection).toContain('} catch (PDOException $e) {');
  });
});

// =====================================================
// 2. workouts.php - SQL error with coach_id
// =====================================================

test.describe('workouts.php - coach_id SQL fix', () => {
  test('workouts query is wrapped in try-catch', () => {
    const content = readFile('views/workouts.php');
    const workoutsSection = content.substring(
      content.indexOf('// Get workouts'),
      content.indexOf('// Get simple workouts')
    );
    expect(workoutsSection).toContain('try {');
    expect(workoutsSection).toContain('} catch (PDOException $e) {');
  });

  test('fallback query does not reference coach_id', () => {
    const content = readFile('views/workouts.php');
    const workoutsSection = content.substring(
      content.indexOf('// Get workouts'),
      content.indexOf('// Get simple workouts')
    );
    // The fallback should use NULL as coach_first/last
    expect(workoutsSection).toContain('NULL as coach_first');
    expect(workoutsSection).toContain('NULL as coach_last');
  });

  test('fallback query does not JOIN on coach_id', () => {
    const content = readFile('views/workouts.php');
    const workoutsSection = content.substring(
      content.indexOf('// Get workouts'),
      content.indexOf('// Get simple workouts')
    );
    // Extract the fallback query (after the catch)
    const fallbackSection = workoutsSection.substring(
      workoutsSection.indexOf('catch (PDOException')
    );
    expect(fallbackSection).not.toContain('LEFT JOIN users coach ON uw.coach_id');
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
