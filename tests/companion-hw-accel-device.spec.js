/**
 * Tests for Companion App HW_ACCEL_DEVICE configuration
 *
 * Verifies:
 * 1. HW_ACCEL_DEVICE is loaded from persistent config with env var fallback
 * 2. _probe_encoder uses HW_ACCEL_DEVICE instead of hardcoded device path
 * 3. _encoder_flags uses HW_ACCEL_DEVICE instead of hardcoded device path
 * 4. _hwaccel_decode_flags uses HW_ACCEL_DEVICE instead of hardcoded device path
 * 5. GET /api/config returns hw_accel_device
 * 6. PUT /api/config accepts and persists hw_accel_device
 * 7. Settings UI has device path input field
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. HW_ACCEL_DEVICE configuration in companion app.py
// =====================================================

test.describe('Companion app.py HW_ACCEL_DEVICE configuration', () => {
  const content = () => readFile('companion/app.py');

  test('should define HW_ACCEL_DEVICE from persistent config with env fallback', () => {
    const c = content();
    expect(c).toContain('HW_ACCEL_DEVICE = _pcfg("hw_accel_device")');
    expect(c).toContain('HW_ACCEL_DEVICE');
  });

  test('_probe_encoder should use HW_ACCEL_DEVICE for vaapi', () => {
    const c = content();
    const probeFunc = c.substring(
      c.indexOf('def _probe_encoder'),
      c.indexOf('def _detect_hw_accel')
    );
    expect(probeFunc).toContain('HW_ACCEL_DEVICE');
    expect(probeFunc).not.toContain('"/dev/dri/renderD128"');
  });

  test('_probe_encoder should use _qsv_render_device(HW_ACCEL_DEVICE) for qsv', () => {
    const c = content();
    const probeFunc = c.substring(
      c.indexOf('def _probe_encoder'),
      c.indexOf('def _detect_hw_accel')
    );
    expect(probeFunc).toContain('child_device={_qsv_render_device(HW_ACCEL_DEVICE)}');
  });

  test('_encoder_flags should use HW_ACCEL_DEVICE not hardcoded path', () => {
    const c = content();
    const flagsFunc = c.substring(
      c.indexOf('def _encoder_flags'),
      c.indexOf('def _hwaccel_decode_flags')
    );
    expect(flagsFunc).toContain('HW_ACCEL_DEVICE');
    expect(flagsFunc).not.toContain('"/dev/dri/renderD128"');
  });

  test('_hwaccel_decode_flags should use HW_ACCEL_DEVICE not hardcoded path', () => {
    const c = content();
    const decodeFunc = c.substring(
      c.indexOf('def _hwaccel_decode_flags'),
      c.indexOf('def _probe_file')
    );
    expect(decodeFunc).toContain('HW_ACCEL_DEVICE');
    expect(decodeFunc).not.toContain('"/dev/dri/renderD128"');
  });
});

// =====================================================
// 2. Config API includes hw_accel_device
// =====================================================

test.describe('Companion app.py config API hw_accel_device', () => {
  const content = () => readFile('companion/app.py');

  test('GET /api/config should return hw_accel_device', () => {
    const c = content();
    const configFunc = c.substring(
      c.indexOf('def get_config'),
      c.indexOf('def update_config')
    );
    expect(configFunc).toContain('hw_accel_device');
  });

  test('PUT /api/config should accept hw_accel_device', () => {
    const c = content();
    const updateFunc = c.substring(
      c.indexOf('def update_config'),
      c.indexOf('def generate_key')
    );
    expect(updateFunc).toContain('"hw_accel_device"');
    expect(updateFunc).toContain('HW_ACCEL_DEVICE');
  });

  test('PUT /api/config should persist hw_accel_device', () => {
    const c = content();
    const updateFunc = c.substring(
      c.indexOf('def update_config'),
      c.indexOf('def generate_key')
    );
    expect(updateFunc).toContain('"hw_accel_device": HW_ACCEL_DEVICE');
  });
});

// =====================================================
// 3. Settings UI has device path field
// =====================================================

test.describe('Companion settings.html hw_accel_device UI', () => {
  const content = () => readFile('companion/templates/settings.html');

  test('should have device path input field', () => {
    const c = content();
    expect(c).toContain('cfg-hw-accel-device');
    expect(c).toContain('hw_accel_device');
  });

  test('should load hw_accel_device in loadConfig', () => {
    const c = content();
    expect(c).toContain("cfg-hw-accel-device').value = c.hw_accel_device");
  });

  test('should save hw_accel_device in saveConfig', () => {
    const c = content();
    expect(c).toContain("payload.hw_accel_device = val('cfg-hw-accel-device')");
  });

  test('should have label mentioning GPU Device Path', () => {
    const c = content();
    expect(c).toContain('GPU Device Path');
  });
});

// =====================================================
// 4. QSV render device resolution (card0 → renderD128)
// =====================================================

test.describe('Companion app.py _qsv_render_device helper', () => {
  const content = () => readFile('companion/app.py');

  test('should define _qsv_render_device function', () => {
    const c = content();
    expect(c).toContain('def _qsv_render_device(device: str) -> str:');
  });

  test('should return device unchanged when already a render node', () => {
    const c = content();
    const func = c.substring(
      c.indexOf('def _qsv_render_device'),
      c.indexOf('def _probe_encoder')
    );
    // Check the regex matches only cardN paths
    expect(func).toContain('/dev/dri/card');
    expect(func).toContain('renderD');
  });

  test('should resolve card node via sysfs lookup', () => {
    const c = content();
    const func = c.substring(
      c.indexOf('def _qsv_render_device'),
      c.indexOf('def _probe_encoder')
    );
    expect(func).toContain('/sys/class/drm/card');
    expect(func).toContain('os.listdir(sysfs_dir)');
  });

  test('should fall back to kernel convention cardN → renderD(128+N)', () => {
    const c = content();
    const func = c.substring(
      c.indexOf('def _qsv_render_device'),
      c.indexOf('def _probe_encoder')
    );
    expect(func).toContain('renderD{128 + int(card_num)}');
  });

  test('_encoder_flags should use _qsv_render_device for qsv', () => {
    const c = content();
    const flagsFunc = c.substring(
      c.indexOf('def _encoder_flags'),
      c.indexOf('def _hwaccel_decode_flags')
    );
    expect(flagsFunc).toContain('_qsv_render_device(HW_ACCEL_DEVICE)');
  });

  test('_hwaccel_decode_flags should use _qsv_render_device for qsv paths', () => {
    const c = content();
    const decodeFunc = c.substring(
      c.indexOf('def _hwaccel_decode_flags'),
      c.indexOf('def _hw_vf')
    );
    // Explicit QSV path (accel == "qsv") should use render device resolution
    const explicitQsv = decodeFunc.substring(
      decodeFunc.indexOf('if accel == "qsv"'),
      decodeFunc.indexOf('if accel in ("vaapi"')
    );
    expect(explicitQsv).toContain('_qsv_render_device(HW_ACCEL_DEVICE)');

    // Auto-detected QSV path (accel == "auto") should also use render device resolution
    const autoBlock = decodeFunc.substring(
      decodeFunc.indexOf('if accel == "auto"')
    );
    expect(autoBlock).toContain('_qsv_render_device(HW_ACCEL_DEVICE)');
  });

  test('_hwaccel_decode_flags should NOT use _qsv_render_device for vaapi paths', () => {
    const c = content();
    const decodeFunc = c.substring(
      c.indexOf('def _hwaccel_decode_flags'),
      c.indexOf('def _hw_vf')
    );
    // VAAPI paths should use HW_ACCEL_DEVICE directly (VAAPI works with card nodes)
    const lines = decodeFunc.split('\n');
    const vaapiLines = lines.filter(l => l.includes('"vaapi"') && l.includes('HW_ACCEL_DEVICE'));
    for (const line of vaapiLines) {
      expect(line).not.toContain('_qsv_render_device');
    }
  });
});

// =====================================================
// 5. Double device open prevention (renderD128 fix)
// =====================================================

test.describe('Companion app.py double device open prevention', () => {
  const content = () => readFile('companion/app.py');

  test('_encoder_flags should accept hw_decode parameter', () => {
    const c = content();
    expect(c).toContain('def _encoder_flags(encoder: str, hw_decode: bool = False)');
  });

  test('_encoder_flags should skip vaapi_device when hw_decode is True', () => {
    const c = content();
    const flagsFunc = c.substring(
      c.indexOf('def _encoder_flags'),
      c.indexOf('def _hwaccel_decode_flags')
    );
    expect(flagsFunc).toContain('if not hw_decode');
    expect(flagsFunc).toContain('-vaapi_device');
  });

  test('_encoder_flags should skip init_hw_device for qsv when hw_decode is True', () => {
    const c = content();
    const flagsFunc = c.substring(
      c.indexOf('def _encoder_flags'),
      c.indexOf('def _hwaccel_decode_flags')
    );
    expect(flagsFunc).toContain('if hw_decode');
    // When hw_decode is True, QSV should only add preset, not device init
    expect(flagsFunc).toContain('-preset');
  });

  test('_select_encoder should accept and forward hw_decode parameter', () => {
    const c = content();
    expect(c).toContain('def _select_encoder(hw_info: dict, codec: str = "h264"');
    expect(c).toContain('hw_decode: bool = False');
    // Should forward hw_decode to _encoder_flags
    const selectFunc = c.substring(
      c.indexOf('def _select_encoder'),
      c.indexOf('def _encoder_flags')
    );
    expect(selectFunc).toContain('hw_decode=hw_decode');
  });

  test('_hwaccel_decode_flags VAAPI should use -vaapi_device instead of -hwaccel_device', () => {
    const c = content();
    const decodeFunc = c.substring(
      c.indexOf('def _hwaccel_decode_flags'),
      c.indexOf('def _hw_vf')
    );
    // VAAPI paths should use -vaapi_device for unified device context
    expect(decodeFunc).toContain('-vaapi_device');
    expect(decodeFunc).toContain('-hwaccel');
    expect(decodeFunc).toContain('"vaapi"');
  });

  test('_hwaccel_decode_flags QSV should use -init_hw_device with named device', () => {
    const c = content();
    const decodeFunc = c.substring(
      c.indexOf('def _hwaccel_decode_flags'),
      c.indexOf('def _hw_vf')
    );
    // QSV decode should create a named device "hw" and reference it
    expect(decodeFunc).toContain('-init_hw_device');
    expect(decodeFunc).toContain('-hwaccel_device');
    expect(decodeFunc).toContain('-filter_hw_device');
  });

  test('transcode call sites should pass hw_decode when decode_flags present', () => {
    const c = content();
    // Call sites that use both decode_flags and encode_flags should pass hw_decode
    expect(c).toContain('hw_decode=bool(decode_flags)');
  });

  test('diagnostic test should NOT use hw_decode (standalone encode)', () => {
    const c = content();
    const diagStart = c.indexOf('def run_diagnostics(');
    const diagEnd = c.indexOf('\ndef ', diagStart + 1);
    const diagBody = c.substring(diagStart, diagEnd > -1 ? diagEnd : undefined);
    // Diagnostic uses standalone encode — must NOT pass hw_decode=True
    expect(diagBody).not.toContain('hw_decode');
  });
});
