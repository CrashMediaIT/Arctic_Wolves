import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. getClientIP() function resolves public IP via proxy headers
 * 2. POS IP restriction uses getClientIP() instead of REMOTE_ADDR
 * 3. Security logging uses getClientIP()
 * 4. Time tracking uses getClientIP()
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. getClientIP() function in security.php
// =====================================================

test.describe('getClientIP function', () => {
  test('security.php defines getClientIP function', () => {
    const content = readFile('security.php');
    expect(content).toContain('function getClientIP()');
  });

  test('getClientIP checks HTTP_X_FORWARDED_FOR header', () => {
    const content = readFile('security.php');
    // The function should check proxy forwarding headers
    expect(content).toContain("'HTTP_X_FORWARDED_FOR'");
  });

  test('getClientIP checks HTTP_CLIENT_IP header', () => {
    const content = readFile('security.php');
    expect(content).toContain("'HTTP_CLIENT_IP'");
  });

  test('getClientIP handles comma-separated IPs in forwarded header', () => {
    const content = readFile('security.php');
    // Must handle "client, proxy1, proxy2" format
    expect(content).toContain("strpos($ip, ',')");
    expect(content).toContain("explode(',', $ip)");
  });

  test('getClientIP validates IP with FILTER_VALIDATE_IP', () => {
    const content = readFile('security.php');
    expect(content).toContain('FILTER_VALIDATE_IP');
  });

  test('getClientIP rejects private/reserved IPs in proxy headers (anti-spoofing)', () => {
    const content = readFile('security.php');
    const fnMatch = content.match(/function getClientIP\(\)\s*\{[\s\S]*?^}/m);
    expect(fnMatch).not.toBeNull();
    const fnBody = fnMatch[0];
    expect(fnBody).toContain('FILTER_FLAG_NO_PRIV_RANGE');
    expect(fnBody).toContain('FILTER_FLAG_NO_RES_RANGE');
  });

  test('getClientIP falls back to REMOTE_ADDR as last resort', () => {
    const content = readFile('security.php');
    const fnMatch = content.match(/function getClientIP\(\)\s*\{[\s\S]*?^}/m);
    expect(fnMatch).not.toBeNull();
    const fnBody = fnMatch[0];
    expect(fnBody).toContain("'REMOTE_ADDR'");
  });
});

// =====================================================
// 2. POS IP restriction uses getClientIP
// =====================================================

test.describe('POS IP restriction uses getClientIP', () => {
  test('checkPOSIPAccess uses getClientIP instead of REMOTE_ADDR', () => {
    const content = readFile('security.php');
    // Extract the checkPOSIPAccess function body
    const fnStart = content.indexOf('function checkPOSIPAccess');
    expect(fnStart).toBeGreaterThan(-1);
    const fnBody = content.substring(fnStart, fnStart + 500);
    expect(fnBody).toContain('getClientIP()');
    expect(fnBody).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('POS terminal view uses getClientIP in security log context', () => {
    const content = readFile('views/pos_terminal.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
    // Should not use REMOTE_ADDR directly for IP logging
    expect(content).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('POS time tracking view uses getClientIP in security log context', () => {
    const content = readFile('views/pos_time_tracking.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
    expect(content).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('POS online orders view uses getClientIP in security log context', () => {
    const content = readFile('views/pos_online_orders.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
    expect(content).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('POS transactions view uses getClientIP in security log context', () => {
    const content = readFile('views/pos_transactions.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
    expect(content).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('Inventory management view uses getClientIP in security log context', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
    expect(content).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('process_pos.php uses getClientIP in security log context', () => {
    const content = readFile('process_pos.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
  });

  test('POS kiosk uses getClientIP in security log context', () => {
    const content = readFile('pos_kiosk.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
  });

  test('Dashboard kiosk uses getClientIP in security log context', () => {
    const content = readFile('dashboard_kiosk.php');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('getClientIP()');
  });
});

// =====================================================
// 3. Security logging uses getClientIP
// =====================================================

test.describe('Security logging uses getClientIP', () => {
  test('logSecurityEvent uses getClientIP instead of REMOTE_ADDR', () => {
    const content = readFile('security.php');
    // Extract the logSecurityEvent function body
    const fnStart = content.indexOf('function logSecurityEvent');
    expect(fnStart).toBeGreaterThan(-1);
    const fnBody = content.substring(fnStart, fnStart + 600);
    expect(fnBody).toContain('getClientIP()');
    expect(fnBody).not.toContain("$_SERVER['REMOTE_ADDR']");
  });
});

// =====================================================
// 4. Time tracking uses getClientIP
// =====================================================

test.describe('Time tracking uses getClientIP', () => {
  test('process_time_tracking uses getClientIP for audit IP logging', () => {
    const content = readFile('process_time_tracking.php');
    expect(content).toContain('getClientIP()');
  });
});
