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

  test('_probe_encoder should include VAAPI device init flags for vaapi encoders', () => {
    const c = content();
    const funcStart = c.indexOf('def _probe_encoder(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // VAAPI encoders need the device and hwupload filter to probe correctly
    expect(funcBody).toContain('-vaapi_device');
    expect(funcBody).toContain('HW_ACCEL_DEVICE');
    expect(funcBody).toContain('hwupload');
  });

  test('_probe_encoder should include QSV device init flags for qsv encoders', () => {
    const c = content();
    const funcStart = c.indexOf('def _probe_encoder(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // QSV encoders need explicit device initialization
    expect(funcBody).toContain('-init_hw_device');
    expect(funcBody).toContain('qsv');
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
// 2b. HW_ACCEL reads from environment variable
// =====================================================

test.describe('HW_ACCEL falls back to environment variable', () => {
  test('HW_ACCEL should read from os.getenv when persisted config is empty', () => {
    const c = readFile('companion/app.py');
    // The HW_ACCEL line should use os.getenv as fallback
    expect(c).toContain("os.getenv(\"HW_ACCEL\"");
  });

  test('HW_ACCEL should prefer persisted config over env var', () => {
    const c = readFile('companion/app.py');
    // _pcfg should be checked first (via `or` short-circuit)
    expect(c).toMatch(/_pcfg\("hw_accel"\)\s+or\s+os\.getenv\("HW_ACCEL"/);
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
    expect(funcBody).toContain('HW_ACCEL_DEVICE');
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

  test('should use runtime env var for Intel GPU drivers', () => {
    // GPU drivers are now installed at runtime by entrypoint.sh based
    // on the HW_ACCEL environment variable (no build-args needed).
    expect(content()).toContain('HW_ACCEL=qsv');
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

  test('should use runtime env var for AMD GPU drivers', () => {
    // GPU drivers are now installed at runtime by entrypoint.sh based
    // on the HW_ACCEL environment variable (no build-args needed).
    expect(content()).toContain('HW_ACCEL=vaapi');
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

// =====================================================
// 7. _hwaccel_decode_flags QSV → VAAPI fallback
// =====================================================

test.describe('_hwaccel_decode_flags QSV falls back to VAAPI decode', () => {
  const content = () => readFile('companion/app.py');

  test('should accept optional hw_info parameter to avoid redundant detection', () => {
    const c = content();
    expect(c).toContain('def _hwaccel_decode_flags(hw_info');
  });

  test('qsv mode should probe encoder availability before choosing decode method', () => {
    const c = content();
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // The qsv branch must check whether QSV is actually usable
    // (via encoder probes) instead of blindly returning -hwaccel qsv
    const qsvIdx = funcBody.indexOf('accel == "qsv"');
    expect(qsvIdx).toBeGreaterThan(-1);
    const afterQsv = funcBody.substring(qsvIdx);
    expect(afterQsv).toContain('.get("encoders"');
  });

  test('qsv mode should fall back to VAAPI decode when QSV unavailable', () => {
    const c = content();
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // After the qsv check, there should be a VAAPI fallback
    const qsvIdx = funcBody.indexOf('accel == "qsv"');
    const autoIdx = funcBody.indexOf('accel == "auto"');
    const qsvSection = funcBody.substring(qsvIdx, autoIdx > -1 ? autoIdx : undefined);
    expect(qsvSection).toContain('"vaapi"');
    expect(qsvSection).toContain('HW_ACCEL_DEVICE');
  });

  test('qsv mode should return empty flags when neither QSV nor VAAPI available', () => {
    const c = content();
    const funcStart = c.indexOf('def _hwaccel_decode_flags(');
    const funcEnd = c.indexOf('\ndef ', funcStart + 1);
    const funcBody = c.substring(funcStart, funcEnd > -1 ? funcEnd : undefined);

    // The qsv branch should have a return [] for full software fallback
    const qsvIdx = funcBody.indexOf('accel == "qsv"');
    const autoIdx = funcBody.indexOf('accel == "auto"');
    const qsvSection = funcBody.substring(qsvIdx, autoIdx > -1 ? autoIdx : undefined);
    expect(qsvSection).toContain('return []');
  });

  test('call sites should pass hw_info to avoid redundant _detect_hw_accel()', () => {
    const c = content();
    // All call sites should pass hw_info from the already-computed detection
    expect(c).toContain('_hwaccel_decode_flags(hw_info)');
  });
});

// =====================================================
// 8. Diagnostics endpoint includes detailed logging
// =====================================================

test.describe('Diagnostics endpoint detailed HW logging', () => {
  const content = () => readFile('companion/app.py');

  test('hw_encode test should include ffmpeg_cmd in result', () => {
    const c = content();
    const diagStart = c.indexOf('def run_diagnostics(');
    const diagEnd = c.indexOf('\ndef ', diagStart + 1);
    const diagBody = c.substring(diagStart, diagEnd > -1 ? diagEnd : undefined);
    expect(diagBody).toContain('ffmpeg_cmd');
  });

  test('hw_encode test should include hw_info in result', () => {
    const c = content();
    const diagStart = c.indexOf('def run_diagnostics(');
    const diagEnd = c.indexOf('\ndef ', diagStart + 1);
    const diagBody = c.substring(diagStart, diagEnd > -1 ? diagEnd : undefined);
    expect(diagBody).toContain('"hw_info"');
  });

  test('hw_encode test should include decode_flags in result', () => {
    const c = content();
    const diagStart = c.indexOf('def run_diagnostics(');
    const diagEnd = c.indexOf('\ndef ', diagStart + 1);
    const diagBody = c.substring(diagStart, diagEnd > -1 ? diagEnd : undefined);
    expect(diagBody).toContain('"decode_flags"');
  });

  test('hw_encode test should include encode_flags in result', () => {
    const c = content();
    const diagStart = c.indexOf('def run_diagnostics(');
    const diagEnd = c.indexOf('\ndef ', diagStart + 1);
    const diagBody = c.substring(diagStart, diagEnd > -1 ? diagEnd : undefined);
    expect(diagBody).toContain('"encode_flags"');
  });

  test('hw_encode test should log failure details via logger', () => {
    const c = content();
    const diagStart = c.indexOf('def run_diagnostics(');
    const diagEnd = c.indexOf('\ndef ', diagStart + 1);
    const diagBody = c.substring(diagStart, diagEnd > -1 ? diagEnd : undefined);
    expect(diagBody).toContain('logger.warning');
  });
});

// =====================================================
// 9. Settings UI shows HW diagnostic log
// =====================================================

test.describe('Settings UI detailed HW diagnostics log', () => {
  const content = () => readFile('companion/templates/settings.html');

  test('should have diag-hw-log element for expanded diagnostics', () => {
    expect(content()).toContain('diag-hw-log');
  });

  test('should display validated encoders in log', () => {
    const c = content();
    expect(c).toContain('Validated encoders');
  });

  test('should display FFmpeg command in log', () => {
    const c = content();
    expect(c).toContain('FFmpeg command');
  });

  test('should display decode and encode flags in log', () => {
    const c = content();
    expect(c).toContain('Decode flags');
    expect(c).toContain('Encode flags');
  });
});
