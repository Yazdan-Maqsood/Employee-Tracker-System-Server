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
    $status = $_GET['status'] ?? 'all';
    
    $query = "SELECT lr.*, e.employeeName, e.email, e.role as member_role
              FROM leave_requests lr
              JOIN employees e ON lr.employee_id = e.id";
    
    if ($status !== 'all') {
        $query .= " WHERE lr.status = :status";
    }
    
    $query .= " ORDER BY 
                CASE lr.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
                lr.created_at DESC";
    
    $stmt = $db->prepare($query);
    if ($status !== 'all') {
        $stmt->bindParam(':status', $status);
    }
    $stmt->execute();
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, ["leaves" => $leaves]);
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>