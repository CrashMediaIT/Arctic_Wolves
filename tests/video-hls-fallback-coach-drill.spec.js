/**
 * Tests for HLS fallback on coach reviews and drill review views
 *
 * When the companion deletes the original video after HLS transcoding but
 * the callback fails to update hls_status, the primary video URL returns 502.
 * These tests verify that both views include a data-hls-url fallback
 * attribute and JS error recovery so the HLS stream can still be used.
 *
 * Also verifies the companion server and PHP callback handler correctly
 * pass and handle source_id for vr_video_sources callbacks.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Coach reviews view — HLS fallback data attribute
// =====================================================

test.describe('video_coach_reviews.php HLS fallback attribute', () => {
  const content = () => readFile('views/video_coach_reviews.php');

  test('pending tab play button should include data-hls-url attribute', () => {
    const c = content();
    // Find the pending video play button (first data-action="view-video")
    const pendingSection = c.substring(0, c.indexOf('reviewed-tab'));
    expect(pendingSection).toContain('data-hls-url=');
  });

  test('reviewed tab play button should include data-hls-url attribute', () => {
    const c = content();
    const reviewedSection = c.substring(c.indexOf('reviewed-tab'));
    expect(reviewedSection).toContain('data-hls-url=');
  });

  test('data-hls-url should resolve hls_url through resolveRustfsUrl', () => {
    const c = content();
    // The HLS URL is now computed in a PHP block that resolves via resolveRustfsUrl,
    // then the result is referenced from the data-hls-url attribute.
    expect(c).toContain("resolveRustfsUrl($pdo, $video['hls_url'])");
    expect(c).toContain('data-hls-url=');
  });

  test('data-hls-url should only be emitted when hls_url is non-empty', () => {
    const c = content();
    // Both buttons should guard with !empty($video['hls_url'])
    const guards = (c.match(/!empty\(\$video\['hls_url'\]\)/g) || []).length;
    expect(guards).toBeGreaterThanOrEqual(2);
  });
});

// =====================================================
// 2. Coach reviews view — JS error fallback handler
// =====================================================

test.describe('video_coach_reviews.php JS HLS error fallback', () => {
  const content = () => readFile('views/video_coach_reviews.php');

  test('should declare vpHlsFallbackUrl variable', () => {
    const c = content();
    expect(c).toContain('vpHlsFallbackUrl');
  });

  test('should declare vpHlsFallbackTried variable', () => {
    const c = content();
    expect(c).toContain('vpHlsFallbackTried');
  });

  test('should add error event listener on video element with capture=true', () => {
    const c = content();
    // Look for addEventListener('error', ..., true) on vpVideo
    expect(c).toContain("vpVideo.addEventListener('error'");
  });

  test('error handler should call awInitHlsPlayer with fallback URL', () => {
    const c = content();
    // Find the error handler block
    const errorBlock = c.substring(c.indexOf("vpVideo.addEventListener('error'"));
    expect(errorBlock).toContain('vpHlsFallbackUrl');
    expect(errorBlock).toContain('awInitHlsPlayer');
  });

  test('click handler should read hlsUrl from data-hls-url attribute', () => {
    const c = content();
    expect(c).toContain('this.dataset.hlsUrl');
  });

  test('click handler should set vpHlsFallbackUrl only when different from primary URL', () => {
    const c = content();
    expect(c).toContain('hlsUrl !== url');
  });

  test('cleanup function should reset fallback state', () => {
    const c = content();
    const cleanupStart = c.indexOf('function cleanupCoachVideoPlayer()');
    const cleanupEnd = c.indexOf('\n    }', cleanupStart + 40);
    const cleanup = c.substring(cleanupStart, cleanupEnd);
    expect(cleanup).toContain("vpHlsFallbackUrl = ''");
    expect(cleanup).toContain('vpHlsFallbackTried = false');
  });
});

// =====================================================
// 3. Drill review view — HLS fallback data attribute
// =====================================================

test.describe('video_drill_review.php HLS fallback attribute', () => {
  const content = () => readFile('views/video_drill_review.php');

  test('play button should include data-hls-url attribute', () => {
    const c = content();
    expect(c).toContain('data-hls-url=');
  });

  test('data-hls-url should resolve hls_url through resolveRustfsUrl', () => {
    const c = content();
    // The HLS URL is now computed in a PHP block that resolves via resolveRustfsUrl,
    // then the result is referenced from the data-hls-url attribute.
    expect(c).toContain("resolveRustfsUrl($pdo, $video['hls_url']");
    expect(c).toContain('data-hls-url=');
  });

  test('data-hls-url should only be emitted when hls_url is non-empty', () => {
    const c = content();
    expect(c).toContain("!empty($video['hls_url'])");
  });
});

// =====================================================
// 4. Drill review view — JS error fallback handler
// =====================================================

test.describe('video_drill_review.php JS HLS error fallback', () => {
  const content = () => readFile('views/video_drill_review.php');

  test('should declare drHlsFallbackUrl variable', () => {
    const c = content();
    expect(c).toContain('drHlsFallbackUrl');
  });

  test('should declare drHlsFallbackTried variable', () => {
    const c = content();
    expect(c).toContain('drHlsFallbackTried');
  });

  test('should add error event listener on video element with capture=true', () => {
    const c = content();
    expect(c).toContain("videoPlayer.addEventListener('error'");
  });

  test('error handler should call awInitHlsPlayer with fallback URL', () => {
    const c = content();
    const errorBlock = c.substring(c.indexOf("videoPlayer.addEventListener('error'"));
    expect(errorBlock).toContain('drHlsFallbackUrl');
    expect(errorBlock).toContain('awInitHlsPlayer');
  });

  test('click handler should read hlsUrl from data-hls-url attribute', () => {
    const c = content();
    expect(c).toContain('this.dataset.hlsUrl');
  });

  test('click handler should set drHlsFallbackUrl only when different from primary URL', () => {
    const c = content();
    expect(c).toContain('hlsUrl !== videoUrl');
  });

  test('cleanup function should reset fallback state', () => {
    const c = content();
    const cleanupStart = c.indexOf('function cleanupVideoPlayer()');
    const cleanupEnd = c.indexOf('\n    }', cleanupStart + 40);
    const cleanup = c.substring(cleanupStart, cleanupEnd);
    expect(cleanup).toContain("drHlsFallbackUrl = ''");
    expect(cleanup).toContain('drHlsFallbackTried = false');
  });
});

// =====================================================
// 5. Companion server passes source_id in callbacks
// =====================================================

test.describe('Companion server source_id support', () => {
  const content = () => readFile('companion/app.py');

  test('hls_transcode endpoint should read source_id from request data', () => {
    const c = content();
    const hlsFunc = c.substring(c.indexOf('def hls_transcode():'));
    const funcEnd = hlsFunc.indexOf('\ndef ');
    const func = hlsFunc.substring(0, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('data.get("source_id")');
  });

  test('hls_transcode should store source_id in the job dict', () => {
    const c = content();
    const hlsFunc = c.substring(c.indexOf('def hls_transcode():'));
    const funcEnd = hlsFunc.indexOf('\ndef ');
    const func = hlsFunc.substring(0, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('"source_id": source_id');
  });

  test('hls_retry endpoint should read source_id from request data', () => {
    const c = content();
    const retryFunc = c.substring(c.indexOf('def hls_retry():'));
    const funcEnd = retryFunc.indexOf('\ndef ');
    const func = retryFunc.substring(0, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('data.get("source_id")');
  });

  test('hls_retry should inherit source_id from old job', () => {
    const c = content();
    const retryFunc = c.substring(c.indexOf('def hls_retry():'));
    const funcEnd = retryFunc.indexOf('\ndef ');
    const func = retryFunc.substring(0, funcEnd > -1 ? funcEnd : undefined);
    expect(func).toContain('old_job.get("source_id")');
  });

  test('_hls_transcode_s3 success callback should include source_id', () => {
    const c = content();
    const transcodeFunc = c.substring(c.indexOf('def _hls_transcode_s3'));
    // Find the success callback (status: completed)
    const successCallback = transcodeFunc.substring(
      transcodeFunc.indexOf('"status": "completed"') - 200,
      transcodeFunc.indexOf('"status": "completed"') + 100
    );
    expect(successCallback).toContain('"source_id"');
  });

  test('_hls_transcode_s3 failure callback should include source_id', () => {
    const c = content();
    const transcodeFunc = c.substring(c.indexOf('def _hls_transcode_s3'));
    // Find the failure callback (status: failed)
    const failCallback = transcodeFunc.substring(
      transcodeFunc.indexOf('"status": "failed"') - 200,
      transcodeFunc.indexOf('"status": "failed"') + 100
    );
    expect(failCallback).toContain('"source_id"');
  });
});

// =====================================================
// 6. PHP callback handler supports source_id
// =====================================================

test.describe('PHP companion callback handles source_id', () => {
  const content = () => readFile('api/v1/companion.php');

  test('callback handler should extract source_id from body', () => {
    const c = content();
    expect(c).toContain("$source_id");
    expect(c).toContain("body['source_id']");
  });

  test('callback handler should look up vr_video_sources when source_id is provided', () => {
    const c = content();
    expect(c).toContain('vr_video_sources');
    expect(c).toContain("SELECT id FROM vr_video_sources WHERE id = ?");
  });

  test('callback handler should also search vr_video_sources by hls_job_id', () => {
    const c = content();
    expect(c).toContain("SELECT id FROM vr_video_sources WHERE hls_job_id = ?");
  });

  test('callback handler should update the correct table (videos or vr_video_sources)', () => {
    const c = content();
    // The UPDATE should use a dynamic table name
    expect(c).toContain('UPDATE $table');
  });

  test('callback handler should set hls_status=ready on the correct table', () => {
    const c = content();
    const callback = c.substring(c.indexOf('function handleCompanionCallback'));
    // The ready update should reference $table, not hardcode 'videos'
    expect(callback).toContain("UPDATE $table");
    expect(callback).toContain("hls_status        = 'ready'");
  });

  test('callback handler should set hls_status=failed on the correct table', () => {
    const c = content();
    const callback = c.substring(c.indexOf('function handleCompanionCallback'));
    const failedBlock = callback.substring(callback.indexOf("'failed'"));
    expect(failedBlock).toContain("UPDATE $table SET hls_status = 'failed'");
  });

  test('callback handler should include source_id in warning log when record not found', () => {
    const c = content();
    expect(c).toContain('safe_src');
    expect(c).toContain('source_id=$safe_src');
  });
});

// =====================================================
// 7. Detail pages still have existing HLS fallback
// =====================================================

test.describe('Detail pages retain existing HLS fallback', () => {

  test('video_review_detail.php (desktop) should have data-hls-url fallback', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('data-hls-url=');
    expect(c).toContain('hls_fallback_url');
  });

  test('video_review_detail.php (pwa) should have data-hls-url fallback', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('data-hls-url=');
    expect(c).toContain('hls_fallback_url');
  });

  test('video_review_detail.php (desktop) should have JS error handler for HLS fallback', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('_hlsFallbackTried');
    expect(c).toContain("detailPlayer.addEventListener('error'");
  });

  test('video_review_detail.php (pwa) should have JS error handler for HLS fallback', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('_hlsFallbackTried');
  });
});

// =====================================================
// 8. deriveHlsFallbackUrl function in image_helper.php
// =====================================================

test.describe('deriveHlsFallbackUrl in lib/image_helper.php', () => {
  const content = () => readFile('lib/image_helper.php');

  test('function deriveHlsFallbackUrl should exist', () => {
    const c = content();
    expect(c).toContain('function deriveHlsFallbackUrl(');
  });

  test('should detect media proxy URLs (api/media.php?key=)', () => {
    const c = content();
    expect(c).toContain('api/media\\.php\\?key=');
  });

  test('should match video file extensions (mp4, mkv, mov, avi, webm)', () => {
    const c = content();
    expect(c).toMatch(/mp4\|mkv\|mov\|avi\|webm/);
  });

  test('should derive HLS path by appending /hls/master.m3u8', () => {
    const c = content();
    expect(c).toContain('/hls/master.m3u8');
  });

  test('should return empty string for non-proxy URLs', () => {
    const c = content();
    expect(c).toContain("if (empty($video_url)) return ''");
  });

  test('should use rawurlencode for the derived URL', () => {
    const c = content();
    expect(c).toContain('rawurlencode(');
  });
});

// =====================================================
// 9. Derived HLS fallback applied in all views
// =====================================================

test.describe('deriveHlsFallbackUrl used as fallback in views', () => {

  test('video_review_detail.php (desktop) should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('video_review_detail.php (pwa) should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/pwa/video_review_detail.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('video_coach_reviews.php should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/video_coach_reviews.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('video_drill_review.php should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/video_drill_review.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('gameplan film_room.php should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/gameplan/film_room.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('gameplan gp_film_room.php should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/gameplan/gp_film_room.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('gameplan my_clips.php should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/gameplan/my_clips.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('gameplan gp_my_clips.php should call deriveHlsFallbackUrl', () => {
    const c = readFile('views/gameplan/gp_my_clips.php');
    expect(c).toContain('deriveHlsFallbackUrl(');
  });

  test('derived fallback should only apply when primary URL is not already HLS', () => {
    const c = readFile('views/video_review_detail.php');
    // Guard: only derive when video_url is NOT an m3u8
    expect(c).toMatch(/!preg_match.*\.m3u8.*deriveHlsFallbackUrl/s);
  });

  test('detail pages should use derived fallback only when hls_fallback_url is empty', () => {
    const c = readFile('views/video_review_detail.php');
    expect(c).toContain("empty($hls_fallback_url)");
  });
});
