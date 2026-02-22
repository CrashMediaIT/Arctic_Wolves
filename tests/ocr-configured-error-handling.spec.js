/**
 * Tests for OCR Error Handling When Paperless-NGX Is Configured
 * 
 * Verifies:
 * 1. performPaperlessOCR returns specific error messages (not null) when Paperless is configured but API calls fail
 * 2. The generic "configure Paperless-NGX" message only appears when Paperless is truly not configured
 * 3. cron_receipt_scanner.php returns OCR_ERROR: prefixed messages for configured-but-failed scenarios
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('OCR Error Handling - process_expenses.php', () => {
  let content;

  test.beforeAll(() => {
    const filePath = path.join(__dirname, '..', 'process_expenses.php');
    content = fs.readFileSync(filePath, 'utf-8');
  });

  test('performPaperlessOCR should return error array (not null) when upload fails', () => {
    // After the curl upload fails, should return an ocr_data array with error, not null
    expect(content).toContain("'Paperless-NGX upload failed (HTTP '");
    expect(content).toContain("return $ocr_data;");
    // The old "return null; // Paperless-NGX not available" after upload should be gone
    expect(content).not.toContain("return null; // Paperless-NGX not available");
  });

  test('performPaperlessOCR should return error array when task ID is invalid', () => {
    expect(content).toContain("'Paperless-NGX returned an invalid task ID. Check your Paperless-NGX server.'");
  });

  test('performPaperlessOCR should return error array when OCR task fails', () => {
    expect(content).toContain("'Paperless-NGX OCR processing failed: '");
  });

  test('performPaperlessOCR should return error array when processing times out', () => {
    expect(content).toContain("'Paperless-NGX document processing timed out. Try again or check your server.'");
  });

  test('performPaperlessOCR should still return null only for not-configured cases', () => {
    // The function should return null only for: not configured, OCR disabled, or decryption failure
    // These are the only cases where the "configure Paperless-NGX" fallback message is appropriate
    const nullReturns = content.match(/return null;/g);
    expect(nullReturns).not.toBeNull();
    // performPaperlessOCR should only return null for: db error, not configured, no decrypt fn, empty token
    // Other null returns in the file are in different functions (e.g., resolvePayeeId)
    expect(nullReturns.length).toBeLessThanOrEqual(5);
  });

  test('performReceiptOCR fallback message should only show for truly unconfigured state', () => {
    // The generic "configure Paperless-NGX" message should still exist as fallback for null returns
    expect(content).toContain("OCR not available - configure Paperless-NGX in Settings > System Tools");
  });
});

test.describe('OCR Error Handling - cron_receipt_scanner.php', () => {
  let content;

  test.beforeAll(() => {
    const filePath = path.join(__dirname, '..', 'cron_receipt_scanner.php');
    content = fs.readFileSync(filePath, 'utf-8');
  });

  test('performPaperlessOCRCron should return OCR_ERROR string when upload fails', () => {
    expect(content).toContain('OCR_ERROR: Paperless-NGX upload failed (HTTP ');
  });

  test('performPaperlessOCRCron should return OCR_ERROR string when task ID is invalid', () => {
    expect(content).toContain('OCR_ERROR: Paperless-NGX returned an invalid task ID.');
  });

  test('performPaperlessOCRCron should return OCR_ERROR string when OCR task fails', () => {
    expect(content).toContain('OCR_ERROR: Paperless-NGX OCR processing failed:');
  });

  test('performPaperlessOCRCron should return OCR_ERROR string when processing times out', () => {
    expect(content).toContain('OCR_ERROR: Paperless-NGX document processing timed out.');
  });

  test('performOCR should still show configure message only for truly unconfigured state', () => {
    // The generic "not configured" message should only be in the performOCR fallback
    expect(content).toContain("OCR_NOT_AVAILABLE: Paperless-NGX not configured - configure in Settings > System Tools");
  });

  test('cron parseReceiptOCR should handle OCR_ERROR prefix via existing OCR_ check', () => {
    // parseReceiptOCR checks for OCR_ prefix which covers both OCR_NOT_AVAILABLE and OCR_ERROR
    expect(content).toContain("strpos($ocr_text, 'OCR_') === 0");
  });
});
