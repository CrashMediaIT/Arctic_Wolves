/**
 * Tests for video source HLS transcode trigger and player UI fixes:
 * 1. video_source uploads now trigger triggerHlsTranscodeSource
 * 2. vr_video_sources schema has HLS columns
 * 3. Video player big play button uses circle (not YouTube shape)
 * 4. CSS specificity fix for button.aw-ctrl-btn
 * 5. Touch zones for skip forward/back
 * 6. confirm_video_upload uses ignore_user_abort + keepalive
 */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const readFile = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');

// =====================================================
// 1. video_source HLS transcode trigger
// =====================================================

test.describe('video_source uploads trigger HLS transcoding', () => {
  const content = () => readFile('process_video.php');

  test('video_source branch should NOT embed triggerHlsTranscodeSource (now separate action)', () => {
    const c = content();
    // Find the handleConfirmVideoUpload function first
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    expect(funcStart).toBeGreaterThan(-1);
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 5000);
    // Transcode is now triggered via separate trigger_transcode action, not embedded here
    expect(funcBody).not.toContain('triggerHlsTranscodeSource');
  });

  test('handleTriggerTranscode should call triggerHlsTranscodeSource for sources', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    expect(funcStart).toBeGreaterThan(-1);
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : funcStart + 3000);
    expect(func).toContain('triggerHlsTranscodeSource');
  });

  test('triggerHlsTranscodeSource function should exist', () => {
    const c = content();
    expect(c).toContain('function triggerHlsTranscodeSource($pdo, $source_id, $object_key)');
  });

  test('triggerHlsTranscodeSource should POST to companion /api/hls', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscodeSource(');
    expect(funcStart).toBeGreaterThan(-1);
    const func = c.substring(funcStart, c.indexOf('\nfunction ', funcStart + 1) > 0 ? c.indexOf('\nfunction ', funcStart + 1) : funcStart + 3000);
    expect(func).toContain('/api/hls');
    expect(func).toContain('source_key');
    expect(func).toContain('output_prefix');
    expect(func).toContain('callback_url');
  });

  test('triggerHlsTranscodeSource should update vr_video_sources table', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscodeSource(');
    const func = c.substring(funcStart, funcStart + 3000);
    expect(func).toContain('UPDATE vr_video_sources SET hls_status');
    expect(func).toContain('hls_url');
    expect(func).toContain('hls_job_id');
  });

  test('triggerHlsTranscodeSource should handle errors gracefully', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscodeSource(');
    const func = c.substring(funcStart, funcStart + 4000);
    expect(func).toContain('ErrorLogger::error');
    expect(func).toContain('catch (Exception');
  });

  test('handleConfirmVideoUpload should use ignore_user_abort', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const func = c.substring(funcStart, funcStart + 500);
    expect(func).toContain('ignore_user_abort(true)');
  });
});

// =====================================================
// 2. vr_video_sources schema HLS columns
// =====================================================

test.describe('vr_video_sources HLS schema', () => {
  const schema = () => readFile('database_schema.sql');

  test('should add hls_url column to vr_video_sources', () => {
    expect(schema()).toContain("ALTER TABLE `vr_video_sources`");
    // Check that HLS columns are added
    const s = schema();
    const idx = s.indexOf("ALTER TABLE `vr_video_sources`");
    // Find the block that adds HLS columns
    expect(s).toMatch(/vr_video_sources[\s\S]*?hls_url/);
  });

  test('should add hls_status column to vr_video_sources', () => {
    expect(schema()).toMatch(/vr_video_sources[\s\S]*?hls_status/);
  });

  test('should add hls_job_id column to vr_video_sources', () => {
    expect(schema()).toMatch(/vr_video_sources[\s\S]*?hls_job_id/);
  });

  test('should add hls_master_url column to vr_video_sources', () => {
    expect(schema()).toMatch(/vr_video_sources[\s\S]*?hls_master_url/);
  });

  test('should add hls_segments_path column to vr_video_sources', () => {
    expect(schema()).toMatch(/vr_video_sources[\s\S]*?hls_segments_path/);
  });
});

// =====================================================
// 3. Video player big play button (no YouTube shape)
// =====================================================

test.describe('Video player big play button', () => {
  const jsContent = () => readFile('js/hls-player.js');
  const cssContent = () => readFile('views/shared_styles.css');

  test('should use circle SVG, not YouTube rounded rect', () => {
    const js = jsContent();
    // Should have circle element
    expect(js).toContain('<circle class="aw-big-play-bg"');
    // Should NOT have the YouTube-style rounded rect path
    expect(js).not.toContain('M66.52 7.74');
  });

  test('big play SVG should use viewBox 0 0 64 64 (square)', () => {
    const js = jsContent();
    const bigPlayIdx = js.indexOf('aw-big-play');
    const block = js.substring(bigPlayIdx, bigPlayIdx + 500);
    expect(block).toContain('viewBox="0 0 64 64"');
  });

  test('CSS should style big play bg with fill and stroke', () => {
    const css = cssContent();
    const bgRule = css.substring(css.indexOf('.aw-big-play-bg'), css.indexOf('.aw-big-play-bg') + 200);
    expect(bgRule).toContain('fill');
    expect(bgRule).toContain('stroke');
  });

  test('should use --primary theme color for big play background', () => {
    const css = cssContent();
    const bgRule = css.substring(css.indexOf('.aw-big-play-bg'), css.indexOf('.aw-big-play-bg') + 200);
    // The background should use the brand primary color for visibility
    expect(bgRule).toContain('var(--primary');
  });
});

// =====================================================
// 4. CSS specificity fix for .aw-ctrl-btn
// =====================================================

test.describe('CSS specificity fix for player controls', () => {
  const css = () => readFile('views/shared_styles.css');

  test('should use button.aw-ctrl-btn selector for higher specificity', () => {
    const c = css();
    expect(c).toContain('button.aw-ctrl-btn');
  });

  test('button.aw-ctrl-btn should have padding: 0', () => {
    const c = css();
    const ruleStart = c.indexOf('button.aw-ctrl-btn {');
    const ruleEnd = c.indexOf('}', ruleStart);
    const rule = c.substring(ruleStart, ruleEnd);
    expect(rule).toContain('padding: 0');
  });

  test('button.aw-ctrl-btn should have min-height: 0', () => {
    const c = css();
    const ruleStart = c.indexOf('button.aw-ctrl-btn {');
    const ruleEnd = c.indexOf('}', ruleStart);
    const rule = c.substring(ruleStart, ruleEnd);
    expect(rule).toContain('min-height: 0');
  });

  test('button.aw-ctrl-btn svg should have fill: currentColor', () => {
    const c = css();
    expect(c).toMatch(/button\.aw-ctrl-btn svg[\s\S]*?fill:\s*currentColor/);
  });

  test('style-guide.css base button rule excludes .aw-ctrl-btn via :not()', () => {
    const sg = readFile('css/style-guide.css');
    expect(sg).toContain(':not(.aw-ctrl-btn)');
  });
});

// =====================================================
// 5. Touch zones for skip forward/back
// =====================================================

test.describe('Video player touch zones', () => {
  const js = () => readFile('js/hls-player.js');
  const css = () => readFile('views/shared_styles.css');

  test('should create left touch zone for skip back', () => {
    const c = js();
    expect(c).toContain('aw-touch-zone-left');
  });

  test('should create right touch zone for skip forward', () => {
    const c = js();
    expect(c).toContain('aw-touch-zone-right');
  });

  test('left touch zone click should skip backward', () => {
    const c = js();
    const leftHandler = c.substring(c.indexOf("touchLeft.addEventListener('click'"));
    expect(leftHandler).toContain('video.currentTime');
    expect(leftHandler).toContain('Math.max(0');
  });

  test('right touch zone click should skip forward', () => {
    const c = js();
    const rightHandler = c.substring(c.indexOf("touchRight.addEventListener('click'"));
    expect(rightHandler).toContain('video.currentTime');
    expect(rightHandler).toContain('Math.min');
  });

  test('CSS should have touch zone styles', () => {
    const c = css();
    expect(c).toContain('.aw-touch-zone');
    expect(c).toContain('.aw-touch-zone-left');
    expect(c).toContain('.aw-touch-zone-right');
  });

  test('should have skip indicator styles', () => {
    const c = css();
    expect(c).toContain('.aw-skip-indicator');
    expect(c).toContain('.aw-skip-show');
  });

  test('cleanup should remove touch zones', () => {
    const c = js();
    expect(c).toContain('touchLeft.parentElement');
    expect(c).toContain('touchRight.parentElement');
  });
});

// =====================================================
// 6. Keepalive on confirm_video_upload fetch calls
// =====================================================

test.describe('confirm_video_upload uses keepalive', () => {
  test('video_record_athlete.php single upload uses keepalive', () => {
    const c = readFile('views/video_record_athlete.php');
    // The confirm fetch should have keepalive
    expect(c).toMatch(/confirm_video_upload[\s\S]*?keepalive:\s*true/);
  });

  test('video_record_drill.php uses keepalive for confirm', () => {
    const c = readFile('views/video_record_drill.php');
    expect(c).toMatch(/confirm_video_upload[\s\S]*?keepalive/);
  });

  test('video_coach_reviews.php uses keepalive for confirm', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toMatch(/confirm_video_upload[\s\S]*?keepalive/);
  });

  test('gameplan film_room.php uses keepalive for confirm', () => {
    const c = readFile('views/gameplan/film_room.php');
    expect(c).toMatch(/confirm_video_upload[\s\S]*?keepalive/);
  });

  test('pwa/video_record_drill.php uses keepalive for confirm', () => {
    const c = readFile('views/pwa/video_record_drill.php');
    expect(c).toMatch(/confirm_video_upload[\s\S]*?keepalive/);
  });
});
