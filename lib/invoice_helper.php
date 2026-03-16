<?php
/**
 * Invoice Helper - Creates invoices and payment records for purchases
 * 
 * Used by payment_success.php and shop_success.php to automatically
 * generate invoices when purchases are completed.
 */

/**
 * Create an invoice and corresponding payment record for a completed purchase.
 *
 * @param PDO    $pdo            Database connection
 * @param int    $user_id        The user/customer ID
 * @param array  $items          Array of line items, each with 'description', 'quantity', 'unit_price'
 * @param float  $subtotal       Subtotal before tax
 * @param float  $tax_amount     Tax amount
 * @param float  $total          Total amount including tax
 * @param string $payment_method Payment method (e.g., 'stripe', 'card', 'cash')
 * @param string $transaction_id External transaction/session ID (e.g., Stripe session ID)
 * @param string $notes          Optional notes for the invoice
 * @return int|false             The invoice ID on success, false on failure
 */
function createPurchaseInvoice($pdo, $user_id, array $items, $subtotal, $tax_amount, $total, $payment_method = 'stripe', $transaction_id = '', $notes = '') {
    try {
        // Generate unique invoice number with larger range to reduce collision risk
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness
        $check_stmt = $pdo->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
        $check_stmt->execute([$invoice_number]);
        $attempts = 0;
        while ($check_stmt->fetch() && $attempts < 20) {
            $invoice_number = 'INV-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $check_stmt->execute([$invoice_number]);
            $attempts++;
        }
        
        // If still not unique after max attempts, abort
        $check_stmt->execute([$invoice_number]);
        if ($check_stmt->fetch()) {
            if (class_exists('ErrorLogger')) {
                ErrorLogger::error("Invoice creation error: Could not generate unique invoice number after $attempts attempts");
            } else {
                error_log("Invoice creation error: Could not generate unique invoice number after $attempts attempts");
            }
            return false;
        }
        
        $today = date('Y-m-d');
        
        // Create invoice (already paid)
        $inv_stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_number, user_id, invoice_date, due_date, subtotal, tax_amount, total_amount, status, paid_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)
        ");
        $inv_stmt->execute([
            $invoice_number,
            $user_id,
            $today,
            $today,
            $subtotal,
            $tax_amount,
            $total,
            $today,
            $notes
        ]);
        $invoice_id = $pdo->lastInsertId();
        
        // Insert line items
        if (!empty($items)) {
            $item_stmt = $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 1);
                $price = floatval($item['unit_price'] ?? 0);
                $item_total = $qty * $price;
                $item_stmt->execute([
                    $invoice_id,
                    $item['description'] ?? 'Purchase',
                    $qty,
                    $price,
                    $item_total
                ]);
            }
        }
        
        // Record payment
        $txn_id = $transaction_id ?: ('TXN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8)));
        $pay_stmt = $pdo->prepare("
            INSERT INTO payments (user_id, invoice_id, amount, payment_method, payment_date, transaction_id, notes)
            VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        $pay_stmt->execute([
            $user_id,
            $invoice_id,
            $total,
            $payment_method,
            $txn_id,
            $notes
        ]);
        
        return $invoice_id;
    } catch (PDOException $e) {
        if (class_exists('ErrorLogger')) {
            ErrorLogger::error("Invoice creation error: " . $e->getMessage());
        } else {
            error_log("Invoice creation error: " . $e->getMessage());
        }
        return false;
    }
}
