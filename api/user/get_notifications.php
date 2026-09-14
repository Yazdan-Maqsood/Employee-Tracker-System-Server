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
    // Get unread count
    $countQuery = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->execute();
    $unreadCount = $countStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

    // Get notifications (latest 50)
    $query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, [
        "notifications" => $notifications,
        "unread_count" => (int)$unreadCount
    ]);

} catch(PDOException $e) {
    Response::error(500, "Failed to fetch notifications");
}
?>