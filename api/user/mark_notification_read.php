<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

$data = json_decode(file_get_contents("php://input"));
$notificationId = $data->notification_id ?? null;

$database = new Database();
$db = $database->getConnection();

try {
    if ($notificationId) {
        // Mark single notification as read
        $query = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
    } else {
        // Mark all as read
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $db->prepare($query);
    }
    
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    Response::send(200, ["message" => "Notification(s) marked as read"]);

} catch(PDOException $e) {
    Response::error(500, "Failed to update notification");
}
?>