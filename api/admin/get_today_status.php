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
    $query = "SELECT a.user_id, a.check_in, a.check_out, a.status 
              FROM attendance a 
              WHERE DATE(a.check_in) = CURDATE()";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $todayStatus = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $todayStatus[$row['user_id']] = [
            'checked_in' => true,
            'check_in_time' => $row['check_in'],
            'checked_out' => $row['check_out'] ? true : false,
            'check_out_time' => $row['check_out'],
            'status' => $row['status']
        ];
    }
    
    Response::send(200, [
        "todayStatus" => $todayStatus
    ]);
    
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>