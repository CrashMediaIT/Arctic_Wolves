import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for fixing the dev program payment verification error.
 * 
 * Root cause: The notification block in payment_success.php caught only PDOException,
 * allowing non-PDO exceptions (from decryptUserRows, sendEmail, etc.) to propagate
 * and mask the successful enrollment with "Payment verification encountered an error."
 * 
 * Fix: Wrap all non-critical post-enrollment operations (notifications, emails) in 
 * \Throwable catches. Same pattern applied to template_session and regular booking paths.
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Dev Program - Notification Catch Fix
// =====================================================

test.describe('Dev Program - Notification catch block catches all exceptions', () => {
  test('notification block catches \\Throwable not just PDOException', () => {
    const content = readFile('payment_success.php');
    
    // Find the dev_program section's notification try/catch
    const devProgramStart = content.indexOf("'dev_program'");
    expect(devProgramStart).toBeGreaterThan(-1);
    
    // After dev_program section, the notification catch should use \Throwable
    const afterDevProgram = content.substring(devProgramStart);
    
    // The old catch was: catch (PDOException $ne)
    // The fix changes it to: catch (\Throwable $ne) 
    expect(afterDevProgram).toContain("catch (\\Throwable $ne)");
    
    // The old PDOException-only catch for notifications should NOT exist in dev_program section
    // (it's a specific pattern: catch PDOException with comment about notifications)
    const devSection = content.substring(
      content.indexOf("metadata->type === 'dev_program'"),
      content.indexOf("metadata->type === 'template_session'")
    );
    expect(devSection).not.toContain("catch (PDOException $ne)");
  });

  test('notification catch logs helpful error message with enrollment success context', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('Dev program notification failed');
  });

  test('decryptUserRows call is guarded with null check', () => {
    const content = readFile('payment_success.php');
    // Should check $athlete_info before passing to decryptUserRows
    expect(content).toContain('$athlete_info && function_exists');
  });
});

// =====================================================
// 2. Dev Program - Email sends wrapped in try/catch
// =====================================================

test.describe('Dev Program - Receipt email wrapped in try/catch', () => {
  test('dev program receipt email is wrapped in try/catch \\Throwable', () => {
    const content = readFile('payment_success.php');
    
    // The sendEmail for payment_receipt in dev program section should be inside try
    const devSection = content.substring(
      content.indexOf("metadata->type === 'dev_program'"),
      content.indexOf("metadata->type === 'template_session'")
    );
    
    // Should have try/catch around receipt email
    expect(devSection).toContain("catch (\\Throwable $emailErr)");
    expect(devSection).toContain("Dev program receipt email failed");
  });

  test('dev program enrollment INSERT is NOT inside the email try/catch', () => {
    const content = readFile('payment_success.php');
    
    // The enrollment INSERT should be BEFORE the email try/catch
    const enrollmentInsert = content.indexOf("INSERT INTO development_program_enrollments");
    const emailTryCatch = content.indexOf("Dev program receipt email failed");
    
    expect(enrollmentInsert).toBeGreaterThan(-1);
    expect(emailTryCatch).toBeGreaterThan(-1);
    expect(enrollmentInsert).toBeLessThan(emailTryCatch);
  });
});

// =====================================================
// 3. Template Session - Email wrapped in try/catch
// =====================================================

test.describe('Template Session - Receipt email wrapped in try/catch', () => {
  test('template session receipt email is wrapped in try/catch \\Throwable', () => {
    const content = readFile('payment_success.php');
    
    const tplSection = content.substring(
      content.indexOf("metadata->type === 'template_session'"),
      content.indexOf('HANDLE REGULAR SESSION BOOKING')
    );
    
    expect(tplSection).toContain("catch (\\Throwable $emailErr)");
    expect(tplSection).toContain("Template session receipt email failed");
  });
});

// =====================================================
// 4. Regular Booking - Email wrapped in try/catch
// =====================================================

test.describe('Regular Booking - Receipt email wrapped in try/catch', () => {
  test('regular booking receipt email is wrapped in try/catch \\Throwable', () => {
    const content = readFile('payment_success.php');
    
    const bookingSection = content.substring(
      content.indexOf('HANDLE REGULAR SESSION BOOKING')
    );
    
    expect(bookingSection).toContain("catch (\\Throwable $emailErr)");
    expect(bookingSection).toContain("Booking receipt email failed");
  });
});

// =====================================================
// 5. Package Purchase - Email wrapped in try/catch
// =====================================================

test.describe('Package Purchase - Receipt email wrapped in try/catch', () => {
  test('package purchase receipt email is wrapped in try/catch \\Throwable', () => {
    const content = readFile('payment_success.php');
    
    const pkgSection = content.substring(
      content.indexOf('HANDLE CAMP / MULTI-WEEK PACKAGE PURCHASE'),
      content.indexOf("metadata->type === 'dev_program'")
    );
    
    expect(pkgSection).toContain("catch (\\Throwable $emailErr)");
    expect(pkgSection).toContain("Package receipt email failed");
  });
});

// =====================================================
// 6. Success Page - Appropriate messages per purchase type
// =====================================================

test.describe('Success Page - Purchase-type-specific messages', () => {
  test('success page shows Enrollment Confirmed for dev programs', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("Enrollment Confirmed!");
    expect(content).toContain("enrolled in the development program");
  });

  test('success page shows Registration Confirmed for packages', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("Registration Confirmed!");
  });

  test('success page still shows Booking Confirmed for regular bookings', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("Booking Confirmed!");
  });

  test('dev program success links to My Program page', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("personal_development_my_program");
    expect(content).toContain("View My Program");
  });

  test('purchase_confirmed variable is initialized and set for each purchase type', () => {
    const content = readFile('payment_success.php');
    
    // Initialized to null before try block
    expect(content).toContain("$purchase_confirmed = null");
    
    // Set for each purchase type
    expect(content).toContain("$purchase_confirmed = 'package'");
    expect(content).toContain("$purchase_confirmed = 'dev_program'");
    expect(content).toContain("$purchase_confirmed = 'template_session'");
    expect(content).toContain("$purchase_confirmed = 'booking'");
  });
});

// =====================================================
// 7. Enrollment INSERT is the critical path
// =====================================================

test.describe('Dev Program - Critical vs non-critical operations', () => {
  test('enrollment INSERT happens before any non-critical operations', () => {
    const content = readFile('payment_success.php');
    
    const devStart = content.indexOf("metadata->type === 'dev_program'");
    const devSection = content.substring(devStart);
    
    const enrollmentInsert = devSection.indexOf("INSERT INTO development_program_enrollments");
    const invoiceCreate = devSection.indexOf("createPurchaseInvoice");
    const notifyCoaches = devSection.indexOf("Notify dev coaches");
    const sendReceipt = devSection.indexOf("Send payment receipt");
    
    // Enrollment should be before all non-critical operations
    expect(enrollmentInsert).toBeLessThan(invoiceCreate);
    expect(enrollmentInsert).toBeLessThan(notifyCoaches);
    expect(enrollmentInsert).toBeLessThan(sendReceipt);
  });

  test('NON-CRITICAL comment marks boundary between critical and non-critical', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('NON-CRITICAL');
    expect(content).toContain('should NOT prevent showing the success page');
  });
});

// =====================================================
// 8. Existing functionality preserved
// =====================================================

test.describe('Existing functionality preserved', () => {
  test('dev program still creates enrollment record', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain("INSERT INTO development_program_enrollments");
  });

  test('dev program still uses Auditor::log', () => {
    const content = readFile('payment_success.php');
    const devSection = content.substring(
      content.indexOf("metadata->type === 'dev_program'"),
      content.indexOf("metadata->type === 'template_session'")
    );
    expect(devSection).toContain("Auditor::log");
  });

  test('dev program still creates invoice', () => {
    const content = readFile('payment_success.php');
    const devSection = content.substring(
      content.indexOf("metadata->type === 'dev_program'"),
      content.indexOf("metadata->type === 'template_session'")
    );
    expect(devSection).toContain("createPurchaseInvoice");
    expect(devSection).toContain("dev_invoice_id");
  });

  test('dev program still sends receipt email with invoice_id', () => {
    const content = readFile('payment_success.php');
    const devSection = content.substring(
      content.indexOf("metadata->type === 'dev_program'"),
      content.indexOf("metadata->type === 'template_session'")
    );
    expect(devSection).toContain("'invoice_id'");
    expect(devSection).toContain("sendEmail");
    expect(devSection).toContain("payment_receipt");
  });

  test('dev program still sends welcome email', () => {
    const content = readFile('payment_success.php');
    const devSection = content.substring(
      content.indexOf("metadata->type === 'dev_program'"),
      content.indexOf("metadata->type === 'template_session'")
    );
    expect(devSection).toContain("Welcome to Your Development Program");
  });

  test('dev program still notifies coaches', () => {
    const content = readFile('payment_success.php');
    const devSection = content.substring(
      content.indexOf("metadata->type === 'dev_program'"),
      content.indexOf("metadata->type === 'template_session'")
    );
    expect(devSection).toContain("Notify dev coaches");
    expect(devSection).toContain("INSERT INTO notifications");
  });

  test('idempotency checks still in place for all purchase types', () => {
    const content = readFile('payment_success.php');
    
    // Dev program idempotency
    expect(content).toContain("development_program_enrollments WHERE athlete_id = ? AND program_type = ? AND template_id = ? AND status = 'active'");
    
    // Template session idempotency
    expect(content).toContain("session_date_athletes WHERE session_date_id = ? AND athlete_id = ?");
    
    // Regular booking idempotency
    expect(content).toContain("payment_status != 'paid'");
  });
});
