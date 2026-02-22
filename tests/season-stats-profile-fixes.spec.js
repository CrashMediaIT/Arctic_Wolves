import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for Season Stats and Profile Season Assignment fixes:
 * 1. Season stats JOIN seasons table so season_id resolves to name
 * 2. Profile "Select from Roster" uses typeahead instead of dropdown
 * 3. Profile "Add New Team" uses season typeahead to link existing seasons
 * 4. New ajax_search_seasons.php endpoint for season typeahead
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Season stats now JOIN seasons table
// =====================================================

test.describe('Season Stats - JOIN seasons table', () => {
  test('stats.php JOINs seasons table to resolve season_id', () => {
    const content = readFile('views/stats.php');
    const statsSection = content.substring(
      content.indexOf('$athleteStats = []'),
      content.indexOf('$allGoals = []')
    );
    expect(statsSection).toContain('LEFT JOIN seasons');
    expect(statsSection).toContain('ast.season_id = s.id');
  });

  test('stats.php uses COALESCE to prefer text season over season_id name', () => {
    const content = readFile('views/stats.php');
    const statsSection = content.substring(
      content.indexOf('$athleteStats = []'),
      content.indexOf('$allGoals = []')
    );
    expect(statsSection).toContain('COALESCE');
    expect(statsSection).toContain('s.name');
  });

  test('stats.php still orders by season DESC', () => {
    const content = readFile('views/stats.php');
    const statsSection = content.substring(
      content.indexOf('$athleteStats = []'),
      content.indexOf('$allGoals = []')
    );
    expect(statsSection).toContain('ORDER BY');
    expect(statsSection).toContain('season DESC');
  });
});

// =====================================================
// 2. Profile "Select from Roster" uses typeahead
// =====================================================

test.describe('Profile - Roster Selection Typeahead', () => {
  test('profile.php uses ArcticTypeahead for team-season selection', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('roster-team-season-typeahead');
    expect(content).toContain("new ArcticTypeahead");
    expect(content).toContain('ajax_search_teams.php');
  });

  test('profile.php no longer uses select dropdown for roster team-season', () => {
    const content = readFile('views/profile.php');
    // The roster section should not have the old select with forEach options
    const rosterSection = content.substring(
      content.indexOf('Select from Roster'),
      content.indexOf('Add New Team')
    );
    expect(rosterSection).not.toContain('<select name="roster_team_season"');
    expect(rosterSection).not.toContain('$rosterTeamOptions as $opt');
  });

  test('profile.php has hidden input for roster_team_season value', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('roster-team-season-hidden');
    expect(content).toContain('name="roster_team_season"');
  });

  test('roster typeahead sets hidden input on select', () => {
    const content = readFile('views/profile.php');
    const rosterSection = content.substring(
      content.indexOf('roster-team-season-typeahead'),
      content.indexOf('Add New Team')
    );
    expect(rosterSection).toContain("roster-team-season-hidden");
    expect(rosterSection).toContain('onSelect');
    expect(rosterSection).toContain('onChange');
  });

  test('roster select button is disabled until selection made', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('roster-select-btn');
    expect(content).toContain('disabled');
  });
});

// =====================================================
// 3. Profile "Add New Team" uses season typeahead
// =====================================================

test.describe('Profile - Add Team Season Typeahead', () => {
  test('profile.php uses typeahead for season selection in Add Team form', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('add-team-season-typeahead');
    expect(content).toContain('ajax_search_seasons.php');
  });

  test('profile.php has hidden input for season_id', () => {
    const content = readFile('views/profile.php');
    expect(content).toContain('add-team-season-id');
    expect(content).toContain('name="season_id"');
  });

  test('profile.php no longer uses manual season_year text input', () => {
    const content = readFile('views/profile.php');
    const addTeamSection = content.substring(
      content.indexOf('Add New Team'),
      content.indexOf('Performance Stats')
    );
    expect(addTeamSection).not.toContain('name="season_year"');
    expect(addTeamSection).not.toContain('name="season_type"');
  });

  test('season typeahead sets hidden season_id on select', () => {
    const content = readFile('views/profile.php');
    const addTeamSection = content.substring(
      content.indexOf('add-team-season-typeahead'),
      content.indexOf('Performance Stats')
    );
    expect(addTeamSection).toContain("add-team-season-id");
    expect(addTeamSection).toContain('onSelect');
    expect(addTeamSection).toContain('onChange');
  });
});

// =====================================================
// 4. Backend - process_profile_update.php handles season_id
// =====================================================

test.describe('Backend - Profile Update Season Handling', () => {
  test('add_team action reads season_id from POST', () => {
    const content = readFile('process_profile_update.php');
    const addTeamSection = content.substring(
      content.indexOf("action == 'add_team'"),
      content.indexOf("ACTION 4B")
    );
    expect(addTeamSection).toContain("$_POST['season_id']");
    expect(addTeamSection).toContain('season_id');
  });

  test('add_team action looks up season name from seasons table', () => {
    const content = readFile('process_profile_update.php');
    const addTeamSection = content.substring(
      content.indexOf("action == 'add_team'"),
      content.indexOf("ACTION 4B")
    );
    expect(addTeamSection).toContain("SELECT name FROM seasons WHERE id = ?");
  });

  test('add_team_from_roster handles teams without seasons', () => {
    const content = readFile('process_profile_update.php');
    const rosterSection = content.substring(
      content.indexOf("action == 'add_team_from_roster'"),
      content.indexOf("ACTION 5")
    );
    // Should handle both with and without season_id
    expect(rosterSection).toContain('$season_id > 0');
    expect(rosterSection).toContain("'' as season_name");
  });
});

// =====================================================
// 5. Ajax Season Search Endpoint
// =====================================================

test.describe('AJAX Season Search Endpoint', () => {
  test('ajax_search_seasons.php exists and is properly structured', () => {
    const content = readFile('ajax_search_seasons.php');
    expect(content).toContain("Content-Type: application/json");
    expect(content).toContain("session_start()");
    expect(content).toContain("$_SESSION['user_id']");
  });

  test('ajax_search_seasons.php requires authentication', () => {
    const content = readFile('ajax_search_seasons.php');
    expect(content).toContain('401');
    expect(content).toContain('Unauthorized');
  });

  test('ajax_search_seasons.php searches seasons by name', () => {
    const content = readFile('ajax_search_seasons.php');
    expect(content).toContain('FROM seasons');
    expect(content).toContain('s.name LIKE ?');
  });

  test('ajax_search_seasons.php returns active/inactive status', () => {
    const content = readFile('ajax_search_seasons.php');
    expect(content).toContain('is_active');
    expect(content).toContain("'Active'");
    expect(content).toContain("'Inactive'");
  });

  test('ajax_search_seasons.php returns proper JSON structure', () => {
    const content = readFile('ajax_search_seasons.php');
    expect(content).toContain("'id'");
    expect(content).toContain("'name'");
    expect(content).toContain("'role'");
    expect(content).toContain("'success' => true");
    expect(content).toContain("json_encode");
  });

  test('ajax_search_seasons.php returns all active seasons when no query', () => {
    const content = readFile('ajax_search_seasons.php');
    // When query is empty, should still return seasons ordered by active status
    expect(content).toContain('ORDER BY is_active DESC');
    expect(content).toContain('start_date DESC');
  });

  test('ajax_search_seasons.php limits results', () => {
    const content = readFile('ajax_search_seasons.php');
    expect(content).toContain('LIMIT ?');
    expect(content).toContain('$limit');
  });
});
