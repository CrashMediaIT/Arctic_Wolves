/**
 * Tests for Stallion Express Shipping Integration
 * 
 * Verifies:
 * 1. Database schema includes stallion_shipping_labels table
 * 2. Stallion Express API library exists with required functions
 * 3. System Tools has Stallion Express configuration tab
 * 4. POS Online Orders view exists with order fulfillment features
 * 5. Dashboard routing includes pos_online_orders page
 * 6. POS navigation includes Online Orders link
 * 7. Process settings handles Stallion Express save and test actions
 * 8. Ship Order modal includes Stallion Express carrier option
 * 9. Process checkout handles label creation and printing
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

test.describe('Stallion Express - Database Schema', () => {
  test('schema should include stallion_shipping_labels table', async () => {
    const schemaPath = path.join(__dirname, '..', 'database_schema.sql');
    const content = fs.readFileSync(schemaPath, 'utf-8');
    
    expect(content).toContain('CREATE TABLE IF NOT EXISTS `stallion_shipping_labels`');
    expect(content).toContain('stallion_shipment_id');
    expect(content).toContain('tracking_number');
    expect(content).toContain('label_url');
    expect(content).toContain('shipment_data');
    expect(content).toContain("'created'");
    expect(content).toContain("'printed'");
    expect(content).toContain("'shipped'");
  });
});

test.describe('Stallion Express - API Library', () => {
  test('stallion_express.php library should exist with required functions', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'stallion_express.php');
    const content = fs.readFileSync(libPath, 'utf-8');
    
    expect(content).toContain('function getStallionSettings(');
    expect(content).toContain('function isStallionConfigured(');
    expect(content).toContain('function stallionApiRequest(');
    expect(content).toContain('function testStallionConnection(');
    expect(content).toContain('function createStallionShipment(');
    expect(content).toContain('function getStallionTracking(');
    expect(content).toContain('function getStallionLabel(');
    expect(content).toContain('function getStallionRates(');
  });

  test('stallion library should describe Stallion as a fulfillment service, not a carrier', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'stallion_express.php');
    const content = fs.readFileSync(libPath, 'utf-8');
    
    expect(content).toContain('shipping fulfillment service');
    expect(content).toContain('aggregates rates from multiple carriers');
    expect(content).toContain('redocly.app/stallionexpress-v4');
  });

  test('stallion API should use Bearer token authentication', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'stallion_express.php');
    const content = fs.readFileSync(libPath, 'utf-8');
    
    expect(content).toContain('Authorization: Bearer');
    expect(content).toContain('CURLOPT_SSL_VERIFYPEER');
  });

  test('stallion shipment creation should build proper payload', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'stallion_express.php');
    const content = fs.readFileSync(libPath, 'utf-8');
    
    expect(content).toContain("'sender'");
    expect(content).toContain("'recipient'");
    expect(content).toContain("'package'");
    expect(content).toContain("'weight'");
    expect(content).toContain("'length'");
    expect(content).toContain("'width'");
    expect(content).toContain("'height'");
  });

  test('stallion getRates function should query rates endpoint', async () => {
    const libPath = path.join(__dirname, '..', 'lib', 'stallion_express.php');
    const content = fs.readFileSync(libPath, 'utf-8');
    
    expect(content).toContain("'/rates'");
    expect(content).toContain('best carrier and rate');
  });
});

test.describe('Stallion Express - System Tools Configuration Tab', () => {
  test('admin_system_tools.php should have Stallion Express tab', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain("tab=stallion");
    expect(content).toContain('Stallion Express');
    expect(content).toContain('fa-shipping-fast');
    expect(content).toContain("id=\"stallion-tab\"");
  });

  test('Stallion tab should have API configuration fields', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('name="stallion_enabled"');
    expect(content).toContain('name="stallion_api_url"');
    expect(content).toContain('name="stallion_api_key"');
    expect(content).toContain('name="stallion_api_secret"');
  });

  test('Stallion tab should have sender address fields', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('name="stallion_sender_name"');
    expect(content).toContain('name="stallion_sender_company"');
    expect(content).toContain('name="stallion_sender_address"');
    expect(content).toContain('name="stallion_sender_city"');
    expect(content).toContain('name="stallion_sender_province"');
    expect(content).toContain('name="stallion_sender_postal_code"');
    expect(content).toContain('name="stallion_sender_phone"');
  });

  test('Stallion tab should have default package dimension fields', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('name="stallion_default_weight"');
    expect(content).toContain('name="stallion_default_length"');
    expect(content).toContain('name="stallion_default_width"');
    expect(content).toContain('name="stallion_default_height"');
  });

  test('Stallion tab should have test connection button', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('id="test-stallion"');
    expect(content).toContain('id="stallion-status"');
    expect(content).toContain("'test_stallion'");
  });

  test('Stallion tab should have save button with correct action', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('name="action" value="update_stallion"');
    expect(content).toContain('Save Stallion Express Settings');
  });

  test('Stallion tab should describe it as a fulfillment service and reference API docs', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'admin_system_tools.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('shipping fulfillment service');
    expect(content).toContain('aggregates rates from multiple carriers');
    expect(content).toContain('stallionexpress.redocly.app/stallionexpress-v4');
    expect(content).toContain('Pickup at Session');
  });
});

test.describe('Stallion Express - Process Settings Handlers', () => {
  test('process_settings.php should handle update_stallion action', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("case 'update_stallion':");
    expect(content).toContain("stallion_enabled");
    expect(content).toContain("stallion_api_url");
    expect(content).toContain("stallion_api_key");
    expect(content).toContain("stallion_sender_name");
    expect(content).toContain("tab=stallion&success=1");
  });

  test('process_settings.php should handle test_stallion action', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("case 'test_stallion':");
    expect(content).toContain("stallion_express.php");
    expect(content).toContain("testStallionConnection");
  });

  test('test_stallion should be in json_actions list', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'test_stallion'");
  });

  test('update_stallion should validate URL format', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("FILTER_VALIDATE_URL");
    expect(content).toContain("Invalid Stallion Express API URL format");
  });

  test('update_stallion should use Auditor logging', async () => {
    const filePath = path.join(__dirname, '..', 'process_settings.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Find the update_stallion section and check for Auditor
    const stallionIdx = content.indexOf("case 'update_stallion':");
    const nextCaseIdx = content.indexOf("case 'test_stallion':", stallionIdx);
    const section = content.substring(stallionIdx, nextCaseIdx);
    
    expect(section).toContain("Auditor::log");
    expect(section).toContain("update_stallion");
  });
});

test.describe('Stallion Express - POS Online Orders View', () => {
  test('pos_online_orders.php should exist', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    expect(fs.existsSync(viewPath)).toBe(true);
  });

  test('POS Online Orders should have access control', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('$canAccessPOS');
    expect(content).toContain('checkPOSIPAccess');
    expect(content).toContain('Access Denied');
  });

  test('POS Online Orders should display order cards with actions', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('order-card');
    expect(content).toContain('order_number');
    expect(content).toContain('viewOrderDetails');
    expect(content).toContain('openShipOrder');
    expect(content).toContain('openCreateLabel');
    expect(content).toContain('printLabel');
  });

  test('POS Online Orders should have create label modal', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('create-label-modal');
    expect(content).toContain('Create Shipping Label');
    expect(content).toContain('submitCreateLabel');
    expect(content).toContain('create_stallion_label');
  });

  test('POS Online Orders should have ship order modal', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('ship-order-modal');
    expect(content).toContain('submitShipOrder');
    expect(content).toContain('ship_order');
  });

  test('POS Online Orders should have status filters', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('Ready to Ship');
    expect(content).toContain('Processing');
    expect(content).toContain('Shipped');
    expect(content).toContain('Delivered');
  });

  test('POS Online Orders should have order stats', async () => {
    const viewPath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(viewPath, 'utf-8');
    
    expect(content).toContain('ready_to_ship');
    expect(content).toContain('processing_count');
    expect(content).toContain('shipped_count');
    expect(content).toContain('total_orders');
  });
});

test.describe('Stallion Express - Dashboard Routing & Navigation', () => {
  test('dashboard.php should route pos_online_orders to correct view', async () => {
    const filePath = path.join(__dirname, '..', 'dashboard.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'pos_online_orders'");
    expect(content).toContain('views/pos_online_orders.php');
  });

  test('dashboard.php should have Online Orders nav link in POS section', async () => {
    const filePath = path.join(__dirname, '..', 'dashboard.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('page=pos_online_orders');
    expect(content).toContain('Online Orders');
    expect(content).toContain('fa-shipping-fast');
  });
});

test.describe('Stallion Express - Process Checkout Label Handlers', () => {
  test('process_shop_checkout.php should handle create_stallion_label action', async () => {
    const filePath = path.join(__dirname, '..', 'process_shop_checkout.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'create_stallion_label'");
    expect(content).toContain('stallion_express.php');
    expect(content).toContain('createStallionShipment');
    expect(content).toContain('getStallionSettings');
  });

  test('process_shop_checkout.php should handle mark_label_printed action', async () => {
    const filePath = path.join(__dirname, '..', 'process_shop_checkout.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("'mark_label_printed'");
    expect(content).toContain('stallion_shipping_labels');
    expect(content).toContain("'printed'");
  });

  test('create_stallion_label handler should have CSRF protection', async () => {
    const filePath = path.join(__dirname, '..', 'process_shop_checkout.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Find the create_stallion_label section
    const idx = content.indexOf("'create_stallion_label'");
    const section = content.substring(idx, idx + 500);
    
    expect(section).toContain('csrf_token');
    expect(section).toContain('hash_equals');
  });

  test('create_stallion_label handler should check access control', async () => {
    const filePath = path.join(__dirname, '..', 'process_shop_checkout.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Find the create_stallion_label section
    const idx = content.indexOf("'create_stallion_label'");
    const section = content.substring(idx, idx + 800);
    
    expect(section).toContain('user_role');
    expect(section).toContain('Access denied');
  });
});

test.describe('Stallion Express - Shop Orders & Fulfillment Options', () => {
  test('ship order modal should include Stallion Express as multi-carrier option', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'shop_orders.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('Stallion Express (Multi-Carrier)');
    expect(content).toContain('Shipping Carrier / Fulfillment');
  });

  test('ship order modal should include Pickup at Session option', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'shop_orders.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('Pickup at Session');
  });

  test('POS ship order modal should include Pickup at Session option', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain('Pickup at Session');
    expect(content).toContain('Stallion Express (Multi-Carrier)');
    expect(content).toContain('Shipping Carrier / Fulfillment');
  });

  test('POS online orders should use intval for defense in depth on IDs', async () => {
    const filePath = path.join(__dirname, '..', 'views', 'pos_online_orders.php');
    const content = fs.readFileSync(filePath, 'utf-8');
    
    expect(content).toContain("intval($order['id'])");
    expect(content).toContain("intval($order['label_id'])");
  });
});
