/**
 * Tests for OAuth SMTP fixes:
 * 1. mailer.php uses office365_smtp_connected_email for XOAUTH2 user
 * 2. admin_system_tools.php hides basic auth when OAuth connected, shows alias
 * 3. OAuth scopes include openid for id_token extraction
 * 4. Token exchange includes scope parameter
 */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

function readFile(filePath) {
  return fs.readFileSync(path.join(__dirname, '..', filePath), 'utf8');
}

test.describe('OAuth SMTP - mailer.php fixes', () => {
  let content;

  test.beforeAll(() => {
    content = readFile('mailer.php');
  });

  test('XOAUTH2 uses office365_smtp_connected_email as the mailbox identity', () => {
    // The XOAUTH2 user should come from the OAuth connected email, not smtp_user
    expect(content).toContain("office365_smtp_connected_email");
    expect(content).toContain("oauthUser");
  });

  test('XOAUTH2 auth does not require smtp_user to be non-empty', () => {
    // The old code required !empty($user) alongside !empty($oauthToken)
    // The new code only requires !empty($oauthToken) and derives user from connected email
    expect(content).toMatch(/if\s*\(\s*!empty\(\$oauthToken\)\s*\)/);
    // Should NOT have the old condition: if (!empty($oauthToken) && !empty($user))
    expect(content).not.toMatch(/if\s*\(\s*!empty\(\$oauthToken\)\s*&&\s*!empty\(\$user\)\s*\)/);
  });

  test('XOAUTH2 falls back to smtp_user when connected email is empty', () => {
    expect(content).toContain('$oauthUser = $user');
  });

  test('XOAUTH2 throws clear error when no mailbox email is available', () => {
    expect(content).toContain('OAuth is configured but no mailbox email is available');
  });

  test('envelope sender uses OAuth connected email as default', () => {
    expect(content).toContain('$defaultSender');
    // Check the ternary that picks oauthConnectedEmail when OAuth is active, otherwise smtp_user
    expect(content).toContain("$defaultSender = (!empty($oauthToken) && !empty($oauthConnectedEmail)) ? $oauthConnectedEmail : $user;");
  });

  test('Sender header uses defaultSender instead of raw smtp_user', () => {
    expect(content).toContain('$headers .= "Sender: $defaultSender\\r\\n"');
  });

  test('refresh token scope includes openid email profile', () => {
    expect(content).toContain("'scope'         => 'https://outlook.office.com/SMTP.Send offline_access openid email profile'");
  });
});

test.describe('OAuth SMTP - admin_system_tools.php UI changes', () => {
  let content;

  test.beforeAll(() => {
    content = readFile('views/admin_system_tools.php');
  });

  test('SMTP username field is hidden when OAuth is connected', () => {
    // The username field should be inside a conditional block
    expect(content).toContain('<?php if (!$o365SmtpConnected): ?>');
    // The block should contain the SMTP Username field
    const conditionalStart = content.indexOf('<?php if (!$o365SmtpConnected): ?>');
    const conditionalEnd = content.indexOf('<?php else: ?>', conditionalStart);
    const conditionalBlock = content.substring(conditionalStart, conditionalEnd);
    expect(conditionalBlock).toContain('SMTP Username');
    expect(conditionalBlock).toContain('SMTP Password');
  });

  test('shows authenticated account when OAuth is connected', () => {
    expect(content).toContain('Authenticated Account');
    expect(content).toContain('Emails are sent via Office 365 OAuth (XOAUTH2) using this account');
  });

  test('Send-As Alias is shown only when OAuth is connected', () => {
    // The alias field should be inside a conditional for OAuth connected
    const aliasSection = content.indexOf('Send-As Alias');
    expect(aliasSection).toBeGreaterThan(-1);
    // Find the nearest conditional before the alias
    const beforeAlias = content.substring(0, aliasSection);
    const lastOauthCheck = beforeAlias.lastIndexOf('$o365SmtpConnected');
    expect(lastOauthCheck).toBeGreaterThan(-1);
    // The alias should be within an OAuth-connected conditional
    const checkBlock = content.substring(lastOauthCheck, aliasSection);
    expect(checkBlock).not.toContain('endif');
  });

  test('basic auth fields are not shown when OAuth is connected', () => {
    // Verify the conditional structure: username/password only when NOT connected
    const smtpUserIdx = content.indexOf('name="smtp_user"');
    const smtpPassIdx = content.indexOf('name="smtp_pass"');
    expect(smtpUserIdx).toBeGreaterThan(-1);
    expect(smtpPassIdx).toBeGreaterThan(-1);
    
    // Both should be after the "if not connected" check
    const notConnectedIdx = content.indexOf('if (!$o365SmtpConnected)');
    const elseIdx = content.indexOf('<?php else: ?>', notConnectedIdx);
    expect(smtpUserIdx).toBeGreaterThan(notConnectedIdx);
    expect(smtpUserIdx).toBeLessThan(elseIdx);
    expect(smtpPassIdx).toBeGreaterThan(notConnectedIdx);
    expect(smtpPassIdx).toBeLessThan(elseIdx);
  });

  test('shows reconnect warning when OAuth connected but email is missing', () => {
    expect(content).toContain('if (empty($o365ConnectedEmail))');
    expect(content).toContain('disconnect and reconnect');
  });
});

test.describe('OAuth SMTP - process_settings.php scope fixes', () => {
  let content;

  test.beforeAll(() => {
    content = readFile('process_settings.php');
  });

  test('SMTP OAuth authorization scope includes openid email profile', () => {
    expect(content).toContain("'scope'         => 'https://outlook.office.com/SMTP.Send offline_access openid email profile'");
  });

  test('Calendar OAuth authorization scope includes openid email profile', () => {
    expect(content).toContain("'scope'         => 'https://graph.microsoft.com/Calendars.ReadWrite offline_access openid email profile'");
  });
});

test.describe('OAuth SMTP - oauth_office365_callback.php token exchange', () => {
  let content;

  test.beforeAll(() => {
    content = readFile('oauth_office365_callback.php');
  });

  test('token exchange includes scope parameter', () => {
    // The token exchange should include scope in the POST data
    expect(content).toContain("'scope'         => $scope,");
  });

  test('SMTP scope for token exchange includes SMTP.Send openid email profile', () => {
    expect(content).toContain("'https://outlook.office.com/SMTP.Send offline_access openid email profile'");
  });

  test('Calendar scope for token exchange includes Calendars.ReadWrite openid email profile', () => {
    expect(content).toContain("'https://graph.microsoft.com/Calendars.ReadWrite offline_access openid email profile'");
  });

  test('scope is determined based on OAuth type (smtp vs calendar)', () => {
    // Verify the scope selection is based on the OAuth type
    expect(content).toContain("$scope = $type === 'smtp'");
  });

  test('connection fails if mailbox email cannot be extracted', () => {
    // Empty connectedEmail must abort before storing tokens
    expect(content).toContain("if (empty($connectedEmail))");
    // Should redirect with error, not silently continue
    expect(content).toContain("Could not determine the mailbox email address from Microsoft");
  });

  test('SMTP tokens are never stored without the connected email', () => {
    // The connected_email upsert must NOT be inside an if(!empty()) guard
    // It should be unconditional since we already validated above
    const smtpBlock = content.substring(
      content.indexOf("if ($type === 'smtp')"),
      content.indexOf("// Calendar:")
    );
    // The email storage should be unconditional (no if-guard around it)
    expect(smtpBlock).toContain("$upsert->execute(['office365_smtp_connected_email', $connectedEmail, $connectedEmail]);");
    expect(smtpBlock).not.toMatch(/if\s*\(\s*!empty\(\$connectedEmail\)\s*\)\s*\{\s*\n\s*\$upsert->execute\(\['office365_smtp_connected_email'/);
  });
});
