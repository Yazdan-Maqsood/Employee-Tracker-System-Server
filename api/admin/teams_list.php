<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$userData = validateToken();
if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

$db = (new Database())->getConnection();

// Get all teams with member count and member IDs
$teams = $db->query("SELECT t.*, COUNT(tm.user_id) as member_count
                     FROM teams t
                     LEFT JOIN team_members tm ON t.id = tm.team_id
                     GROUP BY t.id
                     ORDER BY t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// For each team, get the list of member IDs
foreach ($teams as &$team) {
    $stmt = $db->prepare("SELECT user_id FROM team_members WHERE team_id = :tid");
    $stmt->execute([':tid' => $team['id']]);
    $team['member_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
unset($team);

Response::send(200, ["teams" => $teams]);
?>