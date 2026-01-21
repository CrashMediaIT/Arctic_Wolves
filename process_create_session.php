<?php
session_start();
require 'db_config.php';

if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'coach')) {
    header("Location: dashboard.php"); exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type      = $_POST['session_type'];
    $age_group = $_POST['age_group'];
    $title     = trim($_POST['title']);
    $desc      = trim($_POST['description']);
    $plan      = trim($_POST['session_plan']);
    $date      = $_POST['date'];
    $time      = $_POST['time'];
    $capacity  = $_POST['capacity'];
    
    // FETCH LOCATION DETAILS from ID
    $loc_id = $_POST['location_id'];
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$loc_id]);
    $loc = $stmt->fetch();
    
    $arena   = $loc['name'];
    $city    = $loc['city'];
    $country = 'Canada'; // Default or add to locations table

    // Coaches
    $coaches = isset($_POST['coaches']) ? implode(", ", $_POST['coaches']) : "Staff";

    try {
        $sql = "INSERT INTO sessions 
                (session_type, age_group, title, description, session_plan, session_date, session_time, max_capacity, coaches, arena, city, country) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $age_group, $title, $desc, $plan, $date, $time, $capacity, $coaches, $arena, $city, $country]);

        header("Location: dashboard.php?page=manage_sessions&status=created");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>