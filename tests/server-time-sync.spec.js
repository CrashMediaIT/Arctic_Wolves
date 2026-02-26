import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for server time synchronisation:
 * 1. db_config.php sets the MySQL session time_zone after loading the
 *    application timezone from system_settings.
 * 2. Docker Compose files expose a TZ environment variable so container
 *    system clocks match the configured timezone.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. MySQL session timezone set in db_config.php
// =====================================================

test.describe('MySQL session timezone sync', () => {
  test('db_config.php executes SET time_zone after loading timezone from settings', () => {
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

  test('SET time_zone is executed after date_default_timezone_set', () => {
    const content = readFile('db_config.php');
    const phpTzPos = content.indexOf('date_default_timezone_set($tz_value)');
    const mysqlTzPos = content.indexOf("SET time_zone");
    expect(phpTzPos).toBeGreaterThan(-1);
    expect(mysqlTzPos).toBeGreaterThan(-1);
    expect(phpTzPos).toBeLessThan(mysqlTzPos);
  });
});

// =====================================================
// 2. Docker containers have TZ environment variable
// =====================================================

test.describe('Docker Compose TZ environment variable', () => {
  test('Galera docker-compose sets TZ in the common anchor', () => {
    const content = readFile('deployment/docker-compose-galera.yml');
    expect(content).toContain('TZ:');
  });

  test('Galera node-1 environment includes TZ', () => {
    const content = readFile('deployment/docker-compose-galera.yml');
    // TZ should appear in the galera-node-1 service section
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

  test('Companion docker-compose sets TZ', () => {
    const content = readFile('companion/docker-compose.yml');
    expect(content).toContain('TZ=');
  });
});

// =====================================================
// 3. Env example files document TZ
// =====================================================

test.describe('Environment example files document TZ', () => {
  test('.env.galera.example includes TZ variable', () => {
    const content = readFile('deployment/galera/.env.galera.example');
    expect(content).toContain('TZ=');
  });

  test('companion .env.example includes TZ variable', () => {
    const content = readFile('companion/.env.example');
    expect(content).toContain('TZ=');
  });
});
