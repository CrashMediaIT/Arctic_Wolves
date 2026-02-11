/**
 * Tests for Inventory Tracking System
 * 
 * Verifies:
 * 1. Database schema includes stock movement and audit tables
 * 2. Record Shipment modal exists with proper fields
 * 3. Stock Audit modal exists with system vs actual comparison
 * 4. Stock History modal exists with movements and audit tabs
 * 5. Backend handlers for shipment, audit, and history endpoints
 * 6. Edit modals have consistent fields with create modals
 * 7. Ship Order modal exists in shop orders view
 * 8. Order details include shipping tracking information
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('Inventory Tracking System - Database Schema', () => {
  test('schema should include merchandise_stock_movements table', async () => {
    const schemaPath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(schemaPath, 'utf-8');
    
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `merchandise_stock_movements`');
    expect(content).toContain('movement_type');
    expect(content).toContain('quantity_before');
    expect(content).toContain('quantity_change');
    expect(content).toContain('quantity_after');
    expect(content).toContain("'shipment'");
    expect(content).toContain("'audit_adjustment'");
    expect(content).toContain("'sale'");
  });

  test('schema should include merchandise_stock_audits table', async () => {
    const schemaPath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(schemaPath, 'utf-8');
    
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `merchandise_stock_audits`');
    expect(content).toContain('audit_date');
    expect(content).toContain("'completed'");
  });

  test('schema should include merchandise_stock_audit_items table', async () => {
    const schemaPath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(schemaPath, 'utf-8');
    
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `merchandise_stock_audit_items`');
    expect(content).toContain('system_quantity');
    expect(content).toContain('actual_quantity');
    expect(content).toContain('discrepancy');
  });

  test('schema should include shipping tracking fields for shop_orders', async () => {
    const schemaPath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(schemaPath, 'utf-8');
    
    expect(content).toContain('shipping_carrier');
    expect(content).toContain('tracking_number');
    expect(content).toContain('tracking_url');
    expect(content).toContain('shipped_at');
    expect(content).toContain('delivered_at');
    expect(content).toContain('fulfillment_notes');
  });
});

test.describe('Inventory Tracking - Merchandise Products View', () => {
  test('should have Record Shipment modal with proper fields', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('shipment-modal');
    expect(content).toContain('Record Shipment');
    expect(content).toContain('shipment-product-name');
    expect(content).toContain('name="reference"');
    expect(content).toContain('shipment_quantities[]');
    expect(content).toContain('record_shipment');
  });

  test('should have Stock Audit modal with system vs actual comparison', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('audit-modal');
    expect(content).toContain('Stock Audit');
    expect(content).toContain('audit-product-name');
    expect(content).toContain('actual_quantities[]');
    expect(content).toContain('stock_audit');
    expect(content).toContain('System Qty');
    expect(content).toContain('Actual Count');
    expect(content).toContain('Difference');
  });

  test('should have Stock History modal with movements and audit tabs', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('stock-history-modal');
    expect(content).toContain('Stock History');
    expect(content).toContain('Stock Movements');
    expect(content).toContain('Audit History');
    expect(content).toContain('tab-movements');
    expect(content).toContain('tab-audits');
  });

  test('should have action buttons for shipment and audit on product cards', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('recordShipment(');
    expect(content).toContain('stockAudit(');
    expect(content).toContain('fa-truck');
    expect(content).toContain('fa-clipboard-check');
  });

  test('should have JavaScript functions for shipment and audit workflows', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'merchandise_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('function recordShipment(');
    expect(content).toContain('function stockAudit(');
    expect(content).toContain('function updateAuditSummary(');
    expect(content).toContain('function openStockHistory(');
    expect(content).toContain('function switchHistoryTab(');
    expect(content).toContain('function loadMovements(');
    expect(content).toContain('function loadAudits(');
  });
});

test.describe('Inventory Tracking - Backend Handlers', () => {
  test('process_merchandise_products.php should handle record_shipment action', async () => {
    const processorPath = path.join(__dirname, '..', 'process_merchandise_products.php');
    const content = fs.readFileSync(processorPath, 'utf-8');
    
    expect(content).toContain("case 'record_shipment':");
    expect(content).toContain('shipment_quantities');
    expect(content).toContain("'shipment'");
    expect(content).toContain('quantity = quantity + ?');
    expect(content).toContain('merchandise_stock_movements');
  });

  test('process_merchandise_products.php should handle stock_audit action', async () => {
    const processorPath = path.join(__dirname, '..', 'process_merchandise_products.php');
    const content = fs.readFileSync(processorPath, 'utf-8');
    
    expect(content).toContain("case 'stock_audit':");
    expect(content).toContain('actual_quantities');
    expect(content).toContain('merchandise_stock_audits');
    expect(content).toContain('merchandise_stock_audit_items');
    expect(content).toContain("'audit_adjustment'");
    expect(content).toContain('discrepancy');
  });

  test('process_merchandise_products.php should have get_stock_movements endpoint', async () => {
    const processorPath = path.join(__dirname, '..', 'process_merchandise_products.php');
    const content = fs.readFileSync(processorPath, 'utf-8');
    
    expect(content).toContain("$action === 'get_stock_movements'");
    expect(content).toContain('merchandise_stock_movements');
    expect(content).toContain("'movements'");
  });

  test('process_merchandise_products.php should have get_audit_history endpoint', async () => {
    const processorPath = path.join(__dirname, '..', 'process_merchandise_products.php');
    const content = fs.readFileSync(processorPath, 'utf-8');
    
    expect(content).toContain("$action === 'get_audit_history'");
    expect(content).toContain('merchandise_stock_audits');
    expect(content).toContain("'audits'");
  });
});

test.describe('Edit Modal Consistency - All Modals Match Create Modals', () => {
  test('accounting_products.php edit merch product should have sizes/stock section', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'accounting_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Find the merch-product edit modal section
    expect(content).toContain("type === 'merch-product'");
    
    // Should have size/stock management in edit modal
    expect(content).toContain('edit-merch-sizes-container');
    expect(content).toContain('Size & Stock Options');
    expect(content).toContain('addEditMerchSizeRow');
    expect(content).toContain('name="size_ids[]"');
    
    // Should also have track_inventory as a select (matching create modal)
    expect(content).toContain('Track Inventory');
  });

  test('accounting_products.php edit session should have session_type, show_on_landing, is_template', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'accounting_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // The edit session section (dynamically built JS)
    const editSessionIdx = content.indexOf("if (type === 'session')");
    expect(editSessionIdx).toBeGreaterThan(-1);
    
    const nextTypeIdx = content.indexOf("} else if (type === 'package')", editSessionIdx);
    const editSection = content.substring(editSessionIdx, nextTypeIdx);
    
    // Check for session_type field
    expect(editSection).toContain('name="session_type"');
    expect(editSection).toContain('on_ice');
    expect(editSection).toContain('off_ice');
    
    // Check for show_on_landing checkbox
    expect(editSection).toContain('show_on_landing');
    
    // Check for is_template checkbox
    expect(editSection).toContain('is_template');
  });

  test('accounting_products.php edit discount should have start_date and end_date', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'accounting_products.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    const editDiscountIdx = content.indexOf("} else if (type === 'discount')");
    expect(editDiscountIdx).toBeGreaterThan(-1);
    
    const nextTypeIdx = content.indexOf("} else if (type === 'merch-product')", editDiscountIdx);
    const editSection = content.substring(editDiscountIdx, nextTypeIdx);
    
    expect(editSection).toContain('start_date');
    expect(editSection).toContain('end_date');
  });

  test('admin_cron_jobs.php edit modal should have command field', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_cron_jobs.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Find edit modal
    const editModalIdx = content.indexOf('id="edit-cron-job-modal"');
    expect(editModalIdx).toBeGreaterThan(-1);
    
    const editModalEnd = content.indexOf('</form>', editModalIdx);
    const editModal = content.substring(editModalIdx, editModalEnd);
    
    // Should have command field
    expect(editModal).toContain('name="command"');
    expect(editModal).toContain('edit-cron-job-command');
    
    // Should use is_active not status
    expect(editModal).toContain('name="is_active"');
    
    // Should have custom schedule support
    expect(editModal).toContain('edit-custom-schedule-group');
  });

  test('admin_cron_jobs.php edit button should pass command and status data', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_cron_jobs.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    // Edit button should include data-command and data-status attributes
    expect(content).toContain('data-command=');
    expect(content).toContain('data-status=');
  });
});

test.describe('Shop Orders - Shipping Tracking', () => {
  test('shop orders view should have Ship Order modal', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'shop_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('ship-order-modal');
    expect(content).toContain('Ship Order');
    expect(content).toContain('shipping_carrier');
    expect(content).toContain('tracking_number');
    expect(content).toContain('tracking_url');
    expect(content).toContain('fulfillment_notes');
  });

  test('shop orders should have ship button for paid/processing orders', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'shop_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('openShipOrder(');
    expect(content).toContain('fa-shipping-fast');
  });

  test('shop orders should have submitShipOrder function', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'shop_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('function submitShipOrder(');
    expect(content).toContain('ship_order');
  });

  test('order details should display shipping tracking info', async () => {
    const detailsPath = path.join(__dirname, '..', 'ajax_get_order_details.php');
    const content = fs.readFileSync(detailsPath, 'utf-8');
    
    expect(content).toContain('Shipping & Tracking');
    expect(content).toContain('shipping_carrier');
    expect(content).toContain('tracking_number');
    expect(content).toContain('tracking_url');
    expect(content).toContain('shipped_at');
    expect(content).toContain('delivered_at');
    expect(content).toContain('fulfillment_notes');
  });

  test('ship order handler should record stock movements', async () => {
    const processorPath = path.join(__dirname, '..', 'process_shop_checkout.php');
    const content = fs.readFileSync(processorPath, 'utf-8');
    
    expect(content).toContain("$_GET['action'] === 'ship_order'");
    expect(content).toContain('merchandise_stock_movements');
    expect(content).toContain("'sale'");
    expect(content).toContain('shipping_carrier');
    expect(content).toContain('tracking_number');
    expect(content).toContain("status = 'shipped'");
  });
});
