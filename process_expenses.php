<?php
// process_expenses.php - Handle expense operations
session_start();
require 'db_config.php';
require 'security.php';

setSecurityHeaders();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied.');
}

checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'create':
            $vendor_name = trim($_POST['vendor_name']);
            $category_id = intval($_POST['category_id']);
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount']);
            $tax_amount = floatval($_POST['tax_amount'] ?? 0);
            $total_amount = floatval($_POST['total_amount']);
            $expense_date = $_POST['expense_date'];
            $payment_method = trim($_POST['payment_method'] ?? '');
            $reference_number = trim($_POST['reference_number'] ?? '');
            
            // Handle file upload
            $receipt_file = null;
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $receipt_file = uniqid('receipt_') . '.' . $file_ext;
                    move_uploaded_file($_FILES['receipt_file']['tmp_name'], $upload_dir . $receipt_file);
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO expenses (category_id, vendor_name, description, amount, tax_amount, 
                                     total_amount, expense_date, receipt_file, payment_method, 
                                     reference_number, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $category_id, $vendor_name, $description, $amount, $tax_amount,
                $total_amount, $expense_date, $receipt_file, $payment_method,
                $reference_number, $user_id
            ]);
            
            header("Location: dashboard.php?page=accounts_payable&status=success");
            exit();
            
        case 'update':
            $expense_id = intval($_POST['expense_id']);
            $vendor_name = trim($_POST['vendor_name']);
            $category_id = intval($_POST['category_id']);
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount']);
            $tax_amount = floatval($_POST['tax_amount'] ?? 0);
            $total_amount = floatval($_POST['total_amount']);
            $expense_date = $_POST['expense_date'];
            $payment_method = trim($_POST['payment_method'] ?? '');
            $reference_number = trim($_POST['reference_number'] ?? '');
            
            // Handle file upload for update
            $receipt_file = null;
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/receipts/';
                $file_ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $receipt_file = uniqid('receipt_') . '.' . $file_ext;
                    move_uploaded_file($_FILES['receipt_file']['tmp_name'], $upload_dir . $receipt_file);
                    
                    // Update with new file
                    $stmt = $pdo->prepare("
                        UPDATE expenses 
                        SET category_id = ?, vendor_name = ?, description = ?, amount = ?, 
                            tax_amount = ?, total_amount = ?, expense_date = ?, receipt_file = ?, 
                            payment_method = ?, reference_number = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $category_id, $vendor_name, $description, $amount, $tax_amount,
                        $total_amount, $expense_date, $receipt_file, $payment_method,
                        $reference_number, $expense_id
                    ]);
                }
            } else {
                // Update without changing file
                $stmt = $pdo->prepare("
                    UPDATE expenses 
                    SET category_id = ?, vendor_name = ?, description = ?, amount = ?, 
                        tax_amount = ?, total_amount = ?, expense_date = ?, 
                        payment_method = ?, reference_number = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $category_id, $vendor_name, $description, $amount, $tax_amount,
                    $total_amount, $expense_date, $payment_method,
                    $reference_number, $expense_id
                ]);
            }
            
            header("Location: dashboard.php?page=accounts_payable&status=success");
            exit();
            
        case 'delete':
            $expense_id = intval($_POST['expense_id']);
            
            // Delete receipt file if exists
            $file_stmt = $pdo->prepare("SELECT receipt_file FROM expenses WHERE id = ?");
            $file_stmt->execute([$expense_id]);
            $receipt = $file_stmt->fetchColumn();
            
            if ($receipt && file_exists('uploads/receipts/' . $receipt)) {
                unlink('uploads/receipts/' . $receipt);
            }
            
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$expense_id]);
            
            header("Location: dashboard.php?page=accounts_payable&status=success");
            exit();
            
        case 'create_category':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO expense_categories (name, description, display_order, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $display_order, $is_active]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
            
        case 'update_category':
            $category_id = intval($_POST['category_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE expense_categories 
                SET name = ?, description = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $display_order, $is_active, $category_id]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
            
        case 'delete_category':
            $category_id = intval($_POST['category_id']);
            
            // Check if category is in use
            $check = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category_id = ?");
            $check->execute([$category_id]);
            
            if ($check->fetchColumn() > 0) {
                header("Location: dashboard.php?page=expense_categories&status=error&message=Category+is+in+use");
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Expense processing error: " . $e->getMessage());
    $redirect_page = isset($_POST['category_id']) ? 'expense_categories' : 'accounts_payable';
    header("Location: dashboard.php?page=$redirect_page&status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?>
