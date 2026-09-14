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
    // ✅ Removed LIMIT 100 – returns all records
    $query = "SELECT a.*, 
                     e.employeeName as employee_name, 
                     e.email as employee_email, 
                     e.employment_type, 
                     e.employee_fields,
                     e.expected_arrival,
                     e.expected_leaving
              FROM attendance a 
              JOIN employees e ON a.user_id = e.id 
              ORDER BY a.check_in DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::send(200, [
        "attendances" => $attendances
    ]);
    
} catch(PDOException $e) {
    Response::error(500, "Failed to fetch attendance: " . $e->getMessage());
}
?>