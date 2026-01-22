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
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount']);
            $expense_date = $_POST['expense_date'];
            
            // Handle file upload
            $receipt_url = null;
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $receipt_url = 'uploads/receipts/' . uniqid('receipt_') . '.' . $file_ext;
                    move_uploaded_file($_FILES['receipt_file']['tmp_name'], $receipt_url);
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO expenses (user_id, expense_date, amount, category, description, receipt_url, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $user_id, $expense_date, $amount, $category, $description, $receipt_url
            ]);
            
            header("Location: dashboard.php?page=accounting_expenses&status=success");
            exit();
            
        case 'update':
            $expense_id = intval($_POST['expense_id']);
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount']);
            $expense_date = $_POST['expense_date'];
            
            // Handle file upload for update
            $receipt_url = null;
            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $receipt_url = 'uploads/receipts/' . uniqid('receipt_') . '.' . $file_ext;
                    move_uploaded_file($_FILES['receipt_file']['tmp_name'], $receipt_url);
                    
                    // Update with new file
                    $stmt = $pdo->prepare("
                        UPDATE expenses 
                        SET category = ?, description = ?, amount = ?, expense_date = ?, receipt_url = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $category, $description, $amount, $expense_date, $receipt_url, $expense_id
                    ]);
                }
            } else {
                // Update without changing file
                $stmt = $pdo->prepare("
                    UPDATE expenses 
                    SET category = ?, description = ?, amount = ?, expense_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $category, $description, $amount, $expense_date, $expense_id
                ]);
            }
            
            header("Location: dashboard.php?page=accounting_expenses&status=success");
            exit();
            
        case 'delete':
            $expense_id = intval($_POST['expense_id']);
            
            // Delete receipt file if exists
            $file_stmt = $pdo->prepare("SELECT receipt_url FROM expenses WHERE id = ?");
            $file_stmt->execute([$expense_id]);
            $receipt = $file_stmt->fetchColumn();
            
            if ($receipt && file_exists($receipt)) {
                unlink($receipt);
            }
            
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$expense_id]);
            
            header("Location: dashboard.php?page=accounting_expenses&status=success");
            exit();
            
        case 'create_category':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO expense_categories (name, description, is_active)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $is_active]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
            
        case 'update_category':
            $category_id = intval($_POST['category_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE expense_categories 
                SET name = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $is_active, $category_id]);
            
            header("Location: dashboard.php?page=expense_categories&status=success");
            exit();
            
        case 'delete_category':
            $category_id = intval($_POST['category_id']);
            
            // Check if category is in use by checking expenses with this category name
            $check = $pdo->prepare("SELECT COUNT(*) FROM expenses e JOIN expense_categories ec ON e.category = ec.name WHERE ec.id = ?");
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
    $redirect_page = 'accounting_expenses';
    if ($action === 'create_category' || $action === 'update_category' || $action === 'delete_category') {
        $redirect_page = 'expense_categories';
    }
    header("Location: dashboard.php?page=$redirect_page&status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?>
