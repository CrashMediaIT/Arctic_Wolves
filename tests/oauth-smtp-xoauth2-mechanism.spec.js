/**
 * Tests for Office 365 OAuth SMTP XOAUTH2 mechanism fixes:
 * 1. Auto-correct port/encryption to TLS/587 when OAuth active on Office 365
 * 2. Parse EHLO response for AUTH mechanism checking
 * 3. TLS 1.2+ enforcement for modern mail servers
 * 4. EHLO hostname fallback for CLI contexts
 * 5. Admin UI: basic auth fields completely hidden when OAuth connected (either/or)
 * 6. Auto-configure SMTP settings on OAuth connect
 */
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

function readFile(filePath) {
  return fs.readFileSync(path.join(__dirname, '..', filePath), 'utf8');
}

test.describe('OAuth SMTP - XOAUTH2 mechanism fixes in mailer.php', () => {
  let content;

  test.beforeAll(() => {
    content = readFile('mailer.php');
  });

  test('auto-corrects SSL/465 to TLS/587 when OAuth is active on Office 365 host', () => {
    // The code should detect Office 365 host and auto-correct port/encryption
    expect(content).toContain("office365");
    expect(content).toContain("$enc  = 'tls';");
    expect(content).toContain("$port = '587';");
    // Should check for both ssl encryption and port 465
    expect(content).toContain("$enc === 'ssl'");
    expect(content).toContain("(int)$port === 465");
  });

  test('only auto-corrects for Office 365 hosts when OAuth token is present', () => {
    // The auto-correction should be conditional on both oauthToken and office365 host
    expect(content).toContain("!empty($oauthToken)");
    expect(content).toContain("office365");
  });

  test('parses EHLO response to extract AUTH mechanisms', () => {
    // Should parse the EHLO response for AUTH line
    expect(content).toContain('serverAuthMechanisms');
    // Should extract mechanisms from EHLO AUTH line
    expect(content).toContain("preg_match('/^250[- ]AUTH");
    // Should store as uppercase array for consistent comparison
    expect(content).toContain("array_map('strtoupper'");
  });

  test('checks XOAUTH2 is in server capabilities before AUTH attempt', () => {
    // Must verify XOAUTH2 is advertised before attempting it
    expect(content).toContain("in_array('XOAUTH2', $this->serverAuthMechanisms)");
  });

  test('provides actionable error when XOAUTH2 is not supported by server', () => {
    // Error should mention XOAUTH2 requirement
    expect(content).toContain('XOAUTH2 authentication mechanism');
    // Error should suggest port 587 with TLS
    expect(content).toContain('port 587 with TLS encryption');
    // Error should mention SMTP AUTH needs to be enabled
    expect(content).toContain('SMTP AUTH is');
    expect(content).toContain('enabled for this mailbox');
    // Error should mention the Exchange Online PowerShell command
    expect(content).toContain('Set-CASMailbox');
    expect(content).toContain('SmtpClientAuthenticationDisabled');
  });

  test('uses TLS 1.2+ crypto method instead of generic TLS', () => {
    // Must use TLSv1_2 as minimum
    expect(content).toContain('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT');
    // Should also support TLS 1.3 if available
    expect(content).toContain('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT');
    // Should NOT use the generic STREAM_CRYPTO_METHOD_TLS_CLIENT which allows TLS 1.0
    expect(content).not.toContain('STREAM_CRYPTO_METHOD_TLS_CLIENT');
  });

  test('EHLO uses safe hostname fallback for CLI contexts', () => {
    // Should define $ehloHost with safe fallback
    expect(content).toContain("$ehloHost");
    expect(content).toContain("'localhost'");
    // Should use $ehloHost variable in EHLO commands
    expect(content).toContain('"EHLO " . $ehloHost');
  });

  test('captures EHLO response after STARTTLS for mechanism detection', () => {
    // The EHLO after STARTTLS should capture the response
    expect(content).toContain('sendCommandGetResponse');
    // There should be two EHLO calls that capture the response
    const ehloCaptures = content.match(/sendCommandGetResponse\("EHLO/g);
    expect(ehloCaptures).not.toBeNull();
    expect(ehloCaptures.length).toBe(2);
  });

  test('has sendCommandGetResponse private method', () => {
    expect(content).toMatch(/private\s+function\s+sendCommandGetResponse\s*\(\s*\$cmd\s*\)/);
  });

  test('has serverAuthMechanisms property', () => {
    expect(content).toContain('private $serverAuthMechanisms');
  });

  test('lists advertised mechanisms in the error message', () => {
    // When XOAUTH2 is not available, the error should tell the user what IS available
    expect(content).toContain('only advertises:');
    expect(content).toContain("implode(', ', $this->serverAuthMechanisms)");
  });
});

test.describe('OAuth SMTP - admin UI either/or: basic auth hidden when OAuth connected', () => {
  let content;

  test.beforeAll(() => {
    content = readFile('views/admin_system_tools.php');
  });

  test('SMTP host/port/encryption fields only shown when OAuth is NOT connected', () => {
    // The basic auth section contains a comment 'Basic Authentication'
    const basicAuthStart = content.indexOf('Basic Authentication');
    expect(basicAuthStart).toBeGreaterThan(-1);
    const basicAuthEnd = content.indexOf('<?php endif; ?>', basicAuthStart);
    const basicBlock = content.substring(basicAuthStart, basicAuthEnd);
    
    // SMTP Host editable field should be in the basic auth block
    expect(basicBlock).toContain('name="smtp_host" class="form-input"');
  });

  test('SMTP Username and Password fields only shown when OAuth is NOT connected', () => {
    const basicAuthStart = content.indexOf('Basic Authentication');
    const basicAuthEnd = content.indexOf('<?php endif; ?>', basicAuthStart);
    const basicBlock = content.substring(basicAuthStart, basicAuthEnd);
    
    expect(basicBlock).toContain('name="smtp_user"');
    expect(basicBlock).toContain('name="smtp_pass"');
  });

  test('shows read-only SMTP server info when OAuth is connected', () => {
    expect(content).toContain('Managed automatically by Office 365 OAuth');
    expect(content).toContain('smtp.office365.com');
    expect(content).toContain('Port 587 / TLS (auto-configured)');
  });

  test('sends correct hidden SMTP settings when OAuth is connected', () => {
    // Hidden fields should enforce correct Office 365 settings on form save
    expect(content).toContain('type="hidden" name="smtp_host" value="smtp.office365.com"');
    expect(content).toContain('type="hidden" name="smtp_port" value="587"');
    expect(content).toContain('type="hidden" name="smtp_encryption" value="tls"');
  });

  test('shows authenticated account when OAuth is connected', () => {
    expect(content).toContain('Authenticated Account');
    expect(content).toContain('Emails are sent via Office 365 OAuth (XOAUTH2) using this account');
  });

  test('Send-As Alias is shown only when OAuth is connected', () => {
    const aliasSection = content.indexOf('Send-As Alias');
    expect(aliasSection).toBeGreaterThan(-1);
    const beforeAlias = content.substring(0, aliasSection);
    const lastOauthCheck = beforeAlias.lastIndexOf('$o365SmtpConnected');
    expect(lastOauthCheck).toBeGreaterThan(-1);
    const checkBlock = content.substring(lastOauthCheck, aliasSection);
    expect(checkBlock).not.toContain('endif');
  });

  test('shows reconnect warning when OAuth connected but email is missing', () => {
    expect(content).toContain('if (empty($o365ConnectedEmail))');
    expect(content).toContain('disconnect and reconnect');
  });

  test('shows Exchange Online SMTP AUTH requirement notice', () => {
    expect(content).toContain('SMTP AUTH must be enabled');
    expect(content).toContain('Set-CASMailbox');
    expect(content).toContain('SmtpClientAuthenticationDisabled');
  });

  test('OAuth connected notice mentions auto-configured settings', () => {
    expect(content).toContain('auto-configured');
  });
});

test.describe('OAuth SMTP - oauth_office365_callback.php auto-configures SMTP settings', () => {
  let content;

  test.beforeAll(() => {
    content = readFile('oauth_office365_callback.php');
  });

  test('auto-sets smtp_host to smtp.office365.com on OAuth connect', () => {
    expect(content).toContain("'smtp_host',       'smtp.office365.com'");
  });

  test('auto-sets smtp_port to 587 on OAuth connect', () => {
    expect(content).toContain("'smtp_port',       '587'");
  });

  test('auto-sets smtp_encryption to tls on OAuth connect', () => {
    expect(content).toContain("'smtp_encryption', 'tls'");
  });
});
