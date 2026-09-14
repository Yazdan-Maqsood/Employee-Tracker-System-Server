<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->leave_date) || !isset($data->leave_type)) {
    Response::error(400, "Leave date and type are required");
}

$leaveDate = $data->leave_date;
$leaveType = $data->leave_type;
$reason = $data->reason ?? '';

// Validate leave date is not in past
if (strtotime($leaveDate) < strtotime(date('Y-m-d'))) {
    Response::error(400, "Leave date cannot be in the past");
}

// Validate leave type
$validTypes = ['sick', 'casual', 'emergency', 'personal', 'other'];
if (!in_array($leaveType, $validTypes)) {
    Response::error(400, "Invalid leave type");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if already requested for same date
    $checkQuery = "SELECT id FROM leave_requests 
                   WHERE employee_id = :employee_id AND leave_date = :leave_date AND status != 'rejected'";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':employee_id', $userId, PDO::PARAM_INT);
    $checkStmt->bindParam(':leave_date', $leaveDate);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        Response::error(409, "You already have a leave request for this date");
    }

    $query = "INSERT INTO leave_requests (employee_id, leave_date, leave_type, reason, status) 
              VALUES (:employee_id, :leave_date, :leave_type, :reason, 'pending')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employee_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':leave_date', $leaveDate);
    $stmt->bindParam(':leave_type', $leaveType);
    $stmt->bindParam(':reason', $reason);
    
    if ($stmt->execute()) {
        // Trigger Pusher notification to admins
        require_once __DIR__ . '/../../config/pusher.php';
        
        // Find admin users
        $adminQuery = "SELECT id FROM employees WHERE role = 'admin'";
        $adminStmt = $db->prepare($adminQuery);
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            try {
                $pusher->trigger("user-{$admin['id']}", "new-leave-request", [
                    "message" => "New leave request received",
                    "employee_id" => $userId,
                    "leave_date" => $leaveDate
                ]);
            } catch (Exception $e) {
                error_log("Pusher error: " . $e->getMessage());
            }
        }
        
        Response::send(201, [
            "message" => "Leave request submitted successfully",
            "leave_id" => $db->lastInsertId()
        ]);
    } else {
        Response::error(500, "Failed to submit leave request");
    }
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>