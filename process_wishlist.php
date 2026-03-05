<?php
/**
 * Process Wishlist
 * Handles CRUD operations and reordering for admin wishlist items
 */
session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/error_logger.php';

setSecurityHeaders();

// Admin-only access
$actualRole = $_SESSION['persona_original_role'] ?? ($_SESSION['user_role'] ?? '');
if ($actualRole !== 'admin') {
    http_response_code(403);
    die('Access denied.');
}

$user_id = $_SESSION['user_id'] ?? 0;

// JSON API actions (reorder)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'reorder') {
        $order = $input['order'] ?? [];
        if (!is_array($order)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid order data']);
            exit;
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE wishlist_items SET sort_order = ?, updated_at = NOW() WHERE id = ?");
            foreach ($order as $index => $id) {
                $stmt->execute([(int)$index, (int)$id]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to reorder items']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// Form POST actions
checkCsrfToken();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create_item':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = $_POST['price'] ?? null;
            $link = trim($_POST['link'] ?? '');

            if (empty($name)) {
                header("Location: dashboard.php?page=admin_wishlist&status=error&message=" . urlencode('Item name is required'));
                exit();
            }

            if ($price !== null && $price !== '') {
                $price = floatval($price);
            } else {
                $price = null;
            }

            // Get next sort_order
            $maxOrder = $pdo->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM wishlist_items")->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO wishlist_items (name, description, price, link, sort_order, created_by)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $description ?: null,
                $price,
                $link ?: null,
                (int)$maxOrder,
                $user_id
            ]);

            header("Location: dashboard.php?page=admin_wishlist&status=success&message=" . urlencode('Item added successfully'));
            exit();

        case 'update_item':
            $item_id = intval($_POST['item_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = $_POST['price'] ?? null;
            $link = trim($_POST['link'] ?? '');

            if ($item_id <= 0 || empty($name)) {
                header("Location: dashboard.php?page=admin_wishlist&status=error&message=" . urlencode('Invalid data'));
                exit();
            }

            if ($price !== null && $price !== '') {
                $price = floatval($price);
            } else {
                $price = null;
            }

            $stmt = $pdo->prepare("UPDATE wishlist_items SET name = ?, description = ?, price = ?, link = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $description ?: null, $price, $link ?: null, $item_id]);

            header("Location: dashboard.php?page=admin_wishlist&status=success&message=" . urlencode('Item updated successfully'));
            exit();

        case 'delete_item':
            $item_id = intval($_POST['item_id'] ?? 0);
            if ($item_id <= 0) {
                header("Location: dashboard.php?page=admin_wishlist&status=error&message=" . urlencode('Invalid item'));
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE id = ?");
            $stmt->execute([$item_id]);

            header("Location: dashboard.php?page=admin_wishlist&status=success&message=" . urlencode('Item deleted'));
            exit();

        case 'toggle_purchased':
            $item_id = intval($_POST['item_id'] ?? 0);
            if ($item_id <= 0) {
                header("Location: dashboard.php?page=admin_wishlist&status=error&message=" . urlencode('Invalid item'));
                exit();
            }

            $stmt = $pdo->prepare("UPDATE wishlist_items SET purchased = NOT purchased, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$item_id]);

            header("Location: dashboard.php?page=admin_wishlist&status=success&message=" . urlencode('Item updated'));
            exit();

        default:
            header("Location: dashboard.php?page=admin_wishlist&status=error&message=" . urlencode('Unknown action'));
            exit();
    }
} catch (PDOException $e) {
    error_log("Wishlist error: " . $e->getMessage());
    header("Location: dashboard.php?page=admin_wishlist&status=error&message=" . urlencode('A database error occurred'));
    exit();
}
