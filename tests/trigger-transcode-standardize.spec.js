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

// =====================================================
// 6. handleTriggerTranscode — HLS status query resilience
// =====================================================

test.describe('handleTriggerTranscode HLS status query resilience', () => {
  const content = () => readFile('process_video.php');

  function getHandleTriggerTranscodeBody() {
    const c = content();
    const funcStart = c.indexOf('function handleTriggerTranscode()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    return c.substring(funcStart, funcEnd > funcStart ? funcEnd : undefined);
  }

  test('video branch HLS status SELECT should be wrapped in try-catch', () => {
    const func = getHandleTriggerTranscodeBody();
    // After triggerHlsTranscode call, the SELECT hls_status should be inside a try block
    const videoSelectIdx = func.indexOf("SELECT hls_status, hls_job_id FROM videos");
    expect(videoSelectIdx).toBeGreaterThan(-1);
    // Look backwards from the SELECT for a try { block
    const beforeSelect = func.substring(0, videoSelectIdx);
    const lastTry = beforeSelect.lastIndexOf('try {');
    const lastCatch = beforeSelect.lastIndexOf('catch');
    // The try should appear after the triggerHlsTranscode call, not before it
    expect(lastTry).toBeGreaterThan(-1);
    expect(lastTry).toBeGreaterThan(lastCatch);
  });

  test('source branch HLS status SELECT should be wrapped in try-catch', () => {
    const func = getHandleTriggerTranscodeBody();
    const sourceSelectIdx = func.indexOf("SELECT hls_status, hls_job_id FROM vr_video_sources");
    expect(sourceSelectIdx).toBeGreaterThan(-1);
    const beforeSelect = func.substring(0, sourceSelectIdx);
    const lastTry = beforeSelect.lastIndexOf('try {');
    const lastCatch = beforeSelect.lastIndexOf('catch');
    expect(lastTry).toBeGreaterThan(-1);
    expect(lastTry).toBeGreaterThan(lastCatch);
  });

  test('should catch PDOException for missing HLS columns', () => {
    const func = getHandleTriggerTranscodeBody();
    // Both branches should catch PDOException specifically
    const catches = func.match(/catch\s*\(\s*PDOException\b/g);
    expect(catches).not.toBeNull();
    expect(catches.length).toBeGreaterThanOrEqual(2);
  });

  test('should default hls_status and hls_job_id to null before try blocks', () => {
    const func = getHandleTriggerTranscodeBody();
    // Both branches should initialize to null so the json response always has these keys
    const nullDefaults = func.match(/\$hls_status\s*=\s*null/g);
    expect(nullDefaults).not.toBeNull();
    expect(nullDefaults.length).toBeGreaterThanOrEqual(2);
    const jobDefaults = func.match(/\$hls_job_id\s*=\s*null/g);
    expect(jobDefaults).not.toBeNull();
    expect(jobDefaults.length).toBeGreaterThanOrEqual(2);
  });

  test('should still return success:true even if HLS columns are missing', () => {
    const func = getHandleTriggerTranscodeBody();
    // Both json_encode blocks should include success => true
    const successBlocks = func.match(/json_encode\(\[\s*\n?\s*'success'\s*=>\s*true/g);
    expect(successBlocks).not.toBeNull();
    expect(successBlocks.length).toBeGreaterThanOrEqual(2);
  });
});

// =====================================================
// 7. database_schema.sql — HLS columns in CREATE TABLE
// =====================================================

test.describe('database_schema.sql HLS columns in CREATE TABLE', () => {
  const content = () => readFile('database_schema.sql');

  test('videos CREATE TABLE should include hls_status column', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `videos`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_status`');
  });

  test('videos CREATE TABLE should include hls_url column', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `videos`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_url`');
  });

  test('videos CREATE TABLE should include hls_job_id column', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `videos`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_job_id`');
  });

  test('videos CREATE TABLE should include hls_master_url and hls_segments_path columns', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `videos`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_master_url`');
    expect(createBlock).toContain('`hls_segments_path`');
  });

  test('vr_video_sources CREATE TABLE should include hls_status column', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `vr_video_sources`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_status`');
  });

  test('vr_video_sources CREATE TABLE should include hls_url column', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `vr_video_sources`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_url`');
  });

  test('vr_video_sources CREATE TABLE should include hls_job_id column', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `vr_video_sources`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_job_id`');
  });

  test('vr_video_sources CREATE TABLE should include hls_master_url and hls_segments_path', () => {
    const c = content();
    const createStart = c.indexOf('CREATE TABLE IF NOT EXISTS `vr_video_sources`');
    const createEnd = c.indexOf('ENGINE=InnoDB', createStart);
    const createBlock = c.substring(createStart, createEnd);
    expect(createBlock).toContain('`hls_master_url`');
    expect(createBlock).toContain('`hls_segments_path`');
  });
});

// =====================================================
// 8. setup.php — HLS column inline migrations
// =====================================================

test.describe('setup.php HLS column inline migrations', () => {
  const content = () => readFile('setup.php');

  test('should have inline migration for videos.hls_status', () => {
    const c = content();
    expect(c).toContain("ALTER TABLE videos ADD COLUMN hls_status");
  });

  test('should have inline migration for videos.hls_url', () => {
    const c = content();
    expect(c).toContain("ALTER TABLE videos ADD COLUMN hls_url");
  });

  test('should have inline migration for videos.hls_job_id', () => {
    const c = content();
    expect(c).toContain("ALTER TABLE videos ADD COLUMN hls_job_id");
  });

  test('should have inline migration for videos.hls_master_url and hls_segments_path', () => {
    const c = content();
    expect(c).toContain("ALTER TABLE videos ADD COLUMN hls_master_url");
    expect(c).toContain("ALTER TABLE videos ADD COLUMN hls_segments_path");
  });

  test('should have inline migration for vr_video_sources.hls_status', () => {
    const c = content();
    expect(c).toContain("ALTER TABLE vr_video_sources ADD COLUMN hls_status");
  });

  test('should have inline migration for vr_video_sources.hls_url', () => {
    const c = content();
    expect(c).toContain("ALTER TABLE vr_video_sources ADD COLUMN hls_url");
  });

  test('should have inline migration for vr_video_sources.hls_job_id', () => {
    const c = content();
    expect(c).toContain("ALTER TABLE vr_video_sources ADD COLUMN hls_job_id");
  });
});
