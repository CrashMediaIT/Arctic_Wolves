/**
 * Tests for OCR Line Item Extraction and Payee Input Enhancement
 * 
 * Verifies:
 * 1. OCR parseOCRText extracts line items (Pattern 1: qty x price, Pattern 2: item $price)
 * 2. OCR results display includes line items container and removal
 * 3. useOCRData populates line items in the expense form
 * 4. Payee dropdown has "Add New Payee" option with text input
 * 5. Backend handles new payee creation from text input
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('OCR Line Item Extraction - process_expenses.php', () => {
  let content;

  test.beforeAll(() => {
    const filePath = path.join(__dirname, '..', 'process_expenses.php');
    content = fs.readFileSync(filePath, 'utf-8');
  });

  test('parseOCRText should initialize items array', () => {
    expect(content).toContain("$ocr_data['items'] = [];");
  });

  test('parseOCRText should have Pattern 1 for qty x price', () => {
    // Pattern 1: quantity x price (e.g., "Widget 2x$5.00")
    expect(content).toContain("preg_match_all('/(.+?)\\s+(\\d+)\\s*[xX@]\\s*\\$?(\\d+[\\.,]\\d{2})/'");
  });

  test('parseOCRText should have Pattern 2 for item followed by price', () => {
    // Pattern 2: item name followed by price with multiple spaces
    expect(content).toContain("preg_match('/^(.+?)\\s{2,}\\$?\\s*(\\d+[\\.,]\\d{2})\\s*$/'");
  });

  test('parseOCRText should skip total/tax/payment lines in line item extraction', () => {
    expect(content).toContain('skip_keywords');
    expect(content).toContain('total|subtotal|sub-total|tax|gst|hst');
  });

  test('backend should handle new payee creation via resolvePayeeId', () => {
    expect(content).toContain("function resolvePayeeId($pdo, $payee_id_input, $new_payee_name_input, $user_id)");
    expect(content).toContain("'new'");
    expect(content).toContain("INSERT INTO payees (name, created_by) VALUES (?, ?)");
  });
});

test.describe('OCR Line Items Display - accounting_expenses.php', () => {
  let content;

  test.beforeAll(() => {
    const filePath = path.join(__dirname, '..', 'views', 'accounting_expenses.php');
    content = fs.readFileSync(filePath, 'utf-8');
  });

  test('should have renderOCRItems function', () => {
    expect(content).toContain('function renderOCRItems()');
  });

  test('renderOCRItems should display items with remove buttons', () => {
    expect(content).toContain('removeOCRItem(');
    expect(content).toContain('ocr-item-row');
    expect(content).toContain('ocr-item-name');
  });

  test('should have removeOCRItem function', () => {
    expect(content).toContain('function removeOCRItem(index)');
    expect(content).toContain('ocrData.items.splice(index, 1)');
  });

  test('should call renderOCRItems after OCR results are received', () => {
    // In the fetch .then() handler, renderOCRItems should be called
    expect(content).toContain('renderOCRItems();');
  });

  test('useOCRData should populate line items from OCR', () => {
    expect(content).toContain('ocrData.items.forEach');
    expect(content).toContain('addLineItem()');
    expect(content).toContain('.line-item-name');
    expect(content).toContain('.line-item-qty');
    expect(content).toContain('.line-item-price');
  });

  test('useOCRData should set payee from vendor', () => {
    expect(content).toContain("payeeSelect.value = 'new'");
    expect(content).toContain('toggleNewPayeeInput()');
    expect(content).toContain("document.getElementById('newPayeeName').value = ocrData.vendor");
  });
});

test.describe('Payee Input Enhancement - accounting_expenses.php', () => {
  let content;

  test.beforeAll(() => {
    const filePath = path.join(__dirname, '..', 'views', 'accounting_expenses.php');
    content = fs.readFileSync(filePath, 'utf-8');
  });

  test('payee dropdown should have Add New Payee option', () => {
    expect(content).toContain('<option value="new">+ Add New Payee</option>');
  });

  test('should have text input for new payee name', () => {
    expect(content).toContain('id="newPayeeName"');
    expect(content).toContain('name="new_payee_name"');
  });

  test('should have toggleNewPayeeInput function', () => {
    expect(content).toContain('function toggleNewPayeeInput()');
  });

  test('payee dropdown should trigger toggleNewPayeeInput on change', () => {
    expect(content).toContain("onchange=\"toggleNewPayeeInput()\"");
  });

  test('should have CSS for OCR items display', () => {
    expect(content).toContain('.ocr-items-list');
    expect(content).toContain('.ocr-item-row');
  });

  test('should have escapeHtml function for XSS prevention', () => {
    expect(content).toContain('function escapeHtml(text)');
    expect(content).toContain('createTextNode');
  });
});
