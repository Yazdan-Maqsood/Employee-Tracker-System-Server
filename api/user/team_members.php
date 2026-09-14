<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$userData = validateToken();
$teamId = $_GET['team_id'] ?? null;
if (!$teamId) Response::error(400, "team_id required");

$db = (new Database())->getConnection();

// Check if current user is a member of this team
$check = $db->prepare("SELECT 1 FROM team_members WHERE team_id = :tid AND user_id = :uid");
$check->execute([':tid' => $teamId, ':uid' => $userData['user_id']]);
if ($check->rowCount() === 0) Response::error(403, "Not a member");

// Get all members of the team
$stmt = $db->prepare("SELECT e.id, e.employeeName, e.employee_fields, e.role
                      FROM team_members tm
                      JOIN employees e ON tm.user_id = e.id
                      WHERE tm.team_id = :tid");
$stmt->execute([':tid' => $teamId]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::send(200, ["members" => $members]);
?>