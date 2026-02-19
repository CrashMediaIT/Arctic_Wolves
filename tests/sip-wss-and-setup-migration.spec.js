/**
 * Tests for SIP WSS Port 7443 Configuration & Production Setup with Existing Database
 * 
 * Verifies:
 * 1. SIP settings include WSS port field defaulting to 7443
 * 2. WSS port is used in the WebSocket connection to FusionPBX
 * 3. WSS port is saved via process_profile_update.php
 * 4. Database schema includes sip_wss_port column
 * 5. Setup wizard detects existing databases
 * 6. Setup wizard validates encryption key against existing data
 * 7. Setup wizard runs schema migration using DatabaseMigrator
 * 8. Setup wizard verifies schema correctness after migration
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

// ================================================
// 1. SIP WSS Port 7443 - Database Schema
// ================================================
test.describe('SIP WSS Port - Database Schema', () => {
  test('database_schema.sql should include sip_wss_port column', async () => {
    const filePath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('sip_wss_port');
    expect(content).toContain('DEFAULT 7443');
  });

  test('sip_wss_port column should have correct type and comment', async () => {
    const filePath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('`sip_wss_port` INT DEFAULT 7443');
    expect(content).toContain('WebSocket Secure port');
  });
});

// ================================================
// 2. SIP WSS Port - Settings UI
// ================================================
test.describe('SIP WSS Port - Settings UI', () => {
  test('sip_settings.php should have WSS port input field', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('sip_wss_port');
    expect(content).toContain('id="sip_wss_port"');
    expect(content).toContain('name="sip_wss_port"');
  });

  test('sip_settings.php WSS port field should default to 7443', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'7443'");
    expect(content).toContain('placeholder="7443"');
  });

  test('sip_settings.php should fetch sip_wss_port from database', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('sip_wss_port FROM users');
  });

  test('sip_settings.php should have FusionPBX WSS port label', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('WSS Port');
    expect(content).toContain('FusionPBX connection');
  });
});

// ================================================
// 3. SIP WSS Port - WebSocket Connection
// ================================================
test.describe('SIP WSS Port - WebSocket Connection', () => {
  test('sip_settings.php should have configurable WSS port field', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Should read WSS port from the form field
    expect(content).toContain("document.getElementById('sip_wss_port')");
  });

  test('sip_settings.php should default to 7443 if WSS port is empty', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Should fallback to 7443
    expect(content).toContain("|| '7443'");
  });

  test('saveSipSettings should include WSS port in the request', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('sip_wss_port=');
  });
});

// ================================================
// 4. SIP WSS Port - Backend Save
// ================================================
test.describe('SIP WSS Port - Backend Save', () => {
  test('process_profile_update.php should handle sip_wss_port in update_own_sip', async () => {
    const filePath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('sip_wss_port');
    expect(content).toContain('SET sip_username = ?, sip_domain = ?');
  });

  test('process_profile_update.php should validate WSS port range', async () => {
    const filePath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('$sip_wss_port < 1');
    expect(content).toContain('$sip_wss_port > 65535');
    expect(content).toContain('$sip_wss_port = 7443');
  });

  test('process_profile_update.php should save WSS port for all update paths', async () => {
    const filePath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Count occurrences of sip_wss_port in SQL SET clauses.
    // There are 4 update paths: admin+password, admin-no-password, non-admin+password, non-admin-no-password
    const matches = content.match(/sip_wss_port\s*=\s*\?/g) || [];
    expect(matches.length).toBeGreaterThanOrEqual(4);
  });
});

// ================================================
// 5. Setup - Existing Database Detection
// ================================================
test.describe('Setup - Existing Database Detection', () => {
  test('setup.php should detect existing databases with tables', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('SHOW TABLES');
    expect(content).toContain('existing_database');
    expect(content).toContain('has_users_table');
  });

  test('setup.php should count existing users', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('SELECT COUNT(*) FROM users');
    expect(content).toContain('existing_user_count');
  });

  test('setup.php should skip full schema import for existing databases', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Should have conditional logic: fresh DB imports schema, existing DB does not
    expect(content).toContain('existing_database');
    expect(content).toContain("Fresh database");
  });
});

// ================================================
// 6. Setup - Encryption Key Validation for Existing DB
// ================================================
test.describe('Setup - Encryption Key Validation', () => {
  test('setup.php should validate encryption key against existing data', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('FieldEncryption::decrypt');
    expect(content).toContain('mb_check_encoding');
    expect(content).toContain('Encryption key validation failed');
  });

  test('setup.php Step 2 should show different UI for existing databases', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('Existing database detected');
    expect(content).toContain('Enter the encryption key');
    expect(content).toContain('existing_table_count');
    expect(content).toContain('existing_user_count');
  });

  test('setup.php should not show Generate Key button for existing databases', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('if (!$is_existing_db)');
    expect(content).toContain('generateSetupKey');
  });
});

// ================================================
// 7. Setup - Schema Migration
// ================================================
test.describe('Setup - Schema Migration for Existing Database', () => {
  test('setup.php should use DatabaseMigrator for schema comparison', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('DatabaseMigrator');
    expect(content).toContain('parseSchemaFile');
    expect(content).toContain('getCurrentSchema');
    expect(content).toContain('compareSchemas');
  });

  test('setup.php should create missing tables from schema file', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('create_table');
    expect(content).toContain('Created missing table');
  });

  test('setup.php should run inline migrations for existing databases', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('inline_migrations');
    expect(content).toContain('sip_wss_port');
  });

  test('setup.php should have schema migration UI step', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('Database Schema Migration');
    expect(content).toContain('Scan & Update Schema');
    expect(content).toContain('schema_migration');
  });
});

// ================================================
// 8. Setup - Schema Verification
// ================================================
test.describe('Setup - Schema Verification After Migration', () => {
  test('setup.php should verify schema after migration', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('post_schema');
    expect(content).toContain('remaining_issues');
    expect(content).toContain('Schema verification passed');
  });

  test('setup.php should report migration results', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('migration_results');
    expect(content).toContain('migration_errors');
    expect(content).toContain('Migration Results');
  });

  test('setup.php should skip admin creation for existing databases', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // For existing DB, admin already exists - skip step 3 admin creation
    expect(content).toContain("'admin'] = true"); // Admin already exists
    expect(content).toContain('Skip admin creation for existing databases');
  });
});

// ================================================
// 9. DatabaseMigrator - Capabilities
// ================================================
test.describe('DatabaseMigrator - Required Capabilities', () => {
  test('database_migrator.php should have parseSchemaFile method', async () => {
    const filePath = path.join(__dirname, '..', 'lib', 'database_migrator.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('function parseSchemaFile');
  });

  test('database_migrator.php should have getCurrentSchema method', async () => {
    const filePath = path.join(__dirname, '..', 'lib', 'database_migrator.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('function getCurrentSchema');
  });

  test('database_migrator.php should have compareSchemas method', async () => {
    const filePath = path.join(__dirname, '..', 'lib', 'database_migrator.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('function compareSchemas');
  });

  test('database_migrator.php should handle create_table migrations', async () => {
    const filePath = path.join(__dirname, '..', 'lib', 'database_migrator.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'create_table'");
    expect(content).toContain('function createTable');
  });

  test('database_migrator.php should handle add_column migrations', async () => {
    const filePath = path.join(__dirname, '..', 'lib', 'database_migrator.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'add_column'");
    expect(content).toContain('function addColumn');
  });
});

// ================================================
// 10. Setup Migration - sip_wss_port Inline Migration
// ================================================
test.describe('Setup - sip_wss_port Migration', () => {
  test('setup.php should include sip_wss_port in fresh database migrations', async () => {
    const filePath = path.join(__dirname, '..', 'setup.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("ALTER TABLE users ADD COLUMN sip_wss_port");
    expect(content).toContain("DEFAULT 7443");
  });
});
