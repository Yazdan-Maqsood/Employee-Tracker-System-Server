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

$teamId = $_POST['team_id'] ?? null;
$message = $_POST['message'] ?? '';

if (!$teamId) {
    Response::error(400, "Team ID is required");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Handle file upload
    $filePath = null;
    $fileName = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/chat/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $fileExtension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fileName = $_FILES['file']['name'];
        $storedName = 'team_' . time() . '_' . uniqid() . '.' . $fileExtension;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $storedName)) {
            $filePath = 'uploads/chat/' . $storedName;
        }
    }

    // Insert team message
    $query = "INSERT INTO team_messages (team_id, sender_id, message, file_path, file_name) 
              VALUES (:team_id, :sender_id, :message, :file_path, :file_name)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':team_id', $teamId, PDO::PARAM_INT);
    $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':file_path', $filePath);
    $stmt->bindParam(':file_name', $fileName);
    $stmt->execute();

    $messageId = $db->lastInsertId();

    // Get sender name and team name
    $senderName = 'User';
    $teamName = 'Team';
    try {
        $senderQuery = "SELECT employeeName FROM employees WHERE id = :id";
        $senderStmt = $db->prepare($senderQuery);
        $senderStmt->bindParam(':id', $senderId, PDO::PARAM_INT);
        $senderStmt->execute();
        $sender = $senderStmt->fetch(PDO::FETCH_ASSOC);
        if ($sender) $senderName = $sender['employeeName'];

        $teamQuery = "SELECT name FROM teams WHERE id = :id";
        $teamStmt = $db->prepare($teamQuery);
        $teamStmt->bindParam(':id', $teamId, PDO::PARAM_INT);
        $teamStmt->execute();
        $team = $teamStmt->fetch(PDO::FETCH_ASSOC);
        if ($team) $teamName = $team['name'];
    } catch (Exception $e) {}

    // Send Pusher event to team channel (existing)
    $pusher->trigger('team-' . $teamId, 'new-message', [
        'id' => $messageId,
        'team_id' => $teamId,
        'sender_id' => $senderId,
        'message' => $message,
        'file_path' => $filePath,
        'file_name' => $fileName,
        'sender_name' => $senderName,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // ✅ NEW: Send notification to ALL team members (except sender)
    $membersQuery = "SELECT user_id FROM team_members WHERE team_id = :team_id AND user_id != :sender_id";
    $membersStmt = $db->prepare($membersQuery);
    $membersStmt->bindParam(':team_id', $teamId, PDO::PARAM_INT);
    $membersStmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
    $membersStmt->execute();
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

    $previewMsg = $filePath ? '📎 Sent a file' : (strlen($message) > 80 ? substr($message, 0, 77) . '...' : $message);

    foreach ($members as $member) {
        $notificationData = [
            'type' => 'chat_message',
            'title' => '💬 ' . $teamName,
            'message' => $senderName . ': ' . $previewMsg,
            'sender_id' => (int)$senderId,
            'sender_name' => $senderName,
            'message_id' => (int)$messageId,
            'team_id' => (int)$teamId,
            'team_name' => $teamName,
            'chat_type' => 'team',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Pusher notification to each member
        $pusher->trigger('employee-' . $member['user_id'], 'notification', $notificationData);

        // Store in DB
        try {
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, is_read, created_at) 
                           VALUES (:user_id, 'chat_message', :title, :message, 0, NOW())";
            $notifStmt = $db->prepare($notifQuery);
            $notifStmt->bindParam(':user_id', $member['user_id'], PDO::PARAM_INT);
            $notifStmt->bindParam(':title', $notificationData['title']);
            $notifStmt->bindParam(':message', $notificationData['message']);
            $notifStmt->execute();
        } catch (Exception $e) {}
    }

    Response::send(201, [
        "message" => "Team message sent",
        "message_id" => $messageId
    ]);

} catch(PDOException $e) {
    error_log("Team message error: " . $e->getMessage());
    Response::error(500, "Failed to send team message");
}
?>