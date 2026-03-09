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
    expect(content).toContain('password_hash');
    expect(content).toContain("name=\"email\"");
    expect(content).toContain("name=\"password\"");
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
    expect(content).toContain('BUZZER / HORN');
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
