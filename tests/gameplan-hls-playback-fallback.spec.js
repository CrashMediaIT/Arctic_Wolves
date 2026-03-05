/**
 * Gameplan Views – HLS Playback & Fallback Tests
 *
 * Verifies:
 * 1. Film room views use getPreferredVideoUrl for editor video source
 * 2. Film room views include data-hls-url fallback attribute
 * 3. Film room JS has error handler for HLS fallback
 * 4. My clips views fetch HLS columns from vr_video_sources
 * 5. My clips views use getPreferredVideoUrl for data-source attribute
 * 6. My clips views include data-hls-url fallback and JS error handler
 * 7. getPreferredVideoUrl falls back to file_path when video_url is absent
 * 8. Multi-camera query includes hls_url and hls_status columns
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

/* ------------------------------------------------------------------ */
/*  1. getPreferredVideoUrl supports file_path fallback               */
/* ------------------------------------------------------------------ */
test.describe('getPreferredVideoUrl supports vr_video_sources rows', () => {

  test('should fall back to file_path when video_url is not present', () => {
    const content = readFile('lib/image_helper.php');
    const funcStart = content.indexOf('function getPreferredVideoUrl(');
    const funcEnd = content.indexOf('\n}', funcStart) + 2;
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('file_path');
    expect(func).toContain("video_url");
    expect(func).toContain("hls_url");
    expect(func).toContain("hls_status");
  });

  test('docstring should mention file_path', () => {
    const content = readFile('lib/image_helper.php');
    const funcStart = content.lastIndexOf('/**', content.indexOf('function getPreferredVideoUrl('));
    const funcEnd = content.indexOf('\n}', funcStart) + 2;
    const block = content.substring(funcStart, funcEnd);
    expect(block).toContain('file_path');
  });
});

/* ------------------------------------------------------------------ */
/*  2. Film room editor uses getPreferredVideoUrl                     */
/* ------------------------------------------------------------------ */
test.describe('Film room editor uses HLS-aware video URL', () => {

  test('film_room.php should use getPreferredVideoUrl for editor video', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('gp_film_room.php should use getPreferredVideoUrl for editor video', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('film_room.php editor video should resolve through resolveRustfsUrl', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('resolveRustfsUrl($pdo, getPreferredVideoUrl(');
  });

  test('gp_film_room.php editor video should resolve through resolveRustfsUrl', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('resolveRustfsUrl($pdo, getPreferredVideoUrl(');
  });

  test('film_room.php should show video when hls_url is available even if file_path is empty', () => {
    const content = readFile('views/gameplan/film_room.php');
    // The condition should check both file_path and hls_url
    expect(content).toMatch(/if\s*\(!empty\(\$vr_edit_source\['file_path'\]\)\s*\|\|\s*!empty\(\$vr_edit_source\['hls_url'\]\)\)/);
  });

  test('gp_film_room.php should show video when hls_url is available even if file_path is empty', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toMatch(/if\s*\(!empty\(\$vr_edit_source\['file_path'\]\)\s*\|\|\s*!empty\(\$vr_edit_source\['hls_url'\]\)\)/);
  });
});

/* ------------------------------------------------------------------ */
/*  3. Film room has data-hls-url fallback attribute                  */
/* ------------------------------------------------------------------ */
test.describe('Film room editor has HLS fallback attribute', () => {

  test('film_room.php should have data-hls-url on video element', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('data-hls-url=');
  });

  test('gp_film_room.php should have data-hls-url on video element', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('data-hls-url=');
  });
});

/* ------------------------------------------------------------------ */
/*  4. Film room JS has error handler for HLS fallback                */
/* ------------------------------------------------------------------ */
test.describe('Film room JS error handler retries with HLS URL', () => {

  test('film_room.php should have error event listener on player', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain("player.addEventListener('error'");
  });

  test('film_room.php error handler should read data-hls-url', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('player.dataset.hlsUrl');
  });

  test('film_room.php error handler should have fallback-tried guard', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('_filmHlsFallbackTried');
  });

  test('gp_film_room.php should have error event listener on player', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain("player.addEventListener('error'");
  });

  test('gp_film_room.php error handler should read data-hls-url', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('player.dataset.hlsUrl');
  });

  test('gp_film_room.php error handler should have fallback-tried guard', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('_gpFilmHlsFallbackTried');
  });
});

/* ------------------------------------------------------------------ */
/*  5. Multi-camera query includes HLS columns                       */
/* ------------------------------------------------------------------ */
test.describe('Multi-camera query fetches HLS columns', () => {

  test('film_room.php multicam query should include hls_url', () => {
    const content = readFile('views/gameplan/film_room.php');
    // Find the multicam query (SELECT ... WHERE game_id = ?)
    const mcIdx = content.indexOf('FROM vr_video_sources WHERE game_id');
    expect(mcIdx).toBeGreaterThan(-1);
    const queryStart = content.lastIndexOf('SELECT', mcIdx);
    const querySection = content.substring(queryStart, mcIdx);
    expect(querySection).toContain('hls_url');
    expect(querySection).toContain('hls_status');
  });

  test('gp_film_room.php multicam query should include hls_url', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    const mcIdx = content.indexOf('FROM vr_video_sources WHERE game_id');
    expect(mcIdx).toBeGreaterThan(-1);
    const queryStart = content.lastIndexOf('SELECT', mcIdx);
    const querySection = content.substring(queryStart, mcIdx);
    expect(querySection).toContain('hls_url');
    expect(querySection).toContain('hls_status');
  });
});

/* ------------------------------------------------------------------ */
/*  6. My clips views fetch HLS columns from vr_video_sources        */
/* ------------------------------------------------------------------ */
test.describe('My clips views fetch HLS columns', () => {

  test('my_clips.php query should include hls_url from vr_video_sources', () => {
    const content = readFile('views/gameplan/my_clips.php');
    expect(content).toContain('vs.hls_url AS source_hls_url');
    expect(content).toContain('vs.hls_status AS source_hls_status');
  });

  test('gp_my_clips.php query should include hls_url from vr_video_sources', () => {
    const content = readFile('views/gameplan/gp_my_clips.php');
    expect(content).toContain('vs.hls_url AS source_hls_url');
    expect(content).toContain('vs.hls_status AS source_hls_status');
  });
});

/* ------------------------------------------------------------------ */
/*  7. My clips views use getPreferredVideoUrl for playback           */
/* ------------------------------------------------------------------ */
test.describe('My clips views use HLS-aware video URL', () => {

  test('my_clips.php should use getPreferredVideoUrl for clip source', () => {
    const content = readFile('views/gameplan/my_clips.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('gp_my_clips.php should use getPreferredVideoUrl for clip source', () => {
    const content = readFile('views/gameplan/gp_my_clips.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('my_clips.php should have data-hls-url on clip cards', () => {
    const content = readFile('views/gameplan/my_clips.php');
    expect(content).toContain('data-hls-url=');
  });

  test('gp_my_clips.php should have data-hls-url on clip cards', () => {
    const content = readFile('views/gameplan/gp_my_clips.php');
    expect(content).toContain('data-hls-url=');
  });
});

/* ------------------------------------------------------------------ */
/*  8. My clips JS has error handler for HLS fallback                 */
/* ------------------------------------------------------------------ */
test.describe('My clips JS error handler retries with HLS URL', () => {

  test('my_clips.php should have error event listener on video', () => {
    const content = readFile('views/gameplan/my_clips.php');
    expect(content).toContain("video.addEventListener('error'");
  });

  test('my_clips.php should read hlsUrl from card dataset on click', () => {
    const content = readFile('views/gameplan/my_clips.php');
    expect(content).toContain('card.dataset.hlsUrl');
  });

  test('my_clips.php error handler should have fallback-tried guard', () => {
    const content = readFile('views/gameplan/my_clips.php');
    expect(content).toContain('_clipHlsFallbackTried');
  });

  test('gp_my_clips.php should have error event listener on video', () => {
    const content = readFile('views/gameplan/gp_my_clips.php');
    expect(content).toContain("video.addEventListener('error'");
  });

  test('gp_my_clips.php should read hlsUrl from card dataset on click', () => {
    const content = readFile('views/gameplan/gp_my_clips.php');
    expect(content).toContain('card.dataset.hlsUrl');
  });

  test('gp_my_clips.php error handler should have fallback-tried guard', () => {
    const content = readFile('views/gameplan/gp_my_clips.php');
    expect(content).toContain('_gpClipHlsFallbackTried');
  });
});

/* ------------------------------------------------------------------ */
/*  9. All gameplan views include image_helper.php                    */
/* ------------------------------------------------------------------ */
test.describe('Gameplan views include image_helper.php', () => {

  test('film_room.php should require image_helper.php', () => {
    const content = readFile('views/gameplan/film_room.php');
    expect(content).toContain('image_helper.php');
  });

  test('gp_film_room.php should require image_helper.php', () => {
    const content = readFile('views/gameplan/gp_film_room.php');
    expect(content).toContain('image_helper.php');
  });
});

/* ------------------------------------------------------------------ */
/*  10. Companion deletes original only after successful callback     */
/* ------------------------------------------------------------------ */
test.describe('Companion deletes original only after callback succeeds', () => {

  test('_send_callback should return a dict with ok and confirmed keys', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def _send_callback(');
    const funcEnd = content.indexOf('\ndef ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('"ok"');
    expect(func).toContain('"confirmed"');
    expect(func).toContain('return result');
  });

  test('_send_callback should raise_for_status to detect HTTP errors', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def _send_callback(');
    const funcEnd = content.indexOf('\ndef ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('raise_for_status');
  });

  test('_send_callback should parse response for confirmed and rows_affected', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def _send_callback(');
    const funcEnd = content.indexOf('\ndef ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('resp.json()');
    expect(func).toContain('"confirmed"');
    expect(func).toContain('"rows_affected"');
  });

  test('_hls_transcode_s3 should store callback result', () => {
    const content = readFile('companion/app.py');
    expect(content).toContain('cb_result = _send_callback(');
  });

  test('_hls_transcode_s3 should store callback_confirmed in job data', () => {
    const content = readFile('companion/app.py');
    expect(content).toContain('callback_confirmed');
    expect(content).toContain('callback_ok');
  });

  test('_hls_transcode_s3 should only delete original when callback confirmed', () => {
    const content = readFile('companion/app.py');
    // Find the section where delete_original is checked after callback
    const cbIdx = content.indexOf('cb_result = _send_callback(');
    expect(cbIdx).toBeGreaterThan(-1);
    const afterCb = content.substring(cbIdx, cbIdx + 1500);
    // Should gate deletion on confirmed (not just ok)
    expect(afterCb).toContain('cb_result["confirmed"]');
    expect(afterCb).toContain('_s3_delete');
  });

  test('_hls_transcode_s3 should log when skipping deletion due to callback failure', () => {
    const content = readFile('companion/app.py');
    expect(content).toContain('Skipping original deletion');
  });

  test('_hls_transcode_s3 should track original_deleted status', () => {
    const content = readFile('companion/app.py');
    expect(content).toContain('"original_deleted"');
  });

  test('callback should happen before deletion in code order', () => {
    const content = readFile('companion/app.py');
    const callbackIdx = content.indexOf('cb_result = _send_callback(');
    const deleteIdx = content.indexOf('deleting original source:');
    expect(callbackIdx).toBeGreaterThan(-1);
    expect(deleteIdx).toBeGreaterThan(-1);
    // Callback must come before deletion
    expect(callbackIdx).toBeLessThan(deleteIdx);
  });

  test('main app callback should return confirmed field', () => {
    const content = readFile('api/v1/companion.php');
    expect(content).toContain("'confirmed'");
    expect(content).toContain('rows_affected');
  });
});
