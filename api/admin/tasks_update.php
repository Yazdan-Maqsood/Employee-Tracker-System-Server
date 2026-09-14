<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/pusher.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$adminId = $userData['user_id'];

// Get task ID from URL
$taskId = $_GET['task_id'] ?? null;

if (!$taskId) {
    Response::error(400, "Task ID is required");
}

$data = json_decode(file_get_contents("php://input"));

$title = $data->title ?? '';
$description = $data->description ?? '';
$assignedTo = $data->assigned_to ?? null;
$dueDate = $data->due_date ?? null;
$teamId = $data->team_id ?? null;
$assignmentType = $data->assignment_type ?? 'individual';

$database = new Database();
$db = $database->getConnection();

try {
    // ✅ Fix: Handle empty values
    if (empty($assignedTo) || $assignedTo === '') {
        $assignedTo = null;
    }
    if (empty($teamId) || $teamId === '') {
        $teamId = null;
    }

    // Get old task data for comparison (to detect if assigned_to changed)
    $oldTaskQuery = "SELECT * FROM tasks WHERE id = :id";
    $oldTaskStmt = $db->prepare($oldTaskQuery);
    $oldTaskStmt->bindParam(':id', $taskId, PDO::PARAM_INT);
    $oldTaskStmt->execute();
    $oldTask = $oldTaskStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldTask) {
        Response::error(404, "Task not found");
    }

    // Update task
    $query = "UPDATE tasks 
              SET title = :title, 
                  description = :description, 
                  assigned_to = :assigned_to, 
                  due_date = :due_date,
                  team_id = :team_id,
                  assignment_type = :assignment_type
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':assigned_to', $assignedTo, $assignedTo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':due_date', $dueDate);
    $stmt->bindParam(':team_id', $teamId, $teamId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':assignment_type', $assignmentType);
    $stmt->bindParam(':id', $taskId, PDO::PARAM_INT);
    $stmt->execute();

    // Get admin name
    $adminName = 'Admin';
    try {
        $adminQuery = "SELECT employeeName FROM employees WHERE id = :id";
        $adminStmt = $db->prepare($adminQuery);
        $adminStmt->bindParam(':id', $adminId, PDO::PARAM_INT);
        $adminStmt->execute();
        $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) $adminName = $admin['employeeName'];
    } catch (Exception $e) {}

    // ✅ Send notification if task was assigned to a DIFFERENT employee
    if ($assignedTo && $oldTask['assigned_to'] != $assignedTo) {
        $notificationData = [
            'type' => 'task_updated',
            'title' => '📋 Task Updated',
            'message' => $adminName . ' updated a task assigned to you: "' . $title . '"',
            'task_id' => (int)$taskId,
            'task_title' => $title,
            'updated_by' => $adminName,
            'due_date' => $dueDate,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Send via Pusher
        try {
            $pusher->trigger('employee-' . $assignedTo, 'notification', $notificationData);
        } catch (Exception $e) {
            error_log("Pusher notification failed: " . $e->getMessage());
        }

        // Store in DB
        try {
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, task_id, is_read, created_at) 
                           VALUES (:user_id, 'task_updated', :title, :message, :task_id, 0, NOW())";
            $notifStmt = $db->prepare($notifQuery);
            $notifStmt->bindParam(':user_id', $assignedTo, PDO::PARAM_INT);
            $notifStmt->bindParam(':title', $notificationData['title']);
            $notifStmt->bindParam(':message', $notificationData['message']);
            $notifStmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            $notifStmt->execute();
        } catch (Exception $e) {
            error_log("Notification DB error: " . $e->getMessage());
        }
    }
    // ✅ Also notify if task was updated (even same employee) - for title/due date changes
    else if ($assignedTo && $oldTask['assigned_to'] == $assignedTo) {
        // Only notify if title or due date actually changed
        if ($oldTask['title'] != $title || $oldTask['due_date'] != $dueDate) {
            $notificationData = [
                'type' => 'task_updated',
                'title' => '📋 Task Updated',
                'message' => $adminName . ' updated task: "' . $title . '"',
                'task_id' => (int)$taskId,
                'task_title' => $title,
                'updated_by' => $adminName,
                'due_date' => $dueDate,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            try {
                $pusher->trigger('employee-' . $assignedTo, 'notification', $notificationData);
            } catch (Exception $e) {}

            try {
                $notifQuery = "INSERT INTO notifications (user_id, type, title, message, task_id, is_read, created_at) 
                               VALUES (:user_id, 'task_updated', :title, :message, :task_id, 0, NOW())";
                $notifStmt = $db->prepare($notifQuery);
                $notifStmt->bindParam(':user_id', $assignedTo, PDO::PARAM_INT);
                $notifStmt->bindParam(':title', $notificationData['title']);
                $notifStmt->bindParam(':message', $notificationData['message']);
                $notifStmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
                $notifStmt->execute();
            } catch (Exception $e) {}
        }
    }

    Response::send(200, [
        "message" => "Task updated successfully"
    ]);

} catch(PDOException $e) {
    error_log("Task update error: " . $e->getMessage());
    Response::error(500, "Failed to update task");
} catch(Exception $e) {
    error_log("Task update error: " . $e->getMessage());
    Response::error(500, "Failed to update task");
}
?>