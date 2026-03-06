/**
 * Tests for DASH manifest generation and single-encode pipeline
 *
 * Verifies:
 * 1. _ffmpeg_error_summary helper strips FFmpeg version banner
 * 2. HLS transcode uses fMP4 segments (single encode for both HLS + DASH)
 * 3. _build_dash_mpd_from_hls generates MPD from existing fMP4 segments
 * 4. _dash_only_transcode_s3 detects fMP4 vs legacy TS and uses fast path
 * 5. _generate_dash_manifest (legacy) has audio probing and dynamic adaptation_sets
 * 6. hls-player.js prefers DASH on Chrome/Edge/Firefox, HLS on Safari
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. _ffmpeg_error_summary helper
// =====================================================

test.describe('_ffmpeg_error_summary helper', () => {
  const content = () => readFile('companion/app.py');

  test('should define _ffmpeg_error_summary function', () => {
    const c = content();
    expect(c).toContain('def _ffmpeg_error_summary(stderr:');
  });

  test('should skip ffmpeg version banner lines', () => {
    const c = content();
    const funcStart = c.indexOf('def _ffmpeg_error_summary(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('ffmpeg version');
    expect(func).toContain('built with');
    expect(func).toContain('configuration:');
  });

  test('should handle empty stderr', () => {
    const c = content();
    const funcStart = c.indexOf('def _ffmpeg_error_summary(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('no stderr output');
  });
});

// =====================================================
// 2. HLS transcode uses fMP4 segments
// =====================================================

test.describe('HLS transcode fMP4 segments', () => {
  const content = () => readFile('companion/app.py');

  function getHlsTranscodeFunc() {
    const c = content();
    const funcStart = c.indexOf('def _hls_transcode_s3(');
    // The function ends at the next top-level def (which is _dash_content_type)
    const funcEnd = c.indexOf('\ndef _dash_content_type(');
    return c.substring(funcStart, funcEnd);
  }

  test('should use fMP4 segment type for HLS', () => {
    const func = getHlsTranscodeFunc();
    expect(func).toContain('-hls_segment_type');
    expect(func).toContain('fmp4');
  });

  test('should set fMP4 init segment filename', () => {
    const func = getHlsTranscodeFunc();
    expect(func).toContain('-hls_fmp4_init_filename');
    expect(func).toContain('init.mp4');
  });

  test('should use .m4s extension for segments', () => {
    const func = getHlsTranscodeFunc();
    expect(func).toContain('.m4s');
  });

  test('should set HLS version 7 in master playlist for fMP4', () => {
    const func = getHlsTranscodeFunc();
    expect(func).toContain('#EXT-X-VERSION:7');
  });

  test('should call _build_dash_mpd_from_hls instead of _generate_dash_manifest', () => {
    const func = getHlsTranscodeFunc();
    expect(func).toContain('_build_dash_mpd_from_hls');
    expect(func).not.toContain('_generate_dash_manifest');
  });

  test('should not re-encode for DASH (no separate DASH ffmpeg call)', () => {
    const func = getHlsTranscodeFunc();
    // Should NOT have the old pattern of calling _generate_dash_manifest with local_source
    expect(func).not.toContain('_generate_dash_manifest(');
  });
});

// =====================================================
// 3. _build_dash_mpd_from_hls (manifest-only, no re-encode)
// =====================================================

test.describe('_build_dash_mpd_from_hls manifest generator', () => {
  const content = () => readFile('companion/app.py');

  function getBuildDashFunc() {
    const c = content();
    const funcStart = c.indexOf('def _build_dash_mpd_from_hls(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    return c.substring(funcStart, funcEnd);
  }

  test('should define the function', () => {
    expect(content()).toContain('def _build_dash_mpd_from_hls(');
  });

  test('should parse segment durations from HLS playlist', () => {
    const func = getBuildDashFunc();
    expect(func).toContain('#EXTINF:');
    expect(func).toContain('seg_durations');
  });

  test('should probe init segment for codec info', () => {
    const func = getBuildDashFunc();
    expect(func).toContain('_probe_file');
    expect(func).toContain('init.mp4');
  });

  test('should generate valid MPD XML structure', () => {
    const func = getBuildDashFunc();
    expect(func).toContain('<MPD');
    expect(func).toContain('</MPD>');
    expect(func).toContain('AdaptationSet');
    expect(func).toContain('Representation');
    expect(func).toContain('SegmentList');
    expect(func).toContain('Initialization');
    expect(func).toContain('SegmentURL');
  });

  test('should reference fMP4 segments via relative paths to HLS dirs', () => {
    const func = getBuildDashFunc();
    // MPD references segments in ../label/ directories (shared with HLS)
    expect(func).toContain('../');
    expect(func).toContain('init.mp4');
  });

  test('should NOT invoke ffmpeg or subprocess', () => {
    const func = getBuildDashFunc();
    expect(func).not.toContain('subprocess');
    expect(func).not.toContain('FFMPEG_PATH');
  });

  test('should write MPD file to dash directory', () => {
    const func = getBuildDashFunc();
    expect(func).toContain('manifest.mpd');
    expect(func).toContain('dash');
  });
});

// =====================================================
// 4. _dash_only_transcode_s3 fMP4 detection + fast path
// =====================================================

test.describe('_dash_only_transcode_s3 smart detection', () => {
  const content = () => readFile('companion/app.py');

  function getDashOnlyFunc() {
    const c = content();
    const funcStart = c.indexOf('def _dash_only_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _run_dash_job(');
    return c.substring(funcStart, funcEnd);
  }

  test('should detect fMP4 vs TS segment format', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('is_fmp4');
    expect(func).toContain('.m4s');
  });

  test('should use _build_dash_mpd_from_hls for fMP4 (no FFmpeg)', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('_build_dash_mpd_from_hls');
    expect(func).toContain('manifest only');
  });

  test('should keep legacy FFmpeg re-mux path for TS segments', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('Legacy TS segments');
    expect(func).toContain('"-c:v", "copy"');
    expect(func).toContain('"-c:a", "aac"');
  });

  test('should try downloading init.mp4 for fMP4 detection', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('init.mp4');
    expect(func).toContain('has_init');
  });

  test('should only upload manifest for fMP4 path (segments shared)', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('segments shared with HLS');
  });
});

// =====================================================
// 5. _generate_dash_manifest (legacy) audio handling
// =====================================================

test.describe('_generate_dash_manifest legacy fallback', () => {
  const content = () => readFile('companion/app.py');

  function getGenerateDashFunc() {
    const c = content();
    const funcStart = c.indexOf('def _generate_dash_manifest(');
    const funcEnd = c.indexOf('\ndef _resolution_width(');
    return c.substring(funcStart, funcEnd);
  }

  test('should be marked as legacy in docstring', () => {
    const func = getGenerateDashFunc();
    expect(func).toContain('legacy');
  });

  test('should build adaptation_sets dynamically', () => {
    const func = getGenerateDashFunc();
    expect(func).toContain('video_streams');
    expect(func).toContain('audio_streams');
    expect(func).toContain('adapt_parts');
  });

  test('should use _ffmpeg_error_summary for error reporting', () => {
    const func = getGenerateDashFunc();
    expect(func).toContain('_ffmpeg_error_summary');
  });
});

// =====================================================
// 6. hls-player.js browser-based format selection
// =====================================================

test.describe('hls-player.js format selection', () => {
  const content = () => readFile('js/hls-player.js');

  test('should prefer DASH for non-Safari MSE browsers', () => {
    const c = content();
    expect(c).toContain('preferDash');
    expect(c).toContain('isSafari');
  });

  test('should check for dash.js and MediaSource before preferring DASH', () => {
    const c = content();
    // Must verify dashjs is loaded AND MediaSource is available
    expect(c).toContain("typeof dashjs !== 'undefined'");
    expect(c).toContain("typeof MediaSource !== 'undefined'");
  });

  test('should fall back to HLS if DASH init fails', () => {
    const c = content();
    expect(c).toContain('_awHlsFallbackUrl');
    expect(c).toContain('falling back to HLS');
  });

  test('should update playback priority in docs', () => {
    const c = content();
    expect(c).toContain('MPEG-DASH via dash.js on Chrome/Edge/Firefox');
    expect(c).toContain('Native HLS on Safari/iOS');
  });

  test('should store HLS fallback URL when preferring DASH', () => {
    const c = content();
    expect(c).toContain('_awHlsFallbackUrl');
  });

  test('DASH error handler should fall back to HLS', () => {
    const c = content();
    expect(c).toContain('_awDashFallbackAttempted');
    expect(c).toContain('awInitHlsPlayer');
  });
});
