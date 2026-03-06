/**
 * Tests for DASH manifest generation fixes
 *
 * Verifies:
 * 1. _ffmpeg_error_summary helper strips FFmpeg version banner
 * 2. _dash_only_transcode_s3 re-encodes audio as AAC (not copy)
 * 3. _dash_only_transcode_s3 probes segments for audio before building command
 * 4. adaptation_sets are built dynamically from actual stream layout
 * 5. _generate_dash_manifest probes source for audio
 * 6. Error reporting uses _ffmpeg_error_summary
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
// 2. _dash_only_transcode_s3 re-encodes audio as AAC
// =====================================================

test.describe('_dash_only_transcode_s3 DASH command', () => {
  const content = () => readFile('companion/app.py');

  function getDashOnlyFunc() {
    const c = content();
    const funcStart = c.indexOf('def _dash_only_transcode_s3(');
    const funcEnd = c.indexOf('\ndef _run_dash_job(');
    return c.substring(funcStart, funcEnd);
  }

  test('should re-encode audio as AAC instead of copy', () => {
    const func = getDashOnlyFunc();
    // Must use AAC encoding, NOT copy for audio
    expect(func).toContain('"-c:a", "aac"');
    // Should NOT have -c:a copy in the DASH command
    expect(func).not.toContain('"-c:a", "copy"');
  });

  test('should set per-stream audio bitrate from variant config', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('abitrate');
    expect(func).toContain('-b:a:');
  });

  test('should probe first segment for audio presence', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('_probe_file');
    expect(func).toContain('codec_type');
    expect(func).toContain('audio');
    expect(func).toContain('has_audio');
  });

  test('should build adaptation_sets dynamically from stream layout', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('video_streams');
    expect(func).toContain('audio_streams');
    expect(func).toContain('stream_idx');
    expect(func).toContain('adapt_parts');
  });

  test('should only map audio streams when audio is detected', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('if has_audio');
  });

  test('should copy video codec', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('"-c:v", "copy"');
  });

  test('should use _ffmpeg_error_summary for error reporting', () => {
    const func = getDashOnlyFunc();
    expect(func).toContain('_ffmpeg_error_summary');
    expect(func).toContain('err_summary');
  });
});

// =====================================================
// 3. _generate_dash_manifest audio probing
// =====================================================

test.describe('_generate_dash_manifest audio handling', () => {
  const content = () => readFile('companion/app.py');

  function getGenerateDashFunc() {
    const c = content();
    const funcStart = c.indexOf('def _generate_dash_manifest(');
    const funcEnd = c.indexOf('\ndef _resolution_width(');
    return c.substring(funcStart, funcEnd);
  }

  test('should probe source for audio presence', () => {
    const func = getGenerateDashFunc();
    expect(func).toContain('_probe_file');
    expect(func).toContain('has_audio');
    expect(func).toContain('codec_type');
  });

  test('should build adaptation_sets dynamically', () => {
    const func = getGenerateDashFunc();
    expect(func).toContain('video_streams');
    expect(func).toContain('audio_streams');
    expect(func).toContain('adapt_parts');
  });

  test('should only include audio codec settings when audio detected', () => {
    const func = getGenerateDashFunc();
    expect(func).toContain('if has_audio');
  });

  test('should use _ffmpeg_error_summary for error reporting', () => {
    const func = getGenerateDashFunc();
    expect(func).toContain('_ffmpeg_error_summary');
  });
});
