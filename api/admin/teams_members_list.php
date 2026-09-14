<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$userData = validateToken();
if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

$teamId = $_GET['team_id'] ?? null;
if (!$teamId) Response::error(400, "team_id required");

$db = (new Database())->getConnection();
$stmt = $db->prepare("SELECT e.id, e.employeeName, e.email, e.role
                      FROM team_members tm
                      JOIN employees e ON tm.user_id = e.id
                      WHERE tm.team_id = :team_id");
$stmt->bindParam(':team_id', $teamId);
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::send(200, ["members" => $members]);
?>