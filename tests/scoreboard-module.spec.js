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
    // Scoreboard has its own inline login (PIN + user login) instead of redirecting to login.php
    expect(content).toContain('scoreboard_pin_login');
    expect(content).toContain('scoreboard_user_login');
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

  test('scoreboard.php has PIN login support', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('scoreboard_pin_login');
    expect(content).toContain('staff_pins');
    expect(content).toContain('pin_hash');
    expect(content).toContain('password_verify');
  });

  test('scoreboard.php has user login support', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('scoreboard_user_login');
    expect(content).toContain('password_verify');
    expect(content).toContain("name=\"email\"");
    expect(content).toContain("name=\"password\"");
  });

  test('scoreboard.php user login uses correct password column name from users table', () => {
    const content = readFile('scoreboard.php');
    // The users table column is `password`, not `password_hash`
    // The SQL query must select the correct column
    expect(content).toMatch(/SELECT.*\bpassword\b.*FROM users/);
    // password_verify must use the correct column key
    expect(content).toContain("matchedUser['password']");
  });

  test('scoreboard.php user login checks is_verified status', () => {
    const content = readFile('scoreboard.php');
    // Should check is_verified like the main login flow
    expect(content).toContain('is_verified');
    expect(content).toContain('Account pending verification');
  });

  test('scoreboard.php login validates CSRF token', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('csrf_token');
    expect(content).toContain('validateCSRFToken');
  });

  test('login.php redirects to scoreboard.php when on scoreboard subdomain', () => {
    const content = readFile('login.php');
    expect(content).toContain('isScoreboardSubdomain');
    // After login, scoreboard subdomain should redirect to scoreboard.php
    const scoreboardRedirectIdx = content.indexOf("header(\"Location: scoreboard.php\")");
    expect(scoreboardRedirectIdx).toBeGreaterThan(-1);
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
    expect(content).toContain('validateCSRFToken');
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
    expect(content).toContain('.sb-board-score');
    expect(content).toContain('.sb-board-clock');
    expect(content).toContain('.sb-buzzer-btn');
    expect(content).toContain('.sb-video-board');
  });
});

// =====================================================
// 9b. Professional Scoreboard Features (Nevco/Daktronics)
// =====================================================

test.describe('Professional scoreboard features (Nevco/Daktronics style)', () => {
  test('CSS has dedicated penalty timer box styles', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-penalty-timer-box');
    expect(content).toContain('.sb-pen-countdown');
    expect(content).toContain('.sb-pen-player');
    expect(content).toContain('.sb-board-penalty-stack');
  });

  test('CSS has power play indicator', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-pp-indicator');
    expect(content).toContain('ppPulse');
  });

  test('CSS has goal light flash animation', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-goal-light');
    expect(content).toContain('goalFlash');
  });

  test('CSS has LED-style gold clock color', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-board-clock');
    expect(content).toContain('#FFD700');
    expect(content).toContain('text-shadow');
  });

  test('CSS has timeout indicator boxes', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-timeout-box');
  });

  test('CSS has clock control button styles', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-clock-btn');
    expect(content).toContain('.sb-clock-start');
    expect(content).toContain('.sb-clock-reset');
    expect(content).toContain('.sb-clock-start.running');
  });

  test('display view has penalty timer boxes for both teams', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-board-penalty-stack home');
    expect(content).toContain('sb-board-penalty-stack away');
    expect(content).toContain('sbHomePenTime');
    expect(content).toContain('sbAwayPenTime');
  });

  test('display view has power play indicators', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-pp-indicator');
    expect(content).toContain('$home_pp');
    expect(content).toContain('$away_pp');
  });

  test('display view has goal light overlay element', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-goal-light');
    expect(content).toContain('sbGoalLight');
  });

  test('display view has clock controls (start/stop/reset)', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbClockToggle');
    expect(content).toContain('sbClockReset');
    expect(content).toContain('sbClockStart');
  });

  test('display view has period controls (prev/next)', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbPeriodNext');
    expect(content).toContain('sbPeriodPrev');
    expect(content).toContain('Next Period');
    expect(content).toContain('Prev Period');
  });

  test('display view has intermission status button', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain("sbSetStatus('intermission')");
    expect(content).toContain('Intermission');
  });

  test('display view has timeout indicators for both teams', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbHomeTimeout');
    expect(content).toContain('sbAwayTimeout');
    expect(content).toContain('T/O');
  });

  test('display view has SOG stats for both teams', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbHomeShots');
    expect(content).toContain('sbAwayShots');
    expect(content).toContain('SOG');
  });

  test('display view uses 7-column grid layout (Nevco style)', () => {
    const css = readFile('css/scoreboard.css');
    expect(css).toContain('grid-template-columns: 1fr auto auto 1fr auto auto 1fr');
  });
});

// =====================================================
// 9c. Game Clock JavaScript
// =====================================================

test.describe('Game clock JavaScript (Nevco/Daktronics style)', () => {
  test('JS has working game clock with countdown', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbClockSeconds');
    expect(content).toContain('sbClockRunning');
    expect(content).toContain('sbClockTick');
    expect(content).toContain('sbFormatClock');
  });

  test('JS has clock start/stop/toggle/reset functions', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbClockStart()');
    expect(content).toContain('function sbClockStop()');
    expect(content).toContain('function sbClockToggle()');
    expect(content).toContain('function sbClockReset()');
  });

  test('JS clock defaults to 20 minutes regulation, 5 minutes OT', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('REGULATION_PERIOD_SECS');
    expect(content).toContain('OVERTIME_PERIOD_SECS');
    expect(content).toContain('20 * 60');
    expect(content).toContain('5 * 60');
  });

  test('JS has real-time penalty countdown timers', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbPenaltyTimers');
    expect(content).toContain('sbTickPenaltyTimers');
    expect(content).toContain('sbInitPenaltyTimers');
  });

  test('JS penalty timers tick when game clock runs', () => {
    const content = readFile('js/scoreboard.js');
    // sbClockTick should call sbTickPenaltyTimers
    expect(content).toContain('sbTickPenaltyTimers()');
  });

  test('JS has period management (next/prev/update)', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbPeriodNext()');
    expect(content).toContain('function sbPeriodPrev()');
    expect(content).toContain('sbCurrentPeriod');
  });

  test('JS uses period duration helper for OT', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbGetPeriodDuration');
    expect(content).toContain('OVERTIME_PERIOD_SECS');
  });

  test('JS has goal light flash function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbFlashGoalLight()');
    expect(content).toContain('sbGoalLight');
  });

  test('JS triggers goal light and buzzer on goal', () => {
    const content = readFile('js/scoreboard.js');
    // Inside sbAddGoal, should call sbFlashGoalLight and sbBuzzer
    expect(content).toContain('sbFlashGoalLight()');
  });

  test('JS auto-buzzer at end of period', () => {
    const content = readFile('js/scoreboard.js');
    // When clock reaches 0, should fire buzzer
    const clockTickFn = content.substring(
      content.indexOf('function sbClockTick()'),
      content.indexOf('function sbClockTick()') + 500
    );
    expect(clockTickFn).toContain('sbBuzzer()');
  });

  test('JS updates status display for intermission', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetStatus(');
    expect(content).toContain('update_status');
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

// =====================================================
// 12. Recurring Timed Buzzer
// =====================================================

test.describe('Recurring timed buzzer', () => {
  test('JS has recurring buzzer state variables', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbRecurringBuzzerInterval');
    expect(content).toContain('sbRecurringBuzzerCountdown');
    expect(content).toContain('sbRecurringBuzzerActive');
  });

  test('JS has sbSetRecurringBuzzer function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetRecurringBuzzer(');
  });

  test('JS has sbToggleRecurringBuzzer function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbToggleRecurringBuzzer(');
  });

  test('JS has sbTickRecurringBuzzer function that fires buzzer', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbTickRecurringBuzzer(');
    // When countdown reaches 0, should fire sbBuzzer()
    const fn = content.substring(
      content.indexOf('function sbTickRecurringBuzzer()'),
      content.indexOf('function sbTickRecurringBuzzer()') + 400
    );
    expect(fn).toContain('sbBuzzer()');
  });

  test('JS recurring buzzer ticks with game clock', () => {
    const content = readFile('js/scoreboard.js');
    const clockTickFn = content.substring(
      content.indexOf('function sbClockTick()'),
      content.indexOf('function sbClockTick()') + 500
    );
    expect(clockTickFn).toContain('sbTickRecurringBuzzer()');
  });

  test('JS has sbUpdateRecurringBuzzerDisplay function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbUpdateRecurringBuzzerDisplay(');
    expect(content).toContain('sbRecurringStatus');
    expect(content).toContain('sbRecurringCountdown');
    expect(content).toContain('sbRecurringToggle');
  });

  test('display view has recurring buzzer controls with interval presets', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbRecurringSelect');
    expect(content).toContain('sbSetRecurringBuzzer');
    expect(content).toContain('sbToggleRecurringBuzzer');
    expect(content).toContain('Recurring Buzzer');
  });

  test('display view has U7 and U9 preset intervals', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('1:30 (U7)');
    expect(content).toContain('2:00 (U7/U9)');
    expect(content).toContain('value="90"');
    expect(content).toContain('value="120"');
  });

  test('display view has recurring buzzer status and countdown display', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbRecurringStatus');
    expect(content).toContain('sbRecurringCountdown');
    expect(content).toContain('sbRecurringToggle');
  });

  test('CSS has recurring buzzer styles', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-recurring-buzzer');
    expect(content).toContain('.sb-recurring-controls');
    expect(content).toContain('.sb-recurring-status');
    expect(content).toContain('.sb-recurring-countdown');
    expect(content).toContain('.sb-recurring-toggle');
  });
});

// =====================================================
// 13. Penalty Display Visibility Toggle
// =====================================================

test.describe('Penalty display visibility toggle', () => {
  test('JS has sbPenaltiesHidden state and sbTogglePenaltyDisplay function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbPenaltiesHidden');
    expect(content).toContain('function sbTogglePenaltyDisplay(');
  });

  test('JS toggle adds sb-hidden-from-display class to penalty stacks', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sb-hidden-from-display');
    expect(content).toContain('sb-board-penalty-stack');
    expect(content).toContain('sb-pp-indicator');
  });

  test('JS toggle updates button text and state', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbPenaltyDisplayToggle');
    expect(content).toContain('sb-toggle-hidden');
    expect(content).toContain('Penalties Hidden from Board');
    expect(content).toContain('Penalties Shown on Board');
  });

  test('display view has penalty display toggle button', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbPenaltyDisplayToggle');
    expect(content).toContain('sbTogglePenaltyDisplay');
    expect(content).toContain('Penalties Shown on Board');
  });

  test('CSS has sb-hidden-from-display class', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-hidden-from-display');
    expect(content).toContain('opacity');
  });

  test('CSS has penalty toggle button styling', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-penalty-display-toggle');
    expect(content).toContain('.sb-penalty-toggle-btn');
    expect(content).toContain('.sb-toggle-hidden');
  });
});

// =====================================================
// 14. Adjustable Period Times
// =====================================================

test.describe('Adjustable period times', () => {
  test('JS has sbSetPeriodTime function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetPeriodTime(');
  });

  test('JS has sbSetOvertimeTime function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetOvertimeTime(');
  });

  test('JS sbSetPeriodTime updates REGULATION_PERIOD_SECS and resets clock', () => {
    const content = readFile('js/scoreboard.js');
    const fn = content.substring(
      content.indexOf('function sbSetPeriodTime('),
      content.indexOf('function sbSetPeriodTime(') + 500
    );
    expect(fn).toContain('REGULATION_PERIOD_SECS');
    expect(fn).toContain('sbUpdateClockDisplay');
  });

  test('JS sbSetOvertimeTime updates OVERTIME_PERIOD_SECS', () => {
    const content = readFile('js/scoreboard.js');
    const fn = content.substring(
      content.indexOf('function sbSetOvertimeTime('),
      content.indexOf('function sbSetOvertimeTime(') + 500
    );
    expect(fn).toContain('OVERTIME_PERIOD_SECS');
  });

  test('JS sbSetPeriodTime updates reset button label', () => {
    const content = readFile('js/scoreboard.js');
    const fn = content.substring(
      content.indexOf('function sbSetPeriodTime('),
      content.indexOf('function sbSetPeriodTime(') + 500
    );
    expect(fn).toContain('sb-clock-reset');
    expect(fn).toContain('Reset');
  });

  test('display view has period time select with multiple presets', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbPeriodTimeSelect');
    expect(content).toContain('sbSetPeriodTime');
    expect(content).toContain('Period Length');
  });

  test('display view period select includes youth and standard options', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    // Youth options
    expect(content).toContain('1:00 (U5)');
    expect(content).toContain('value="10"');
    expect(content).toContain('value="12"');
    expect(content).toContain('value="15"');
    // Standard
    expect(content).toContain('20:00 (Default)');
    expect(content).toContain('value="25"');
  });

  test('display view has overtime time select', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbOTTimeSelect');
    expect(content).toContain('sbSetOvertimeTime');
    expect(content).toContain('OT Length');
  });

  test('CSS has period time config styles', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-period-time-config');
    expect(content).toContain('.sb-config-label');
    expect(content).toContain('.sb-config-select');
  });
});

// =====================================================
// 15. Reorganized 4-Column Operator Layout
// =====================================================

test.describe('Reorganized operator controls layout', () => {
  test('display view uses 4-column operator grid', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-controls-grid');
    expect(content).toContain('grid-template-columns: 1fr 1fr 1fr 1fr');
  });

  test('display view has Home team panel with team name', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('Home —');
    expect(content).toContain('+1 Home Goal');
    expect(content).toContain('+1 Shot');
    expect(content).toContain('Add Home Penalty');
  });

  test('display view has Away team panel with team name', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('Away —');
    expect(content).toContain('+1 Away Goal');
    expect(content).toContain('Add Away Penalty');
  });

  test('display view has Clock & Period panel', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('Clock &amp; Period');
    expect(content).toContain('Period Navigation');
    expect(content).toContain('Recurring Buzzer');
    expect(content).toContain('BUZZER');
    expect(content).toContain('GOAL HORN');
  });

  test('display view has Music & Audio panel', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('Music &amp; Audio');
    expect(content).toContain('Music Library');
    expect(content).toContain('Now Playing');
    expect(content).toContain('Announce');
  });

  test('display view has responsive breakpoints for tablet/mobile', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('max-width: 1200px');
    expect(content).toContain('max-width: 640px');
  });
});

// =====================================================
// 16. Score & Shot Edit Modals
// =====================================================

test.describe('Score and shot edit modals', () => {
  test('display view has Score Edit modal', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-score-edit-modal');
    expect(content).toContain('Edit Score');
    expect(content).toContain('sbScoreEditTeam');
    expect(content).toContain('sbScoreEditValue');
  });

  test('display view has Shot Edit modal', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-shot-edit-modal');
    expect(content).toContain('Edit Shots');
    expect(content).toContain('sbShotEditTeam');
    expect(content).toContain('sbShotEditValue');
  });

  test('display view has Edit Score buttons for both teams', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain("sbShowScoreEdit('home')");
    expect(content).toContain("sbShowScoreEdit('away')");
  });

  test('display view has Edit Shot buttons for both teams', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain("sbShowShotEdit('home')");
    expect(content).toContain("sbShowShotEdit('away')");
  });

  test('display view has score adjust helpers (-1 and reset)', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbScoreEditAdjust(-1)');
    expect(content).toContain('Reset to 0');
  });

  test('JS has sbSetScore function for direct score setting', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetScore(');
    expect(content).toContain('set_score');
  });

  test('JS has sbSetShots function for direct shot setting', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetShots(');
    expect(content).toContain('set_shots');
  });
});

// =====================================================
// 17. Audio Settings Modal & Announce
// =====================================================

test.describe('Audio settings and announce', () => {
  test('display view has Audio Settings modal with mic/speaker selects', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-audio-settings-modal');
    expect(content).toContain('Audio Settings');
    expect(content).toContain('sbAudioMicSelect');
    expect(content).toContain('sbAudioSpeakerSelect');
    expect(content).toContain('Microphone Input');
    expect(content).toContain('Speaker Output');
  });

  test('display view has music volume slider', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbAudioMusicVolume');
    expect(content).toContain('Music Volume');
  });

  test('display view has Announce button', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbAnnounceBtn');
    expect(content).toContain('sbToggleAnnounce');
    expect(content).toContain('Announce');
  });

  test('display view audio settings enumerates devices', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('enumerateDevices');
    expect(content).toContain('audioinput');
    expect(content).toContain('audiooutput');
  });

  test('JS has sbSetAudioConfig function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetAudioConfig(');
    expect(content).toContain('setSinkId');
  });
});

// =====================================================
// 18. Custom Period Time Input
// =====================================================

test.describe('Custom period time input', () => {
  test('display view has custom period minutes input', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbPeriodCustomMin');
    expect(content).toContain('type="number"');
    expect(content).toContain('Custom');
  });
});

// =====================================================
// 19. Tablet-Friendly Button Sizing
// =====================================================

test.describe('Tablet-friendly button sizing', () => {
  test('display view has large primary buttons with min-height 52px', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('min-height: 52px');
  });

  test('display view has secondary buttons with min-height 48px', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('min-height: 48px');
  });

  test('display view has goal buttons with 18px font', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('goal-btn');
    expect(content).toContain('font-size: 18px');
  });
});

// =====================================================
// NGINX Configuration for Scoreboard Subdomain
// =====================================================

test.describe('NGINX scoreboard subdomain server block', () => {
  function getScoreboardServerBlock() {
    const content = readFile('deployment/arctic_wolves.conf');
    const sbStart = content.indexOf('server_name scoreboard.arcticwolves.ca;');
    expect(sbStart).toBeGreaterThan(0);
    // Find the end of this server block (next section separator or EOF)
    const sbEnd = content.indexOf('# =====', sbStart);
    return content.substring(sbStart, sbEnd > -1 ? sbEnd : undefined);
  }

  test('nginx config has a dedicated scoreboard server block', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    expect(content).toContain('server_name scoreboard.arcticwolves.ca;');
  });

  test('scoreboard server block uses correct document root', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('root /config/www/Arctic_Wolves');
  });

  test('scoreboard server block sets scoreboard.php as default index', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('index scoreboard.php');
  });

  test('scoreboard server block redirects / to scoreboard.php', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('return 302 /scoreboard.php');
  });

  test('scoreboard server block has PHP-FPM location', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('location ~ \\.php$');
    expect(block).toContain('fastcgi_pass');
  });

  test('scoreboard server block has security headers', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('X-Frame-Options');
    expect(block).toContain('X-Content-Type-Options');
    expect(block).toContain('Referrer-Policy');
  });

  test('scoreboard server block has dedicated log files', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('scoreboard_access.log');
    expect(block).toContain('scoreboard_error.log');
  });

  test('scoreboard server block denies sensitive files', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('deny all');
    expect(block).toContain('(env|sql)');
  });

  test('scoreboard server block hides nginx version', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('server_tokens off');
  });

  test('scoreboard server block passes SSL offload headers', () => {
    const block = getScoreboardServerBlock();
    expect(block).toContain('fastcgi_param HTTPS');
    expect(block).toContain('http_x_forwarded_proto');
  });

  test('scoreboard included in HTTP-to-HTTPS redirect comment', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    // The commented-out redirect block should include scoreboard
    expect(content).toContain('scoreboard.arcticwolves.ca');
  });
});

// =====================================================
// 20. Scrollable Layout & Dynamic Responsive Sizing
// =====================================================

test.describe('Scrollable layout and dynamic sizing', () => {
  test('CSS body.sb-body is not a competing scroll container', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toMatch(/body\.sb-body\s*\{[^}]*overflow:\s*visible\s*!important/s);
    expect(content).not.toMatch(/body\.sb-body\s*\{[^}]*overflow:\s*hidden/);
  });

  test('CSS body.sb-body uses min-height with dvh units', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('min-height: 100dvh');
  });

  test('CSS sb-main uses min-height instead of fixed height', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-main');
    expect(content).toContain('min-height: calc(100vh');
    expect(content).toContain('min-height: calc(100dvh');
  });

  test('CSS board scores use clamp() for dynamic sizing', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('font-size: clamp(');
    const scoreSection = content.substring(
      content.indexOf('.sb-board-score'),
      content.indexOf('.sb-board-score') + 200
    );
    expect(scoreSection).toContain('clamp(');
  });

  test('CSS board clock uses clamp() for dynamic sizing', () => {
    const content = readFile('css/scoreboard.css');
    const clockSection = content.substring(
      content.indexOf('.sb-board-clock'),
      content.indexOf('.sb-board-clock') + 200
    );
    expect(clockSection).toContain('clamp(');
  });

  test('CSS has responsive breakpoint at 480px for small tablets', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('max-width: 480px');
  });

  test('display view controls grid uses clamp() for gap and padding', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('gap: clamp(');
    expect(content).toContain('padding: clamp(');
  });

  test('CSS html override prevents dual scroll container conflict with style-guide.css', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('html:has(body.sb-body)');
    expect(content).toContain('html.sb-html');
    expect(content).toContain('overflow-x: hidden !important');
    expect(content).toContain('overflow-y: scroll');
  });

  test('CSS body.sb-body has touch-action for pan gestures', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toMatch(/body\.sb-body\s*\{[^}]*touch-action:\s*pan-y/s);
  });

  test('CSS html override has webkit touch scrolling', () => {
    const content = readFile('css/scoreboard.css');
    const htmlRule = content.substring(
      content.indexOf('html:has(body.sb-body)'),
      content.indexOf('html:has(body.sb-body)') + 200
    );
    expect(htmlRule).toContain('-webkit-overflow-scrolling: touch');
  });

  test('settings view uses overflow visible to let html handle scrolling', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toMatch(/\.sb-settings\s*\{[^}]*overflow:\s*visible\s*!important/s);
  });

  test('settings view does not create competing scroll container', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    // sb-settings should NOT have overflow-y:auto (which creates a competing scroll container)
    const settingsRule = content.substring(
      content.indexOf('.sb-settings {'),
      content.indexOf('.sb-settings {') + 300
    );
    expect(settingsRule).not.toContain('overflow-y: auto');
  });

  test('scoresheet uses overflow visible for proper scrolling', () => {
    const content = readFile('css/scoreboard.css');
    const sheetSection = content.substring(
      content.indexOf('.sb-scoresheet {'),
      content.indexOf('.sb-scoresheet {') + 300
    );
    expect(sheetSection).toContain('overflow: visible !important');
  });

  test('display view sb-main div is properly closed', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('</div><!-- /.sb-main -->');
  });

  test('operator penalty list has touch scrolling', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    const penaltySection = content.substring(
      content.indexOf('.sb-ctrl-penalty-list'),
      content.indexOf('.sb-ctrl-penalty-list') + 300
    );
    expect(penaltySection).toContain('-webkit-overflow-scrolling: touch');
  });

  test('CSS topbar uses min-height instead of fixed height', () => {
    const content = readFile('css/scoreboard.css');
    const topbarSection = content.substring(
      content.indexOf('.sb-topbar {'),
      content.indexOf('.sb-topbar {') + 300
    );
    expect(topbarSection).toContain('min-height: 48px');
    expect(topbarSection).not.toMatch(/[^-]height:\s*48px/);
  });

  test('CSS topbar supports flex-wrap for button overflow', () => {
    const content = readFile('css/scoreboard.css');
    const topbarSection = content.substring(
      content.indexOf('.sb-topbar {'),
      content.indexOf('.sb-topbar {') + 300
    );
    expect(topbarSection).toContain('flex-wrap: wrap');
  });

  test('CSS topbar actions support flex-wrap', () => {
    const content = readFile('css/scoreboard.css');
    const actionsSection = content.substring(
      content.indexOf('.sb-topbar-actions {'),
      content.indexOf('.sb-topbar-actions {') + 200
    );
    expect(actionsSection).toContain('flex-wrap: wrap');
  });

  test('display view topbar buttons use span for responsive text hiding', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toMatch(/<i class="fas fa-flag-checkered"><\/i>\s*<span>End Game<\/span>/);
    expect(content).toMatch(/<i class="fas fa-clipboard-list"><\/i>\s*<span>Scoresheet<\/span>/);
  });

  test('CSS hides button text labels at 480px breakpoint', () => {
    const content = readFile('css/scoreboard.css');
    // Verify the rule exists within the 480px media query
    const idx480 = content.indexOf('@media (max-width: 480px)');
    expect(idx480).toBeGreaterThan(-1);
    const section480 = content.substring(idx480, idx480 + 500);
    expect(section480).toContain('.sb-topbar-actions .sb-btn span { display: none; }');
  });

  test('JS adds sb-html class to html element as :has() fallback', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain("document.documentElement.classList.add('sb-html')");
  });

  test('CSS html.sb-html fallback selector matches html:has selector', () => {
    const content = readFile('css/scoreboard.css');
    // Both selectors must be present in the same rule block
    expect(content).toContain('html:has(body.sb-body)');
    expect(content).toContain('html.sb-html');
    // The fallback should be right after the :has() selector
    expect(content).toMatch(/html:has\(body\.sb-body\),\s*\nhtml\.sb-html\s*\{/);
  });

  test('CSS body.sb-body uses !important on overflow to prevent cascade issues', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toMatch(/body\.sb-body\s*\{[^}]*overflow:\s*visible\s*!important/s);
  });

  test('CSS body.sb-body has height auto to allow content expansion', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toMatch(/body\.sb-body\s*\{[^}]*height:\s*auto\s*!important/s);
  });

  test('CSS html scroll container has explicit height auto', () => {
    const content = readFile('css/scoreboard.css');
    const htmlRule = content.substring(
      content.indexOf('html:has(body.sb-body)'),
      content.indexOf('html:has(body.sb-body)') + 300
    );
    expect(htmlRule).toContain('height: auto !important');
  });

  test('CSS sb-main has overflow visible to prevent content clipping', () => {
    const content = readFile('css/scoreboard.css');
    const mainSection = content.substring(
      content.indexOf('.sb-main {'),
      content.indexOf('.sb-main {') + 300
    );
    expect(mainSection).toContain('overflow: visible');
  });

  test('display view controls grid has overflow visible', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    const gridSection = content.substring(
      content.indexOf('.sb-controls-grid'),
      content.indexOf('.sb-controls-grid') + 300
    );
    expect(gridSection).toContain('overflow: visible');
  });

  test('CSS body.sb-body has webkit overflow scrolling for iOS momentum', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toMatch(/body\.sb-body\s*\{[^}]*-webkit-overflow-scrolling:\s*touch/s);
  });

  test('settings view overflow uses !important', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    const settingsRule = content.substring(
      content.indexOf('.sb-settings {'),
      content.indexOf('.sb-settings {') + 500
    );
    expect(settingsRule).toContain('overflow: visible !important');
  });

  test('scoresheet overflow uses !important', () => {
    const content = readFile('css/scoreboard.css');
    const sheetSection = content.substring(
      content.indexOf('.sb-scoresheet {'),
      content.indexOf('.sb-scoresheet {') + 300
    );
    expect(sheetSection).toContain('overflow: visible !important');
  });
});

// =====================================================
// 21. Clear Penalty Feature
// =====================================================

test.describe('Clear penalty feature', () => {
  test('JS has sbClearPenalty function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbClearPenalty(');
  });

  test('JS sbClearPenalty sends clear_penalty action', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain("'clear_penalty'");
    expect(content).toContain('penalty_id');
  });

  test('display view has clear penalty buttons for home penalties', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-ctrl-penalty-clear-btn');
    expect(content).toContain('sbClearPenalty');
    expect(content).toContain('Clear penalty');
  });

  test('display view has clear penalty buttons for away penalties', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    const awaySection = content.substring(content.indexOf('COLUMN 4: AWAY TEAM'));
    expect(awaySection).toContain('sb-ctrl-penalty-clear-btn');
    expect(awaySection).toContain('sbClearPenalty');
  });

  test('process_scoreboard.php has clear_penalty action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'clear_penalty':");
    expect(content).toContain('DELETE FROM scoreboard_penalties');
  });
});

// =====================================================
// 22. NHL Penalty Rules: Queue, PPG Clear, Types
// =====================================================

test.describe('NHL penalty rules implementation', () => {
  test('JS has SB_MAX_CONCURRENT_PENALTIES constant set to 2', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('SB_MAX_CONCURRENT_PENALTIES');
    expect(content).toContain('= 2');
  });

  test('JS has sbHasClearableMinor function for PPG detection', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbHasClearableMinor(');
    expect(content).toContain('data-penalty-type');
  });

  test('JS sbHasClearableMinor checks for minor/double_minor/bench but not major', () => {
    const content = readFile('js/scoreboard.js');
    const fn = content.substring(
      content.indexOf('function sbHasClearableMinor('),
      content.indexOf('function sbHasClearableMinor(') + 500
    );
    expect(fn).toContain("'minor'");
    expect(fn).toContain("'double_minor'");
    expect(fn).toContain("'bench'");
  });

  test('JS has sbClearPenaltyOnGoal function (NHL Rule 16.2)', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbClearPenaltyOnGoal(');
    expect(content).toContain('NHL Rule 16.2');
  });

  test('JS sbClearPenaltyOnGoal skips major penalties', () => {
    const content = readFile('js/scoreboard.js');
    const fnStart = content.indexOf('function sbClearPenaltyOnGoal(');
    const fn = content.substring(fnStart, fnStart + 800);
    expect(fn).toContain("'minor'");
    expect(fn).toContain("'double_minor'");
    // Only clear ONE (the oldest)
    expect(fn).toContain('return');
  });

  test('JS sbAddGoal prompts for PPG penalty clear', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbAddGoal(');
    expect(content).toContain('sbHasClearableMinor');
    expect(content).toContain('sbClearPenaltyOnGoal');
    expect(content).toContain('Power play goal');
  });

  test('JS has sbUpdatePenaltyQueueStatus function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbUpdatePenaltyQueueStatus(');
    expect(content).toContain('sb-penalty-queued');
  });

  test('JS queue status skips misconducts from shorthanded count', () => {
    const content = readFile('js/scoreboard.js');
    const fnStart = content.indexOf('function sbUpdatePenaltyQueueStatus(');
    const fn = content.substring(fnStart, fnStart + 800);
    expect(fn).toContain('misconduct');
    expect(fn).toContain('SB_MAX_CONCURRENT_PENALTIES');
  });

  test('display view penalty items have data-team and data-penalty-type attributes', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('data-team="home"');
    expect(content).toContain('data-penalty-type');
    expect(content).toContain('data-duration');
  });

  test('display view has individual penalty visibility toggle buttons', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-ctrl-penalty-vis-btn');
    expect(content).toContain('sbTogglePenaltyItemVisibility');
    expect(content).toContain('Toggle board visibility');
  });

  test('JS has sbTogglePenaltyItemVisibility function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbTogglePenaltyItemVisibility(');
    expect(content).toContain('sb-hidden-from-display');
  });

  test('display view shows MAJ label for major penalties', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain("'major'");
    expect(content).toContain('MAJ');
  });

  test('display view shows QUEUED label for 3rd+ penalties', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-penalty-queued');
    expect(content).toContain('QUEUED');
  });

  test('PHP has sbGetPenaltyType helper function', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('function sbGetPenaltyType(');
    expect(content).toContain("'minor'");
    expect(content).toContain("'double_minor'");
    expect(content).toContain("'major'");
    expect(content).toContain("'misconduct'");
  });

  test('PHP calculates coincidental/offsetting penalties correctly', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('offset_count');
    expect(content).toContain('home_net_penalties');
    expect(content).toContain('away_net_penalties');
    expect(content).toContain('Coincidental');
  });

  test('PHP calculates strength display (5v4, 4v4, 5v3)', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('strength_display');
    expect(content).toContain('home_skaters');
    expect(content).toContain('away_skaters');
  });

  test('display view shows strength indicator when not even strength', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-board-strength');
    expect(content).toContain('sbStrength');
    expect(content).toContain('is_even_strength');
  });

  test('CSS has strength indicator styles', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-board-strength');
  });
});

// =====================================================
// 23. Custom Penalty Durations (Beer League / Minor Hockey)
// =====================================================

test.describe('Custom penalty durations', () => {
  test('display view penalty modal has beer league duration presets', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('3 min (Minor – Beer/Minor League)');
    expect(content).toContain('6 min (Double Minor – Beer League)');
    expect(content).toContain('7 min (Major – Beer League)');
  });

  test('display view penalty modal has custom duration option', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-pen-duration-custom');
    expect(content).toContain('value="custom"');
    expect(content).toContain('Custom');
  });

  test('display view has sbPenDurationPresetChanged handler', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('function sbPenDurationPresetChanged(');
    expect(content).toContain('sb-pen-duration-custom');
  });

  test('JS sbAddPenalty supports custom duration_minutes_custom', () => {
    const content = readFile('js/scoreboard.js');
    const fn = content.substring(
      content.indexOf('function sbAddPenalty('),
      content.indexOf('function sbAddPenalty(') + 600
    );
    expect(fn).toContain("'custom'");
    expect(fn).toContain('duration_minutes_custom');
  });

  test('display view penalty modal has comprehensive infractions list', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('Charging');
    expect(content).toContain('Elbowing');
    expect(content).toContain('Kneeing');
    expect(content).toContain('Spearing');
    expect(content).toContain('Head Contact');
    expect(content).toContain('Match Penalty');
    expect(content).toContain('Bench Minor');
  });

  test('display view penalty modal has served-by field for bench/goalie penalties', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('served_by');
    expect(content).toContain('Served By');
    expect(content).toContain('bench/goalie penalties');
  });
});

// =====================================================
// 24. Game Situation Indicators (Delayed Penalty, Empty Net)
// =====================================================

test.describe('Game situation indicators', () => {
  test('display view has delayed penalty indicators for both teams', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbDelayedHome');
    expect(content).toContain('sbDelayedAway');
    expect(content).toContain('DEL');
  });

  test('display view has empty net indicators for both teams', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbEmptyNetHome');
    expect(content).toContain('sbEmptyNetAway');
    expect(content).toContain('EN');
  });

  test('display view has delayed penalty toggle buttons', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain("sbToggleDelayedPenalty('home')");
    expect(content).toContain("sbToggleDelayedPenalty('away')");
    expect(content).toContain('Delayed Pen');
  });

  test('display view has empty net toggle buttons', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain("sbToggleEmptyNet('home')");
    expect(content).toContain("sbToggleEmptyNet('away')");
    expect(content).toContain('Empty Net');
  });

  test('display view has sbToggleDelayedPenalty JS function', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('function sbToggleDelayedPenalty(');
    expect(content).toContain('sbDelayed');
  });

  test('display view has sbToggleEmptyNet JS function', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('function sbToggleEmptyNet(');
    expect(content).toContain('sbEmptyNet');
  });

  test('CSS has board indicator badge styles', () => {
    const content = readFile('css/scoreboard.css');
    expect(content).toContain('.sb-board-indicator');
  });
});

// =====================================================
// 25. Clock Mode (Stop Time vs Running Time)
// =====================================================

test.describe('Clock mode - stop time vs running time', () => {
  test('display view has clock mode select', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbClockModeSelect');
    expect(content).toContain('sbSetClockMode');
    expect(content).toContain('Clock Mode');
  });

  test('display view has stop time and running time options', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('stop_time');
    expect(content).toContain('running_time');
    expect(content).toContain('Stop Time (NHL)');
    expect(content).toContain('Running Time (Beer/Minor League)');
  });

  test('JS has sbSetClockMode function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbSetClockMode(');
    expect(content).toContain('sbClockMode');
  });

  test('JS has sbClockMode state variable', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain("var sbClockMode = 'stop_time'");
  });

  test('display view OT select includes playoff 20min option', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('20:00 (Playoff)');
  });
});

// =====================================================
// 26. Custom Buzzer Sound Upload
// =====================================================

test.describe('Custom buzzer sound upload', () => {
  test('JS buzzer uses CUSTOM_BUZZER_URL when available', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('CUSTOM_BUZZER_URL');
    const buzzerFn = content.substring(
      content.indexOf('function sbBuzzer()'),
      content.indexOf('function sbBuzzer()') + 600
    );
    expect(buzzerFn).toContain('CUSTOM_BUZZER_URL');
    expect(buzzerFn).toContain('new Audio(');
  });

  test('JS buzzer falls back to synthesized tone when no custom sound', () => {
    const content = readFile('js/scoreboard.js');
    const fnStart = content.indexOf('function sbBuzzer()');
    const buzzerFn = content.substring(fnStart, fnStart + 1000);
    expect(buzzerFn).toContain('AudioContext');
    expect(buzzerFn).toContain('sawtooth');
  });

  test('scoreboard.php passes CUSTOM_BUZZER_URL to JS', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('CUSTOM_BUZZER_URL');
    expect(content).toContain('scoreboard_buzzer_url');
  });

  test('process_scoreboard.php has upload_buzzer action (admin-only)', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'upload_buzzer':");
    expect(content).toContain('!$isAdmin');
    expect(content).toContain('buzzer_file');
    expect(content).toContain("audio/mpeg");
    expect(content).toContain("audio/wav");
    expect(content).toContain("audio/ogg");
  });

  test('process_scoreboard.php has remove_buzzer action (admin-only)', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'remove_buzzer':");
    expect(content).toContain('!$isAdmin');
  });
});

// =====================================================
// 27. Settings Page (Admin-Only)
// =====================================================

test.describe('Settings page - admin only', () => {
  test('scoreboard.php allows settings view', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain("'settings'");
    expect(content).toContain('allowed_views');
  });

  test('scoreboard.php restricts settings view to admins', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain("$view === 'settings' && !$isAdmin");
  });

  test('scoreboard.php includes settings view file', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('scoreboard_settings.php');
  });

  test('scoreboard.php passes IS_ADMIN to JS', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('IS_ADMIN');
  });

  test('settings view exists', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content.length).toBeGreaterThan(100);
  });

  test('settings view has Spotify configuration section', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Spotify');
    expect(content).toContain('spotify_client_id');
    expect(content).toContain('spotify_client_secret');
  });

  test('settings view has Apple Music configuration section', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Apple Music');
    expect(content).toContain('apple_music_token');
    expect(content).toContain('apple_music_team_id');
  });

  test('settings view has Subsonic configuration section', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Subsonic');
    expect(content).toContain('subsonic_url');
    expect(content).toContain('subsonic_username');
    expect(content).toContain('subsonic_password');
  });

  test('settings view has custom buzzer sound upload section', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Buzzer Sound');
    expect(content).toContain('sbBuzzerFile');
    expect(content).toContain('sbUploadBuzzerSound');
    expect(content).toContain('sbRemoveBuzzerSound');
  });

  test('settings view has network speakers section with Bluesound BSP1000', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Network Speakers');
    expect(content).toContain('Bluesound Professional BSP1000');
    expect(content).toContain('speaker_type');
    expect(content).toContain('speaker_host');
    expect(content).toContain('speaker_port');
  });

  test('settings view has team logo upload and browse section', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Team Logos');
    expect(content).toContain('sbLogoFile');
    expect(content).toContain('sbUploadTeamLogo');
    expect(content).toContain('sb-settings-logos-grid');
  });

  test('display view has Settings link visible to admins only', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain("view=settings");
    expect(content).toContain('$isAdmin');
    expect(content).toContain('fa-cog');
  });

  test('process_scoreboard.php has save_settings action (admin-only)', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'save_settings':");
    expect(content).toContain('!$isAdmin');
    expect(content).toContain("'spotify'");
    expect(content).toContain("'apple_music'");
    expect(content).toContain("'subsonic'");
    expect(content).toContain("'network_speakers'");
  });

  test('process_scoreboard.php has upload_team_logo action (admin-only)', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'upload_team_logo':");
    expect(content).toContain('!$isAdmin');
    expect(content).toContain('logo_file');
    expect(content).toContain("image/png");
    expect(content).toContain("image/svg+xml");
  });
});

// =====================================================
// 28. Apple Music & Multiple Audio Outputs
// =====================================================

test.describe('Apple Music and multiple audio outputs', () => {
  test('scoreboard.php loads apple_music_configured flag', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('apple_music_configured');
    expect(content).toContain('apple_music_token');
  });

  test('display view shows Apple Music button when configured', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('apple_music_configured');
    expect(content).toContain('Apple Music');
    expect(content).toContain('sbAppleMusicConnect');
  });

  test('scoreboard.php loads network_speakers config', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('scoreboard_network_speakers');
    expect(content).toContain('network_speakers');
  });

  test('display view links to settings when no music sources configured', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('No music sources configured');
    expect(content).toContain('Configure in Settings');
  });
});

// =====================================================
// 29. Goal Horn (separate from Buzzer)
// =====================================================

test.describe('Goal horn separate from buzzer', () => {
  test('JS has separate sbGoalHorn function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbGoalHorn()');
  });

  test('JS sbGoalHorn uses CUSTOM_HORN_URL when available', () => {
    const content = readFile('js/scoreboard.js');
    const hornFn = content.substring(
      content.indexOf('function sbGoalHorn()'),
      content.indexOf('function sbGoalHorn()') + 400
    );
    expect(hornFn).toContain('CUSTOM_HORN_URL');
  });

  test('JS sbGoalHorn falls back to sbBuzzer when no horn configured', () => {
    const content = readFile('js/scoreboard.js');
    const hornStart = content.indexOf('function sbGoalHorn()');
    const hornEnd = content.indexOf('\n}', hornStart) + 2;
    const hornFn = content.substring(hornStart, hornEnd);
    expect(hornFn).toContain('sbBuzzer()');
  });

  test('JS sbAddGoal calls sbGoalHorn instead of sbBuzzer', () => {
    const content = readFile('js/scoreboard.js');
    const goalStart = content.indexOf('function sbAddGoal(');
    const goalEnd = content.indexOf('\n}', goalStart) + 2;
    const goalFn = content.substring(goalStart, goalEnd);
    expect(goalFn).toContain('sbGoalHorn()');
    expect(goalFn).not.toContain('sbBuzzer()');
  });

  test('scoreboard.php passes CUSTOM_HORN_URL to JS', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('CUSTOM_HORN_URL');
    expect(content).toContain('custom_horn_url');
  });

  test('scoreboard.php loads scoreboard_horn_url from settings', () => {
    const content = readFile('scoreboard.php');
    expect(content).toContain('scoreboard_horn_url');
  });

  test('display view has separate buzzer and goal horn buttons', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sbBuzzerBtn');
    expect(content).toContain('sbHornBtn');
    expect(content).toContain('sbGoalHorn()');
    expect(content).toContain('BUZZER');
    expect(content).toContain('GOAL HORN');
  });

  test('display view has horn-btn CSS class', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('.horn-btn');
  });
});

// =====================================================
// 30. Buzzer and Horn Libraries
// =====================================================

test.describe('Buzzer and horn sound libraries', () => {
  test('settings view has separate buzzer and horn sections', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Buzzer Sound (End of Period)');
    expect(content).toContain('Goal Horn Sound');
  });

  test('settings view has buzzer library display', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Buzzer Library');
    expect(content).toContain('buzzer_library');
  });

  test('settings view has horn library display', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('Horn Library');
    expect(content).toContain('horn_library');
  });

  test('settings view has horn upload form', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('sbHornUploadForm');
    expect(content).toContain('sbHornFile');
    expect(content).toContain('sbUploadHornSound');
    expect(content).toContain('sbRemoveHornSound');
  });

  test('settings view has library item selection and removal JS', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('sbSelectLibraryItem');
    expect(content).toContain('sbRemoveLibraryItem');
  });

  test('process_scoreboard.php has upload_horn action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'upload_horn':");
    expect(content).toContain('horn_file');
    expect(content).toContain('scoreboard_horn_url');
    expect(content).toContain('scoreboard_horn_library');
  });

  test('process_scoreboard.php has remove_horn action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'remove_horn':");
  });

  test('process_scoreboard.php has select_buzzer action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'select_buzzer':");
  });

  test('process_scoreboard.php has select_horn action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'select_horn':");
  });

  test('process_scoreboard.php has buzzer library item removal action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'remove_buzzer_library_item':");
    expect(content).toContain('scoreboard_buzzer_library');
  });

  test('process_scoreboard.php has horn library item removal action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'remove_horn_library_item':");
    expect(content).toContain('scoreboard_horn_library');
  });

  test('process_scoreboard.php upload_buzzer adds to library', () => {
    const content = readFile('process_scoreboard.php');
    const buzzerUpload = content.substring(
      content.indexOf("case 'upload_buzzer':"),
      content.indexOf("case 'remove_buzzer':")
    );
    expect(buzzerUpload).toContain('scoreboard_buzzer_library');
  });

  test('settings view has library item CSS styles', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('.sb-settings-library');
    expect(content).toContain('.sb-settings-library-item');
  });
});

// =====================================================
// 31. Security - Credential Encryption
// =====================================================

test.describe('Scoreboard credential encryption', () => {
  test('process_scoreboard.php encrypts sensitive credentials on save', () => {
    const content = readFile('process_scoreboard.php');
    // Look for the encryption code in the save_settings action area (broader search)
    const saveStart = content.indexOf("case 'save_settings':");
    const saveEnd = content.indexOf("case 'upload_buzzer':", saveStart);
    const saveSection = content.substring(saveStart, saveEnd);
    expect(saveSection).toContain('encryptPassword');
    expect(saveSection).toContain('spotify_client_secret');
    expect(saveSection).toContain('apple_music_token');
    expect(saveSection).toContain('subsonic_password');
  });

  test('settings view decrypts credentials on load', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('decryptCredential');
    expect(content).toContain('spotify_client_secret');
    expect(content).toContain('apple_music_token');
    expect(content).toContain('subsonic_password');
  });

  test('security.php getEncryptedSettingKeys includes scoreboard credentials', () => {
    const content = readFile('security.php');
    const fn = content.substring(
      content.indexOf('function getEncryptedSettingKeys'),
      content.indexOf('}', content.indexOf('function getEncryptedSettingKeys')) + 1
    );
    expect(fn).toContain('spotify_client_secret');
    expect(fn).toContain('apple_music_token');
    expect(fn).toContain('subsonic_password');
  });

  test('process_scoreboard.php uses FieldEncryption for credential encryption', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('FieldEncryption::isConfigured');
  });
});

// =====================================================
// 32. Set Score / Set Shots Backend Actions
// =====================================================

test.describe('Set score and shots backend actions', () => {
  test('process_scoreboard.php has set_score action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'set_score':");
    expect(content).toContain('home_score');
    expect(content).toContain('away_score');
  });

  test('process_scoreboard.php has set_shots action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'set_shots':");
    expect(content).toContain('home_shots');
    expect(content).toContain('away_shots');
  });
});

// =====================================================
// 33. RustFS Upload Integration
// =====================================================

test.describe('RustFS upload integration', () => {
  test('process_scoreboard.php includes RustFS storage library', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain('rustfs_storage.php');
    expect(content).toContain('cloud_config.php');
  });

  test('upload_buzzer uses persistUploadedFile for RustFS', () => {
    const content = readFile('process_scoreboard.php');
    const buzzerSection = content.substring(
      content.indexOf("case 'upload_buzzer':"),
      content.indexOf("case 'remove_buzzer':")
    );
    expect(buzzerSection).toContain('persistUploadedFile');
    expect(buzzerSection).toContain('scoreboard/buzzer');
  });

  test('upload_horn uses persistUploadedFile for RustFS', () => {
    const content = readFile('process_scoreboard.php');
    const hornSection = content.substring(
      content.indexOf("case 'upload_horn':"),
      content.indexOf("case 'remove_horn':")
    );
    expect(hornSection).toContain('persistUploadedFile');
    expect(hornSection).toContain('scoreboard/horn');
  });

  test('upload_team_logo uses persistUploadedFile for RustFS', () => {
    const content = readFile('process_scoreboard.php');
    const logoSection = content.substring(
      content.indexOf("case 'upload_team_logo':"),
      content.indexOf("case 'delete_team_logo':")
    );
    expect(logoSection).toContain('persistUploadedFile');
    expect(logoSection).toContain('team_logos');
  });
});

// =====================================================
// 34. Multiselect Buzzer/Horn Upload
// =====================================================

test.describe('Multiselect buzzer and horn uploads', () => {
  test('buzzer file input supports multiple file selection', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('id="sbBuzzerFile"');
    const buzzerInput = content.substring(
      content.indexOf('id="sbBuzzerFile"') - 100,
      content.indexOf('id="sbBuzzerFile"') + 200
    );
    expect(buzzerInput).toContain('multiple');
  });

  test('horn file input supports multiple file selection', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('id="sbHornFile"');
    const hornInput = content.substring(
      content.indexOf('id="sbHornFile"') - 100,
      content.indexOf('id="sbHornFile"') + 200
    );
    expect(hornInput).toContain('multiple');
  });

  test('upload_buzzer backend handles multiple files', () => {
    const content = readFile('process_scoreboard.php');
    const buzzerSection = content.substring(
      content.indexOf("case 'upload_buzzer':"),
      content.indexOf("case 'remove_buzzer':")
    );
    expect(buzzerSection).toContain("is_array($_FILES['buzzer_file']['name'])");
    expect(buzzerSection).toContain('uploadedCount');
  });

  test('upload_horn backend handles multiple files', () => {
    const content = readFile('process_scoreboard.php');
    const hornSection = content.substring(
      content.indexOf("case 'upload_horn':"),
      content.indexOf("case 'remove_horn':")
    );
    expect(hornSection).toContain("is_array($_FILES['horn_file']['name'])");
    expect(hornSection).toContain('uploadedCount');
  });

  test('JS upload functions send multiple files', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain("fd.append('buzzer_file[]', fileInput.files[i])");
    expect(content).toContain("fd.append('horn_file[]', fileInput.files[i])");
  });
});

// =====================================================
// 35. CSRF Token in Settings AJAX Requests
// =====================================================

test.describe('CSRF token in all settings AJAX requests', () => {
  test('save_settings includes csrf_token in body', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    const saveSection = content.substring(
      content.indexOf('function sbSaveSettings'),
      content.indexOf('function sbUploadBuzzerSound')
    );
    expect(saveSection).toContain("params.append('csrf_token', CSRF_TOKEN)");
  });

  test('upload functions include csrf_token in FormData', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    // Both upload functions should add csrf_token
    const buzzerUpload = content.substring(
      content.indexOf('function sbUploadBuzzerSound'),
      content.indexOf('function sbRemoveBuzzerSound')
    );
    expect(buzzerUpload).toContain("fd.append('csrf_token', CSRF_TOKEN)");

    const hornUpload = content.substring(
      content.indexOf('function sbUploadHornSound'),
      content.indexOf('function sbRemoveHornSound')
    );
    expect(hornUpload).toContain("fd.append('csrf_token', CSRF_TOKEN)");
  });

  test('remove and select actions include csrf_token in body', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain("'action=remove_buzzer&csrf_token=' + encodeURIComponent(CSRF_TOKEN)");
    expect(content).toContain("'action=remove_horn&csrf_token=' + encodeURIComponent(CSRF_TOKEN)");
  });
});

// =====================================================
// 36. Penalty Countdown Clocks in Operator Controls
// =====================================================

test.describe('Penalty countdown clocks in operator controls', () => {
  test('display view has penalty clock elements in penalty items', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('sb-ctrl-penalty-clock');
    expect(content).toContain('data-penalty-clock');
    expect(content).toContain('data-penalty-seconds');
  });

  test('display view has CSS for penalty clock badges', () => {
    const content = readFile('views/scoreboard/scoreboard_display.php');
    expect(content).toContain('.sb-ctrl-penalty-clock');
    expect(content).toContain('.sb-ctrl-penalty-clock.expired');
  });

  test('JS has sbInitPenaltyItemClocks function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbInitPenaltyItemClocks');
    expect(content).toContain('data-penalty-clock');
    expect(content).toContain('data-penalty-seconds');
  });

  test('JS has sbTickPenaltyItemClocks function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbTickPenaltyItemClocks');
    expect(content).toContain('sbFormatClock');
  });

  test('JS sbTickPenaltyTimers calls sbTickPenaltyItemClocks', () => {
    const content = readFile('js/scoreboard.js');
    const tickSection = content.substring(
      content.indexOf('function sbTickPenaltyTimers'),
      content.indexOf('function sbTickPenaltyItemClocks') || content.indexOf('var sbPenaltyItemClocks')
    );
    expect(tickSection).toContain('sbTickPenaltyItemClocks()');
  });

  test('JS initializes penalty item clocks on load', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('sbInitPenaltyItemClocks()');
  });
});

// =====================================================
// 37. Apple Music Connect Function
// =====================================================

test.describe('Apple Music connect function', () => {
  test('JS has sbAppleMusicConnect function', () => {
    const content = readFile('js/scoreboard.js');
    expect(content).toContain('function sbAppleMusicConnect');
  });
});

// =====================================================
// 38. Team Logo Management
// =====================================================

test.describe('Team logo management', () => {
  test('settings view has logo delete buttons', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('sb-settings-logo-delete');
    expect(content).toContain('sbDeleteTeamLogo');
  });

  test('settings view has sbDeleteTeamLogo JS function', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('function sbDeleteTeamLogo');
    expect(content).toContain("action=delete_team_logo");
  });

  test('process_scoreboard.php has delete_team_logo action', () => {
    const content = readFile('process_scoreboard.php');
    expect(content).toContain("case 'delete_team_logo':");
    expect(content).toContain("logo_url = NULL");
  });

  test('settings CSS has logo delete button hover styles', () => {
    const content = readFile('views/scoreboard/scoreboard_settings.php');
    expect(content).toContain('.sb-settings-logo-delete');
    expect(content).toContain('.sb-settings-logo-card:hover .sb-settings-logo-delete');
  });
});
