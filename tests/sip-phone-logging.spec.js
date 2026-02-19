/**
 * Tests for Company Directory View
 *
 * Verifies:
 * 1. SIP configuration and dialer have been removed
 * 2. Page is now a Company Directory with search functionality
 * 3. Directory shows all verified staff (not just those with SIP profiles)
 * 4. Admin directory entry management is preserved
 * 5. Company Directory button exists in POS terminal
 * 6. Dashboard navigation uses "Company Directory" instead of "SIP Phone"
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const sipSettingsPath = path.join(__dirname, '..', 'views', 'sip_settings.php');
const dashboardPath = path.join(__dirname, '..', 'dashboard.php');
const posTerminalPath = path.join(__dirname, '..', 'views', 'pos_terminal.php');
const dbConfigPath = path.join(__dirname, '..', 'db_config.php');

// ================================================
// 1. SIP Configuration & Dialer Removed
// ================================================
test.describe('Company Directory - SIP Config Removed', () => {
  test('should not have SIP account configuration form', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).not.toContain('My SIP Account');
    expect(content).not.toContain('sip_username');
    expect(content).not.toContain('sip_domain');
    expect(content).not.toContain('sip_wss_port');
    expect(content).not.toContain('saveSipSettings');
  });

  test('should not have dialer', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).not.toContain('Dialer');
    expect(content).not.toContain('dialer-input');
    expect(content).not.toContain('dialerPress');
    expect(content).not.toContain('dialerCall');
  });

  test('should not have SIP URI calling', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).not.toContain('callExtension');
    expect(content).not.toContain('sip:');
    expect(content).not.toContain('FusionPBX Web Dialer');
  });
});

// ================================================
// 2. Company Directory with Search
// ================================================
test.describe('Company Directory - Search Functionality', () => {
  test('should have Company Directory title', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('Company Directory');
    expect(content).toContain('fa-address-book');
  });

  test('should have a search input', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('directory-search');
    expect(content).toContain('Search by name, title, extension, or email');
  });

  test('should have filterDirectory JavaScript function', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('function filterDirectory()');
  });

  test('should have no-results message element', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('no-results-message');
    expect(content).toContain('No matching entries found');
  });

  test('directory rows should have searchable class', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('directory-row');
  });
});

// ================================================
// 3. Directory Columns: Name, Title, DID, Extension, Email
// ================================================
test.describe('Company Directory - Table Columns', () => {
  test('should have Name, Title, DID, Extension, Email columns', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('<th>Name</th>');
    expect(content).toContain('<th>Title</th>');
    expect(content).toContain('<th>DID</th>');
    expect(content).toContain('<th>Extension</th>');
    expect(content).toContain('<th>Email</th>');
  });

  test('should display staff DID from sip_did field', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("staff['sip_did']");
  });

  test('should display staff email with mailto link', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("mailto:");
    expect(content).toContain("staff['email']");
  });

  test('should display custom entry DID and email', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("entry['did']");
    expect(content).toContain("entry['email']");
  });

  test('SQL query should fetch sip_did for staff', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('u.sip_did');
  });
});

// ================================================
// 4. Directory Shows All Verified Staff
// ================================================
test.describe('Company Directory - All Verified Staff', () => {
  test('should query all verified users not just SIP users', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    // Should query WHERE is_verified = 1 without SIP-specific filters
    expect(content).toContain('is_verified = 1');
    expect(content).not.toContain('sip_username IS NOT NULL');
    expect(content).not.toContain('sip_domain IS NOT NULL');
  });
});

// ================================================
// 5. Admin Directory Entry Management Preserved
// ================================================
test.describe('Company Directory - Admin Management', () => {
  test('should have admin form to add directory entries', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('add-directory-entry-form');
    expect(content).toContain('entry_name');
    expect(content).toContain('entry_extension');
    expect(content).toContain('entry_type');
    expect(content).toContain('addDirectoryEntry');
  });

  test('admin form should have DID and Email fields', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('entry_did');
    expect(content).toContain('entry_email');
  });

  test('should show custom entries in directory table', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('custom_entries');
    expect(content).toContain('deleteDirectoryEntry');
  });

  test('should have showNotification function', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain('function showNotification(message, type');
  });
});

// ================================================
// 5. POS Terminal - Company Directory Button
// ================================================
test.describe('POS Terminal - Company Directory Button', () => {
  test('POS terminal should have Company Directory link', async () => {
    const content = fs.readFileSync(posTerminalPath, 'utf-8');
    expect(content).toContain('Company Directory');
    expect(content).toContain('page=sip_settings');
    expect(content).toContain('fa-address-book');
  });
});

// ================================================
// 6. Dashboard Navigation Updated
// ================================================
test.describe('Dashboard - Company Directory Navigation', () => {
  test('dashboard should show Company Directory instead of SIP Phone', async () => {
    const content = fs.readFileSync(dashboardPath, 'utf-8');
    expect(content).toContain('Company Directory');
    expect(content).not.toContain('SIP Phone');
  });

  test('dashboard POS section should have Company Directory link', async () => {
    const content = fs.readFileSync(dashboardPath, 'utf-8');
    // There should be two sip_settings links: one in POS section and one in sidebar footer
    const matches = content.match(/page=sip_settings/g) || [];
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });
});

// ================================================
// 7. Site-wide Phone Number Formatting (xxx.xxx.xxxx)
// ================================================
test.describe('Phone Formatting - formatPhone() in db_config.php', () => {
  test('db_config.php should define formatPhone function', async () => {
    const content = fs.readFileSync(dbConfigPath, 'utf-8');
    expect(content).toContain('function formatPhone(');
  });

  test('formatPhone should format 10-digit numbers as xxx.xxx.xxxx', async () => {
    const content = fs.readFileSync(dbConfigPath, 'utf-8');
    expect(content).toContain("substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 4)");
  });

  test('formatPhone should format 11-digit numbers starting with 1', async () => {
    const content = fs.readFileSync(dbConfigPath, 'utf-8');
    expect(content).toContain("strlen($digits) === 11");
    expect(content).toContain("$digits[0] === '1'");
  });

  test('formatPhone should strip non-digit characters before formatting', async () => {
    const content = fs.readFileSync(dbConfigPath, 'utf-8');
    expect(content).toContain("preg_replace('/[^0-9]/', '', $phone)");
  });
});

test.describe('Phone Formatting - Applied in Views', () => {
  test('sip_settings.php should use formatPhone for DID and extension display', async () => {
    const content = fs.readFileSync(sipSettingsPath, 'utf-8');
    expect(content).toContain("formatPhone($staff['sip_did'])");
    expect(content).toContain("formatPhone($staff['sip_extension'])");
    expect(content).toContain("formatPhone($entry['did'])");
    expect(content).toContain("formatPhone($entry['extension'])");
  });

  test('phone_directory.php should use formatPhone for phone numbers', async () => {
    const dirPath = path.join(__dirname, '..', 'views', 'phone_directory.php');
    const content = fs.readFileSync(dirPath, 'utf-8');
    expect(content).toContain("formatPhone($du['sip_extension'])");
    expect(content).toContain("formatPhone($du['sip_did'])");
    expect(content).toContain("formatPhone($du['phone']");
  });

  test('admin_users.php should use formatPhone for phone display', async () => {
    const usersPath = path.join(__dirname, '..', 'views', 'admin_users.php');
    const content = fs.readFileSync(usersPath, 'utf-8');
    expect(content).toContain("formatPhone($user['phone'])");
  });

  test('admin_business_cards.php should use formatPhone for phone display', async () => {
    const cardsPath = path.join(__dirname, '..', 'views', 'admin_business_cards.php');
    const content = fs.readFileSync(cardsPath, 'utf-8');
    expect(content).toContain("formatPhone($selected_user['phone']");
  });

  test('profile.php should use formatPhone for phone display', async () => {
    const profilePath = path.join(__dirname, '..', 'views', 'profile.php');
    const content = fs.readFileSync(profilePath, 'utf-8');
    expect(content).toContain("formatPhone($userData['phone']");
  });

  test('admin_business_cards.php should have JS formatPhone function', async () => {
    const cardsPath = path.join(__dirname, '..', 'views', 'admin_business_cards.php');
    const content = fs.readFileSync(cardsPath, 'utf-8');
    expect(content).toContain('function formatPhone(phone)');
    expect(content).toContain("digits.length === 10");
    expect(content).toContain("digits.slice(");
  });
});
