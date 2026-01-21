<?php
session_start();
require 'db_config.php';

$action = $_POST['action'];

if ($action == 'assign_workout_template') {
    $uid = $_POST['user_id'];
    $tid = $_POST['template_id'];
    $cid = $_SESSION['user_id'];
    
    // 1. Get Template Info
    $tmpl = $pdo->prepare("SELECT * FROM workout_templates WHERE id = ?");
    $tmpl->execute([$tid]);
    $t_data = $tmpl->fetch();
    
    // 2. Create User Workout Record
    $pdo->prepare("INSERT INTO user_workouts (user_id, coach_id, title) VALUES (?, ?, ?)")
        ->execute([$uid, $cid, $t_data['title']]);
    $uw_id = $pdo->lastInsertId();
    
    // 3. Copy Items from Template to User Items
    $items = $pdo->prepare("SELECT * FROM workout_template_items WHERE template_id = ?");
    $items->execute([$tid]);
    
    $insert = $pdo->prepare("INSERT INTO user_workout_items (user_workout_id, exercise_id, sets, reps) VALUES (?, ?, ?, ?)");
    
    while($row = $items->fetch()) {
        $insert->execute([$uw_id, $row['exercise_id'], $row['default_sets'], $row['default_reps']]);
    }
    
    header("Location: dashboard.php?page=athlete_detail&id=" . $uid);
}

if ($action == 'update_workout_items') {
    $uw_id = $_POST['user_workout_id'];
    $uid   = $_POST['user_id'];
    
    $sets   = $_POST['sets']; // Array [item_id => val]
    $reps   = $_POST['reps'];
    $weight = $_POST['weight'];
    
    $update = $pdo->prepare("UPDATE user_workout_items SET sets=?, reps=?, weight=? WHERE id=?");
    
    foreach ($sets as $item_id => $val) {
        $s = $sets[$item_id];
        $r = $reps[$item_id];
        $w = $weight[$item_id];
        $update->execute([$s, $r, $w, $item_id]);
    }
    
    header("Location: dashboard.php?page=athlete_detail&id=" . $uid);
}
?>