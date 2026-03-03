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

  test('_probe_encoder should use HW_ACCEL_DEVICE for qsv', () => {
    const c = content();
    const probeFunc = c.substring(
      c.indexOf('def _probe_encoder'),
      c.indexOf('def _detect_hw_accel')
    );
    expect(probeFunc).toContain('child_device={HW_ACCEL_DEVICE}');
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
