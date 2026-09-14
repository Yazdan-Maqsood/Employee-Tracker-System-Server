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
    
    // Check if already checked in today
    $checkQuery = "SELECT id FROM attendance 
                   WHERE user_id = :user_id 
                   AND DATE(check_in) = CURDATE() 
                   LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':user_id', $employeeId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        Response::error(409, "Employee already checked in today");
    }
    
    // Insert check-in
    $currentHour = date('H');
    $status = ($currentHour >= 9 && $currentHour < 10) ? 'present' : 'late';
    
    $query = "INSERT INTO attendance (user_id, check_in, status) 
              VALUES (:user_id, NOW(), :status)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $employeeId);
    $stmt->bindParam(':status', $status);
    
    if ($stmt->execute()) {
        Response::send(201, ["message" => "Employee checked in successfully"]);
    } else {
        Response::error(500, "Check-in failed");
    }
    
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>