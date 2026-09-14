<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error(405, "Method not allowed");
$userData = validateToken();
if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->team_id) || !isset($data->user_id)) Response::error(400, "team_id and user_id required");

$db = (new Database())->getConnection();

// Check if user is already in another team
$check = $db->prepare("SELECT team_id FROM team_members WHERE user_id = :uid");
$check->execute([':uid' => $data->user_id]);
if ($check->rowCount() > 0) {
    $existing = $check->fetchColumn();
    if ($existing != $data->team_id) {
        Response::error(409, "This employee is already in another team");
    } else {
        Response::send(200, ["message" => "Already a member"]);
        exit;
    }
}

try {
    $stmt = $db->prepare("INSERT IGNORE INTO team_members (team_id, user_id) VALUES (:team_id, :user_id)");
    $stmt->bindParam(':team_id', $data->team_id);
    $stmt->bindParam(':user_id', $data->user_id);
    $stmt->execute();
    Response::send(200, ["message" => "Member added"]);
} catch (PDOException $e) {
    Response::error(500, "Error: " . $e->getMessage());
}
?>