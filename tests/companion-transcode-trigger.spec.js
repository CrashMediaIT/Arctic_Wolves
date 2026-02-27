/**
 * Tests for Companion Transcode Trigger
 *
 * Verifies that all video upload handlers in process_video.php trigger
 * HLS transcoding via the companion server, and that the companion app
 * performs resolution-aware transcoding (skipping upscale).
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. All upload handlers must trigger HLS transcoding
// =====================================================

test.describe('All upload handlers trigger HLS transcoding', () => {
  const content = () => readFile('process_video.php');

  test('handleVideoUpload should call triggerHlsTranscode with guard', () => {
    const c = content();
    const funcStart = c.indexOf('function handleVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('triggerHlsTranscode');
    expect(func).toContain("persist['object_key']");
    expect(func).toContain("!empty($persist['object_key'])");
  });

  test('handleAthleteVideoUpload should call triggerHlsTranscode with guard', () => {
    const c = content();
    const funcStart = c.indexOf('function handleAthleteVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('triggerHlsTranscode');
    expect(func).toContain("persist['object_key']");
    expect(func).toContain("!empty($persist['object_key'])");
  });

  test('handleDrillVideoUpload should call triggerHlsTranscode with guard', () => {
    const c = content();
    const funcStart = c.indexOf('function handleDrillVideoUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('triggerHlsTranscode');
    expect(func).toContain("persist['object_key']");
    expect(func).toContain("!empty($persist['object_key'])");
  });

  test('handleConfirmAthleteUpload should call triggerHlsTranscode with guard', () => {
    const c = content();
    const funcStart = c.indexOf('function handleConfirmAthleteUpload()');
    const funcEnd = c.indexOf('\nfunction ', funcStart + 1);
    const func = c.substring(funcStart, funcEnd);
    expect(func).toContain('triggerHlsTranscode');
    expect(func).toContain('!empty($object_key)');
  });

  test('triggerHlsTranscode should pass source_key and output_prefix to companion', () => {
    const c = content();
    const func = c.substring(c.indexOf('function triggerHlsTranscode'));
    expect(func).toContain('"source_key"');
    expect(func).toContain('"output_prefix"');
    expect(func).toContain('"video_id"');
    expect(func).toContain('"callback_url"');
  });
});

// =====================================================
// 2. Companion resolution-aware transcoding
// =====================================================

test.describe('Companion resolution-aware transcoding', () => {
  const content = () => readFile('companion/app.py');

  test('should define HLS_VARIANTS with multiple resolutions', () => {
    const c = content();
    expect(c).toContain('HLS_VARIANTS');
    expect(c).toContain('"height": 360');
    expect(c).toContain('"height": 480');
    expect(c).toContain('"height": 720');
    expect(c).toContain('"height": 1080');
  });

  test('should probe source video resolution before transcoding', () => {
    const c = content();
    expect(c).toContain('_probe_file(local_source)');
    expect(c).toContain('source_height');
  });

  test('should filter variants to those at or below source resolution', () => {
    const c = content();
    expect(c).toContain('v["height"] <= source_height');
  });

  test('should produce at minimum 360p even for very low-res sources', () => {
    const c = content();
    expect(c).toContain('variants = [HLS_VARIANTS[0]]');
  });
});
