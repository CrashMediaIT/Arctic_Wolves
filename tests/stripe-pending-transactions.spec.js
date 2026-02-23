import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Tests for:
 * 1. Stripe pending balance transactions fetched in finance_overview.php
 * 2. Stripe pending transactions UI section in finance_overview.php
 * 3. Stripe pending funds data in API /v1/finance/overview
 */

const ROOT = path.resolve(__dirname, '..');

function readFile(relativePath) {
  return fs.readFileSync(path.join(ROOT, relativePath), 'utf-8');
}

// =====================================================
// 1. Stripe Pending Transactions - Backend (PHP)
// =====================================================

test.describe('Stripe Pending Transactions - Backend', () => {
  test('finance_overview.php initializes stripePendingTransactions array', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('$stripePendingTransactions = []');
  });

  test('finance_overview.php fetches pending balance transactions from Stripe API', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain("\\Stripe\\BalanceTransaction::all");
    expect(content).toContain("'status' => 'pending'");
  });

  test('finance_overview.php stores pending transactions in stripePendingTransactions', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('$stripePendingTransactions = $pendingTxns->data');
  });

  test('finance_overview.php fetches pending transactions only when Stripe is configured and lib loaded', () => {
    const content = readFile('views/finance_overview.php');
    // The BalanceTransaction::all call should be inside the stripeLibLoaded block
    const stripeSection = content.substring(
      content.indexOf('if ($stripeLibLoaded)'),
      content.indexOf('} catch (Exception $e)')
    );
    expect(stripeSection).toContain('BalanceTransaction::all');
    expect(stripeSection).toContain("'status' => 'pending'");
  });
});

// =====================================================
// 2. Stripe Pending Transactions - UI
// =====================================================

test.describe('Stripe Pending Transactions - UI Section', () => {
  test('finance_overview.php has Stripe Pending Transactions section', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('Stripe Pending Transactions');
    expect(content).toContain('stripe-pending-section');
  });

  test('finance_overview.php shows pending transactions only when configured and data exists', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain("$stripeConfigured && !empty($stripePendingTransactions)");
  });

  test('finance_overview.php displays transaction amount, type, and dates', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('$ptxnAmount');
    expect(content).toContain('$ptxnType');
    expect(content).toContain('$ptxnCreated');
    expect(content).toContain('$ptxnAvailable');
  });

  test('finance_overview.php displays transaction fee and net amount', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('$ptxnFee');
    expect(content).toContain('$ptxnNet');
    expect(content).toContain('pending-txn-fee');
    expect(content).toContain('pending-txn-net');
  });

  test('finance_overview.php shows pending transaction count badge', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('pending-count-badge');
    expect(content).toContain("count($stripePendingTransactions)");
  });

  test('finance_overview.php has link to Stripe Dashboard from pending section', () => {
    const content = readFile('views/finance_overview.php');
    // Count occurrences of Stripe Dashboard link - should appear in both balance card and pending section
    const matches = content.match(/dashboard\.stripe\.com\/balance\/overview/g);
    expect(matches).not.toBeNull();
    expect(matches.length).toBeGreaterThanOrEqual(2);
  });

  test('finance_overview.php has CSS styles for pending transactions', () => {
    const content = readFile('views/finance_overview.php');
    expect(content).toContain('.stripe-pending-section');
    expect(content).toContain('.pending-txn-item');
    expect(content).toContain('.pending-txn-icon');
    expect(content).toContain('.pending-txn-details');
    expect(content).toContain('.pending-txn-amounts');
    expect(content).toContain('.pending-txn-gross');
    expect(content).toContain('.pending-txn-net');
  });
});

// =====================================================
// 3. Stripe Pending Funds - API Endpoint
// =====================================================

test.describe('Stripe Pending Funds - API', () => {
  test('finance API overview endpoint includes stripe_pending_funds', () => {
    const content = readFile('api/v1/finance.php');
    expect(content).toContain("stripe_pending_funds");
  });

  test('finance API fetches Stripe balance for pending funds', () => {
    const content = readFile('api/v1/finance.php');
    expect(content).toContain("\\Stripe\\Balance::retrieve()");
    expect(content).toContain("\\Stripe\\BalanceTransaction::all");
  });

  test('finance API returns pending fund details with transaction list', () => {
    const content = readFile('api/v1/finance.php');
    expect(content).toContain("'total_amount'");
    expect(content).toContain("'transaction_count'");
    expect(content).toContain("'transactions'");
  });

  test('finance API maps pending transaction fields correctly', () => {
    const content = readFile('api/v1/finance.php');
    const mapSection = content.substring(
      content.indexOf('array_map(function ($txn)'),
      content.indexOf('}, $pendingTxns->data')
    );
    expect(mapSection).toContain("'amount'");
    expect(mapSection).toContain("'fee'");
    expect(mapSection).toContain("'net'");
    expect(mapSection).toContain("'type'");
    expect(mapSection).toContain("'description'");
    expect(mapSection).toContain("'created'");
    expect(mapSection).toContain("'available_on'");
  });

  test('finance API decrypts Stripe keys before use', () => {
    const content = readFile('api/v1/finance.php');
    // Should decrypt keys in the overview handler
    const overviewSection = content.substring(
      content.indexOf('function handleFinanceOverview'),
      content.indexOf('function handleListTransactions')
    );
    expect(overviewSection).toContain('decryptCredential');
    expect(overviewSection).toContain("stripe_secret_key");
  });

  test('finance API handles Stripe errors gracefully for pending funds', () => {
    const content = readFile('api/v1/finance.php');
    expect(content).toContain('Stripe pending funds error');
  });
});
