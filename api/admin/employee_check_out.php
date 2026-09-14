<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();

if ($userData['role'] !== 'admin') {
    Response::error(403, "Access denied. Admin only");
}

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->employee_id)) {
    Response::error(400, "Employee ID is required");
}

$database = new Database();
$db = $database->getConnection();

try {
    $employeeId = $data->employee_id;
    
    // Find today's active check-in
    $checkQuery = "SELECT id FROM attendance 
                   WHERE user_id = :user_id 
                   AND DATE(check_in) = CURDATE() 
                   AND check_out IS NULL 
                   LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':user_id', $employeeId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        Response::error(404, "No active check-in found");
    }
    
    // Update check-out
    $query = "UPDATE attendance SET check_out = NOW() WHERE user_id = :user_id AND check_out IS NULL";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $employeeId);
    
    if ($stmt->execute()) {
        Response::send(200, ["message" => "Employee checked out successfully"]);
    } else {
        Response::error(500, "Check-out failed");
    }
    
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>