<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$userData = validateToken();
$teamId = $_GET['team_id'] ?? null;
if (!$teamId) Response::error(400, "team_id required");

$db = (new Database())->getConnection();

// ✅ Admin bypass
$isAdmin = $userData['role'] === 'admin';
if (!$isAdmin) {
    $check = $db->prepare("SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id");
    $check->bindParam(':team_id', $teamId);
    $check->bindParam(':user_id', $userData['user_id']);
    $check->execute();
    if ($check->rowCount() === 0) Response::error(403, "Not a member of this team");
}

$stmt = $db->prepare("SELECT tm.*, e.employeeName as sender_name FROM team_messages tm JOIN employees e ON tm.sender_id = e.id WHERE tm.team_id = :team_id ORDER BY tm.created_at ASC");
$stmt->bindParam(':team_id', $teamId);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::send(200, ["messages" => $messages]);
?>