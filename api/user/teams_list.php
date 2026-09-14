<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$userData = validateToken();
$db = (new Database())->getConnection();

$stmt = $db->prepare("SELECT t.* FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE tm.user_id = :user_id");
$stmt->bindParam(':user_id', $userData['user_id']);
$stmt->execute();
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::send(200, ["teams" => $teams]);
?>