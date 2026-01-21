<?php
session_start();
require 'db_config.php';

// 1. SECURITY CHECK: Admins & Coaches Only
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'coach')) {
    header("Location: dashboard.php"); exit();
}

$action = $_POST['action'];

// =========================================================
// MODULE 1: WORKOUT LIBRARY
// =========================================================

// A. Add Single Exercise
if ($action == 'add_exercise') {
    $name   = trim($_POST['name']);
    $target = $_POST['target'];
    $link   = $_POST['link'];

    // Check Duplicate
    $check = $pdo->prepare("SELECT id FROM exercises WHERE name = ?");
    $check->execute([$name]);
    if ($check->rowCount() > 0) {
        header("Location: dashboard.php?page=library_workouts&error=duplicate");
        exit();
    }

    $pdo->prepare("INSERT INTO exercises (name, target_area, video_link) VALUES (?, ?, ?)")->execute([$name, $target, $link]);
    header("Location: dashboard.php?page=library_workouts&status=added");
}

// B. Create Workout Template
if ($action == 'create_template') {
    $title = trim($_POST['title']);
    $selected_exercises = $_POST['exercises'] ?? []; // Array of IDs
    
    // 1. Create Template Header
    $pdo->prepare("INSERT INTO workout_templates (title) VALUES (?)")->execute([$title]);
    $template_id = $pdo->lastInsertId();
    
    // 2. Link Exercises
    if (!empty($selected_exercises)) {
        $stmt = $pdo->prepare("INSERT INTO workout_template_items (template_id, exercise_id) VALUES (?, ?)");
        foreach ($selected_exercises as $ex_id) {
            $stmt->execute([$template_id, $ex_id]);
        }
    }
    header("Location: dashboard.php?page=library_workouts&status=created");
}

// C. Delete Workout Template
if ($action == 'delete_workout_template') {
    $id = $_POST['id'];
    $pdo->prepare("DELETE FROM workout_templates WHERE id = ?")->execute([$id]);
    header("Location: dashboard.php?page=library_workouts");
}

// =========================================================
// MODULE 2: NUTRITION LIBRARY
// =========================================================

// A. Add Food Item
if ($action == 'add_food') {
    $name   = trim($_POST['name']);
    $type   = $_POST['type'];
    $recipe = $_POST['recipe'];

    // Check Duplicate
    $check = $pdo->prepare("SELECT id FROM foods WHERE name = ?");
    $check->execute([$name]);
    if ($check->rowCount() > 0) {
        header("Location: dashboard.php?page=library_nutrition&error=duplicate");
        exit();
    }

    $pdo->prepare("INSERT INTO foods (name, type, recipe) VALUES (?, ?, ?)")->execute([$name, $type, $recipe]);
    header("Location: dashboard.php?page=library_nutrition&status=added");
}

// B. Create Nutrition Template
if ($action == 'create_nutrition_template') {
    $title = trim($_POST['title']);
    $selected_foods = $_POST['foods'] ?? [];
    $meal_types     = $_POST['meal_type'] ?? []; // Array [food_id => 'Breakfast']
    
    // 1. Create Template Header
    $pdo->prepare("INSERT INTO nutrition_templates (title) VALUES (?)")->execute([$title]);
    $template_id = $pdo->lastInsertId();
    
    // 2. Link Foods
    if (!empty($selected_foods)) {
        $stmt = $pdo->prepare("INSERT INTO nutrition_template_items (template_id, food_id, meal_type, default_portion) VALUES (?, ?, ?, ?)");
        foreach ($selected_foods as $food_id) {
            $m_type = $meal_types[$food_id];
            // Default portion is generic '1 Serving', can be modified later per athlete
            $stmt->execute([$template_id, $food_id, $m_type, '1 Serving']);
        }
    }
    header("Location: dashboard.php?page=library_nutrition&status=created");
}

// C. Delete Nutrition Template
if ($action == 'delete_nutrition_template') {
    $id = $_POST['id'];
    $pdo->prepare("DELETE FROM nutrition_templates WHERE id = ?")->execute([$id]);
    header("Location: dashboard.php?page=library_nutrition");
}

// =========================================================
// MODULE 3: SESSION LIBRARY (NEW)
// =========================================================

// A. Create Session Template
if ($action == 'create_session_template') {
    $title = trim($_POST['title']);
    $type  = $_POST['session_type'];
    $age   = $_POST['age_group'];
    $desc  = $_POST['description'];
    $plan  = $_POST['session_plan'];

    $pdo->prepare("INSERT INTO session_templates (title, session_type, age_group, description, session_plan) VALUES (?, ?, ?, ?, ?)")
        ->execute([$title, $type, $age, $desc, $plan]);
    
    header("Location: dashboard.php?page=library_sessions&status=created");
}

// B. Delete Session Template
if ($action == 'delete_session_template') {
    $id = $_POST['id'];
    $pdo->prepare("DELETE FROM session_templates WHERE id = ?")->execute([$id]);
    header("Location: dashboard.php?page=library_sessions");
}

?>