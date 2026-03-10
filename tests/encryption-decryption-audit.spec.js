import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { join } from 'path';

/**
 * Comprehensive Encryption/Decryption Audit Tests
 * Validates:
 * 1. decryptUserRow includes 'message' field for conversation content decryption
 * 2. Development program messages are encrypted at rest
 * 3. process_reports.php decrypts PII in payment queries
 * 4. process_time_tracking.php decrypts PII in schedule and shift queries
 * 5. RLS goals table maps to athlete_id (not user_id)
 * 6. RLS TABLE_OWNER_MAP includes critical user-data tables
 */

const ROOT = join(__dirname, '..');

function readFile(relativePath) {
  return readFileSync(join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. decryptUserRow includes 'message' field
// =====================================================

test.describe('decryptUserRow message field decryption', () => {

  test('decryptUserRow piiFields includes message field', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'message'");
  });

  test('decryptUserRow still includes sender_first alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'sender_first'");
  });

  test('decryptUserRow still includes sender_last alias', () => {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("'sender_last'");
  });
});

// =====================================================
// 2. Development program messages encrypted at rest
// =====================================================

test.describe('Development program message encryption', () => {

  test('handleSendMessage encrypts message body before insert', () => {
    const content = readFile('process_development_programs.php');
    const fnStart = content.indexOf('function handleSendMessage');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('FieldEncryption::encrypt');
  });

  test('handleSendMessage requires encryption library', () => {
    const content = readFile('process_development_programs.php');
    const fnStart = content.indexOf('function handleSendMessage');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain("lib/encryption.php");
  });

  test('handleSendMessage uses encrypted_message variable for INSERT', () => {
    const content = readFile('process_development_programs.php');
    const fnStart = content.indexOf('function handleSendMessage');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('$encrypted_message');
  });

  test('athlete view decrypts program chat messages', () => {
    const content = readFile('views/personal_development_my_program.php');
    // Messages query with sender_first/sender_last
    expect(content).toContain('sender_first');
    expect(content).toContain('sender_last');
    // decryptUserRows is called on messages
    expect(content).toContain("decryptUserRows($enrollment['messages'])");
  });

  test('athlete view also applies FieldEncryption::decryptRows on messages with sender fields', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain("FieldEncryption::decryptRows($enrollment['messages'], array_merge(");
    expect(content).toContain("FieldEncryption::MESSAGE_ENCRYPTED_FIELDS");
    expect(content).toContain("'sender_first', 'sender_last'");
  });

  test('coach view decrypts program chat messages', () => {
    const content = readFile('views/development_programs.php');
    // Messages query with sender_first/sender_last
    expect(content).toContain('sender_first');
    expect(content).toContain('sender_last');
    // decryptUserRows is called on selected_messages
    expect(content).toContain('decryptUserRows($selected_messages)');
  });

  test('coach view also applies FieldEncryption::decryptRows on messages with sender fields', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain("FieldEncryption::decryptRows($selected_messages, array_merge(");
    expect(content).toContain("FieldEncryption::MESSAGE_ENCRYPTED_FIELDS");
    expect(content).toContain("'sender_first', 'sender_last'");
  });
});

// =====================================================
// Chat bubble UI and encryption indicators
// =====================================================

test.describe('Development program chat bubble UI', () => {

  test('athlete chat uses bubble-row layout with proper alignment classes', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('chat-bubble-row');
    expect(content).toContain('chat-bubble');
    expect(content).toContain('from-me');
    expect(content).toContain('from-coach');
  });

  test('athlete chat has flex-direction column for message flow', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('display: flex');
    expect(content).toContain('flex-direction: column');
  });

  test('athlete chat sent messages are right-aligned', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('.chat-bubble-row.from-me { align-self: flex-end');
  });

  test('athlete chat received messages are left-aligned', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('.chat-bubble-row.from-coach { align-self: flex-start');
  });

  test('athlete chat has encryption badge', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('chat-e2e-badge');
    expect(content).toContain('fa-lock');
    expect(content).toContain('Encrypted');
  });

  test('athlete chat bubbles have lock icon per message', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain('chat-bubble-meta');
    // Lock icon within the meta line
    expect(content).toMatch(/chat-bubble-meta.*fa-lock/s);
  });

  test('coach chat uses bubble-row layout with proper alignment classes', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('dev-chat-bubble-row');
    expect(content).toContain('dev-chat-bubble');
    expect(content).toContain('from-coach');
    expect(content).toContain('from-athlete');
  });

  test('coach chat sent messages are right-aligned', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('.dev-chat-bubble-row.from-coach { align-self: flex-end');
  });

  test('coach chat received messages are left-aligned', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('.dev-chat-bubble-row.from-athlete { align-self: flex-start');
  });

  test('coach chat has encryption badge', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('dev-chat-e2e-badge');
    expect(content).toContain('fa-lock');
    expect(content).toContain('Encrypted');
  });

  test('coach chat bubbles have lock icon per message', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain('dev-chat-bubble-meta');
    expect(content).toMatch(/dev-chat-bubble-meta.*fa-lock/s);
  });
});

// =====================================================
// 3. process_reports.php decrypts PII in payment queries
// =====================================================

test.describe('process_reports.php PII decryption', () => {

  test('getLocalPaymentsWithStripeInfo decrypts user rows', () => {
    const content = readFile('process_reports.php');
    const fnStart = content.indexOf('function getLocalPaymentsWithStripeInfo');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('decryptUserRows');
  });

  test('getLocalPaymentsWithStripeInfo selects user PII fields', () => {
    const content = readFile('process_reports.php');
    const fnStart = content.indexOf('function getLocalPaymentsWithStripeInfo');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('u.first_name');
    expect(fnBody).toContain('u.last_name');
  });
});

// =====================================================
// 4. process_time_tracking.php PII decryption
// =====================================================

test.describe('process_time_tracking.php PII decryption', () => {

  test('schedule query results are decrypted', () => {
    const content = readFile('process_time_tracking.php');
    // Find the schedule fetch and verify decryptUserRows is called
    const fetchIdx = content.indexOf("$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC)");
    expect(fetchIdx).toBeGreaterThan(-1);
    // Check the next 200 chars after the fetchAll for decryptUserRows
    const afterFetch = content.substring(fetchIdx, fetchIdx + 300);
    expect(afterFetch).toContain('decryptUserRows');
  });

  test('shift export query results are decrypted', () => {
    const content = readFile('process_time_tracking.php');
    // Find the export_report section
    const exportIdx = content.indexOf("'export_report'");
    if (exportIdx === -1) return;
    const sectionEnd = content.indexOf('break;', exportIdx + 100);
    const section = content.substring(exportIdx, sectionEnd > -1 ? sectionEnd : exportIdx + 5000);
    expect(section).toContain('decryptUserRows');
  });
});

// =====================================================
// 5. RLS goals table uses correct column mapping
// =====================================================

test.describe('RLS goals table mapping', () => {

  test('goals table maps to athlete_id not user_id', () => {
    const content = readFile('lib/row_level_security.php');
    // Find TABLE_OWNER_MAP
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    // Goals should map to athlete_id
    expect(mapBody).toContain("'goals'");
    expect(mapBody).toContain("'athlete_id'");
    // Should NOT have goals => user_id
    expect(mapBody).not.toMatch(/'goals'\s*=>\s*'user_id'/);
  });
});

// =====================================================
// 6. RLS TABLE_OWNER_MAP includes critical tables
// =====================================================

test.describe('RLS TABLE_OWNER_MAP coverage', () => {

  test('TABLE_OWNER_MAP includes videos table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'videos'");
  });

  test('TABLE_OWNER_MAP includes expenses table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'expenses'");
  });

  test('TABLE_OWNER_MAP includes athlete_stats table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'athlete_stats'");
  });

  test('TABLE_OWNER_MAP includes development_program_enrollments table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'development_program_enrollments'");
  });

  test('TABLE_OWNER_MAP includes development_program_videos table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'development_program_videos'");
  });

  test('TABLE_OWNER_MAP includes session_attendance table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'session_attendance'");
  });

  test('TABLE_OWNER_MAP includes session_feedback table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'session_feedback'");
  });

  test('TABLE_OWNER_MAP includes mileage_logs table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'mileage_logs'");
  });

  test('TABLE_OWNER_MAP includes athlete_notes table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'athlete_notes'");
  });

  test('TABLE_OWNER_MAP includes staff_pins table', () => {
    const content = readFile('lib/row_level_security.php');
    const mapStart = content.indexOf('TABLE_OWNER_MAP');
    const mapEnd = content.indexOf('];', mapStart) + 2;
    const mapBody = content.substring(mapStart, mapEnd);
    expect(mapBody).toContain("'staff_pins'");
  });
});

// =====================================================
// Comprehensive PII alias coverage in decryptUserRow
// =====================================================

test.describe('decryptUserRow comprehensive PII alias coverage', () => {
  function getPiiFieldsBody() {
    const content = readFile('db_config.php');
    const fnStart = content.indexOf('function decryptUserRow($row)');
    const fnEnd = content.indexOf('\n    }', fnStart) + 6;
    return content.substring(fnStart, fnEnd);
  }

  test('includes evaluator_first_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'evaluator_first_name'");
  });

  test('includes evaluator_last_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'evaluator_last_name'");
  });

  test('includes evaluator_first alias', () => {
    expect(getPiiFieldsBody()).toContain("'evaluator_first'");
  });

  test('includes evaluator_last alias', () => {
    expect(getPiiFieldsBody()).toContain("'evaluator_last'");
  });

  test('includes generated_by_first_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'generated_by_first_name'");
  });

  test('includes generated_by_last_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'generated_by_last_name'");
  });

  test('includes performed_by_first_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'performed_by_first_name'");
  });

  test('includes performed_by_last_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'performed_by_last_name'");
  });

  test('includes performer_first_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'performer_first_name'");
  });

  test('includes performer_last_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'performer_last_name'");
  });

  test('includes created_by_first alias', () => {
    expect(getPiiFieldsBody()).toContain("'created_by_first'");
  });

  test('includes created_by_last alias', () => {
    expect(getPiiFieldsBody()).toContain("'created_by_last'");
  });

  test('includes processed_first alias', () => {
    expect(getPiiFieldsBody()).toContain("'processed_first'");
  });

  test('includes processed_last alias', () => {
    expect(getPiiFieldsBody()).toContain("'processed_last'");
  });

  test('includes athlete_first alias', () => {
    expect(getPiiFieldsBody()).toContain("'athlete_first'");
  });

  test('includes athlete_last alias', () => {
    expect(getPiiFieldsBody()).toContain("'athlete_last'");
  });

  test('includes inviter_first_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'inviter_first_name'");
  });

  test('includes inviter_last_name alias', () => {
    expect(getPiiFieldsBody()).toContain("'inviter_last_name'");
  });
});

// =====================================================
// Belt-and-suspenders decryption in development programs
// =====================================================

test.describe('Development programs belt-and-suspenders decryption', () => {

  test('coach view applies FieldEncryption::decryptRows on drills with coach fields', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain("FieldEncryption::decryptRows($selected_drills, ['coach_first', 'coach_last'])");
  });

  test('coach view applies FieldEncryption::decryptRows on appointments with coach fields', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain("FieldEncryption::decryptRows($selected_appointments, ['coach_first', 'coach_last'])");
  });

  test('coach view applies FieldEncryption::decryptRows on athletes list', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain("FieldEncryption::decryptRows($athletes, ['first_name', 'last_name', 'athlete_coach_first', 'athlete_coach_last'])");
  });

  test('coach view applies FieldEncryption::decryptRows on history list', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain("FieldEncryption::decryptRows($history_athletes, ['first_name', 'last_name', 'athlete_coach_first', 'athlete_coach_last'])");
  });

  test('coach view applies FieldEncryption::decryptFields on selected enrollment', () => {
    const content = readFile('views/development_programs.php');
    expect(content).toContain("FieldEncryption::decryptFields($selected, ['first_name', 'last_name', 'athlete_coach_first', 'athlete_coach_last'])");
  });

  test('athlete view applies FieldEncryption::decryptRows on drills with coach fields', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain("FieldEncryption::decryptRows($enrollment['drills'], ['coach_first', 'coach_last'])");
  });

  test('athlete view applies FieldEncryption::decryptRows on appointments with coach fields', () => {
    const content = readFile('views/personal_development_my_program.php');
    expect(content).toContain("FieldEncryption::decryptRows($enrollment['appointments'], ['coach_first', 'coach_last'])");
  });
});
