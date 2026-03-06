import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. NGINX real_ip module enabled for HAProxy proxy forwarding
 * 2. Application-wide timezone loaded from database system_settings
 * 3. ErrorLogger and Logger use proxy-aware IP detection
 * 4. RateLimiter logging uses getClientIP() instead of REMOTE_ADDR
 * 5. Login/register/audit files use getClientIP()
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. NGINX real_ip directives enabled
// =====================================================

test.describe('NGINX real_ip configuration for HAProxy', () => {
  test('arctic_wolves.conf has set_real_ip_from for private subnets (uncommented)', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    // These should be active (not commented out)
    const lines = content.split('\n');
    const realIpLines = lines.filter(l => l.match(/^\s*set_real_ip_from\s/));
    expect(realIpLines.length).toBeGreaterThanOrEqual(1);
    expect(content).toContain('set_real_ip_from 10.0.0.0/8');
    expect(content).toContain('set_real_ip_from 172.16.0.0/12');
    expect(content).toContain('set_real_ip_from 192.168.0.0/16');
  });

  test('arctic_wolves.conf has real_ip_header X-Forwarded-For (uncommented)', () => {
    const content = readFile('deployment/arctic_wolves.conf');
    const lines = content.split('\n');
    const headerLine = lines.find(l => l.match(/^\s*real_ip_header\s+X-Forwarded-For/));
    expect(headerLine).toBeTruthy();
  });
});

// =====================================================
// 2. Timezone loaded from database at bootstrap
// =====================================================

test.describe('Application timezone from database', () => {
  test('db_config.php loads timezone from system_settings after connection', () => {
    const content = readFile('db_config.php');
    expect(content).toContain("'timezone'");
    expect(content).toContain("'app_time_offset'");
    expect(content).toContain('date_default_timezone_set');
    expect(content).toContain('timezone_identifiers_list');
  });

  test('db_config.php applies timezone before defining DB_CONNECTED constant', () => {
    const content = readFile('db_config.php');
    const tzPos = content.indexOf('date_default_timezone_set($tz_value)');
    const constPos = content.indexOf("define('DB_CONNECTED'");
    expect(tzPos).toBeGreaterThan(-1);
    expect(constPos).toBeGreaterThan(-1);
    expect(tzPos).toBeLessThan(constPos);
  });
});

// =====================================================
// 3. ErrorLogger proxy-aware IP detection
// =====================================================

test.describe('ErrorLogger proxy-aware IP detection', () => {
  test('error_logger.php defines resolveClientIP method', () => {
    const content = readFile('error_logger.php');
    expect(content).toContain('resolveClientIP');
  });

  test('error_logger resolveClientIP checks X-Forwarded-For', () => {
    const content = readFile('error_logger.php');
    expect(content).toContain("'HTTP_X_FORWARDED_FOR'");
  });

  test('error_logger logToDatabase uses resolveClientIP', () => {
    const content = readFile('error_logger.php');
    const fnMatch = content.match(/function logToDatabase[\s\S]*?^\s{4}\}/m);
    expect(fnMatch).not.toBeNull();
    expect(fnMatch[0]).toContain('resolveClientIP');
    expect(fnMatch[0]).not.toContain("$_SERVER['REMOTE_ADDR']");
  });
});

// =====================================================
// 4. Logger proxy-aware IP detection
// =====================================================

test.describe('Logger proxy-aware IP detection', () => {
  test('lib/logger.php defines resolveClientIP method', () => {
    const content = readFile('lib/logger.php');
    expect(content).toContain('resolveClientIP');
  });

  test('Logger logSecurityEvent uses resolveClientIP instead of REMOTE_ADDR', () => {
    const content = readFile('lib/logger.php');
    const fnStart = content.indexOf('function logSecurityEvent');
    expect(fnStart).toBeGreaterThan(-1);
    const fnBody = content.substring(fnStart, fnStart + 500);
    expect(fnBody).toContain('resolveClientIP');
    expect(fnBody).not.toContain("$_SERVER['REMOTE_ADDR']");
  });
});

// =====================================================
// 5. RateLimiter logging uses getClientIP
// =====================================================

test.describe('RateLimiter logging uses getClientIP', () => {
  test('RateLimiter recordRequest uses getClientIP', () => {
    const content = readFile('lib/rate_limiter.php');
    const fnStart = content.indexOf('function recordRequest');
    const fnEnd = content.indexOf('function logViolation');
    expect(fnStart).toBeGreaterThan(-1);
    expect(fnEnd).toBeGreaterThan(fnStart);
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('getClientIP');
    expect(fnBody).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('RateLimiter logViolation uses getClientIP', () => {
    const content = readFile('lib/rate_limiter.php');
    const fnStart = content.indexOf('function logViolation');
    const fnEnd = content.indexOf('function getClientIP');
    expect(fnStart).toBeGreaterThan(-1);
    expect(fnEnd).toBeGreaterThan(fnStart);
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('getClientIP');
    expect(fnBody).not.toContain("$_SERVER['REMOTE_ADDR']");
  });
});

// =====================================================
// 6. Login/register files use getClientIP
// =====================================================

test.describe('Login and registration use getClientIP', () => {
  test('process_login.php uses getClientIP for login history', () => {
    const content = readFile('process_login.php');
    const fnStart = content.indexOf('function recordLoginHistory');
    expect(fnStart).toBeGreaterThan(-1);
    const fnBody = content.substring(fnStart, fnStart + 500);
    expect(fnBody).toContain('getClientIP()');
    expect(fnBody).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('login.php uses getClientIP for login history', () => {
    const content = readFile('login.php');
    const fnStart = content.indexOf('function recordLoginHistory');
    expect(fnStart).toBeGreaterThan(-1);
    const fnBody = content.substring(fnStart, fnStart + 500);
    expect(fnBody).toContain('getClientIP()');
    expect(fnBody).not.toContain("$_SERVER['REMOTE_ADDR']");
  });

  test('process_register.php uses getClientIP for agreement IP', () => {
    const content = readFile('process_register.php');
    expect(content).toContain('getClientIP()');
    // No lines should assign REMOTE_ADDR directly to $client_ip
    const agreementLines = content.split('\n').filter(l =>
      l.includes('$client_ip') && l.includes("REMOTE_ADDR")
    );
    expect(agreementLines.length).toBe(0);
  });

  test('process_agreements.php uses getClientIP', () => {
    const content = readFile('process_agreements.php');
    expect(content).toContain('getClientIP()');
    expect(content).not.toContain("$_SERVER['REMOTE_ADDR']");
  });
});
