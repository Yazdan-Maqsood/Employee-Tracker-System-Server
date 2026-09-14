<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') Response::error(405, "Method not allowed");
$userData = validateToken();
if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

$teamId = $_GET['team_id'] ?? null;
$userId = $_GET['user_id'] ?? null;
if (!$teamId || !$userId) Response::error(400, "team_id and user_id required");

$db = (new Database())->getConnection();
$stmt = $db->prepare("DELETE FROM team_members WHERE team_id = :team_id AND user_id = :user_id");
$stmt->bindParam(':team_id', $teamId);
$stmt->bindParam(':user_id', $userId);
$stmt->execute();

Response::send(200, ["message" => "Member removed"]);
?>