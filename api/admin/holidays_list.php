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
    $query = "SELECT * FROM holidays ORDER BY holiday_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, ["holidays" => $holidays]);
} catch(PDOException $e) {
    error_log("Holidays list error: " . $e->getMessage());
    Response::error(500, "Failed to fetch holidays: " . $e->getMessage());
}
?>