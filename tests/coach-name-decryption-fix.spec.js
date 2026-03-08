import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { join } from 'path';

/**
 * Coach Name Decryption Fix Tests
 * Validates that coach_first / coach_last field aliases are properly
 * decrypted in development program views and the central decryptUserRow helper.
 */

const ROOT = join(__dirname, '..');

function readFile(relativePath) {
  return readFileSync(join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Central decryptUserRow includes coach_first/coach_last
// =====================================================

test.describe('decryptUserRow PII field aliases', () => {

  test('decryptUserRow includes coach_first alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'coach_first'");
  });

  test('decryptUserRow includes coach_last alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'coach_last'");
  });

  test('decryptUserRow includes sender_first alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'sender_first'");
  });

  test('decryptUserRow includes sender_last alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'sender_last'");
  });
});

// =====================================================
// 2. dev_drill_detail.php decrypts coach names
// =====================================================

test.describe('dev_drill_detail.php decrypts coach names', () => {

  test('calls decryptUserRows after fetching drill', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('decryptUserRows');
  });

  test('fetches coach_first and coach_last from users table', () => {
    const content = readFile('views/dev_drill_detail.php');
    expect(content).toContain('u.first_name as coach_first');
    expect(content).toContain('u.last_name as coach_last');
  });
});

// =====================================================
// 3. personal_development_my_program.php decrypts coach names
// =====================================================

test.describe('personal_development_my_program.php decrypts coach names', () => {

  test('decrypts drills data containing coach_first/coach_last', () => {
    const content = readFile('views/personal_development_my_program.php');
    // After fetching drills (which contain coach_first/coach_last), decryption should happen
    const drillsFetch = content.indexOf("enrollment['drills']");
    const afterDrills = content.indexOf('decryptUserRows', drillsFetch);
    expect(afterDrills).toBeGreaterThan(drillsFetch);
  });

  test('decrypts appointments data containing coach_first/coach_last', () => {
    const content = readFile('views/personal_development_my_program.php');
    const apptsFetch = content.indexOf("enrollment['appointments']");
    const afterAppts = content.indexOf('decryptUserRows', apptsFetch);
    expect(afterAppts).toBeGreaterThan(apptsFetch);
  });

  test('decrypts messages data containing sender_first/sender_last', () => {
    const content = readFile('views/personal_development_my_program.php');
    const msgsFetch = content.indexOf("enrollment['messages']");
    const afterMsgs = content.indexOf('decryptUserRows', msgsFetch);
    expect(afterMsgs).toBeGreaterThan(msgsFetch);
  });
});

// =====================================================
// 4. development_programs.php decrypts coach and sender names
// =====================================================

test.describe('development_programs.php decrypts coach and sender names', () => {

  test('decrypts selected_drills containing coach_first/coach_last', () => {
    const content = readFile('views/development_programs.php');
    const drillsFetch = content.indexOf('$selected_drills = $drills_stmt');
    const afterDrills = content.indexOf('decryptUserRows($selected_drills)', drillsFetch);
    expect(afterDrills).toBeGreaterThan(drillsFetch);
  });

  test('decrypts selected_messages containing sender_first/sender_last', () => {
    const content = readFile('views/development_programs.php');
    const msgsFetch = content.indexOf('$selected_messages = $msgs_stmt');
    const afterMsgs = content.indexOf('decryptUserRows($selected_messages)', msgsFetch);
    expect(afterMsgs).toBeGreaterThan(msgsFetch);
  });

  test('decrypts selected_appointments containing coach_first/coach_last', () => {
    const content = readFile('views/development_programs.php');
    const apptsFetch = content.indexOf('$selected_appointments = $appts_stmt');
    const afterAppts = content.indexOf('decryptUserRows($selected_appointments)', apptsFetch);
    expect(afterAppts).toBeGreaterThan(apptsFetch);
  });
});
