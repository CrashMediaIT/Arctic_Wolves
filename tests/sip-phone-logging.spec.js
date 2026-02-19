/**
 * Tests for SIP Phone View - showNotification, Logging & SIP URI Dialing
 *
 * Verifies:
 * 1. showNotification function is defined in sip_settings.php
 * 2. showNotification supports error, success, warning, and info types
 * 3. Console logging is present for SIP settings save
 * 4. Calls use sip: URI protocol to open native SIP app or FusionPBX Web Dialer
 * 5. JsSIP/WebRTC is removed (calls delegated to external SIP apps)
 * 6. Info about native SIP app / FusionPBX Web Dialer is shown
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const sipSettingsPath = path.join(__dirname, '..', 'views', 'sip_settings.php');

// ================================================
// 1. showNotification Function Defined
// ================================================
test.describe('SIP Settings - showNotification defined', () => {
  test('sip_settings.php should define showNotification function', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('function showNotification(message, type');
  });

  test('showNotification should support warning type', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('alert-warning');
  });

  test('showNotification should support error type', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('alert-error');
  });

  test('showNotification should support success type', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('alert-success');
  });

  test('showNotification should default to info type', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('alert-info');
  });
});

// ================================================
// 2. SIP URI Dialing (Native App / FusionPBX Web Dialer)
// ================================================
test.describe('SIP Settings - SIP URI Dialing', () => {
  test('callExtension should build sip: URI', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("'sip:' + extension + '@' + domain");
  });

  test('callExtension should use window.location.href for sip: URI', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('window.location.href = sipUri');
  });

  test('should log SIP URI when calling', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Opening SIP URI:'");
  });

  test('callExtension should check domain is configured', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('Please configure your SIP domain first');
  });
});

// ================================================
// 3. JsSIP/WebRTC Removed
// ================================================
test.describe('SIP Settings - JsSIP/WebRTC Removed', () => {
  test('should not include JsSIP library', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).not.toContain('jssip');
    expect(content).not.toContain('JsSIP');
  });

  test('should not have WebRTC session handling', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).not.toContain('handleSession');
    expect(content).not.toContain('peerconnection');
    expect(content).not.toContain('RTCSession');
  });

  test('should not have SIP registration flow', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).not.toContain('registerSip');
    expect(content).not.toContain('doSipRegister');
    expect(content).not.toContain('sipUA');
  });

  test('should not have call modal', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).not.toContain('sip-call-modal');
    expect(content).not.toContain('endCall');
  });
});

// ================================================
// 4. Info About Native SIP App / FusionPBX Web Dialer
// ================================================
test.describe('SIP Settings - Native App / Extension Info', () => {
  test('should mention FusionPBX Web Dialer browser extension', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('FusionPBX Web Dialer');
  });

  test('should mention native SIP applications', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('native SIP application');
  });

  test('should explain how calls work', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('How calls work');
    expect(content).toContain('sip:');
  });
});

// ================================================
// 5. Console Logging for Save Operations
// ================================================
test.describe('SIP Settings - Save & Credential Logging', () => {
  test('should log SIP settings save', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Saving SIP settings...'");
  });

  test('should log save success', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Settings saved successfully'");
  });

  test('should log save errors', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.error('[SIP] Save error:'");
  });
});
