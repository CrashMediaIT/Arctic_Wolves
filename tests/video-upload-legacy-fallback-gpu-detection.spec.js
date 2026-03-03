/**
 * Tests for Video Upload 504 / Legacy Fallback & GPU Detection Fixes
 *
 * Verifies:
 * 1. Legacy fallback XHR uses getAttribute('action') instead of .action
 *    property to avoid the <input name="action"> shadowing bug that caused
 *    POST to [object HTMLInputElement].
 * 2. Companion app validates hardware encoders actually work (probe encode)
 *    instead of only checking if they are compiled into FFmpeg.
 * 3. _hwaccel_decode_flags auto mode detects actual hardware rather than
 *    unconditionally assuming CUDA.
 * 4. AMD GPU support in decode flags and Docker Compose overlays.
 * 5. Intel OpenCL Docker Compose overlay for QSV/VA-API.
 * 6. Nextcloud references removed from video upload view comments.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Legacy fallback uses getAttribute('action') not .action
// =====================================================

test.describe('Legacy upload fallback uses getAttribute to avoid input name="action" shadowing', () => {
  const viewFiles = [
    'views/video_record_athlete.php',
    'views/video_coach_reviews.php',
    'views/gameplan/gp_film_room.php',
    'views/gameplan/film_room.php',
  ];

  for (const viewFile of viewFiles) {
    test(`${viewFile} legacy XHR should use getAttribute('action')`, () => {
      const content = readFile(viewFile);
      expect(content).toContain("getAttribute('action')");
    });

    test(`${viewFile} should NOT use uploadForm.action as XHR URL`, () => {
      const content = readFile(viewFile);
      // The .action property is shadowed by <input name="action">,
      // so it must NOT be used as the legacy XHR URL.
      expect(content).not.toContain("legacyXhr.open('POST', uploadForm.action,");
    });
  }
});

// =====================================================
// 2. Companion validates encoders actually work
// =====================================================

test.describe('Companion app probe-validates hardware encoders', () => {
  const content = () => readFile('companion/app.py');

  test('should define _probe_encoder function', () => {
    expect(content()).toContain('def _probe_encoder(');
  });

  test('_probe_encoder should run a minimal FFmpeg encode to validate', () => {
    const c = content();
    const funcStart = c.indexOf('def _probe_encoder(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('subprocess.run');
    expect(funcBody).toContain('-f');
    expect(funcBody).toContain('lavfi');
    expect(funcBody).toContain('returncode == 0');
  });

  test('_detect_hw_accel should call _probe_encoder for each candidate', () => {
    const c = content();
    const funcStart = c.indexOf('def _detect_hw_accel(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('_probe_encoder(hw_enc)');
  });

  test('_detect_hw_accel should only include encoders that pass probe', () => {
    const c = content();
    const funcStart = c.indexOf('def _detect_hw_accel(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // The encoder is only appended if _probe_encoder returns True
    expect(funcBody).toContain('if hw_enc in line and _probe_encoder(hw_enc)');
  });
});

// =====================================================
// 3. Auto mode decode flags detect actual hardware
// =====================================================

test.describe('_hwaccel_decode_flags auto mode detects actual hardware', () => {
  const content = () => readFile('companion/app.py');

  test('auto mode should NOT unconditionally return CUDA flags', () => {
    const c = content();
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // There should NOT be: if accel in ("nvenc", "auto"): return cuda
    expect(funcBody).not.toMatch(/if\s+accel\s+in\s+\("nvenc",\s*"auto"\)/);
  });

  test('auto mode should call _detect_hw_accel to discover usable hardware', () => {
    const c = content();
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // In the auto branch, it should detect hardware
    expect(funcBody).toContain('_detect_hw_accel()');
  });

  test('auto mode should check for nvenc, qsv, and vaapi encoders', () => {
    const c = content();
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('"nvenc"');
    expect(funcBody).toContain('"qsv"');
    expect(funcBody).toContain('"vaapi"');
  });

  test('auto mode should fall back to empty flags if no hardware is usable', () => {
    const c = content();
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // Should have a return [] for the software fallback case
    expect(funcBody).toContain('return []');
  });
});

// =====================================================
// 4. AMD GPU support in decode flags
// =====================================================

test.describe('AMD GPU support in _hwaccel_decode_flags', () => {
  test('should handle amf acceleration mode', () => {
    const c = readFile('companion/app.py');
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    expect(funcBody).toContain('"amf"');
  });

  test('amf mode should use vaapi device for decoding', () => {
    const c = readFile('companion/app.py');
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // AMF uses VAAPI for decoding on Linux
    expect(funcBody).toContain('/dev/dri/renderD128');
  });
});

// =====================================================
// 5. Docker Compose overlays for Intel and AMD
// =====================================================

test.describe('Docker Compose Intel OpenCL overlay', () => {
  const content = () => readFile('companion/docker-compose.intel.yml');

  test('should set HW_ACCEL=qsv', () => {
    expect(content()).toContain('HW_ACCEL=qsv');
  });

  test('should reference linuxserver/mods:jellyfin-opencl-intel', () => {
    expect(content()).toContain('linuxserver/mods:jellyfin-opencl-intel');
  });

  test('should pass /dev/dri device', () => {
    expect(content()).toContain('/dev/dri:/dev/dri');
  });
});

test.describe('Docker Compose AMD GPU overlay', () => {
  const content = () => readFile('companion/docker-compose.amd.yml');

  test('should set HW_ACCEL=vaapi', () => {
    expect(content()).toContain('HW_ACCEL=vaapi');
  });

  test('should reference linuxserver/mods:jellyfin-amd', () => {
    expect(content()).toContain('linuxserver/mods:jellyfin-amd');
  });

  test('should pass /dev/dri device', () => {
    expect(content()).toContain('/dev/dri:/dev/dri');
  });
});

// =====================================================
// 6. Nextcloud reference removed from video upload views
// =====================================================

test.describe('No Nextcloud references in video upload view comments', () => {
  test('video_record_athlete.php should not mention Nextcloud in doc comment', () => {
    const c = readFile('views/video_record_athlete.php');
    // The file header comment should reference RustFS, not Nextcloud
    const header = c.substring(0, 500);
    expect(header).not.toContain('Nextcloud');
    expect(header).toContain('RustFS');
  });
});
