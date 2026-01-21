<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'coach')) {
    header("Location: dashboard.php"); exit();
}

$action = $_POST['action'];
$id     = $_POST['id'];

// DELETE
if ($action == 'delete') {
    // Note: Foreign keys in 'bookings' should be set to ON DELETE CASCADE
    // If not, you'd delete bookings first: $pdo->prepare("DELETE FROM bookings WHERE session_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$id]);
    header("Location: dashboard.php?page=schedule&status=deleted");
    exit();
}

// UPDATE
if ($action == 'update') {
    $type  = $_POST['session_type'];
    $title = $_POST['title'];
    $date  = $_POST['date'];
    $time  = $_POST['time'];
    $plan  = $_POST['session_plan'];
    
    // Find Location City based on Name (Since the form sends name)
    $locName = $_POST['location_name'];
    $stmt = $pdo->prepare("SELECT city FROM locations WHERE name = ?");
    $stmt->execute([$locName]);
    $locData = $stmt->fetch();
    $city = $locData ? $locData['city'] : 'Unknown';

    try {
        $sql = "UPDATE sessions SET session_type=?, title=?, session_date=?, session_time=?, session_plan=?, arena=?, city=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $title, $date, $time, $plan, $locName, $city, $id]);
        
        header("Location: dashboard.php?page=schedule&status=updated");
        exit();
    } catch (PDOException $e) {
        die("Error updating session: " . $e->getMessage());
    }
}
?>