<?php
// Minimal mock to render the marketing page
$isAdmin = true;
$isParent = false;
$user_id = 1;
$user_role = 'admin';
$selected_user = null;
$search = '';
$role_filter = '';
$status_filter = '';

// Mock PDO
class MockPDO {
    public function prepare($sql) { return new MockStmt(); }
    public function query($sql) { return new MockStmt(); }
}
class MockStmt {
    public function execute($params = []) { return true; }
    public function fetchAll($mode = 0) { return []; }
    public function fetch($mode = 0) { return false; }
}
$pdo = new MockPDO();

// Mock functions
function decryptUserRows($rows) { return $rows; }
function decryptUserRow($row) { return $row; }
function formatPhone($phone) { return $phone; }

// Output the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Marketing Test</title>
<link rel="stylesheet" href="css/style-guide.css">
<link rel="stylesheet" href="css/components.css">
<style>
    body { margin: 0; background: var(--bg-main); font-family: 'Inter', sans-serif; color: #fff; display: flex; height: 100vh; overflow: hidden; }
    .sidebar { width: 280px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 25px; overflow-y: auto; }
    .brand { font-size: 22px; font-weight: 900; margin-bottom: 40px; letter-spacing: -1px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
    .brand span { color: var(--primary); }
    .main-content { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
    .content-area { flex: 1; padding: 40px; overflow-y: auto; }
</style>
<link rel="stylesheet" href="views/shared_styles.css">
</head>
<body>
<aside class="sidebar">
    <a href="#" class="brand">ARCTIC <span>WOLVES</span></a>
</aside>
<main class="main-content">
    <div class="content-area">
        <?php include __DIR__ . '/views/admin_business_cards.php'; ?>
    </div>
</main>
</body>
</html>
