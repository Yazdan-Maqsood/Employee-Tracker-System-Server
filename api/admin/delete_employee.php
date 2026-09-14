<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();

if ($userData['role'] !== 'admin') {
    Response::error(403, "Access denied. Admin only");
}

$employeeId = $_GET['employee_id'] ?? null;

if (!$employeeId) {
    Response::error(400, "Employee ID is required");
}

// Prevent admin from deleting themselves
if ($employeeId == $userData['user_id']) {
    Response::error(400, "You cannot delete your own account");
}

$database = new Database();
$db = $database->getConnection();

$checkQuery = "SELECT id, employeeName FROM employees WHERE id = :id AND role IN ('employee', 'intern', 'student') LIMIT 1";
$query = "DELETE FROM employees WHERE id = :id AND role IN ('employee', 'intern', 'student')";

try {
    // Check if employee exists
    $checkQuery = "SELECT id, employeeName FROM employees WHERE id = :id AND role = 'employee' LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $employeeId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        Response::error(404, "Employee not found");
    }
    
    // Delete attendance records first (or they'll be cascade deleted if FK is set)
    // Then delete employee
    $query = "DELETE FROM employees WHERE id = :id AND role = 'employee'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $employeeId);
    
    if ($stmt->execute()) {
        Response::send(200, [
            "message" => "Employee deleted successfully"
        ]);
    } else {
        Response::error(500, "Failed to delete employee");
    }
    
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>