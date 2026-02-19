/**
 * Tests for SIP Phone View - showNotification & Logging
 *
 * Verifies:
 * 1. showNotification function is defined in sip_settings.php
 * 2. showNotification supports error, success, warning, and info types
 * 3. Console logging is present for SIP connection flow
 * 4. Console logging is present for SIP registration events
 * 5. Console logging is present for call events
 * 6. Console logging is present for WebSocket events
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
});

// ================================================
// 2. Console Logging for SIP Connection
// ================================================
test.describe('SIP Settings - Connection Logging', () => {
  test('should log WebSocket connection URL', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Connecting via WebSocket:'");
  });

  test('should log SIP URI during registration', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] SIP URI:");
  });

  test('should log WebSocket connected event', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] UA WebSocket connected'");
  });

  test('should log WebSocket disconnected event', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.warn('[SIP] UA WebSocket disconnected:'");
  });
});

// ================================================
// 3. Console Logging for SIP Registration
// ================================================
test.describe('SIP Settings - Registration Logging', () => {
  test('should log successful registration', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Registration successful:'");
  });

  test('should log registration failure', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.error('[SIP] Registration failed");
  });

  test('should log JsSIP UA start', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Starting JsSIP User Agent...'");
  });
});

// ================================================
// 4. Console Logging for Call Events
// ================================================
test.describe('SIP Settings - Call Event Logging', () => {
  test('should log call initiation', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Initiating call to:'");
  });

  test('should log call accepted', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Call accepted'");
  });

  test('should log call ended', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] Call ended:'");
  });

  test('should log call failure', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.error('[SIP] Call failed");
  });

  test('should log ICE connection state changes', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] ICE connection state:'");
  });
});

// ================================================
// 5. Console Logging for Save & Credential Operations
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

  test('should log credential retrieval', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("console.log('[SIP] No password entered, fetching saved credentials...'");
  });
});
