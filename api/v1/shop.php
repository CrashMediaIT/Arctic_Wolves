<?php
/**
 * API v1 - Shop Endpoints
 * Provides merchandise shop access for ACWolvesAPP.
 *
 * Endpoints:
 *   GET  /v1/shop/products     - List products
 *   GET  /v1/shop/categories   - List categories
 *   GET  /v1/shop/cart         - Get cart (placeholder)
 *   POST /v1/shop/cart         - Add to cart (placeholder)
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? null;

if ($method === 'GET' && $action === 'products') {
    handleListProducts($auth);
} elseif ($method === 'GET' && $action === 'categories') {
    handleListCategories($auth);
} elseif ($method === 'GET' && $action === 'cart') {
    handleGetCart($auth);
} elseif ($method === 'POST' && $action === 'cart') {
    handleAddToCart($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Shop endpoint not found. Use: products, categories, cart']);
}

/**
 * GET /v1/shop/products
 */
function handleListProducts($auth) {
    global $pdo;

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = ['mp.is_active = 1'];
    $params = [];

    if (!empty($_GET['category_id'])) {
        $where[] = 'mp.category_id = ?';
        $params[] = (int) $_GET['category_id'];
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM merchandise_products mp $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT mp.id, mp.name, mp.description, mp.sku, mp.price, mp.image_url,
                   mp.is_active, mp.track_inventory, mp.created_at,
                   mc.name AS category_name, mc.id AS category_id
            FROM merchandise_products mp
            LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
            $where_sql
            ORDER BY mp.name
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add sizes for each product
        foreach ($products as &$product) {
            $size_stmt = $pdo->prepare("
                SELECT id, size, quantity FROM merchandise_product_sizes WHERE product_id = ? ORDER BY id
            ");
            $size_stmt->execute([$product['id']]);
            $product['sizes'] = $size_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($product);

        logApiAccess('list_products', "Listed products (page $page)", $auth['user_id']);
        paginatedResponse($products, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API SHOP ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/shop/categories
 */
function handleListCategories($auth) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, description, image_url, display_order
            FROM merchandise_categories
            WHERE is_active = 1
            ORDER BY display_order, name
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiAccess('list_categories', 'Listed shop categories', $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $categories]);
    } catch (PDOException $e) {
        error_log('[API SHOP ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/shop/cart
 * Cart state is managed client-side in the mobile app; this endpoint provides
 * server-side cart support when available.
 */
function handleGetCart($auth) {
    apiResponse(200, [
        'success' => true,
        'data' => [
            'items' => [],
            'total' => 0,
        ],
    ]);
}

/**
 * POST /v1/shop/cart
 * Validates product availability when adding to cart.
 */
function handleAddToCart($auth) {
    global $pdo;

    $body = getJsonBody();
    $product_id = (int) ($body['productId'] ?? $body['product_id'] ?? 0);
    $quantity = max(1, (int) ($body['quantity'] ?? 1));

    if (!$product_id) {
        apiResponse(400, ['success' => false, 'error' => 'productId is required']);
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, price, is_active FROM merchandise_products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || !$product['is_active']) {
            apiResponse(404, ['success' => false, 'error' => 'Product not found or unavailable']);
        }

        logApiAccess('add_to_cart', "Added product $product_id to cart (qty: $quantity)", $auth['user_id']);
        apiResponse(200, [
            'success' => true,
            'message' => 'Product validated and added to cart',
            'data' => [
                'product_id' => (int) $product['id'],
                'name' => $product['name'],
                'price' => (float) $product['price'],
                'quantity' => $quantity,
            ],
        ]);
    } catch (PDOException $e) {
        error_log('[API SHOP ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
