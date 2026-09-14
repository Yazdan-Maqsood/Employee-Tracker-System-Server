<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/pusher.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();

$receiverId = $_POST['receiver_id'] ?? null;
$message    = $_POST['message'] ?? '';
$file       = $_FILES['file'] ?? null;

if (!$receiverId || !$file) {
    Response::error(400, "receiver_id and file are required");
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    Response::error(400, "File upload error");
}

$database = new Database();
$db = $database->getConnection();

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../../uploads/chat/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ✅ Keep the original filename
$originalName = basename($file['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);

// ✅ Use original name but add a short unique prefix to prevent overwrites
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$filename = time() . '_' . $safeName . '.' . $extension;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    Response::error(500, "Failed to store file");
}

$filePath = 'uploads/chat/' . $filename;

try {
    // ✅ Store both file_path and original file name
    $query = "INSERT INTO chat_messages (sender_id, receiver_id, message, file_path, file_name)
              VALUES (:sender, :receiver, :msg, :file_path, :file_name)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':sender', $userData['user_id']);
    $stmt->bindParam(':receiver', $receiverId);
    $stmt->bindParam(':msg', $message);
    $stmt->bindParam(':file_path', $filePath);
    $stmt->bindParam(':file_name', $originalName);  // ✅ Store original name
    $stmt->execute();
    $messageId = $db->lastInsertId();

    // Get sender name
    $senderStmt = $db->prepare("SELECT employeeName FROM employees WHERE id = :id");
    $senderStmt->execute([':id' => $userData['user_id']]);
    $senderName = $senderStmt->fetchColumn() ?: 'Unknown';

    // Pusher event
    $options = ['cluster' => PUSHER_CLUSTER, 'useTLS' => true];
    $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, $options);

    $payload = [
        'id'           => $messageId,
        'sender_id'    => $userData['user_id'],
        'sender_name'  => $senderName,
        'receiver_id'  => $receiverId,
        'message'      => $message,
        'file_path'    => $filePath,
        'file_name'    => $originalName,  // ✅ Include original file name
        'pinned'       => 0,
        'created_at'   => date('Y-m-d H:i:s')
    ];

    $pusher->trigger('chat', 'new-message', $payload);

    Response::send(201, ["message" => "File sent"]);
} catch (Exception $e) {
    Response::error(500, "Error: " . $e->getMessage());
}
?>