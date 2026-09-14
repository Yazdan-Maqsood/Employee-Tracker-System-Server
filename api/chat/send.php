<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/pusher.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$senderId = $userData['user_id'];

$data = json_decode(file_get_contents("php://input"));
$receiverId = $data->receiver_id ?? null;
$message = $data->message ?? '';

if (!$receiverId || empty($message)) {
    Response::error(400, "Receiver ID and message are required");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Insert message
    $query = "INSERT INTO chat_messages (sender_id, receiver_id, message) 
              VALUES (:sender_id, :receiver_id, :message)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
    $stmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message);
    $stmt->execute();
    
    $messageId = $db->lastInsertId();

    // Get sender name
    $senderQuery = "SELECT employeeName FROM employees WHERE id = :id";
    $senderStmt = $db->prepare($senderQuery);
    $senderStmt->bindParam(':id', $senderId, PDO::PARAM_INT);
    $senderStmt->execute();
    $sender = $senderStmt->fetch(PDO::FETCH_ASSOC);
    $senderName = $sender['employeeName'] ?? 'User';

    // ✅ Send Pusher event for real-time chat update (existing)
    $pusher->trigger('chat', 'new-message', [
        'id' => $messageId,
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'message' => $message,
        'sender_name' => $senderName,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // ✅ NEW: Send notification to receiver (for Flutter push notification)
    $notificationData = [
        'type' => 'chat_message',
        'title' => '💬 New Message',
        'message' => $senderName . ': ' . (strlen($message) > 100 ? substr($message, 0, 97) . '...' : $message),
        'sender_id' => (int)$senderId,
        'sender_name' => $senderName,
        'message_id' => (int)$messageId,
        'chat_type' => 'private',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    $pusher->trigger('employee-' . $receiverId, 'notification', $notificationData);

    // Store notification in DB
    try {
        $notifQuery = "INSERT INTO notifications (user_id, type, title, message, is_read, created_at) 
                       VALUES (:user_id, 'chat_message', :title, :message, 0, NOW())";
        $notifStmt = $db->prepare($notifQuery);
        $notifStmt->bindParam(':user_id', $receiverId, PDO::PARAM_INT);
        $notifStmt->bindParam(':title', $notificationData['title']);
        $notifStmt->bindParam(':message', $notificationData['message']);
        $notifStmt->execute();
    } catch (Exception $e) {
        error_log("Chat notification DB: " . $e->getMessage());
    }

    Response::send(201, [
        "message" => "Message sent",
        "message_id" => $messageId
    ]);

} catch(PDOException $e) {
    Response::error(500, "Failed to send message");
}
?>