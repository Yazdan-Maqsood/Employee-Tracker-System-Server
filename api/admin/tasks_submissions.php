<?php
    require_once __DIR__ . '/../../middleware/cors.php';
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/Response.php';

    $userData = validateToken();
    if ($userData['role'] !== 'admin') Response::error(403, "Admin only");

    $taskId = $_GET['task_id'] ?? null;
    if (!$taskId) Response::error(400, "task_id required");

    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT s.*, e.employeeName 
                        FROM task_submissions s 
                        JOIN employees e ON s.submitted_by = e.id 
                        WHERE s.task_id = ? 
                        ORDER BY s.submitted_at DESC");
    $stmt->execute([$taskId]);
    Response::send(200, ["submissions" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
?>