<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$userData = validateToken();
$userId = $userData['user_id'];

$db = (new Database())->getConnection();

// Get admin
$admin = $db->query("SELECT id, employeeName, role, employee_fields FROM employees WHERE role='admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$response = ['admin' => $admin ?: null, 'teams' => []];

// Get teams with members
$stmt = $db->prepare("SELECT t.id, t.name FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE tm.user_id = :uid");
$stmt->bindParam(':uid', $userId);
$stmt->execute();
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($teams as &$team) {
    $stmt2 = $db->prepare("SELECT e.id, e.employeeName, e.role, e.employee_fields FROM team_members tm JOIN employees e ON tm.user_id = e.id WHERE tm.team_id = :tid");
    $stmt2->bindParam(':tid', $team['id']);
    $stmt2->execute();
    $team['members'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

$response['teams'] = $teams;
Response::send(200, $response);
?>