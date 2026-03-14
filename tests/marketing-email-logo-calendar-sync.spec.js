/**
 * Tests for:
 * 1. Marketing email logo - resolves RustFS-uploaded logos through media proxy
 * 2. Calendar sync - uses correct column name (coach_id) in session_coaches table
 */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

function readFile(filePath) {
  return fs.readFileSync(path.join(__dirname, '..', filePath), 'utf8');
}

// =====================================================
// Issue 1: Marketing Email Logo - RustFS URL Resolution
// =====================================================

test.describe('Marketing email logo - RustFS URL resolution', () => {
  let mailerContent;

  test.beforeAll(() => {
    mailerContent = readFile('mailer.php');
  });

  test('mailer.php includes image_helper.php for resolveRustfsUrl', () => {
    expect(mailerContent).toContain("require_once __DIR__ . '/lib/image_helper.php'");
  });

  test('getThemeSettings resolves logo_url through resolveRustfsUrl', () => {
    expect(mailerContent).toContain('resolveRustfsUrl($pdo, $settings[\'logo_url\'])');
  });

  test('getThemeSettings makes relative URLs absolute for email clients', () => {
    // Email clients need absolute URLs - relative proxy URLs won't work
    expect(mailerContent).toContain("strpos($resolved, 'http') !== 0");
    expect(mailerContent).toContain("getenv('APP_URL')");
  });

  test('getThemeSettings guards resolveRustfsUrl with function_exists', () => {
    // Defensive check in case image_helper is not loaded
    expect(mailerContent).toContain("function_exists('resolveRustfsUrl')");
  });

  test('resolved logo URL is stored back into settings array', () => {
    expect(mailerContent).toContain("$settings['logo_url'] = $resolved");
  });

  test('marketing email buildMarketingEmailBody uses getThemeSettings for logo', () => {
    const marketingContent = readFile('process_send_marketing_email.php');
    expect(marketingContent).toContain('getThemeSettings()');
    expect(marketingContent).toContain("$theme['logo_url']");
  });
});

// =====================================================
// Issue 2: Calendar Sync - session_coaches column fix
// =====================================================

test.describe('Calendar sync - session_coaches column fix', () => {
  let settingsContent;

  test.beforeAll(() => {
    settingsContent = readFile('process_settings.php');
  });

  test('calendar push query uses coach_id (not user_id) for session_coaches', () => {
    // session_coaches table has coach_id column, not user_id
    expect(settingsContent).toContain('SELECT session_id FROM session_coaches WHERE coach_id = ?');
    expect(settingsContent).not.toContain('SELECT session_id FROM session_coaches WHERE user_id = ?');
  });

  test('session_coaches schema defines coach_id column', () => {
    const schema = readFile('database_schema.sql');
    // Verify the session_coaches table uses coach_id (backticks used in schema)
    const tableStart = schema.indexOf('`session_coaches`');
    expect(tableStart).toBeGreaterThan(-1);
    const tableEnd = schema.indexOf(';', tableStart);
    const tableDef = schema.substring(tableStart, tableEnd);
    expect(tableDef).toContain('coach_id');
    // Should NOT have user_id in the session_coaches table
    expect(tableDef).not.toContain('user_id');
  });

  test('user_oauth_tokens queries correctly use user_id', () => {
    // user_oauth_tokens table DOES have user_id - these should remain unchanged
    expect(settingsContent).toContain("FROM user_oauth_tokens WHERE user_id = ?");
  });

  test('calendar sync handles sync_office365_calendar action', () => {
    expect(settingsContent).toContain("case 'sync_office365_calendar':");
  });

  test('sessions main query uses coach_id for sessions table', () => {
    expect(settingsContent).toContain('s.coach_id = ?');
  });
});
