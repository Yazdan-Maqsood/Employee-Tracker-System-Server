<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') Response::error(405, "Method not allowed");
$userData = validateToken();
if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->team_id) || !isset($data->name)) {
    Response::error(400, "team_id and name required");
}

$db = (new Database())->getConnection();
$stmt = $db->prepare("UPDATE teams SET name = :name WHERE id = :id");
$stmt->bindParam(':name', $data->name);
$stmt->bindParam(':id', $data->team_id);
$stmt->execute();

Response::send(200, ["message" => "Team updated"]);
?>