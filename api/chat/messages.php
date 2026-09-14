<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$database = new Database();
$db = $database->getConnection();

// Get admin ID (first admin) – still needed for default
$stmt = $db->query("SELECT id FROM employees WHERE role='admin' LIMIT 1");
$adminId = $stmt->fetchColumn();
if (!$adminId) Response::error(500, "No admin found");

$isAdmin = $userData['role'] === 'admin';
$currentUserId = $userData['user_id'];

if ($isAdmin) {
    // Admin must specify a user_id to chat with
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) Response::error(400, "user_id required for admin");
    $otherUserId = $userId;
} else {
    // Non-admin can optionally specify a user_id to chat with someone specific
    // If not provided, default to admin
    $userId = $_GET['user_id'] ?? null;
    $otherUserId = $userId ? $userId : $adminId;
}

$query = "SELECT * FROM chat_messages 
          WHERE (sender_id = :uid1 AND receiver_id = :uid2) 
             OR (sender_id = :uid2 AND receiver_id = :uid1) 
          ORDER BY created_at ASC";
          
$stmt = $db->prepare($query);
$stmt->bindParam(':uid1', $currentUserId);
$stmt->bindParam(':uid2', $otherUserId);
$stmt->execute();

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::send(200, [
    "messages" => $messages,
    "admin_id" => $adminId,
    "other_user_id" => $otherUserId
]);
?>