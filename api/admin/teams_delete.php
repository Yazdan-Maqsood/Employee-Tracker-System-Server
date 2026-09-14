<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') Response::error(405, "Method not allowed");
$userData = validateToken();
if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

$teamId = $_GET['team_id'] ?? null;
if (!$teamId) Response::error(400, "team_id required");

$db = (new Database())->getConnection();
$db->prepare("DELETE FROM teams WHERE id = :id")->execute([':id' => $teamId]);

Response::send(200, ["message" => "Team deleted"]);
?>