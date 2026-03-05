/**
 * Tests for Video HLS Playback and Complete Deletion
 *
 * Verifies:
 * 1. Video views serve HLS URL when transcoding is complete (hls_status='ready')
 * 2. Video deletion removes both original file AND HLS transcoded files
 * 3. deleteRustFSPrefix function exists for bulk HLS cleanup
 * 4. The complete video lifecycle: upload → transcode → serve HLS → delete all
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Views serve HLS URL when transcoding is complete
// =====================================================

test.describe('Views serve HLS when hls_status is ready', () => {

  test('video_coach_reviews.php should use getPreferredVideoUrl for playback URL', () => {
    const content = readFile('views/video_coach_reviews.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('video_coach_reviews.php should use getPreferredVideoUrl in both pending and reviewed tabs', () => {
    const content = readFile('views/video_coach_reviews.php');
    // Count how many play buttons reference getPreferredVideoUrl
    const occurrences = (content.match(/getPreferredVideoUrl/g) || []).length;
    // There should be at least 2 occurrences (pending tab + reviewed tab)
    expect(occurrences).toBeGreaterThanOrEqual(2);
  });

  test('video_drill_review.php should use getPreferredVideoUrl for playback URL', () => {
    const content = readFile('views/video_drill_review.php');
    expect(content).toContain('getPreferredVideoUrl');
  });

  test('video_coach_reviews.php data-video-url should resolve through resolveRustfsUrl', () => {
    const content = readFile('views/video_coach_reviews.php');
    // The HLS URL should still go through resolveRustfsUrl for proper URL resolution
    expect(content).toContain('resolveRustfsUrl($pdo');
    expect(content).toContain('data-video-url=');
  });

  test('video_drill_review.php data-video-url should resolve through resolveRustfsUrl', () => {
    const content = readFile('views/video_drill_review.php');
    expect(content).toContain('resolveRustfsUrl($pdo');
    expect(content).toContain('data-video-url=');
  });

  test('getPreferredVideoUrl helper should exist in lib/image_helper.php', () => {
    const content = readFile('lib/image_helper.php');
    expect(content).toContain('function getPreferredVideoUrl(');
  });

  test('getPreferredVideoUrl should check hls_status ready and hls_url', () => {
    const content = readFile('lib/image_helper.php');
    const funcStart = content.indexOf('function getPreferredVideoUrl(');
    const funcEnd = content.indexOf('\n}', funcStart) + 2;
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain("hls_status");
    expect(func).toContain("'failed'");
    expect(func).toContain("hls_url");
    expect(func).toContain("video_url");
  });

});

// =====================================================
// 2. Video deletion removes HLS files
// =====================================================

test.describe('Video deletion removes HLS transcoded files', () => {

  test('handleVideoDelete should reference hls_segments_path from video record', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('hls_segments_path');
  });

  test('handleVideoDelete should call deleteRustFSPrefix for HLS cleanup', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('deleteRustFSPrefix');
  });

  test('handleVideoDelete should still delete original video file', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('deleteFromRustFS');
    expect(func).toContain('video_url');
  });

  test('handleVideoDelete should handle both RustFS proxy URLs and local paths', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('api/media.php?key=');
    expect(func).toContain('unlink');
  });

  test('handleVideoDelete should not crash when hls_segments_path is empty', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    // Should check if hls_segments_path is not empty before calling delete
    expect(func).toContain("!empty($hls_segments_path)");
  });

  test('handleVideoDelete should log HLS deletion results', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function handleVideoDelete()');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('error_log');
    expect(func).toContain('HLS');
  });

});

// =====================================================
// 3. deleteRustFSPrefix function
// =====================================================

test.describe('deleteRustFSPrefix helper function', () => {

  test('deleteRustFSPrefix should exist in lib/rustfs_storage.php', () => {
    const content = readFile('lib/rustfs_storage.php');
    expect(content).toContain('function deleteRustFSPrefix(');
  });

  test('deleteRustFSPrefix should accept settings and prefix parameters', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function deleteRustFSPrefix(');
    const funcSig = content.substring(funcStart, content.indexOf(')', funcStart) + 1);
    expect(funcSig).toContain('$settings');
    expect(funcSig).toContain('$prefix');
  });

  test('deleteRustFSPrefix should use listRustFSObjects to find objects', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function deleteRustFSPrefix(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('listRustFSObjects');
  });

  test('deleteRustFSPrefix should use deleteFromRustFS for each object', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function deleteRustFSPrefix(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('deleteFromRustFS');
  });

  test('deleteRustFSPrefix should reject empty prefix to prevent accidental full bucket deletion', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function deleteRustFSPrefix(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain("empty($prefix)");
  });

  test('deleteRustFSPrefix should return deleted count', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function deleteRustFSPrefix(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain("'deleted'");
    expect(func).toContain('$deleted');
  });

  test('deleteRustFSPrefix should log individual deletion failures', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function deleteRustFSPrefix(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('error_log');
    expect(func).toContain('failed to delete object');
  });

  test('deleteRustFSPrefix should return list of failed keys', () => {
    const content = readFile('lib/rustfs_storage.php');
    const funcStart = content.indexOf('function deleteRustFSPrefix(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain("'failed'");
  });

});

// =====================================================
// 4. Complete video lifecycle flow verification
// =====================================================

test.describe('Complete video lifecycle flow', () => {

  test('triggerHlsTranscode sends delete_original=true to companion', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function triggerHlsTranscode(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('"delete_original" => true');
  });

  test('companion _hls_transcode_s3 deletes original after transcoding', () => {
    const content = readFile('companion/app.py');
    const funcStart = content.indexOf('def _hls_transcode_s3');
    const funcEnd = content.indexOf('\ndef ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('delete_original');
    expect(func).toContain('_s3_delete');
  });

  test('companion callback handler updates hls_status to ready on success', () => {
    const content = readFile('api/v1/companion.php');
    expect(content).toContain("hls_status");
    expect(content).toContain(":hls_status");
    expect(content).toContain('hls_master_url');
    expect(content).toContain('hls_segments_path');
  });

  test('companion callback handler sets hls_status to failed on error', () => {
    const content = readFile('api/v1/companion.php');
    expect(content).toContain("hls_status = 'failed'");
  });

  test('triggerHlsTranscode pre-sets hls_url with expected HLS manifest path', () => {
    const content = readFile('process_video.php');
    const funcStart = content.indexOf('function triggerHlsTranscode(');
    const funcEnd = content.indexOf('\nfunction ', funcStart + 1);
    const func = content.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('master.m3u8');
    expect(func).toContain('hls_url');
    expect(func).toContain("hls_status = 'pending'");
  });

  test('HLS player (js/hls-player.js) detects m3u8 URLs and uses HLS.js', () => {
    const content = readFile('js/hls-player.js');
    expect(content).toContain('m3u8');
    expect(content).toContain('Hls.isSupported');
    expect(content).toContain('loadSource');
    expect(content).toContain('attachMedia');
  });

});

// =====================================================
// 5. Admin Video Test page uses shared HLS player
// =====================================================

test.describe('Admin Video Test loadVideoPlayer uses awInitHlsPlayer', () => {

  test('loadVideoPlayer should call awInitHlsPlayer when available', () => {
    const content = readFile('views/admin_video_test.php');
    expect(content).toContain('awInitHlsPlayer');
  });

  test('loadVideoPlayer should check typeof awInitHlsPlayer before calling it', () => {
    const content = readFile('views/admin_video_test.php');
    expect(content).toContain("typeof window.awInitHlsPlayer === 'function'");
  });

  test('loadVideoPlayer should fall back to direct src assignment when awInitHlsPlayer is unavailable', () => {
    const content = readFile('views/admin_video_test.php');
    // The else branch should still set videoPlayer.src as a fallback
    const funcStart = content.indexOf('function loadVideoPlayer(');
    const funcEnd = content.indexOf('\n    }', content.indexOf('loadedmetadata', funcStart));
    const func = content.substring(funcStart, funcEnd);
    expect(func).toContain('videoPlayer.src = hlsUrl');
    expect(func).toContain('videoPlayer.load()');
  });

  test('dashboard.php includes HLS.js CDN and hls-player.js before admin_video_test view', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('hls.min.js');
    expect(content).toContain('js/hls-player.js');
    expect(content).toContain('admin_video_test');
  });

});
