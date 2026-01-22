<?php
/**
 * Database Schema Verification Script
 * Checks if all required tables exist and reports missing ones
 */

require_once 'db_config.php';

// Check database connection
if (!$db_connected || $pdo === null) {
    die("❌ Database connection failed. Please check db_config.php\n");
}

echo "🔍 Arctic Wolves Database Schema Verification\n";
echo "==========================================\n\n";

// List of critical tables that should exist
$required_tables = [
    'users',
    'sessions',
    'session_types',
    'locations',
    'packages',
    'athlete_programs',
    'training_programs',
    'credits_refunds',
    'employee_terminations',
    'expense_categories',
    'expenses',
    'audit_logs',
    'invoices',
    'payments',
    'notifications',
    'goals',
    'performance_stats',
    'teams',
    'team_roster'
];

$missing_tables = [];
$existing_tables = [];

// Check each table
foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        $existing_tables[] = $table;
        echo "✅ Table '$table' exists\n";
    } catch (PDOException $e) {
        $missing_tables[] = $table;
        echo "❌ Table '$table' is MISSING\n";
    }
}

echo "\n==========================================\n";
echo "Summary:\n";
echo "✅ Existing tables: " . count($existing_tables) . "\n";
echo "❌ Missing tables: " . count($missing_tables) . "\n";

if (count($missing_tables) > 0) {
    echo "\n⚠️  CRITICAL: The following tables are missing:\n";
    foreach ($missing_tables as $table) {
        echo "   - $table\n";
    }
    echo "\nTo fix this, run the setup wizard or import database_schema.sql:\n";
    echo "1. Visit setup.php in your browser\n";
    echo "   OR\n";
    echo "2. Import: mysql -u [user] -p [database] < database_schema.sql\n";
} else {
    echo "\n🎉 All required tables exist!\n";
}

// Check for common column issues
echo "\n==========================================\n";
echo "Checking Common Column Issues:\n";

try {
    // Check users table has 'id' column (not 'user_id')
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('id', $columns)) {
        echo "✅ users.id column exists (correct)\n";
    } else {
        echo "❌ users.id column missing\n";
    }
    
    // Check expense_categories has 'name' column
    $stmt = $pdo->query("DESCRIBE expense_categories");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('name', $columns)) {
        echo "✅ expense_categories.name column exists (correct)\n";
    } else {
        echo "❌ expense_categories.name column missing\n";
    }
} catch (PDOException $e) {
    echo "⚠️  Could not verify columns: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "Verification complete!\n";
?>
