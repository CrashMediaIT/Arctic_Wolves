/**
 * Tests for standardized video upload transcode trigger.
 *
 * Verifies that all upload views trigger transcoding via the dedicated
 * trigger_transcode action (matching the admin video test view pattern)
 * instead of embedding the trigger inside handleConfirmVideoUpload.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. process_video.php — trigger_transcode action
// =====================================================

test.describe('trigger_transcode action in process_video.php', () => {
  const content = () => readFile('process_video.php');

  test('switch statement should have trigger_transcode case', () => {
    const c = content();
    expect(c).toContain("case 'trigger_transcode':");
    expect(c).toContain('handleTriggerTranscode()');
  });

  test('handleTriggerTranscode function should exist', () => {
    const c = content();
    expect(c).toContain('function handleTriggerTranscode()');
  });

  test('handleTriggerTranscode should use ignore_user_abort', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('ignore_user_abort(true)');
  });

  test('handleTriggerTranscode should accept video_id and source_id', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('video_id');
    expect(func).toContain('source_id');
    expect(func).toContain('object_key');
  });

  test('handleTriggerTranscode should call triggerHlsTranscode for videos', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('triggerHlsTranscode($pdo, $video_id, $object_key)');
  });

  test('handleTriggerTranscode should call triggerHlsTranscodeSource for sources', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('triggerHlsTranscodeSource($pdo, $source_id, $object_key)');
  });

  test('handleTriggerTranscode should verify video ownership', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('athlete_id = ? OR coach_id = ?');
    expect(func).toContain('access denied');
  });

  test('handleTriggerTranscode should verify source ownership', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('uploaded_by = ?');
  });

  test('handleTriggerTranscode should return hls_status and hls_job_id', () => {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('hls_status');
    expect(func).toContain('hls_job_id');
  });
});

// =====================================================
// 2. handleConfirmVideoUpload — no embedded transcode
// =====================================================

test.describe('handleConfirmVideoUpload decoupled from transcode', () => {
  const content = () => readFile('process_video.php');

  test('handleConfirmVideoUpload should NOT call triggerHlsTranscode directly', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).not.toContain('triggerHlsTranscode(');
    expect(func).not.toContain('triggerHlsTranscodeSource(');
  });

  test('handleConfirmVideoUpload should return object_key in response', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain("'object_key' => $object_key");
  });

  test('handleConfirmVideoUpload should still return video_id and source_id', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain("'video_id'");
    expect(func).toContain("'source_id'");
  });
});

// =====================================================
// 3. All views call trigger_transcode after confirm
// =====================================================

test.describe('Views trigger transcode as separate action', () => {

  test('video_record_athlete.php should call trigger_transcode after confirm', () => {
    const c = readFile('views/video_record_athlete.php');
    expect(c).toContain("action: 'trigger_transcode'");
    expect(c).toContain('object_key: result.object_key');
    // Should appear in both small file and multipart paths
    const matches = c.match(/trigger_transcode/g);
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });

  test('video_record_drill.php should call trigger_transcode after confirm', () => {
    const c = readFile('views/video_record_drill.php');
    expect(c).toContain("action: 'trigger_transcode'");
    expect(c).toContain('object_key: result.object_key');
    const matches = c.match(/trigger_transcode/g);
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });

  test('video_coach_reviews.php should call trigger_transcode after confirm', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain("action: 'trigger_transcode'");
    expect(c).toContain('object_key: result.object_key');
    const matches = c.match(/trigger_transcode/g);
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });

  test('pwa/video_record_drill.php should call trigger_transcode after confirm', () => {
    const c = readFile('views/pwa/video_record_drill.php');
    expect(c).toContain("'trigger_transcode'");
    expect(c).toContain('result.object_key');
    const matches = c.match(/trigger_transcode/g);
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });

  test('gameplan/gp_film_room.php should call trigger_transcode after confirm', () => {
    const c = readFile('views/gameplan/gp_film_room.php');
    expect(c).toContain("'trigger_transcode'");
    expect(c).toContain('result.object_key');
    const matches = c.match(/trigger_transcode/g);
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });

  test('gameplan/film_room.php should call trigger_transcode after confirm', () => {
    const c = readFile('views/gameplan/film_room.php');
    expect(c).toContain("'trigger_transcode'");
    expect(c).toContain('result.object_key');
    const matches = c.match(/trigger_transcode/g);
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });
});

// =====================================================
// 4. All views use keepalive for transcode trigger
// =====================================================

test.describe('Transcode trigger uses keepalive for reliability', () => {

  test('video_record_athlete.php transcode calls use keepalive', () => {
    const c = readFile('views/video_record_athlete.php');
    // The postAction calls with trigger_transcode should pass { keepalive: true }
    expect(c).toContain("postAction(transcodeParams, { keepalive: true })");
  });

  test('video_record_drill.php transcode calls use keepalive', () => {
    const c = readFile('views/video_record_drill.php');
    expect(c).toContain("drillPostAction(tp, { keepalive: true })");
  });

  test('video_coach_reviews.php transcode calls use keepalive', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain("crPostAction(tp, csrfToken, { keepalive: true })");
  });

  test('pwa/video_record_drill.php transcode calls use keepalive', () => {
    const c = readFile('views/pwa/video_record_drill.php');
    // Uses raw fetch with keepalive — check each trigger_transcode block
    const triggerSections = c.split('trigger_transcode');
    // Each trigger section should have keepalive nearby
    for (let i = 1; i < triggerSections.length; i++) {
      const nearby = triggerSections[i].substring(0, 500);
      expect(nearby).toContain('keepalive: true');
    }
  });
});

// =====================================================
// 5. triggerHlsTranscode still exists for the new action
// =====================================================

test.describe('triggerHlsTranscode functions still intact', () => {
  const content = () => readFile('process_video.php');

  test('triggerHlsTranscode function should still exist', () => {
    const c = content();
    expect(c).toContain('function triggerHlsTranscode($pdo, $video_id, $object_key)');
  });

  test('triggerHlsTranscodeSource function should still exist', () => {
    const c = content();
    expect(c).toContain('function triggerHlsTranscodeSource($pdo, $source_id, $object_key)');
  });

  test('triggerHlsTranscode should POST to companion /api/hls', () => {
    const c = content();
    const funcStart = c.indexOf('function triggerHlsTranscode($pdo, $video_id, $object_key)');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
    expect(func).toContain('/api/hls');
    expect(func).toContain('source_key');
    expect(func).toContain('output_prefix');
  });
});
