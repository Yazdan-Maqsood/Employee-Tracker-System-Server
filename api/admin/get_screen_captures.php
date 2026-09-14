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
    Response::error(403, "Only admins can view screen captures");
}

$employeeId = $_GET['employee_id'] ?? null;

if (!$employeeId) {
    Response::error(400, "Employee ID is required");
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT sc.*, e.employeeName 
              FROM screen_captures sc 
              JOIN employees e ON sc.employee_id = e.id 
              WHERE sc.employee_id = :employee_id 
              ORDER BY sc.created_at DESC 
              LIMIT 20";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
    $stmt->execute();
    
    $captures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build full URLs
    $baseUrl = 'https://dt-attendance-system.desired-techs.com/dt-backend/';
    foreach ($captures as &$capture) {
        $capture['image_url'] = $capture['image_path'] ? $baseUrl . $capture['image_path'] : null;
        $capture['is_video'] = $capture['image_path'] ? preg_match('/\.(mp4|webm|mov)$/i', $capture['image_path']) : false;
        $capture['is_image'] = $capture['image_path'] ? preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $capture['image_path']) : false;
    }
    
    Response::send(200, [
        'captures' => $captures,
        'count' => count($captures),
        'base_url' => $baseUrl
    ]);
    
} catch(PDOException $e) {
    error_log("Get captures error: " . $e->getMessage());
    Response::error(500, "Failed to fetch screen captures");
}
?>