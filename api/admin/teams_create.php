<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error(405, "Method not allowed");
$userData = validateToken();
if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->name)) Response::error(400, "Team name is required");

$db = (new Database())->getConnection();
$stmt = $db->prepare("INSERT INTO teams (name, created_by) VALUES (:name, :admin_id)");
$stmt->bindParam(':name', $data->name);
$stmt->bindParam(':admin_id', $userData['user_id']);
$stmt->execute();

Response::send(201, ["message" => "Team created", "team_id" => $db->lastInsertId()]);
?>