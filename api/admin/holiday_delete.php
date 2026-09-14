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

if (!isset($_GET['id'])) {
    Response::error(400, "Holiday ID is required");
}

$holidayId = intval($_GET['id']);

$database = new Database();
$db = $database->getConnection();

try {
    $query = "DELETE FROM holidays WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $holidayId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            Response::send(200, ["message" => "Holiday deleted successfully"]);
        } else {
            Response::error(404, "Holiday not found");
        }
    } else {
        Response::error(500, "Failed to delete holiday");
    }
} catch(PDOException $e) {
    error_log("Holiday delete error: " . $e->getMessage());
    Response::error(500, "Failed to delete holiday: " . $e->getMessage());
}
?>