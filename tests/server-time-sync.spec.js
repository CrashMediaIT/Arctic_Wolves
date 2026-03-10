import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for server time:
 * 1. db_config.php does NOT override PHP timezone — system timezone only.
 * 2. MySQL session time_zone is synced using date_default_timezone_get().
 * 3. Docker Compose files expose a TZ environment variable.
 * 4. No database-stored timezone or app_time_offset is used.
 * 5. All time setting UI and actions have been removed.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. No PHP timezone override — system timezone only
// =====================================================

test.describe('No PHP timezone override', () => {
  test('db_config.php does NOT call date_default_timezone_set', () => {
    const content = readFile('db_config.php');
    expect(content).not.toContain('date_default_timezone_set');
  });

  test('db_config.php uses date_default_timezone_get for MySQL sync', () => {
    const content = readFile('db_config.php');
    expect(content).toContain('date_default_timezone_get()');
  });

  test('db_config.php does NOT query system_settings for timezone', () => {
    const content = readFile('db_config.php');
    expect(content).not.toContain("setting_key IN ('timezone'");
    expect(content).not.toContain("'app_time_offset'");
  });

  test('db_config.php does NOT contain _awFallbackTimezone', () => {
    const content = readFile('db_config.php');
    expect(content).not.toContain('_awFallbackTimezone');
  });

  test('APP_TIME_OFFSET is always 0', () => {
    const content = readFile('db_config.php');
    expect(content).toContain("define('APP_TIME_OFFSET', 0)");
  });

  test('appTime() returns time() directly without offset', () => {
    const content = readFile('db_config.php');
    const appTimeFn = content.substring(
      content.indexOf("function appTime()"),
      content.indexOf('}', content.indexOf("function appTime()")) + 1
    );
    expect(appTimeFn).toContain('return time()');
    expect(appTimeFn).not.toContain('APP_TIME_OFFSET');
  });

  test('lib/logger.php does not call date_default_timezone_set', () => {
    const content = readFile('lib/logger.php');
    expect(content).not.toContain('date_default_timezone_set');
  });

  test('error_logger.php does not call date_default_timezone_set', () => {
    const content = readFile('error_logger.php');
    expect(content).not.toContain('date_default_timezone_set');
  });
});

// =====================================================
// 2. MySQL session timezone still synced
// =====================================================

test.describe('MySQL session timezone sync', () => {
  test('db_config.php executes SET time_zone', () => {
    const content = readFile('db_config.php');
    expect(content).toContain("SET time_zone");
  });

  test('db_config.php computes offset with DateTimeZone before SET time_zone', () => {
    const content = readFile('db_config.php');
    const dtPos = content.indexOf('new DateTimeZone($tz_value)');
    const setPos = content.indexOf("SET time_zone");
    expect(dtPos).toBeGreaterThan(-1);
    expect(setPos).toBeGreaterThan(-1);
    expect(dtPos).toBeLessThan(setPos);
  });
});

// =====================================================
// 3. Docker containers have TZ environment variable
// =====================================================

test.describe('Docker Compose TZ environment variable', () => {
  test('Galera docker-compose sets TZ in the common anchor', () => {
    const content = readFile('deployment/docker-compose-galera.yml');
    expect(content).toContain('TZ:');
  });

  test('Galera node-1 environment includes TZ', () => {
    const content = readFile('deployment/docker-compose-galera.yml');
    const node1Start = content.indexOf('galera-node-1:');
    const node2Start = content.indexOf('galera-node-2:');
    expect(node1Start).toBeGreaterThan(-1);
    const node1Block = content.substring(node1Start, node2Start);
    expect(node1Block).toContain('TZ:');
  });

  test('Galera node-2 environment includes TZ', () => {
    const content = readFile('deployment/docker-compose-galera.yml');
    const node2Start = content.indexOf('galera-node-2:');
    const node3Start = content.indexOf('galera-node-3:');
    expect(node2Start).toBeGreaterThan(-1);
    const node2Block = content.substring(node2Start, node3Start);
    expect(node2Block).toContain('TZ:');
  });

  test('Galera node-3 environment includes TZ', () => {
    const content = readFile('deployment/docker-compose-galera.yml');
    const node3Start = content.indexOf('galera-node-3:');
    const proxyStart = content.indexOf('proxysql:');
    expect(node3Start).toBeGreaterThan(-1);
    const node3Block = content.substring(node3Start, proxyStart);
    expect(node3Block).toContain('TZ:');
  });
});

// =====================================================
// 4. Env example files document TZ
// =====================================================

test.describe('Environment example files document TZ', () => {
  test('.env.galera.example includes TZ variable', () => {
    const content = readFile('deployment/galera/.env.galera.example');
    expect(content).toContain('TZ=');
  });
});

// =====================================================
// 5. No time settings UI or actions remain
// =====================================================

test.describe('Time settings removed from UI and backend', () => {
  test('process_settings.php does not contain ntp_sync action', () => {
    const content = readFile('process_settings.php');
    expect(content).not.toContain("case 'ntp_sync'");
  });

  test('process_settings.php does not contain set_manual_time action', () => {
    const content = readFile('process_settings.php');
    expect(content).not.toContain("case 'set_manual_time'");
  });

  test('process_settings.php does not contain reset_time_offset action', () => {
    const content = readFile('process_settings.php');
    expect(content).not.toContain("case 'reset_time_offset'");
  });

  test('admin_system_tools.php does not have NTP sync button', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).not.toContain('awNtpSync');
  });

  test('admin_system_tools.php does not have manual time set', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).not.toContain('awSetManualTime');
  });

  test('admin_system_tools.php shows timezone from Docker ENV as read-only', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Docker TZ environment variable');
  });

  test('settings.php shows timezone as read-only from Docker ENV', () => {
    const content = readFile('views/settings.php');
    expect(content).toContain('from Docker ENV');
    expect(content).toContain('date_default_timezone_get()');
  });

  test('pwa/settings.php shows timezone from Docker ENV', () => {
    const content = readFile('views/pwa/settings.php');
    expect(content).toContain('Docker ENV');
    expect(content).toContain('date_default_timezone_get()');
  });

  test('logger.php does not query DB for timezone', () => {
    const content = readFile('lib/logger.php');
    expect(content).not.toContain("setting_key = 'timezone'");
  });

  test('error_logger.php does not query DB for timezone', () => {
    const content = readFile('error_logger.php');
    expect(content).not.toContain("setting_key = 'timezone'");
  });
});
