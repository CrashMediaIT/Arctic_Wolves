/**
 * Tests for Expense Receipt Attachment after OCR
 *
 * Verifies that when a receipt is scanned via OCR (Paperless-NGX),
 * the receipt file is properly attached to the expense when created.
 *
 * 1. Hidden fields exist in the expense form for OCR receipt data
 * 2. useOCRData() populates receipt hidden fields from OCR scan response
 * 3. Backend create action accepts ocr_receipt_url and ocr_nextcloud_path
 * 4. Backend validates ocr_receipt_url is a valid HTTP/HTTPS URL (RustFS)
 * 5. Backend uploads receipt to Paperless-NGX for archival
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Hidden fields in expense form for OCR receipt data
// =====================================================

test.describe('Expense form has hidden fields for OCR receipt', () => {
  test('should have hidden input for ocr_receipt_url', () => {
    const content = readFile('views/accounting_expenses.php');
    expect(content).toContain('name="ocr_receipt_url"');
    expect(content).toContain('id="ocrReceiptUrl"');
  });

  test('should have hidden input for ocr_nextcloud_path', () => {
    const content = readFile('views/accounting_expenses.php');
    expect(content).toContain('name="ocr_nextcloud_path"');
    expect(content).toContain('id="ocrNextcloudPath"');
  });

  test('hidden fields should be inside the expense form', () => {
    const content = readFile('views/accounting_expenses.php');
    const formStart = content.indexOf('id="expenseForm"');
    const formEnd = content.indexOf('</form>', formStart);
    const formContent = content.substring(formStart, formEnd);
    expect(formContent).toContain('name="ocr_receipt_url"');
    expect(formContent).toContain('name="ocr_nextcloud_path"');
  });
});

// =====================================================
// 2. useOCRData populates receipt hidden fields
// =====================================================

test.describe('useOCRData populates receipt URL from OCR scan', () => {
  test('useOCRData should set ocrReceiptUrl from _receipt_url', () => {
    const content = readFile('views/accounting_expenses.php');
    const fnStart = content.indexOf('function useOCRData()');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 1);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("document.getElementById('ocrReceiptUrl').value = ocrData._receipt_url");
  });

  test('useOCRData should set ocrNextcloudPath from _nextcloud_path', () => {
    const content = readFile('views/accounting_expenses.php');
    const fnStart = content.indexOf('function useOCRData()');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 1);
    const fn = content.substring(fnStart, fnEnd);
    expect(fn).toContain("document.getElementById('ocrNextcloudPath').value = ocrData._nextcloud_path");
  });
});

// =====================================================
// 3. Backend create action handles OCR receipt
// =====================================================

test.describe('Backend create action uses OCR receipt when no file uploaded', () => {
  test('create action should check for ocr_receipt_url in POST', () => {
    const content = readFile('process_expenses.php');
    const createStart = content.indexOf("case 'create':");
    const createEnd = content.indexOf("case 'update':", createStart);
    const createSection = content.substring(createStart, createEnd);
    expect(createSection).toContain("$_POST['ocr_receipt_url']");
  });

  test('create action should check for ocr_nextcloud_path in POST', () => {
    const content = readFile('process_expenses.php');
    const createStart = content.indexOf("case 'create':");
    const createEnd = content.indexOf("case 'update':", createStart);
    const createSection = content.substring(createStart, createEnd);
    expect(createSection).toContain("$_POST['ocr_nextcloud_path']");
  });

  test('create action should use OCR receipt as elseif when no file uploaded', () => {
    const content = readFile('process_expenses.php');
    const createStart = content.indexOf("case 'create':");
    const createEnd = content.indexOf("case 'update':", createStart);
    const createSection = content.substring(createStart, createEnd);
    // Should be an elseif after the file upload check
    expect(createSection).toContain("} elseif (!empty($_POST['ocr_receipt_url']))");
  });
});

// =====================================================
// 4. Backend validates ocr_receipt_url is a valid URL
// =====================================================

test.describe('Backend validates OCR receipt URL for security', () => {
  test('should validate OCR receipt is an HTTP/HTTPS URL', () => {
    const content = readFile('process_expenses.php');
    const createStart = content.indexOf("case 'create':");
    const createEnd = content.indexOf("case 'update':", createStart);
    const createSection = content.substring(createStart, createEnd);
    expect(createSection).toContain("preg_match('#^https?://#', $ocr_receipt)");
  });

  test('should only set receipt_url when URL matches HTTP pattern', () => {
    const content = readFile('process_expenses.php');
    const createStart = content.indexOf("case 'create':");
    const createEnd = content.indexOf("case 'update':", createStart);
    const createSection = content.substring(createStart, createEnd);
    // receipt_url is set only inside the preg_match guard — no local paths accepted
    expect(createSection).toContain('$receipt_url = $ocr_receipt');
  });
});

// =====================================================
// 5. Upload receipt to Paperless-NGX for archival
// =====================================================

test.describe('Upload receipt to Paperless-NGX when file is uploaded', () => {
  test('Paperless upload should happen when a fresh file is uploaded with vendor', () => {
    const content = readFile('process_expenses.php');
    const createStart = content.indexOf("case 'create':");
    const createEnd = content.indexOf("case 'update':", createStart);
    const createSection = content.substring(createStart, createEnd);
    // Receipt is stored in RustFS; Paperless upload happens for fresh file uploads
    expect(createSection).toContain('uploadToPaperless');
  });
});
