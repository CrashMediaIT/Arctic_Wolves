import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Arctic Wolves - Purchase Invoice Creation Tests
 * Tests for:
 * 1. Invoice helper library creates invoices and payment records
 * 2. Payment success creates invoices for all purchase types
 * 3. Shop success creates invoices for shop orders
 * 4. Email receipts include invoice download links
 * 5. Payment history displays user invoices
 * 6. User-accessible invoice download page
 * 7. PWA payment history shows invoices
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Invoice Helper Library
// =====================================================

test.describe('Invoice Helper Library', () => {
  test('lib/invoice_helper.php exists and defines createPurchaseInvoice function', () => {
    const content = readFile('lib/invoice_helper.php');
    expect(content).toContain('function createPurchaseInvoice');
    expect(content).toContain('$pdo');
    expect(content).toContain('$user_id');
    expect(content).toContain('$items');
  });

  test('createPurchaseInvoice generates unique invoice numbers', () => {
    const content = readFile('lib/invoice_helper.php');
    expect(content).toContain("INV-");
    expect(content).toContain('invoice_number');
    // Should check for uniqueness
    expect(content).toContain('SELECT id FROM invoices WHERE invoice_number');
  });

  test('createPurchaseInvoice creates invoice with paid status', () => {
    const content = readFile('lib/invoice_helper.php');
    expect(content).toContain('INSERT INTO invoices');
    expect(content).toContain("'paid'");
    expect(content).toContain('paid_date');
  });

  test('createPurchaseInvoice inserts line items', () => {
    const content = readFile('lib/invoice_helper.php');
    expect(content).toContain('INSERT INTO invoice_items');
    expect(content).toContain('invoice_id');
    expect(content).toContain('description');
    expect(content).toContain('quantity');
    expect(content).toContain('unit_price');
    expect(content).toContain('total_price');
  });

  test('createPurchaseInvoice records payment', () => {
    const content = readFile('lib/invoice_helper.php');
    expect(content).toContain('INSERT INTO payments');
    expect(content).toContain('invoice_id');
    expect(content).toContain('payment_method');
    expect(content).toContain('transaction_id');
  });

  test('createPurchaseInvoice returns invoice ID on success', () => {
    const content = readFile('lib/invoice_helper.php');
    expect(content).toContain('return $invoice_id');
    expect(content).toContain('return false');
  });

  test('createPurchaseInvoice handles errors gracefully', () => {
    const content = readFile('lib/invoice_helper.php');
    expect(content).toContain('catch (PDOException');
    expect(content).toContain('return false');
  });
});

// =====================================================
// 2. Payment Success - Invoice Creation
// =====================================================

test.describe('Payment Success - Invoice Creation for All Purchase Types', () => {
  test('payment_success.php includes invoice helper', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('invoice_helper.php');
  });

  test('payment_success.php creates invoice for package purchases', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('createPurchaseInvoice');
    expect(content).toContain("Package purchase");
    expect(content).toContain('pkg_invoice_id');
  });

  test('payment_success.php creates invoice for development program enrollments', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('dev_invoice_id');
    expect(content).toContain('Development program enrollment');
  });

  test('payment_success.php creates invoice for template session registrations', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('tpl_invoice_id');
    expect(content).toContain('Session registration');
  });

  test('payment_success.php creates invoice for regular session bookings', () => {
    const content = readFile('payment_success.php');
    expect(content).toContain('booking_invoice_id');
    expect(content).toContain('Session booking');
  });

  test('invoices are only created after Stripe confirms payment', () => {
    const content = readFile('payment_success.php');
    // The createPurchaseInvoice calls should be inside the payment_status == 'paid' block
    const paidCheckIndex = content.indexOf("payment_status == 'paid'");
    const firstInvoiceCallIndex = content.indexOf('createPurchaseInvoice');
    expect(paidCheckIndex).toBeGreaterThan(-1);
    expect(firstInvoiceCallIndex).toBeGreaterThan(paidCheckIndex);
  });

  test('invoice IDs are passed to email receipts', () => {
    const content = readFile('payment_success.php');
    const invoiceIdInEmailCount = (content.match(/'invoice_id'\s*=>\s*\$/g) || []).length;
    // Should have invoice_id in email data for all 4 purchase types
    expect(invoiceIdInEmailCount).toBeGreaterThanOrEqual(4);
  });
});

// =====================================================
// 3. Shop Success - Invoice Creation
// =====================================================

test.describe('Shop Success - Invoice Creation', () => {
  test('shop_success.php includes invoice helper', () => {
    const content = readFile('shop_success.php');
    expect(content).toContain('invoice_helper.php');
  });

  test('shop_success.php creates invoice for completed shop orders', () => {
    const content = readFile('shop_success.php');
    expect(content).toContain('createPurchaseInvoice');
    expect(content).toContain('Shop order');
  });

  test('shop_success.php builds invoice line items from order items', () => {
    const content = readFile('shop_success.php');
    expect(content).toContain('invoice_line_items');
    expect(content).toContain('product_name');
    expect(content).toContain('unit_price');
  });

  test('shop_success.php only creates invoice when payment is confirmed paid', () => {
    const content = readFile('shop_success.php');
    // Invoice creation should be inside the payment_status === 'paid' block
    const paidCheckIndex = content.indexOf("payment_status === 'paid'");
    const invoiceCallIndex = content.indexOf('createPurchaseInvoice');
    expect(paidCheckIndex).toBeGreaterThan(-1);
    expect(invoiceCallIndex).toBeGreaterThan(paidCheckIndex);
  });

  test('shop_success.php only creates invoice for valid user_id', () => {
    const content = readFile('shop_success.php');
    expect(content).toContain('shop_user_id > 0');
  });
});

// =====================================================
// 4. Email Receipt - Invoice Download Link
// =====================================================

test.describe('Email Receipt - Invoice Download Link', () => {
  test('mailer.php payment_receipt template supports invoice_id parameter', () => {
    const content = readFile('mailer.php');
    expect(content).toContain('invoice_id');
    expect(content).toContain('download_invoice.php');
  });

  test('mailer.php renders View Invoice button when invoice_id is provided', () => {
    const content = readFile('mailer.php');
    expect(content).toContain('View Invoice');
    expect(content).toContain('invoiceLink');
  });

  test('mailer.php builds proper invoice download URL', () => {
    const content = readFile('mailer.php');
    expect(content).toContain('download_invoice.php?invoice_id=');
    expect(content).toContain('APP_URL');
  });
});

// =====================================================
// 5. Payment History - Invoice Display
// =====================================================

test.describe('Payment History - Invoice Display', () => {
  test('payment_history.php queries user invoices', () => {
    const content = readFile('views/payment_history.php');
    expect(content).toContain('SELECT id, invoice_number, invoice_date, total_amount, status');
    expect(content).toContain('FROM invoices');
    expect(content).toContain('WHERE user_id');
  });

  test('payment_history.php displays Invoices section', () => {
    const content = readFile('views/payment_history.php');
    expect(content).toContain('Invoices');
    expect(content).toContain('user_invoices');
    expect(content).toContain('invoice_number');
  });

  test('payment_history.php has download links for invoices', () => {
    const content = readFile('views/payment_history.php');
    expect(content).toContain('download_invoice.php?invoice_id=');
    expect(content).toContain('View Invoice');
  });

  test('payment_history.php shows invoice status badges', () => {
    const content = readFile('views/payment_history.php');
    expect(content).toContain('inv_status');
    expect(content).toContain('paid');
    expect(content).toContain('overdue');
  });
});

// =====================================================
// 6. User-Accessible Invoice Download
// =====================================================

test.describe('User-Accessible Invoice Download', () => {
  test('download_invoice.php exists', () => {
    const content = readFile('download_invoice.php');
    expect(content).toBeTruthy();
  });

  test('download_invoice.php requires login', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain("SESSION['user_id']");
    expect(content).toContain('login.php');
  });

  test('download_invoice.php enforces access control - users see only own invoices', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain('user_id');
    expect(content).toContain('current_user_id');
    expect(content).toContain('access_denied');
  });

  test('download_invoice.php allows admins to view any invoice', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain("user_role !== 'admin'");
  });

  test('download_invoice.php allows parents to view managed athlete invoices', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain('managed_athletes');
    expect(content).toContain('parent_id');
    expect(content).toContain('athlete_id');
  });

  test('download_invoice.php renders invoice with line items', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain('SELECT * FROM invoice_items WHERE invoice_id');
    expect(content).toContain('INVOICE');
    expect(content).toContain('Description');
    expect(content).toContain('Qty');
    expect(content).toContain('Unit Price');
    expect(content).toContain('Total');
  });

  test('download_invoice.php has print functionality', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain('window.print()');
    expect(content).toContain('@media print');
  });

  test('download_invoice.php validates invoice_id parameter', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain('invoice_id <= 0');
    expect(content).toContain('invalid_invoice');
  });

  test('download_invoice.php uses security headers', () => {
    const content = readFile('download_invoice.php');
    expect(content).toContain('setSecurityHeaders');
  });
});

// =====================================================
// 7. PWA Payment History - Invoice Support
// =====================================================

test.describe('PWA Payment History - Invoice Support', () => {
  test('PWA payment_history.php queries user invoices', () => {
    const content = readFile('views/pwa/payment_history.php');
    expect(content).toContain('pwa_invoices');
    expect(content).toContain('FROM invoices');
    expect(content).toContain('WHERE user_id');
  });

  test('PWA payment_history.php displays invoice cards', () => {
    const content = readFile('views/pwa/payment_history.php');
    expect(content).toContain('Invoices');
    expect(content).toContain('invoice_number');
    expect(content).toContain('invoice_date');
    expect(content).toContain('total_amount');
  });

  test('PWA payment_history.php links to invoice download', () => {
    const content = readFile('views/pwa/payment_history.php');
    expect(content).toContain('download_invoice.php?invoice_id=');
  });

  test('PWA payment_history.php shows View Invoice button for completed payments with invoices', () => {
    const content = readFile('views/pwa/payment_history.php');
    expect(content).toContain('View Invoice');
    expect(content).toContain('invoice_id');
  });

  test('PWA payment_history.php uses correct column names for payments query', () => {
    const content = readFile('views/pwa/payment_history.php');
    expect(content).toContain('payment_status as status');
    expect(content).toContain('notes as description');
    expect(content).toContain('invoice_id');
  });
});
