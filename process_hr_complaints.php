<?php
/**
 * Process HR Complaints Actions
 * Handles CRUD operations for HR complaints per Canada's HR best practices
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

// Set security headers
setSecurityHeaders();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
    }
    header("Location: login.php");
    exit();
}

// Validate CSRF token
checkCsrfToken();

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// =========================================================
// CREATE NEW COMPLAINT
// =========================================================
if ($action === 'create') {
    $complaint_number = trim($_POST['complaint_number'] ?? '');
    $complaint_type = $_POST['complaint_type'] ?? '';
    $category = $_POST['category'] ?? '';
    $severity = $_POST['severity'] ?? 'medium';
    $confidentiality_level = $_POST['confidentiality_level'] ?? 'standard';
    $priority = $_POST['priority'] ?? 'normal';
    $complainant_id = !empty($_POST['complainant_id']) ? intval($_POST['complainant_id']) : null;
    $complainant_name = trim($_POST['complainant_name'] ?? '');
    $complainant_contact = trim($_POST['complainant_contact'] ?? '');
    $respondent_id = !empty($_POST['respondent_id']) ? intval($_POST['respondent_id']) : null;
    $respondent_name = trim($_POST['respondent_name'] ?? '');
    $complaint_date = $_POST['complaint_date'] ?? date('Y-m-d');
    $incident_date = !empty($_POST['incident_date']) ? $_POST['incident_date'] : null;
    $incident_location = trim($_POST['incident_location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $witnesses = trim($_POST['witnesses'] ?? '');
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    
    // Validation
    if (empty($complaint_type) || empty($category) || empty($description)) {
        header("Location: dashboard.php?page=complaints&tab=new&error=missing_required_fields");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO hr_complaints (
                complaint_number, complaint_type, category, severity, confidentiality_level,
                priority, complainant_id, complainant_name, complainant_contact,
                respondent_id, respondent_name, complaint_date, incident_date,
                incident_location, description, witnesses, assigned_to, status, created_by
            ) VALUES (
                :complaint_number, :complaint_type, :category, :severity, :confidentiality_level,
                :priority, :complainant_id, :complainant_name, :complainant_contact,
                :respondent_id, :respondent_name, :complaint_date, :incident_date,
                :incident_location, :description, :witnesses, :assigned_to, 'received', :created_by
            )
        ");
        
        $stmt->execute([
            'complaint_number' => $complaint_number,
            'complaint_type' => $complaint_type,
            'category' => $category,
            'severity' => $severity,
            'confidentiality_level' => $confidentiality_level,
            'priority' => $priority,
            'complainant_id' => $complainant_id,
            'complainant_name' => $complainant_name ?: null,
            'complainant_contact' => $complainant_contact ?: null,
            'respondent_id' => $respondent_id,
            'respondent_name' => $respondent_name ?: null,
            'complaint_date' => $complaint_date,
            'incident_date' => $incident_date,
            'incident_location' => $incident_location ?: null,
            'description' => $description,
            'witnesses' => $witnesses ?: null,
            'assigned_to' => $assigned_to,
            'created_by' => $user_id
        ]);
        
        $complaint_id = $pdo->lastInsertId();
        
        // Add initial note
        $initial_note = "Complaint filed and recorded in the system.";
        $note_stmt = $pdo->prepare("
            INSERT INTO hr_complaint_notes (complaint_id, note_type, note_content, created_by)
            VALUES (:complaint_id, 'general', :note_content, :created_by)
        ");
        $note_stmt->execute([
            'complaint_id' => $complaint_id,
            'note_content' => $initial_note,
            'created_by' => $user_id
        ]);
        
        header("Location: dashboard.php?page=complaints&tab=list&success=complaint_created");
        exit();
        
    } catch (PDOException $e) {
        error_log("HR Complaint creation error: " . $e->getMessage());
        header("Location: dashboard.php?page=complaints&tab=new&error=database_error");
        exit();
    }
}

// =========================================================
// UPDATE COMPLAINT
// =========================================================
if ($action === 'update') {
    $complaint_id = intval($_POST['complaint_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $severity = $_POST['severity'] ?? '';
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    $resolution = trim($_POST['resolution'] ?? '');
    $resolution_date = !empty($_POST['resolution_date']) ? $_POST['resolution_date'] : null;
    $corrective_actions = trim($_POST['corrective_actions'] ?? '');
    $new_note = trim($_POST['new_note'] ?? '');
    
    if (!$complaint_id) {
        header("Location: dashboard.php?page=complaints&tab=list&error=invalid_complaint");
        exit();
    }
    
    try {
        // Update complaint
        $stmt = $pdo->prepare("
            UPDATE hr_complaints SET
                status = :status,
                severity = :severity,
                assigned_to = :assigned_to,
                resolution = :resolution,
                resolution_date = :resolution_date,
                corrective_actions = :corrective_actions,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'status' => $status,
            'severity' => $severity,
            'assigned_to' => $assigned_to,
            'resolution' => $resolution ?: null,
            'resolution_date' => $resolution_date,
            'corrective_actions' => $corrective_actions ?: null,
            'id' => $complaint_id
        ]);
        
        // Add note if provided
        if (!empty($new_note)) {
            $note_type = 'update';
            if ($status === 'resolved') $note_type = 'resolution';
            if ($status === 'escalated') $note_type = 'escalation';
            if ($status === 'investigation') $note_type = 'investigation';
            
            $note_stmt = $pdo->prepare("
                INSERT INTO hr_complaint_notes (complaint_id, note_type, note_content, created_by)
                VALUES (:complaint_id, :note_type, :note_content, :created_by)
            ");
            $note_stmt->execute([
                'complaint_id' => $complaint_id,
                'note_type' => $note_type,
                'note_content' => $new_note,
                'created_by' => $user_id
            ]);
        }
        
        // Add status change note
        $status_note = "Status updated to: " . ucfirst(str_replace('_', ' ', $status));
        $note_stmt = $pdo->prepare("
            INSERT INTO hr_complaint_notes (complaint_id, note_type, note_content, created_by)
            VALUES (:complaint_id, 'update', :note_content, :created_by)
        ");
        $note_stmt->execute([
            'complaint_id' => $complaint_id,
            'note_content' => $status_note,
            'created_by' => $user_id
        ]);
        
        header("Location: dashboard.php?page=complaints&tab=list&success=complaint_updated");
        exit();
        
    } catch (PDOException $e) {
        error_log("HR Complaint update error: " . $e->getMessage());
        header("Location: dashboard.php?page=complaints&tab=list&error=update_failed");
        exit();
    }
}

// =========================================================
// GET COMPLAINT DETAILS (AJAX)
// =========================================================
if ($action === 'get_details') {
    header('Content-Type: application/json');
    
    $complaint_id = intval($_POST['complaint_id'] ?? 0);
    $format = $_POST['format'] ?? 'html';
    
    if (!$complaint_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
        exit();
    }
    
    try {
        // Fetch complaint
        $stmt = $pdo->prepare("
            SELECT c.*, 
                comp.first_name as complainant_first, comp.last_name as complainant_last,
                resp.first_name as respondent_first, resp.last_name as respondent_last,
                assign.first_name as assigned_first, assign.last_name as assigned_last,
                creator.first_name as created_first, creator.last_name as created_last
            FROM hr_complaints c
            LEFT JOIN users comp ON c.complainant_id = comp.id
            LEFT JOIN users resp ON c.respondent_id = resp.id
            LEFT JOIN users assign ON c.assigned_to = assign.id
            LEFT JOIN users creator ON c.created_by = creator.id
            WHERE c.id = :id
        ");
        $stmt->execute(['id' => $complaint_id]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$complaint) {
            echo json_encode(['success' => false, 'message' => 'Complaint not found']);
            exit();
        }
        
        // If JSON format requested, return raw data
        if ($format === 'json') {
            echo json_encode(['success' => true, 'complaint' => $complaint]);
            exit();
        }
        
        // Fetch notes
        $notes_stmt = $pdo->prepare("
            SELECT n.*, u.first_name, u.last_name
            FROM hr_complaint_notes n
            LEFT JOIN users u ON n.created_by = u.id
            WHERE n.complaint_id = :id
            ORDER BY n.created_at DESC
        ");
        $notes_stmt->execute(['id' => $complaint_id]);
        $notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build HTML response
        $html = '<div class="complaint-details">';
        
        // Header info
        $html .= '<div class="detail-section">';
        $html .= '<div class="detail-row">';
        $html .= '<div class="detail-item"><strong>Complaint #:</strong> ' . htmlspecialchars($complaint['complaint_number']) . '</div>';
        $html .= '<div class="detail-item"><strong>Type:</strong> <span class="type-badge ' . $complaint['complaint_type'] . '">' . ucfirst($complaint['complaint_type']) . '</span></div>';
        $html .= '<div class="detail-item"><strong>Status:</strong> <span class="status-badge ' . $complaint['status'] . '">' . ucfirst(str_replace('_', ' ', $complaint['status'])) . '</span></div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Parties
        $html .= '<div class="detail-section">';
        $html .= '<h4>Parties Involved</h4>';
        
        $complainant_display = 'Anonymous';
        if ($complaint['complainant_first']) {
            $complainant_display = htmlspecialchars($complaint['complainant_first'] . ' ' . $complaint['complainant_last']);
        } elseif ($complaint['complainant_name']) {
            $complainant_display = htmlspecialchars($complaint['complainant_name']) . ' (External)';
        }
        
        $respondent_display = '-';
        if ($complaint['respondent_first']) {
            $respondent_display = htmlspecialchars($complaint['respondent_first'] . ' ' . $complaint['respondent_last']);
        } elseif ($complaint['respondent_name']) {
            $respondent_display = htmlspecialchars($complaint['respondent_name']);
        }
        
        $html .= '<p><strong>Complainant:</strong> ' . $complainant_display . '</p>';
        $html .= '<p><strong>Respondent:</strong> ' . $respondent_display . '</p>';
        $html .= '</div>';
        
        // Dates
        $html .= '<div class="detail-section">';
        $html .= '<h4>Timeline</h4>';
        $html .= '<p><strong>Date Filed:</strong> ' . date('M d, Y', strtotime($complaint['complaint_date'])) . '</p>';
        if ($complaint['incident_date']) {
            $html .= '<p><strong>Incident Date:</strong> ' . date('M d, Y', strtotime($complaint['incident_date'])) . '</p>';
        }
        if ($complaint['resolution_date']) {
            $html .= '<p><strong>Resolution Date:</strong> ' . date('M d, Y', strtotime($complaint['resolution_date'])) . '</p>';
        }
        $html .= '</div>';
        
        // Description
        $html .= '<div class="detail-section">';
        $html .= '<h4>Description</h4>';
        $html .= '<p>' . nl2br(htmlspecialchars($complaint['description'])) . '</p>';
        $html .= '</div>';
        
        // Resolution if exists
        if ($complaint['resolution']) {
            $html .= '<div class="detail-section">';
            $html .= '<h4>Resolution</h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($complaint['resolution'])) . '</p>';
            $html .= '</div>';
        }
        
        // Notes/Activity
        if (!empty($notes)) {
            $html .= '<div class="detail-section">';
            $html .= '<h4>Activity Log</h4>';
            $html .= '<div class="activity-log" style="max-height: 200px; overflow-y: auto;">';
            foreach ($notes as $note) {
                $html .= '<div class="activity-item" style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 13px;">';
                $html .= '<strong>' . ucfirst($note['note_type']) . '</strong> - ';
                $html .= '<span style="color: var(--text-dim);">' . date('M d, Y H:i', strtotime($note['created_at'])) . '</span>';
                $html .= '<br>' . htmlspecialchars($note['note_content']);
                $html .= '<br><small style="color: var(--text-dim);">By: ' . htmlspecialchars($note['first_name'] . ' ' . $note['last_name']) . '</small>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        echo json_encode(['success' => true, 'html' => $html]);
        exit();
        
    } catch (PDOException $e) {
        error_log("HR Complaint details error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }
}

// =========================================================
// ADD NOTE TO COMPLAINT
// =========================================================
if ($action === 'add_note') {
    header('Content-Type: application/json');
    
    $complaint_id = intval($_POST['complaint_id'] ?? 0);
    $note_type = $_POST['note_type'] ?? 'general';
    $note_content = trim($_POST['note_content'] ?? '');
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
    
    if (!$complaint_id || empty($note_content)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO hr_complaint_notes (complaint_id, note_type, note_content, is_confidential, created_by)
            VALUES (:complaint_id, :note_type, :note_content, :is_confidential, :created_by)
        ");
        $stmt->execute([
            'complaint_id' => $complaint_id,
            'note_type' => $note_type,
            'note_content' => $note_content,
            'is_confidential' => $is_confidential,
            'created_by' => $user_id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Note added successfully']);
        exit();
        
    } catch (PDOException $e) {
        error_log("HR Complaint add note error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add note']);
        exit();
    }
}

// =========================================================
// DELETE COMPLAINT (Admin Only)
// =========================================================
if ($action === 'delete') {
    $complaint_id = intval($_POST['complaint_id'] ?? 0);
    
    if (!$complaint_id) {
        header("Location: dashboard.php?page=complaints&tab=list&error=invalid_complaint");
        exit();
    }
    
    try {
        // Notes and documents will cascade delete
        $stmt = $pdo->prepare("DELETE FROM hr_complaints WHERE id = :id");
        $stmt->execute(['id' => $complaint_id]);
        
        header("Location: dashboard.php?page=complaints&tab=list&success=complaint_deleted");
        exit();
        
    } catch (PDOException $e) {
        error_log("HR Complaint delete error: " . $e->getMessage());
        header("Location: dashboard.php?page=complaints&tab=list&error=delete_failed");
        exit();
    }
}

// Fallback redirect
header("Location: dashboard.php?page=complaints");
exit();
