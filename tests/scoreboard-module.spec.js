import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Scoreboard Module Tests
 * Tests for the scoreboard.arcticwolves.ca module:
 * 1. Subdomain routing in index.php, login.php, register.php
 * 2. Staff-only access control in scoreboard.php
 * 3. POS IP restriction in scoreboard.php and process_scoreboard.php
 * 4. Database schema tables
 * 5. View structure (scoreboard, scoresheet, video board)
 * 6. Process file AJAX/CSRF security
 * 7. Game Plan sync capability
 * 8. Music/audio integration points
 * 9. CSS file exists
 * 10. JS file exists
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

function fileExists(relativePath) {
  return fs.existsSync(path.join(ROOT, relativePath));
}

// =====================================================
// 1. Subdomain Routing
// =====================================================

test.describe('Scoreboard subdomain routing', () => {
  test('index.php detects scoreboard subdomain', () => {
    const content = readFile('index.php');
    expect(content).toContain('isScoreboardSubdomain');
    expect(content).toContain('scoreboard.arcticwolves.ca');
    expect(content).toContain('scoreboard.php');
  });

  test('login.php detects scoreboard subdomain', () => {
    const content = readFile('login.php');
    expect(content).toContain('isScoreboardSubdomain');
    expect(content).toContain('scoreboard.arcticwolves.ca');
  });

  test('register.php detects scoreboard subdomain and redirects', () => {
    const content = readFile('register.php');
    expect(content).toContain('isScoreboardSubdomain');
    expect(content).toContain('scoreboard.php');
  });
});

// =====================================================
// 2. Staff-Only Access Control
// =====================================================

test.describe('Scoreboard staff-only access', () => {
  test('scoreboard.php checks isStaff before allowing access', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('isStaff');
    expect(content).toContain('restricted to staff accounts');
    // Must not render scoreboard content if not staff
    expect(content).toContain('if (!$isStaff)');
  });

  test('scoreboard.php requires login session', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain("if (!isset($_SESSION['logged_in']))");
    expect(content).toContain('login.php');
  });

  test('scoreboard.php includes role checks for all staff roles', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('$isAdmin');
    expect(content).toContain('$isCoach');
    expect(content).toContain('$isHealthCoach');
    expect(content).toContain('$isFrontDesk');
    expect(content).toContain('$isHR');
    expect(content).toContain('$isAccounting');
  });
});

// =====================================================
// 3. POS IP Restriction
// =====================================================

test.describe('Scoreboard POS IP restriction', () => {
  test('scoreboard.php calls checkPOSIPAccess', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('scoreboard_ip_blocked');
  });

  test('process_scoreboard.php calls checkPOSIPAccess', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('scoreboard_ip_blocked');
  });
});

// =====================================================
// 4. Database Schema
// =====================================================

test.describe('Scoreboard database schema', () => {
  test('database_schema.sql has scoreboard_games table', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `scoreboard_games`');
    expect(content).toContain('home_team_name');
    expect(content).toContain('away_team_name');
    expect(content).toContain('home_score');
    expect(content).toContain('away_score');
    expect(content).toContain('current_period');
    expect(content).toContain('is_arctic_wolves_game');
    expect(content).toContain('synced_to_gameplan');
  });

  test('database_schema.sql has scoreboard_goals table', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `scoreboard_goals`');
    expect(content).toContain('scorer_number');
    expect(content).toContain('scorer_name');
    expect(content).toContain('assist1_name');
    expect(content).toContain('assist2_name');
    expect(content).toContain('goal_type');
  });

  test('database_schema.sql has scoreboard_penalties table', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `scoreboard_penalties`');
    expect(content).toContain('infraction');
    expect(content).toContain('duration_minutes');
    expect(content).toContain('player_number');
  });

  test('database_schema.sql has scoreboard_shots table', () => {
    const content = readFile('database_schema.sql');
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `scoreboard_shots`');
  });
});

// =====================================================
// 5. View Structure
// =====================================================

test.describe('Scoreboard view structure', () => {
  test('scoreboard.php supports three view modes', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain("'scoreboard'");
    expect(content).toContain("'scoresheet'");
    expect(content).toContain("'video_board'");
  });

  test('scoreboard display view exists', () => {
    expect(fileExists('views/scoreboard/scoreboard_display.php')).toBe(true);
  });

  test('scoresheet view exists', () => {
    expect(fileExists('views/scoreboard/scoresheet.php')).toBe(true);
  });

  test('video board view exists', () => {
    expect(fileExists('views/scoreboard/video_board.php')).toBe(true);
  });

  test('scoreboard display has goal tracking controls', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbAddGoal');
    expect(content).toContain('sbAddShot');
    expect(content).toContain('Home Goal');
    expect(content).toContain('Away Goal');
  });

  test('scoreboard display has penalty tracking', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbShowPenaltyModal');
    expect(content).toContain('Penalty');
  });

  test('scoreboard display has buzzer button', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbBuzzer');
    expect(content).toContain('BUZZER');
  });

  test('scoreboard display has music controls', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbSpotifyConnect');
    expect(content).toContain('sbSubsonicBrowse');
    expect(content).toContain('sbToggleMic');
    expect(content).toContain('sbSpeakerSettings');
  });

  test('scoreboard display has video board switch button', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('view=video_board');
    expect(content).toContain('Video Board');
  });

  test('video board has source selection buttons', () => {
    const content = readFile('views/scoreboard/video_board.php');
    expect(content).toContain('Pregame Hype');
    expect(content).toContain('In-Game Promo');
    expect(content).toContain('Arena Cam');
    expect(content).toContain('Browser Video');
    expect(content).toContain('Broadcast Feed');
  });

  test('video board has back to scoreboard button', () => {
    const content = readFile('views/scoreboard/video_board.php');
    expect(content).toContain('view=scoreboard');
    expect(content).toContain('Back to Scoreboard');
  });

  test('scoresheet view has goal details table', () => {
    const content = readFile('views/scoreboard/scoresheet.php');
    expect(content).toContain('Scorer');
    expect(content).toContain('Assist 1');
    expect(content).toContain('Assist 2');
    expect(content).toContain('Period');
    expect(content).toContain('Time');
  });

  test('scoresheet view has Game Plan sync button for AW games', () => {
    const content = readFile('views/scoreboard/scoresheet.php');
    expect(content).toContain('sbSyncToGamePlan');
    expect(content).toContain('Sync to Game Plan');
    expect(content).toContain('isAWGame');
  });
});

// =====================================================
// 6. Process File Security
// =====================================================

test.describe('Process scoreboard security', () => {
  test('process_scoreboard.php exists', () => {
    expect(fileExists('process_scoreboard.php')).toBe(true);
  });

  test('process_scoreboard.php checks session authentication', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("if (!isset($_SESSION['user_id']))");
    expect(content).toContain('401');
  });

  test('process_scoreboard.php validates CSRF token', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('CSRFProtection::validateToken');
    expect(content).toContain('HTTP_X_CSRF_TOKEN');
  });

  test('process_scoreboard.php checks for AJAX request', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('isAjaxRequest');
    expect(content).toContain('xmlhttprequest');
  });

  test('process_scoreboard.php enforces staff-only access', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('$isStaff');
    expect(content).toContain('Staff access required');
    expect(content).toContain('403');
  });
});

// =====================================================
// 7. Game Plan Integration
// =====================================================

test.describe('Scoreboard Game Plan integration', () => {
  test('process_scoreboard.php has sync_to_gameplan action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("'sync_to_gameplan'");
    expect(content).toContain('vr_game_plans');
    expect(content).toContain('athlete_stats');
  });

  test('sync updates goals, assists, and penalty minutes', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('goals = goals + 1');
    expect(content).toContain('assists = assists + 1');
    expect(content).toContain('points = points + 1');
    expect(content).toContain('penalty_minutes = penalty_minutes');
  });

  test('sync marks game as synced_to_gameplan', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('synced_to_gameplan = 1');
  });
});

// =====================================================
// 8. Music/Audio Integration
// =====================================================

test.describe('Scoreboard music and audio', () => {
  test('scoreboard.php checks Spotify configuration', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('spotify_configured');
    expect(content).toContain('spotify_client_id');
  });

  test('scoreboard.php checks Subsonic configuration', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('subsonic_configured');
    expect(content).toContain('subsonic_url');
  });

  test('JS has buzzer function using Web Audio API', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbBuzzer');
    expect(content).toContain('AudioContext');
    expect(content).toContain('createOscillator');
  });

  test('JS has mic toggle function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbToggleMic');
    expect(content).toContain('getUserMedia');
  });

  test('JS has wireless speaker settings function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbSpeakerSettings');
  });
});

// =====================================================
// 9. Static Assets
// =====================================================

test.describe('Scoreboard static assets', () => {
  test('CSS file exists', () => {
    expect(fileExists('css/scoreboard.css')).toBe(true);
  });

  test('JS file exists', () => {
    expect(fileExists('js/scoreboard.js')).toBe(true);
  });

  test('CSS has professional scoreboard layout classes', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-body');
    expect(content).toContain('.sb-team-score');
    expect(content).toContain('.sb-game-clock');
    expect(content).toContain('.sb-buzzer-btn');
    expect(content).toContain('.sb-video-board');
  });
});

// =====================================================
// 10. Not linked in main navigation
// =====================================================

test.describe('Scoreboard not in main navigation', () => {
  test('dashboard.php does not link to scoreboard', () => {
    const content = readFile('dashboard.php');
    expect(content).not.toContain("'scoreboard'");
    // Make sure there's no scoreboard route in the allowed_pages array
    expect(content).not.toMatch(/['"]scoreboard['"].*=>.*views/);
  });

  test('pwa.php does not link to scoreboard', () => {
    const content = readFile('pwa.php');
    expect(content).not.toContain("'scoreboard'");
  });

  test('pwa_tablet.php does not link to scoreboard', () => {
    const content = readFile('pwa_tablet.php');
    expect(content).not.toContain("'scoreboard'");
  });

  test('pwa_more_menu.php does not link to scoreboard', () => {
    const content = readFile('pwa_more_menu.php');
    expect(content).not.toContain('scoreboard');
  });
});

// =====================================================
// 11. Video Board Mode
// =====================================================

test.describe('Video board mode', () => {
  test('JS has video loading functions', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbLoadVideo');
    expect(content).toContain('sbStopVideo');
    expect(content).toContain('sbLoadBrowserVideo');
  });

  test('JS supports pregame, ingame promo, arena cam, broadcast sources', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain("'pregame'");
    expect(content).toContain("'ingame_promo'");
    expect(content).toContain("'arena_cam'");
    expect(content).toContain("'broadcast'");
  });

  test('JS handles YouTube and Vimeo URL detection for browser video', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('youtube.com');
    expect(content).toContain('vimeo.com');
  });

  test('Arena cam uses getUserMedia for camera and mic', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('getUserMedia');
    expect(content).toContain('video: true');
    expect(content).toContain('audio: true');
  });
});
