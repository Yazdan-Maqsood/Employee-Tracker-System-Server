<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

// Try to load Pusher, but don't fail if it's not available
$pusher = null;
if (file_exists(__DIR__ . '/../../config/pusher.php')) {
    require_once __DIR__ . '/../../config/pusher.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$adminId = $userData['user_id'];

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Grab variables and strictly convert empty ones to PHP null
    $title = $_POST['title'] ?? '';
    $description = !empty($_POST['description']) ? $_POST['description'] : null;
    $assignmentType = $_POST['assignment_type'] ?? 'individual';
    
    // If empty, set to null. If not, cast to integer
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $teamId = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (empty($title)) {
        Response::error(400, "Task title is required");
    }

    // Handle file upload
    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/tasks/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fileName = 'task_' . time() . '_' . uniqid() . '.' . $fileExtension;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName)) {
            $filePath = 'uploads/tasks/' . $fileName;
        }
    }

    // Get admin name for notification
    $adminName = 'Admin';
    try {
        $adminQuery = "SELECT employeeName FROM employees WHERE id = :id";
        $adminStmt = $db->prepare($adminQuery);
        $adminStmt->bindParam(':id', $adminId, PDO::PARAM_INT);
        $adminStmt->execute();
        $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) $adminName = $admin['employeeName'];
    } catch (Exception $e) {}

    // Insert task
    $query = "INSERT INTO tasks (title, description, assigned_by, assigned_to, due_date, file_path, status, team_id, assignment_type) 
              VALUES (:title, :description, :admin_id, :assigned_to, :due_date, :file_path, 'pending', :team_id, :assignment_type)";
    
    $stmt = $db->prepare($query);
    
    // Use bindValue to safely handle NULLs
    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':assigned_to', $assignedTo, $assignedTo ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':due_date', $dueDate, $dueDate ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':file_path', $filePath, $filePath ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':team_id', $teamId, $teamId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':assignment_type', $assignmentType);
    
    $stmt->execute();

    $taskId = $db->lastInsertId();

    // Try to send Pusher notification (only if assigned to individual)
    if ($pusher && $assignedTo) {
        try {
            $notificationData = [
                'type' => 'task_assigned',
                'title' => 'New Task Assigned',
                'message' => $adminName . ' assigned you a task: "' . $title . '"',
                'task_id' => (int)$taskId,
                'task_title' => $title,
                'assigned_by' => $adminName,
                'due_date' => $dueDate,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $pusher->trigger('employee-' . $assignedTo, 'notification', $notificationData);
        } catch (Exception $e) {}
    }

    // Try to store notification in DB (only if assigned to individual)
    if ($assignedTo) {
        try {
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, task_id, is_read, created_at) 
                           VALUES (:user_id, 'task_assigned', :notif_title, :message, :task_id, 0, NOW())";
            $notifStmt = $db->prepare($notifQuery);
            $notifStmt->bindValue(':user_id', $assignedTo, PDO::PARAM_INT);
            $notifStmt->bindValue(':notif_title', 'New Task Assigned');
            $notifStmt->bindValue(':message', $adminName . ' assigned you a task: "' . $title . '"');
            $notifStmt->bindValue(':task_id', $taskId, PDO::PARAM_INT);
            $notifStmt->execute();
        } catch (Exception $e) {}
    }

    Response::send(201, [
        "message" => "Task created successfully",
        "task_id" => $taskId
    ]);

} catch(PDOException $e) {
    error_log("Task creation DB error: " . $e->getMessage());
    Response::error(500, "Database error: " . $e->getMessage());
} catch(Exception $e) {
    error_log("Task creation general error: " . $e->getMessage());
    Response::error(500, "Failed to create task");
}
?>