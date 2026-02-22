import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. User data encryption migration during setup
 * 2. Navigation rename from "Online Orders" to "Inventory & Orders"
 * 3. Inventory management view with in-store/warehouse tabs
 * 4. Stock location column in schema
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. User Data Encryption Migration
// =====================================================

test.describe('User Data Encryption Migration', () => {
  test('security.php defines ensureUserDataEncrypted function', () => {
    const content = readFile('security.php');
    expect(content).toContain('function ensureUserDataEncrypted($pdo)');
  });

  test('ensureUserDataEncrypted checks all USER_PII_FIELDS', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function ensureUserDataEncrypted');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 10);
    const fnBody = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 4000);
    expect(fnBody).toContain('USER_PII_FIELDS');
    expect(fnBody).toContain('FROM users');
  });

  test('ensureUserDataEncrypted encrypts plaintext user fields', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function ensureUserDataEncrypted');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 10);
    const fnBody = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 4000);
    expect(fnBody).toContain('FieldEncryption::encrypt');
    expect(fnBody).toContain('isFieldEncrypted');
    expect(fnBody).toContain('UPDATE users SET');
  });

  test('ensureUserDataEncrypted returns migration summary', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function ensureUserDataEncrypted');
    const fnEnd = content.indexOf('\nfunction ', fnStart + 10);
    const fnBody = content.substring(fnStart, fnEnd > -1 ? fnEnd : fnStart + 4000);
    expect(fnBody).toContain("'migrated_users'");
    expect(fnBody).toContain("'already_encrypted'");
    expect(fnBody).toContain("'fields_checked'");
  });

  test('security.php defines isFieldEncrypted function', () => {
    const content = readFile('security.php');
    expect(content).toContain('function isFieldEncrypted($value)');
  });

  test('isFieldEncrypted uses FieldEncryption::decrypt to verify', () => {
    const content = readFile('security.php');
    const fnStart = content.indexOf('function isFieldEncrypted');
    const fnEnd = content.indexOf('\n}', fnStart) + 2;
    const fnBody = content.substring(fnStart, fnEnd);
    expect(fnBody).toContain('FieldEncryption::decrypt');
    expect(fnBody).toContain('FieldEncryption::isConfigured');
  });
});

// =====================================================
// 2. Setup.php Calls User Data Migration
// =====================================================

test.describe('Setup Step 5 User Data Migration', () => {
  test('setup.php step 5 calls ensureUserDataEncrypted', () => {
    const content = readFile('setup.php');
    const phpStep5Start = content.indexOf("elseif ($step == 5)");
    const phpStep5End = content.indexOf("} catch (PDOException", phpStep5Start);
    const step5Section = content.substring(phpStep5Start, phpStep5End);
    expect(step5Section).toContain('ensureUserDataEncrypted');
  });

  test('setup.php logs user data migration results', () => {
    const content = readFile('setup.php');
    const phpStep5Start = content.indexOf("elseif ($step == 5)");
    const phpStep5End = content.indexOf("} catch (PDOException", phpStep5Start);
    const step5Section = content.substring(phpStep5Start, phpStep5End);
    expect(step5Section).toContain("migrated_users");
    expect(step5Section).toContain("Encrypted PII");
  });

  test('user data migration runs after credential migration', () => {
    const content = readFile('setup.php');
    const phpStep5Start = content.indexOf("elseif ($step == 5)");
    const phpStep5End = content.indexOf("} catch (PDOException", phpStep5Start);
    const step5Section = content.substring(phpStep5Start, phpStep5End);
    const credIdx = step5Section.indexOf('ensureCredentialsEncrypted');
    const userIdx = step5Section.indexOf('ensureUserDataEncrypted');
    expect(credIdx).toBeGreaterThan(-1);
    expect(userIdx).toBeGreaterThan(-1);
    expect(credIdx).toBeLessThan(userIdx);
  });
});

// =====================================================
// 3. Navigation Update
// =====================================================

test.describe('Navigation Update', () => {
  test('dashboard.php has Inventory & Orders nav link', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain('Inventory & Orders');
    expect(content).toContain('inventory_management');
  });

  test('dashboard.php replaced Online Orders with Inventory & Orders', () => {
    const content = readFile('dashboard.php');
    // The old text should not exist in the sidebar nav anymore
    expect(content).not.toContain('Online Orders');
  });

  test('dashboard.php routes inventory_management to view', () => {
    const content = readFile('dashboard.php');
    expect(content).toContain("'inventory_management'    => 'views/inventory_management.php'");
  });
});

// =====================================================
// 4. Stock Location Column
// =====================================================

test.describe('Stock Location Schema', () => {
  test('database_schema.sql has stock_location in merchandise_product_sizes', () => {
    const content = readFile('database_schema.sql');
    const tableSection = content.substring(
      content.indexOf('CREATE TABLE IF NOT EXISTS `merchandise_product_sizes`'),
      content.indexOf('ENGINE=InnoDB', content.indexOf('merchandise_product_sizes')) + 50
    );
    expect(tableSection).toContain("stock_location");
    expect(tableSection).toContain("ENUM('in_store', 'warehouse')");
    expect(tableSection).toContain("DEFAULT 'in_store'");
  });

  test('migration SQL exists for stock_location', () => {
    const content = readFile('deployment/sql/add_stock_location.sql');
    expect(content).toContain('stock_location');
    expect(content).toContain("ENUM('in_store', 'warehouse')");
    expect(content).toContain('ALTER TABLE');
  });

  test('unique key includes stock_location', () => {
    const content = readFile('database_schema.sql');
    const tableSection = content.substring(
      content.indexOf('CREATE TABLE IF NOT EXISTS `merchandise_product_sizes`'),
      content.indexOf('ENGINE=InnoDB', content.indexOf('merchandise_product_sizes')) + 50
    );
    expect(tableSection).toContain("`product_id`, `size`, `stock_location`");
  });
});

// =====================================================
// 5. Inventory Management View
// =====================================================

test.describe('Inventory Management View', () => {
  test('inventory_management.php exists', () => {
    expect(fs.existsSync(path.join(ROOT, 'views/inventory_management.php'))).toBe(true);
  });

  test('view has access control checks', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain('$canAccessPOS');
    expect(content).toContain('checkPOSIPAccess');
  });

  test('view has 4 tabs: in_store, warehouse, incoming, outgoing', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain("tab=in_store");
    expect(content).toContain("tab=warehouse");
    expect(content).toContain("tab=incoming");
    expect(content).toContain("tab=outgoing");
  });

  test('view queries in-store inventory with stock_location filter', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain("stock_location");
    expect(content).toContain("in_store");
  });

  test('view queries warehouse inventory', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain("warehouse");
  });

  test('view shows incoming shipments from stock_movements', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain('merchandise_stock_movements');
    expect(content).toContain('shipment');
  });

  test('view shows outgoing orders from shop_orders', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain('shop_orders');
    expect(content).toContain('processing');
    expect(content).toContain('shipped');
  });

  test('view decrypts customer data', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain('decryptUserRows');
  });

  test('view handles missing stock_location column gracefully', () => {
    const content = readFile('views/inventory_management.php');
    expect(content).toContain("SHOW COLUMNS FROM merchandise_product_sizes LIKE 'stock_location'");
    expect(content).toContain('hasStockLocation');
  });
});
