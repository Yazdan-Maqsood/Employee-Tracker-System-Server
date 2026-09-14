<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
if ($userData['role'] !== 'admin') {
    Response::error(403, "Access denied. Admin only");
}

$database = new Database();
$db = $database->getConnection();

try {
    // ✅ Include status in SELECT
    $query = "SELECT id, employeeName, email, cnic, employment_type, employee_fields, 
                     role, expected_arrival, expected_leaving, status 
              FROM employees 
              WHERE role IN ('employee', 'intern', 'student')
              ORDER BY employeeName ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, ["employees" => $employees]);
} catch(PDOException $e) {
    Response::error(500, "Failed to fetch employees: " . $e->getMessage());
}
?>