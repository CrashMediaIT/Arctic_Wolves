/**
 * Gamesheets Feature Tests
 *
 * Verifies:
 * 1. gp_gamesheets.php view file exists and has correct structure
 * 2. gameplan.php routing includes gamesheets page
 * 3. gameplan.php sidebar has Gamesheets navigation link
 * 4. PWA gameplan.php includes gamesheets in routing and navigation
 * 5. Gamesheets view has filtering/search capabilities
 * 6. Gamesheets view has detail view for individual games
 * 7. Gamesheets view uses parameterized queries for security
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');
const fileExists = (rel) => fs.existsSync(path.join(ROOT, rel));

// =====================================================
// 1. View file exists
// =====================================================
test.describe('Gamesheets view file', () => {
  test('gp_gamesheets.php exists', () => {
    expect(fileExists('views/gameplan/gp_gamesheets.php')).toBe(true);
  });

  test('gp_gamesheets.php has proper docblock', () => {
    const content = readFile('views/gameplan/gp_gamesheets.php');
    expect(content).toContain('Gamesheets');
    expect(content).toContain('scoreboard_games');
  });
});

// =====================================================
// 2. Gameplan routing
// =====================================================
test.describe('Gamesheets routing in gameplan.php', () => {
  test('gameplan.php allowed_pages includes gamesheets', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain("'gamesheets'");
    expect(content).toContain('gp_gamesheets.php');
  });

  test('gameplan.php sidebar has Gamesheets nav link', () => {
    const content = readFile('gameplan.php');
    expect(content).toContain("page=gamesheets");
    expect(content).toContain("Gamesheets");
    expect(content).toContain("fa-file-alt");
  });
});

// =====================================================
// 3. PWA routing
// =====================================================
test.describe('Gamesheets routing in PWA gameplan', () => {
  test('PWA gameplan.php gp_views includes gamesheets', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain("'gamesheets'");
    expect(content).toContain('gp_gamesheets.php');
  });

  test('PWA gameplan.php navigation grid has Gamesheets card', () => {
    const content = readFile('views/pwa/gameplan.php');
    expect(content).toContain("gp=gamesheets");
    expect(content).toContain("Gamesheets");
  });
});

// =====================================================
// 4. Filter controls
// =====================================================
test.describe('Gamesheets filter controls', () => {
  const content = () => readFile('views/gameplan/gp_gamesheets.php');

  test('has team name filter', () => {
    expect(content()).toContain('name="team"');
    expect(content()).toContain('All Teams');
  });

  test('has age group filter', () => {
    expect(content()).toContain('name="age_group"');
    expect(content()).toContain('All Age Groups');
  });

  test('has season filter', () => {
    expect(content()).toContain('name="season_id"');
    expect(content()).toContain('All Seasons');
  });

  test('has player filter', () => {
    expect(content()).toContain('name="player"');
    expect(content()).toContain('Player name');
  });

  test('has date range filters (from/to)', () => {
    expect(content()).toContain('name="date_from"');
    expect(content()).toContain('name="date_to"');
    expect(content()).toContain('type="date"');
  });

  test('has free text search', () => {
    expect(content()).toContain('name="q"');
    expect(content()).toContain('Search');
  });

  test('has clear filters button', () => {
    expect(content()).toContain('page=gamesheets');
    expect(content()).toContain('Clear');
  });
});

// =====================================================
// 5. Games list display
// =====================================================
test.describe('Gamesheets list display', () => {
  const content = () => readFile('views/gameplan/gp_gamesheets.php');

  test('queries only final games', () => {
    expect(content()).toContain("status = 'final'");
  });

  test('displays home and away team names', () => {
    expect(content()).toContain('home_team_name');
    expect(content()).toContain('away_team_name');
  });

  test('displays score', () => {
    expect(content()).toContain('home_score');
    expect(content()).toContain('away_score');
  });

  test('displays shots on goal', () => {
    expect(content()).toContain('home_shots');
    expect(content()).toContain('away_shots');
  });

  test('has empty state when no games found', () => {
    expect(content()).toContain('No Gamesheets Found');
  });

  test('has view link for individual game', () => {
    expect(content()).toContain('game_id=');
    expect(content()).toContain('View');
  });

  test('has pagination', () => {
    expect(content()).toContain('gs_total_pages');
    expect(content()).toContain('Prev');
    expect(content()).toContain('Next');
  });
});

// =====================================================
// 6. Detail view
// =====================================================
test.describe('Gamesheets detail view', () => {
  const content = () => readFile('views/gameplan/gp_gamesheets.php');

  test('has detail view for single game', () => {
    expect(content()).toContain('gs_detail_game');
    expect(content()).toContain('gs_game_detail_id');
  });

  test('detail view shows goals table', () => {
    expect(content()).toContain('gs_detail_goals');
    expect(content()).toContain('scorer_name');
    expect(content()).toContain('assist1_name');
    expect(content()).toContain('assist2_name');
    expect(content()).toContain('goal_type');
  });

  test('detail view shows penalties table', () => {
    expect(content()).toContain('gs_detail_penalties');
    expect(content()).toContain('infraction');
    expect(content()).toContain('duration_minutes');
    expect(content()).toContain('player_name');
  });

  test('detail view has back to list link', () => {
    expect(content()).toContain('Back to Gamesheets');
    expect(content()).toContain('page=gamesheets');
  });

  test('detail view shows game info (score, shots, periods)', () => {
    expect(content()).toContain('Final Score');
    expect(content()).toContain('Shots on Goal');
    expect(content()).toContain('current_period');
  });
});

// =====================================================
// 7. Security – parameterized queries
// =====================================================
test.describe('Gamesheets security', () => {
  const content = () => readFile('views/gameplan/gp_gamesheets.php');

  test('uses prepared statements for game queries', () => {
    expect(content()).toContain('$pdo->prepare(');
    expect(content()).toContain('$stmt->execute(');
  });

  test('uses htmlspecialchars for output', () => {
    const c = content();
    const matches = c.match(/htmlspecialchars/g);
    expect(matches).not.toBeNull();
    expect(matches.length).toBeGreaterThan(10);
  });

  test('validates date format parameters', () => {
    expect(content()).toContain("preg_match('/^\\d{4}-\\d{2}-\\d{2}$/'");
  });

  test('casts integer parameters', () => {
    expect(content()).toContain('(int)$_GET');
  });
});
