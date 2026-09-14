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

if (!isset($data->holiday_date) || !isset($data->holiday_name)) {
    Response::error(400, "Holiday date and name are required");
}

$database = new Database();
$db = $database->getConnection();

try {
    // ✅ Check if holiday already exists
    $checkQuery = "SELECT id FROM holidays WHERE holiday_date = :holiday_date";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':holiday_date', $data->holiday_date);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        Response::error(409, "Holiday already exists for this date");
    }
    
    // ✅ Insert holiday
    $query = "INSERT INTO holidays (holiday_date, holiday_name, description, created_by) 
              VALUES (:holiday_date, :holiday_name, :description, :created_by)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':holiday_date', $data->holiday_date);
    $stmt->bindParam(':holiday_name', $data->holiday_name);
    
    // Handle optional description
    $description = isset($data->description) ? $data->description : null;
    $stmt->bindParam(':description', $description);
    
    $stmt->bindParam(':created_by', $userData['user_id'], PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        Response::send(201, [
            "message" => "Holiday added successfully",
            "holiday_id" => $db->lastInsertId()
        ]);
    } else {
        Response::error(500, "Failed to add holiday: " . implode(", ", $stmt->errorInfo()));
    }
    
} catch(PDOException $e) {
    error_log("Holiday add error: " . $e->getMessage());
    Response::error(500, "Failed to add holiday: " . $e->getMessage());
} catch(Exception $e) {
    error_log("Holiday add general error: " . $e->getMessage());
    Response::error(500, "Server error: " . $e->getMessage());
}
?>