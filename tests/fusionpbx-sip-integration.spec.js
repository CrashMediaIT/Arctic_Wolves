/**
 * Tests for FusionPBX Removal & SIP Phone Directory Changes
 * 
 * Verifies:
 * 1. FusionPBX API library has been removed
 * 2. FusionPBX tab removed from System Tools
 * 3. FusionPBX settings actions removed from process_settings.php
 * 4. Phone directory removed from dashboard navigation
 * 5. Onboarding sends email to IT instead of provisioning via FusionPBX API
 * 6. Extension request email template exists in mailer.php
 * 7. SIP settings page has no FusionPBX references
 * 8. SIP directory shows users with any SIP profile info (not just extension)
 * 9. Database schema includes phone_directory_entries table
 * 10. Admins can add/delete custom directory entries (rooms, shared lines)
 * 11. SIP password encryption still works
 * 12. Admin SIP password in All Users page still works
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

// ================================================
// 1. FusionPBX API Library Removed
// ================================================
test.describe('FusionPBX API Removal', () => {
  test('lib/fusionpbx.php should no longer exist', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'fusionpbx.php');
    expect(fs.existsSync(libPath)).toBe(false);
  });

  test('process_onboarding.php should not require fusionpbx.php', async () => {
    const filePath = path.join(__dirname, '..', 'process_onboarding.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toContain('fusionpbx.php');
  });

  test('process_settings.php should not reference test_fusionpbx action', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toContain('test_fusionpbx');
    expect(content).not.toContain('update_fusionpbx');
  });
});

// ================================================
// 2. FusionPBX Tab Removed from System Tools
// ================================================
test.describe('System Tools - FusionPBX Tab Removed', () => {
  test('admin_system_tools.php should not have FusionPBX tab link', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toContain('tab=fusionpbx');
  });

  test('admin_system_tools.php should not have FusionPBX form', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toContain('fusionpbx-form');
    expect(content).not.toContain('update_fusionpbx');
  });

  test('admin_system_tools.php should not have testFusionPBXConnection JS', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toContain('function testFusionPBXConnection');
  });
});

// ================================================
// 3. Phone Directory Navigation Removed
// ================================================
test.describe('Phone Directory Navigation Removed', () => {
  test('dashboard.php should not have phone_directory nav links', async () => {
    const filePath = path.join(__dirname, '..', 'dashboard.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    expect(content).not.toContain('page=phone_directory');
  });
});

// ================================================
// 4. Onboarding Email to IT
// ================================================
test.describe('Onboarding - Extension Request Email', () => {
  test('process_onboarding.php should send email to IT for extension requests', async () => {
    const filePath = path.join(__dirname, '..', 'process_onboarding.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("it@arcticwolves.ca");
    expect(content).toContain("extension_request");
    expect(content).toContain('sendEmail');
  });

  test('process_onboarding.php should not use FusionPBX provisioning', async () => {
    const filePath = path.join(__dirname, '..', 'process_onboarding.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).not.toContain('provisionFusionPBXExtension');
    expect(content).not.toContain('getFusionPBXSettings');
    expect(content).not.toContain('isFusionPBXConfigured');
  });

  test('hr_onboarding.php should describe extension request as email to IT', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'hr_onboarding.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('Request a phone extension');
    expect(content).toContain('email will be sent to IT');
  });

  test('mailer.php should have extension_request email template', async () => {
    const filePath = path.join(__dirname, '..', 'mailer.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'extension_request'");
    expect(content).toContain('Phone Extension Request');
    expect(content).toContain('staff_name');
  });
});

// ================================================
// 5. SIP Settings - FusionPBX References Removed
// ================================================
test.describe('SIP Settings - FusionPBX References Removed', () => {
  test('sip_settings.php should not reference FusionPBX in descriptions', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).not.toContain('via FusionPBX');
    expect(content).not.toContain('FusionPBX server domain');
    expect(content).not.toContain('fusionpbx_domain');
  });
});

// ================================================
// 6. SIP Directory Shows Users with Any SIP Info
// ================================================
test.describe('SIP Directory - Enhanced User Display', () => {
  test('sip_settings.php should query users with any SIP profile info', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Should check for sip_username OR sip_domain in addition to sip_extension
    expect(content).toContain('sip_username IS NOT NULL');
    expect(content).toContain('sip_domain IS NOT NULL');
  });
});

// ================================================
// 7. Phone Directory Entries Table
// ================================================
test.describe('Phone Directory Entries - Database Schema', () => {
  test('database_schema.sql should have phone_directory_entries table', async () => {
    const filePath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('phone_directory_entries');
    expect(content).toContain('display_name');
    expect(content).toContain('entry_type');
    expect(content).toContain("'room'");
    expect(content).toContain("'shared'");
    expect(content).toContain("'external'");
  });
});

// ================================================
// 8. Admin Custom Directory Entry Management
// ================================================
test.describe('Admin - Custom Directory Entries', () => {
  test('sip_settings.php should have admin form to add directory entries', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('add-directory-entry-form');
    expect(content).toContain('entry_name');
    expect(content).toContain('entry_extension');
    expect(content).toContain('entry_type');
    expect(content).toContain('addDirectoryEntry');
  });

  test('sip_settings.php should show custom entries in directory table', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('custom_entries');
    expect(content).toContain('deleteDirectoryEntry');
  });

  test('process_profile_update.php should handle add_directory_entry action', async () => {
    const filePath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'add_directory_entry'");
    expect(content).toContain('phone_directory_entries');
    expect(content).toContain('display_name');
  });

  test('process_profile_update.php should handle delete_directory_entry action', async () => {
    const filePath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'delete_directory_entry'");
    expect(content).toContain('DELETE FROM phone_directory_entries');
  });

  test('directory entry handlers should require admin role', async () => {
    const filePath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Both add and delete should check for admin role
    const adminChecks = content.match(/role !== 'admin'/g) || [];
    expect(adminChecks.length).toBeGreaterThanOrEqual(2);
  });
});

// ================================================
// 9. SIP Password Encryption (Unchanged)
// ================================================
test.describe('SIP Password Encryption - Still Working', () => {
  test('database schema should include sip_password column', async () => {
    const filePath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('sip_password');
    expect(content).toContain('VARCHAR(512)');
  });

  test('sip_settings.php should fetch sip_password from database', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('sip_password');
    expect(content).toContain('FROM users WHERE id');
  });

  test('sip_settings.php should check for saved password', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'sip_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('has_saved_password');
    expect(content).toContain('Password saved');
  });

  test('process_profile_update.php should encrypt SIP password', async () => {
    const filePath = path.join(__dirname, '..', 'process_profile_update.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('FieldEncryption::encrypt');
    expect(content).toContain('FieldEncryption::decrypt');
  });

  test('encryption.php should have encrypt and decrypt methods', async () => {
    const filePath = path.join(__dirname, '..', 'lib', 'encryption.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('public static function encrypt');
    expect(content).toContain('public static function decrypt');
    expect(content).toContain('aes-256-cbc');
  });
});

// ================================================
// 10. Admin SIP Password in All Users Page (Unchanged)
// ================================================
test.describe('Admin SIP Password in All Users Page', () => {
  test('admin_users.php should have SIP password field in edit form', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'admin_users.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('id="edit-sip-password"');
    expect(content).toContain('name="sip_password"');
  });

  test('process_admin_action.php should encrypt SIP password before saving', async () => {
    const filePath = path.join(__dirname, '..', 'process_admin_action.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('FieldEncryption::encrypt($sip_password)');
  });
});
