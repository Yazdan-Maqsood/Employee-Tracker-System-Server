<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
if ($userData['role'] !== 'admin') {
    Response::error(403, "Access denied. Admin only");
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->leave_id) || !isset($data->action)) {
    Response::error(400, "Leave ID and action are required");
}

$leaveId = intval($data->leave_id);
$action = $data->action;
$adminComment = $data->comment ?? '';

if (!in_array($action, ['approve', 'reject'])) {
    Response::error(400, "Invalid action");
}

$newStatus = ($action === 'approve') ? 'approved' : 'rejected';

$database = new Database();
$db = $database->getConnection();

try {
    // Get leave request details
    $checkQuery = "SELECT lr.*, e.employeeName, e.email 
                   FROM leave_requests lr
                   JOIN employees e ON lr.employee_id = e.id
                   WHERE lr.id = :leave_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':leave_id', $leaveId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        Response::error(404, "Leave request not found");
    }
    
    $leaveRequest = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($leaveRequest['status'] !== 'pending') {
        Response::error(400, "This request has already been processed");
    }

    $query = "UPDATE leave_requests 
              SET status = :status, admin_comment = :comment, updated_at = NOW()
              WHERE id = :leave_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $newStatus);
    $stmt->bindParam(':comment', $adminComment);
    $stmt->bindParam(':leave_id', $leaveId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Send Pusher notification to employee
        require_once __DIR__ . '/../../config/pusher.php';
        
        try {
            $statusMessage = ($newStatus === 'approved') ? 'approved' : 'rejected';
            $pusher->trigger("user-{$leaveRequest['employee_id']}", "leave-status-update", [
                "message" => "Your leave request has been {$statusMessage}",
                "leave_id" => $leaveId,
                "status" => $newStatus,
                "leave_date" => $leaveRequest['leave_date']
            ]);
        } catch (Exception $e) {
            error_log("Pusher error: " . $e->getMessage());
        }
        
        Response::send(200, [
            "message" => "Leave request {$statusMessage} successfully",
            "leave_id" => $leaveId,
            "status" => $newStatus
        ]);
    } else {
        Response::error(500, "Failed to update leave request");
    }
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>