<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT lr.*, e.employeeName 
              FROM leave_requests lr
              JOIN employees e ON lr.employee_id = e.id
              WHERE lr.employee_id = :employee_id
              ORDER BY lr.leave_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employee_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, ["leaves" => $leaves]);
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>