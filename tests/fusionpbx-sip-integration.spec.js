/**
 * Tests for FusionPBX SIP Integration Fixes
 * 
 * Verifies:
 * 1. FusionPBX API URL uses correct /app/api path (fixes 404)
 * 2. Database schema includes sip_password column for encrypted password storage
 * 3. SIP settings page fetches and decrypts sip_password from database
 * 4. SIP settings save handler accepts and encrypts sip_password
 * 5. Admin users can edit extension and DID in SIP phone settings
 * 6. Non-admin users have readonly extension and DID fields
 * 7. SIP settings form sends password, extension, and DID to server
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('FusionPBX - API URL Fix', () => {
  test('fusionpbx.php should use /app/api path for API requests', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'fusionpbx.php');
    const content = fs.readFileSync(libPath, 'utf-8');
    
    // Should use /app/api (correct FusionPBX API path)
    expect(content).toContain("'/app/api'");
    // Should NOT use bare /api path without /app prefix (causes 404)
    expect(content).not.toContain("'/api'");
  });

  test('fusionpbx.php should have testFusionPBXConnection function', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'fusionpbx.php');
    const content = fs.readFileSync(libPath, 'utf-8');
    
    expect(content).toContain('function testFusionPBXConnection');
    expect(content).toContain('function fusionpbxApiRequest');
    expect(content).toContain('function createFusionPBXExtension');
  });
});

test.describe('FusionPBX - Database Schema', () => {
  test('schema should include sip_password column in users table', async () => {
    const schemaPath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(schemaPath, 'utf-8');
    
    expect(content).toContain('sip_password');
    expect(content).toContain('VARCHAR(512)');
    expect(content).toContain('Encrypted SIP account password');
  });

  test('schema should have all SIP columns in users table', async () => {
    const schemaPath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(schemaPath, 'utf-8');
    
    expect(content).toContain('sip_username');
    expect(content).toContain('sip_domain');
    expect(content).toContain('sip_extension');
    expect(content).toContain('sip_did');
    expect(content).toContain('sip_password');
  });
});

test.describe('FusionPBX - SIP Settings View', () => {
  test('sip_settings.php should fetch sip_password from database', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Should fetch sip_password in the SQL query
    expect(content).toContain('sip_password FROM users');
  });

  test('sip_settings.php should decrypt stored SIP password via get_sip_password endpoint', async () => {
    const processPath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(processPath, 'utf-8');
    
    // Should use FieldEncryption to decrypt in the get_sip_password handler
    expect(content).toContain('FieldEncryption::decrypt');
    expect(content).toContain("get_sip_password");
  });

  test('sip_settings.php should check for saved password to show appropriate placeholder', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Should track whether a password is saved
    expect(content).toContain('has_saved_password');
    // Should show different placeholders based on whether password exists
    expect(content).toContain('Password saved');
  });

  test('sip_settings.php should indicate password is encrypted on server', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Should show encrypted/saved hint, not browser-only
    expect(content).toContain('encrypted and saved securely on the server');
    expect(content).not.toContain('stored in browser only');
  });

  test('sip_settings.php should allow admins to edit extension field', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Extension field should be conditionally readonly based on admin status
    expect(content).toContain("!$isAdmin ? 'readonly' : ''");
  });

  test('sip_settings.php should allow admins to edit DID field', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Both extension and DID should use the !$isAdmin conditional readonly pattern
    const conditionalMatches = content.match(/!\$isAdmin \? 'readonly' : ''/g) || [];
    expect(conditionalMatches.length).toBeGreaterThanOrEqual(2);
  });

  test('sip_settings.php JS should send password, extension, and DID in save request', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // saveSipSettings function should include password
    expect(content).toContain("sip_password=");
    expect(content).toContain("sip_extension=");
    expect(content).toContain("sip_did=");
  });
});

test.describe('FusionPBX - Process Profile Update', () => {
  test('process_profile_update.php should handle sip_password in update_own_sip action', async () => {
    const processPath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(processPath, 'utf-8');
    
    // Should accept sip_password from POST
    expect(content).toContain("sip_password");
    // Should encrypt before saving
    expect(content).toContain('FieldEncryption::encrypt');
  });

  test('process_profile_update.php should have get_sip_password action for auto-connect', async () => {
    const processPath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(processPath, 'utf-8');
    
    // Should have get_sip_password action
    expect(content).toContain("get_sip_password");
    // Should decrypt password for retrieval
    expect(content).toContain('FieldEncryption::decrypt');
  });

  test('process_profile_update.php should allow admins to update extension and DID', async () => {
    const processPath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(processPath, 'utf-8');
    
    // Should have admin check for extension/DID updates
    expect(content).toContain("role === 'admin'");
    // Should update sip_extension and sip_did for admins
    expect(content).toContain('sip_extension = ?');
    expect(content).toContain('sip_did = ?');
  });

  test('process_profile_update.php should only update password when user provides one', async () => {
    const processPath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(processPath, 'utf-8');
    
    // Should check if password was provided before updating
    expect(content).toContain('update_password');
    expect(content).toContain('sip_password = ?');
  });

  test('process_profile_update.php should include encryption library', async () => {
    const processPath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(processPath, 'utf-8');
    
    expect(content).toContain("require_once __DIR__ . '/lib/encryption.php'");
  });
});

test.describe('FusionPBX - Encryption Library', () => {
  test('encryption.php should have encrypt and decrypt methods', async () => {
    const encPath = path.join(__dirname, '..', 'lib', 'encryption.php');
    const content = fs.readFileSync(encPath, 'utf-8');
    
    expect(content).toContain('public static function encrypt');
    expect(content).toContain('public static function decrypt');
    expect(content).toContain('aes-256-cbc');
  });
});
