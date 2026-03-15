import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * Arctic Wolves - Valkey Cache Integration Tests
 * Tests to verify the Valkey cache settings UI in Database tab,
 * backend handlers in process_settings.php, and the ValkeyCache library.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. ValkeyCache Library Structure
// =====================================================

test.describe('Valkey Cache Library - lib/valkey_cache.php', () => {

  test('valkey_cache.php should exist', () => {
    expect(fs.existsSync(path.join(ROOT, 'lib/valkey_cache.php'))).toBe(true);
  });

  test('should define ValkeyCache class', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('class ValkeyCache');
  });

  test('should have init() method for lazy initialization', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function init(');
  });

  test('should have isEnabled() method', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function isEnabled()');
  });

  test('should have core cache operations: get, set, delete', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function get(');
    expect(content).toContain('public static function set(');
    expect(content).toContain('public static function delete(');
  });

  test('should have flushPrefix() to safely flush only app keys', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function flushPrefix()');
    // Should use prefix pattern, not flushAll
    expect(content).not.toContain('flushAll');
    expect(content).not.toContain('flushDB');
  });

  test('should have increment() for rate limiting counters', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function increment(');
  });

  test('should have testConnection() for settings UI', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function testConnection(');
  });

  test('should have getStats() for admin dashboard', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function getStats()');
  });

  test('should check for Redis extension availability', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain("class_exists('Redis')");
  });

  test('should use connection timeout to prevent blocking', () => {
    const content = readFile('lib/valkey_cache.php');
    // Should have a timeout parameter in connect call
    expect(content).toMatch(/connect\(\$host,\s*\$port,\s*\d/);
  });

  test('should handle connection failures gracefully', () => {
    const content = readFile('lib/valkey_cache.php');
    // Should catch exceptions in init
    expect(content).toContain("catch (\\Exception");
    // Should return false/null on failure, not throw
    expect(content).toContain('self::$enabled = false');
  });

  test('should use key prefix to avoid collisions', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain("self::\$prefix");
    expect(content).toContain("self::\$prefix . \$key");
  });

  test('should have TTL constants for different cache categories', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('TTL_SETTINGS');
    expect(content).toContain('TTL_USER_ROLE');
    expect(content).toContain('TTL_RATE_LIMIT');
    expect(content).toContain('TTL_DASHBOARD');
  });

  test('should only initialize once per request', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('self::$initialized');
    // Check that init returns early if already initialized
    expect(content).toMatch(/if\s*\(self::\$initialized\)/);
  });

  test('should load config from database and environment', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain("VALKEY_ENABLED");
    expect(content).toContain("VALKEY_HOST");
    expect(content).toContain("VALKEY_PORT");
    expect(content).toContain("valkey_%");
  });

  // Domain-specific helpers

  test('should have getSystemSettings() for settings caching', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function getSystemSettings(');
    expect(content).toContain('system_settings');
  });

  test('should have invalidateSettings() for cache busting', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function invalidateSettings()');
  });

  test('should have setUserRole() and getUserRole()', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function setUserRole(');
    expect(content).toContain('public static function getUserRole(');
    expect(content).toContain("'user_role:'");
  });

  test('should have rateLimit() helper', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function rateLimit(');
    // Should return true (allow) when cache is unavailable
    expect(content).toMatch(/return true;.*Allow/s);
  });

  test('should have dashboard stats caching helpers', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('public static function setDashboardStats(');
    expect(content).toContain('public static function getDashboardStats(');
    expect(content).toContain("'dash:'");
  });

  test('should JSON-encode complex values', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('json_encode');
    expect(content).toContain('json_decode');
  });

  test('should verify connection with PING', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('->ping()');
    expect(content).toContain('+PONG');
  });

  test('testConnection should return server info on success', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain("'version'");
    expect(content).toContain("'uptime'");
    expect(content).toContain("'memory_used'");
    expect(content).toContain("'memory_peak'");
    expect(content).toContain("'connected_clients'");
    expect(content).toContain("'total_keys'");
  });
});

// =====================================================
// 2. Database Tab - Valkey Settings UI
// =====================================================

test.describe('System Tools - Database Tab - Valkey Settings UI', () => {

  test('Database tab should have Valkey Cache section', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Valkey Cache');
    expect(content).toContain('valkey-status-badge');
  });

  test('Valkey section should be marked as optional', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Valkey Cache (Optional)');
    expect(content).toContain('completely optional');
  });

  test('should have enable/disable toggle', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="valkey-enabled"');
    expect(content).toContain('type="checkbox"');
    expect(content).toContain('Enable Valkey Cache');
    expect(content).toContain('toggleValkeyFields()');
  });

  test('should have connection settings fields', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="valkey-host"');
    expect(content).toContain('id="valkey-port"');
    expect(content).toContain('id="valkey-password"');
    expect(content).toContain('id="valkey-database"');
    expect(content).toContain('id="valkey-prefix"');
  });

  test('password field should be type password', () => {
    const content = readFile('views/admin_system_tools.php');
    // Find the input element near the valkey-password id
    const passwordMatch = content.match(/type="password"[^>]*id="valkey-password"|id="valkey-password"[^>]*type="password"/);
    expect(passwordMatch).not.toBeNull();
  });

  test('should have Save, Test Connection, and Flush Cache buttons', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('saveValkeySettings()');
    expect(content).toContain('testValkeyConnection()');
    expect(content).toContain('flushValkeyCache()');
  });

  test('should have result area for feedback', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="valkey-result"');
  });

  test('should have cache status display area', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="valkey-status-content"');
    expect(content).toContain('id="valkey-status-card"');
  });

  test('should list features that use Valkey cache', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('Features That Use Valkey Cache');
    expect(content).toContain('System Settings');
    expect(content).toContain('User Roles');
    expect(content).toContain('Rate Limiting');
    expect(content).toContain('Dashboard Statistics');
  });

  test('should show TTL information for each feature', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('TTL: 5 minutes');
    expect(content).toContain('TTL: 10 minutes');
    expect(content).toContain('TTL: 1 hour');
    expect(content).toContain('TTL: 2 minutes');
  });

  test('should display status badge showing enabled/disabled state', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('id="valkey-status-badge"');
    expect(content).toContain('ENABLED');
    expect(content).toContain('DISABLED');
  });

  test('should have informational description about Valkey', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('What is Valkey?');
    expect(content).toContain('Redis-compatible');
    expect(content).toContain('in-memory data store');
  });

  test('should load current settings from database', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain("valkey_enabled");
    expect(content).toContain("valkey_host");
    expect(content).toContain("valkey_port");
    expect(content).toContain("valkey_password");
    expect(content).toContain("valkey_database");
    expect(content).toContain("valkey_prefix");
  });

  test('should use htmlspecialchars for output safety', () => {
    const content = readFile('views/admin_system_tools.php');
    // The Valkey settings section should use htmlspecialchars
    expect(content).toContain('htmlspecialchars($valkey_host)');
    expect(content).toContain('htmlspecialchars($valkey_port)');
    expect(content).toContain('htmlspecialchars($valkey_password)');
  });
});

// =====================================================
// 3. JavaScript Functions for Valkey Management
// =====================================================

test.describe('Valkey Cache JavaScript Functions', () => {

  test('should have toggleValkeyFields() function', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function toggleValkeyFields()');
  });

  test('should have saveValkeySettings() function', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function saveValkeySettings()');
    expect(content).toContain("'save_valkey_settings'");
  });

  test('should have testValkeyConnection() function', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function testValkeyConnection()');
    expect(content).toContain("'test_valkey'");
  });

  test('should have flushValkeyCache() function', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function flushValkeyCache()');
    expect(content).toContain("'flush_valkey'");
  });

  test('saveValkeySettings should update status badge on success', () => {
    const content = readFile('views/admin_system_tools.php');
    // Check it updates the badge
    const saveFunc = content.substring(
      content.indexOf('function saveValkeySettings()'),
      content.indexOf('function testValkeyConnection()')
    );
    expect(saveFunc).toContain('valkey-status-badge');
    expect(saveFunc).toContain('ENABLED');
    expect(saveFunc).toContain('DISABLED');
  });

  test('testValkeyConnection should display server info table', () => {
    const content = readFile('views/admin_system_tools.php');
    const testFunc = content.substring(
      content.indexOf('function testValkeyConnection()'),
      content.indexOf('function flushValkeyCache()')
    );
    expect(testFunc).toContain('Server Version');
    expect(testFunc).toContain('Uptime');
    expect(testFunc).toContain('Memory Used');
    expect(testFunc).toContain('Memory Peak');
    expect(testFunc).toContain('Connected Clients');
    expect(testFunc).toContain('Total Keys');
  });

  test('should use escapeHtml for XSS prevention', () => {
    const content = readFile('views/admin_system_tools.php');
    // Valkey functions should escape output
    expect(content).toContain('escapeHtml(data.message');
  });

  test('should send CSRF token with Valkey requests', () => {
    const content = readFile('views/admin_system_tools.php');
    expect(content).toContain('function valkeyPost(');
    expect(content).toContain('csrf_token: getCsrfToken()');
  });

  test('should show loading spinner during async operations', () => {
    const content = readFile('views/admin_system_tools.php');
    // Check for spinner in save, test, and flush functions
    const valkeySection = content.substring(
      content.indexOf('Valkey Cache Management'),
      content.indexOf('End Valkey Cache Management')
    );
    const spinnerCount = (valkeySection.match(/fa-spinner fa-spin/g) || []).length;
    expect(spinnerCount).toBeGreaterThanOrEqual(3);
  });
});

// =====================================================
// 4. Backend Handlers in process_settings.php
// =====================================================

test.describe('process_settings.php - Valkey Handlers', () => {

  test('should register Valkey actions as JSON actions', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("'save_valkey_settings'");
    expect(content).toContain("'test_valkey'");
    expect(content).toContain("'flush_valkey'");
    // Verify they are in the json_actions array
    const jsonActionsLine = content.match(/\$json_actions\s*=\s*\[([^\]]+)\]/);
    expect(jsonActionsLine).not.toBeNull();
    expect(jsonActionsLine[1]).toContain('save_valkey_settings');
    expect(jsonActionsLine[1]).toContain('test_valkey');
    expect(jsonActionsLine[1]).toContain('flush_valkey');
  });

  test('should have save_valkey_settings case handler', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'save_valkey_settings':");
  });

  test('save_valkey_settings should validate host format', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    expect(saveHandler).toContain('Invalid host address');
    expect(saveHandler).toMatch(/preg_match.*valkey_host/);
  });

  test('save_valkey_settings should validate port range', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    expect(saveHandler).toContain('Port must be between');
    expect(saveHandler).toContain('65535');
  });

  test('save_valkey_settings should validate database index', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    expect(saveHandler).toContain('Database index must be between');
  });

  test('save_valkey_settings should validate prefix format', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    expect(saveHandler).toContain('Invalid prefix');
  });

  test('save_valkey_settings should persist all settings via updateSetting()', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    expect(saveHandler).toContain("updateSetting($pdo, 'valkey_enabled'");
    expect(saveHandler).toContain("updateSetting($pdo, 'valkey_host'");
    expect(saveHandler).toContain("updateSetting($pdo, 'valkey_port'");
    expect(saveHandler).toContain("updateSetting($pdo, 'valkey_password'");
    expect(saveHandler).toContain("updateSetting($pdo, 'valkey_database'");
    expect(saveHandler).toContain("updateSetting($pdo, 'valkey_prefix'");
  });

  test('save_valkey_settings should log to auditor', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    expect(saveHandler).toContain('Auditor::log');
    expect(saveHandler).toContain('save_valkey_settings');
  });

  test('should have test_valkey case handler', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'test_valkey':");
    expect(content).toContain('ValkeyCache::testConnection');
  });

  test('test_valkey should require valkey_cache.php', () => {
    const content = readFile('process_settings.php');
    const testHandler = content.substring(
      content.indexOf("case 'test_valkey':"),
      content.indexOf("case 'flush_valkey':")
    );
    expect(testHandler).toContain("require_once");
    expect(testHandler).toContain('valkey_cache.php');
  });

  test('should have flush_valkey case handler', () => {
    const content = readFile('process_settings.php');
    expect(content).toContain("case 'flush_valkey':");
    expect(content).toContain('ValkeyCache::flushPrefix');
  });

  test('flush_valkey should check if Valkey is enabled before flushing', () => {
    const content = readFile('process_settings.php');
    const flushHandler = content.substring(
      content.indexOf("case 'flush_valkey':"),
      content.indexOf("End Valkey Cache Management")
    );
    expect(flushHandler).toContain('ValkeyCache::isEnabled()');
    expect(flushHandler).toContain('not enabled or not connected');
  });

  test('flush_valkey should log to auditor', () => {
    const content = readFile('process_settings.php');
    const flushHandler = content.substring(
      content.indexOf("case 'flush_valkey':"),
      content.indexOf("End Valkey Cache Management")
    );
    expect(flushHandler).toContain('Auditor::log');
    expect(flushHandler).toContain('flush_valkey_cache');
  });

  test('save_valkey_settings should sanitize enabled value to 0 or 1', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    // Should force to '1' or '0'
    expect(saveHandler).toMatch(/=== '1' \? '1' : '0'/);
  });

  test('save_valkey_settings should cast port to integer', () => {
    const content = readFile('process_settings.php');
    const saveHandler = content.substring(
      content.indexOf("case 'save_valkey_settings':"),
      content.indexOf("case 'test_valkey':")
    );
    expect(saveHandler).toContain('intval');
  });
});

// =====================================================
// 5. Integration - Settings Load & Display
// =====================================================

test.describe('Valkey Settings Integration', () => {

  test('admin_system_tools.php should read valkey settings from $settings array', () => {
    const content = readFile('views/admin_system_tools.php');
    // Should use the $settings array populated at the top
    expect(content).toContain("$settings['valkey_enabled']");
    expect(content).toContain("$settings['valkey_host']");
    expect(content).toContain("$settings['valkey_port']");
    expect(content).toContain("$settings['valkey_password']");
    expect(content).toContain("$settings['valkey_database']");
    expect(content).toContain("$settings['valkey_prefix']");
  });

  test('Valkey section should be inside the database-tab div', () => {
    const content = readFile('views/admin_system_tools.php');
    const dbTabStart = content.indexOf('id="database-tab"');
    const valkeySection = content.indexOf('Valkey Cache (Optional)');
    // Find the next tab after database tab
    const nextTab = content.indexOf('id="cron-tab"');
    
    expect(dbTabStart).toBeGreaterThan(-1);
    expect(valkeySection).toBeGreaterThan(dbTabStart);
    expect(valkeySection).toBeLessThan(nextTab);
  });

  test('Valkey settings section should come after Galera Cluster section', () => {
    const content = readFile('views/admin_system_tools.php');
    const clusterEnd = content.indexOf('End Cluster Management');
    const valkeyStart = content.indexOf('Valkey Cache Settings');
    
    expect(clusterEnd).toBeGreaterThan(-1);
    expect(valkeyStart).toBeGreaterThan(clusterEnd);
  });

  test('ValkeyCache library should gracefully degrade when disabled', () => {
    const content = readFile('lib/valkey_cache.php');
    // get() should return null when disabled
    expect(content).toMatch(/function get\(.*\)[\s\S]*?if \(!self::\$enabled.*\) return null/);
    // set() should return false when disabled
    expect(content).toMatch(/function set\(.*\)[\s\S]*?if \(!self::\$enabled.*\) return false/);
    // delete() should return 0 when disabled
    expect(content).toMatch(/function delete\(.*\)[\s\S]*?if \(!self::\$enabled.*\) return 0/);
  });

  test('ValkeyCache should not use flushAll or flushDB (safety)', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).not.toContain('flushAll');
    expect(content).not.toContain('flushDB');
  });

  test('ValkeyCache should support both Valkey and Redis version strings', () => {
    const content = readFile('lib/valkey_cache.php');
    expect(content).toContain('redis_version');
    expect(content).toContain('valkey_version');
  });
});
