import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for Stopwatch and Shot Speed fixes:
 * 1. User names are decrypted in stopwatch session history
 * 2. Shot speed tracker no longer requires user_roles join
 * 3. Stopwatch lap/checkpoint uses typeahead search instead of dropdown
 * 4. Multi-watch feature for timing multiple athletes simultaneously
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Stopwatch user names decryption fix
// =====================================================

test.describe('Stopwatch - User Name Decryption', () => {
  test('process_stopwatch.php decrypts user rows in get_session action', () => {
    const content = readFile('process_stopwatch.php');
    // After fetching times, should call decryptUserRows
    const getSessionSection = content.substring(
      content.indexOf("case 'get_session':"),
      content.indexOf("case 'delete_session':")
    );
    expect(getSessionSection).toContain('decryptUserRows');
  });

  test('process_stopwatch.php still fetches first_name and last_name from users', () => {
    const content = readFile('process_stopwatch.php');
    const getSessionSection = content.substring(
      content.indexOf("case 'get_session':"),
      content.indexOf("case 'delete_session':")
    );
    expect(getSessionSection).toContain('u.first_name');
    expect(getSessionSection).toContain('u.last_name');
  });
});

// =====================================================
// 2. Shot speed "Athlete not found" fix
// =====================================================

test.describe('Shot Speed - Athlete Validation Fix', () => {
  test('process_shot_speed.php does NOT join user_roles table for athlete verification', () => {
    const content = readFile('process_shot_speed.php');
    const recordSection = content.substring(
      content.indexOf("case 'record_speed':"),
      content.indexOf("case 'get_recent_speeds':")
    );
    expect(recordSection).not.toContain('user_roles');
    expect(recordSection).not.toContain('JOIN user_roles');
  });

  test('process_shot_speed.php validates athlete against users table directly', () => {
    const content = readFile('process_shot_speed.php');
    const recordSection = content.substring(
      content.indexOf("case 'record_speed':"),
      content.indexOf("case 'get_recent_speeds':")
    );
    expect(recordSection).toContain('FROM users u');
    expect(recordSection).toContain('u.is_active = 1');
    expect(recordSection).toContain('u.id = ?');
  });

  test('process_shot_speed.php still validates athlete_id is positive', () => {
    const content = readFile('process_shot_speed.php');
    expect(content).toContain('$athlete_id <= 0');
    expect(content).toContain('Invalid athlete ID');
  });

  test('coach_shot_speed.php uses typeahead for athlete selection', () => {
    const content = readFile('views/coach_shot_speed.php');
    expect(content).toContain('ArcticTypeahead');
    expect(content).toContain('shot-speed-athlete-typeahead');
  });
});

// =====================================================
// 3. Stopwatch lap/checkpoint uses typeahead
// =====================================================

test.describe('Stopwatch - Typeahead in Lap Table', () => {
  test('renderLaps does NOT build select dropdown for athlete assignment', () => {
    const content = readFile('views/coach_stopwatch.php');
    const renderLapsSection = content.substring(
      content.indexOf('function renderLaps()'),
      content.indexOf('function swInitLapTypeahead')
    );
    // Should NOT contain the old select element builder
    expect(renderLapsSection).not.toContain("athleteSelect += '<option");
    expect(renderLapsSection).not.toContain("'<select class=\"sw-assign-select\"");
  });

  test('renderLaps creates typeahead container div for each lap', () => {
    const content = readFile('views/coach_stopwatch.php');
    const renderLapsSection = content.substring(
      content.indexOf('function renderLaps()'),
      content.indexOf('function swInitLapTypeahead')
    );
    expect(renderLapsSection).toContain('sw-lap-ta-');
    expect(renderLapsSection).toContain('swInitLapTypeahead');
  });

  test('swInitLapTypeahead creates ArcticTypeahead for lap rows', () => {
    const content = readFile('views/coach_stopwatch.php');
    expect(content).toContain('function swInitLapTypeahead');
    const fnSection = content.substring(
      content.indexOf('function swInitLapTypeahead'),
      content.indexOf('function swClearLapAthlete')
    );
    expect(fnSection).toContain('new ArcticTypeahead');
    expect(fnSection).toContain('swAssignAthlete');
    expect(fnSection).toContain("placeholder: 'Search athlete");
  });

  test('swClearLapAthlete allows clearing athlete assignment from lap', () => {
    const content = readFile('views/coach_stopwatch.php');
    expect(content).toContain('function swClearLapAthlete');
    const fnSection = content.substring(
      content.indexOf('function swClearLapAthlete'),
      content.indexOf('function swAssignAthlete')
    );
    expect(fnSection).toContain('athleteId = null');
    expect(fnSection).toContain("athleteName = ''");
    expect(fnSection).toContain('renderLaps()');
  });

  test('assigned athletes show name with clear button', () => {
    const content = readFile('views/coach_stopwatch.php');
    expect(content).toContain('sw-lap-athlete-name');
    expect(content).toContain('sw-lap-athlete-clear');
    expect(content).toContain('swClearLapAthlete');
  });
});

// =====================================================
// 4. Multi-Watch Feature
// =====================================================

test.describe('Stopwatch - Multi-Watch Feature', () => {
  test('Multi-Athlete Watches section exists in HTML', () => {
    const content = readFile('views/coach_stopwatch.php');
    expect(content).toContain('multi-watch-card');
    expect(content).toContain('Multi-Athlete Watches');
    expect(content).toContain('mw-watches-container');
    expect(content).toContain("mwAddWatch()");
  });

  test('mwAddWatch creates a new watch instance', () => {
    const content = readFile('views/coach_stopwatch.php');
    expect(content).toContain('function mwAddWatch()');
    const fnSection = content.substring(
      content.indexOf('function mwAddWatch()'),
      content.indexOf('function mwRenderWatch')
    );
    expect(fnSection).toContain('new Stopwatch');
    expect(fnSection).toContain('mwWatches.push');
    expect(fnSection).toContain('mwRenderWatch');
  });

  test('mwRenderWatch creates watch DOM with athlete typeahead', () => {
    const content = readFile('views/coach_stopwatch.php');
    const fnSection = content.substring(
      content.indexOf('function mwRenderWatch'),
      content.indexOf('function mwGetWatch')
    );
    expect(fnSection).toContain('new ArcticTypeahead');
    expect(fnSection).toContain('mw-display-');
    expect(fnSection).toContain('mw-start-');
    expect(fnSection).toContain('mw-stop-');
    expect(fnSection).toContain('mw-lap-');
  });

  test('multi-watch has start, stop, lap, checkpoint, reset controls', () => {
    const content = readFile('views/coach_stopwatch.php');
    expect(content).toContain('function mwStart(');
    expect(content).toContain('function mwStop(');
    expect(content).toContain('function mwLap(');
    expect(content).toContain('function mwCheckpoint(');
    expect(content).toContain('function mwReset(');
  });

  test('multi-watch lap automatically assigns athlete from watch', () => {
    const content = readFile('views/coach_stopwatch.php');
    const lapFn = content.substring(
      content.indexOf('function mwLap('),
      content.indexOf('function mwCheckpoint(')
    );
    expect(lapFn).toContain('lap.athleteId = watch.athleteId');
    expect(lapFn).toContain('lap.athleteName = watch.athleteName');
  });

  test('mwRemoveWatch cleans up watch resources', () => {
    const content = readFile('views/coach_stopwatch.php');
    const fnSection = content.substring(
      content.indexOf('function mwRemoveWatch'),
      content.indexOf('function mwRenderLaps')
    );
    expect(fnSection).toContain('clearInterval');
    expect(fnSection).toContain('mwWatches.filter');
    expect(fnSection).toContain('.remove()');
  });

  test('multi-watch has proper CSS styles', () => {
    const content = readFile('views/coach_stopwatch.php');
    expect(content).toContain('.mw-watch-item');
    expect(content).toContain('.mw-running');
    expect(content).toContain('.mw-watch-display');
    expect(content).toContain('.mw-watch-controls');
    expect(content).toContain('.mw-watch-laps');
  });
});

// =====================================================
// 5. Stopwatch class has getElapsedMs
// =====================================================

test.describe('Stopwatch Class', () => {
  test('Stopwatch class has getElapsedMs method', () => {
    const content = readFile('js/stopwatch.js');
    expect(content).toContain('getElapsedMs()');
  });

  test('Stopwatch class has static formatTimeMs method', () => {
    const content = readFile('js/stopwatch.js');
    expect(content).toContain('static formatTimeMs(ms)');
  });
});
