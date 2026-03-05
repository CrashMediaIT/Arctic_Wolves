/**
 * CSP Worker Policy & HLS Error Fallback Tests
 *
 * Verifies:
 * 1. CSP worker-src allows blob: for HLS.js web workers
 * 2. HLS.js player has network error retry limit (not infinite)
 * 3. HLS.js dispatches native error event after retry exhaustion
 * 4. All video views have HLS fallback error handlers
 * 5. HLS.js explicitly enables workers
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  1. CSP worker-src allows blob: for HLS.js web workers             */
/* ------------------------------------------------------------------ */
test.describe('CSP worker-src allows blob: for HLS.js', () => {

  test('security.php worker-src should include blob:', () => {
    const content = readFile('security.php');
    expect(content).toMatch(/worker-src\s+'self'\s+blob:/);
  });

  test('security.php should still include self in worker-src', () => {
    const content = readFile('security.php');
    expect(content).toMatch(/worker-src\s+'self'/);
  });
});

/* ------------------------------------------------------------------ */
/*  2. HLS.js network error retry limit                               */
/* ------------------------------------------------------------------ */
test.describe('HLS.js network error retry limit', () => {

  test('hls-player.js should have a max network retries constant', () => {
    const content = readFile('js/hls-player.js');
    expect(content).toContain('_MAX_NETWORK_RETRIES');
  });

  test('hls-player.js should track network retry count', () => {
    const content = readFile('js/hls-player.js');
    expect(content).toContain('_networkRetries');
  });

  test('hls-player.js should check retry count before startLoad', () => {
    const content = readFile('js/hls-player.js');
    // Find the NETWORK_ERROR handler
    const networkIdx = content.indexOf('NETWORK_ERROR');
    expect(networkIdx).toBeGreaterThan(-1);
    const section = content.substring(networkIdx, networkIdx + 500);
    // Should check retries before calling startLoad
    expect(section).toContain('_networkRetries < _MAX_NETWORK_RETRIES');
    expect(section).toContain('hls.startLoad()');
  });

  test('hls-player.js should destroy HLS and dispatch error after exhausting retries', () => {
    const content = readFile('js/hls-player.js');
    const networkIdx = content.indexOf('NETWORK_ERROR');
    const section = content.substring(networkIdx, networkIdx + 600);
    // After retries exhausted, should destroy HLS.js
    expect(section).toContain('hls.destroy()');
    // Should dispatch a native error event for view-level handlers
    expect(section).toContain("dispatchEvent(new Event('error'))");
  });

  test('hls-player.js should NOT infinitely retry network errors', () => {
    const content = readFile('js/hls-player.js');
    const networkIdx = content.indexOf('NETWORK_ERROR');
    const section = content.substring(networkIdx, networkIdx + 100);
    // Should NOT just unconditionally call startLoad
    expect(section).not.toMatch(/NETWORK_ERROR:\s*\n\s*hls\.startLoad\(\)/);
  });
});

/* ------------------------------------------------------------------ */
/*  3. HLS.js explicitly enables workers                              */
/* ------------------------------------------------------------------ */
test.describe('HLS.js worker configuration', () => {

  test('hls-player.js should explicitly set enableWorker: true', () => {
    const content = readFile('js/hls-player.js');
    expect(content).toContain('enableWorker: true');
  });
});

/* ------------------------------------------------------------------ */
/*  4. All video views have HLS error fallback handlers               */
/* ------------------------------------------------------------------ */
test.describe('All video views have HLS fallback error handlers', () => {

  const viewsWithVideoPlayers = [
    { file: 'views/video_coach_reviews.php', name: 'Coach Reviews' },
    { file: 'views/video_drill_review.php', name: 'Drill Review' },
    { file: 'views/video_review_detail.php', name: 'Review Detail (desktop)' },
    { file: 'views/pwa/video_review_detail.php', name: 'Review Detail (PWA)' },
    { file: 'views/gameplan/film_room.php', name: 'Film Room' },
    { file: 'views/gameplan/gp_film_room.php', name: 'GP Film Room' },
    { file: 'views/gameplan/my_clips.php', name: 'My Clips' },
    { file: 'views/gameplan/gp_my_clips.php', name: 'GP My Clips' },
  ];

  for (const view of viewsWithVideoPlayers) {
    test(`${view.name} (${view.file}) should have error event listener`, () => {
      const content = readFile(view.file);
      expect(content).toContain("addEventListener('error'");
    });

    test(`${view.name} (${view.file}) should have HLS fallback URL variable`, () => {
      const content = readFile(view.file);
      // Should have some form of HLS fallback URL tracking
      expect(content).toMatch(/[Hh]ls[Ff]allback/);
    });

    test(`${view.name} (${view.file}) should call awInitHlsPlayer in fallback`, () => {
      const content = readFile(view.file);
      // The error handler should use awInitHlsPlayer for the retry
      const errorIdx = content.indexOf("addEventListener('error'");
      expect(errorIdx).toBeGreaterThan(-1);
      const afterError = content.substring(errorIdx, errorIdx + 400);
      expect(afterError).toContain('awInitHlsPlayer');
    });

    test(`${view.name} (${view.file}) should have fallback-tried guard`, () => {
      const content = readFile(view.file);
      // Should prevent infinite retry loops
      expect(content).toMatch(/[Ff]allback[Tt]ried/);
    });
  }

  // Coach reviews specifically: uses getPreferredVideoUrl + data-hls-url
  test('Coach Reviews should use getPreferredVideoUrl for primary URL', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('Coach Reviews should have data-hls-url attribute on buttons', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('data-hls-url=');
  });

  test('Drill Review should use getPreferredVideoUrl for primary URL', () => {
    const content = readFile('views/video_drill_review.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('Drill Review should have data-hls-url attribute on buttons', () => {
    const content = readFile('views/video_drill_review.php');
    expect(content).toContain('data-hls-url');
  });
});
