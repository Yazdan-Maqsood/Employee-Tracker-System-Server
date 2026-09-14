<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
if ($userData['role'] !== 'admin') {
    Response::error(403, "Admin only");
}

$taskId = $_GET['task_id'] ?? null;
if (!$taskId) {
    Response::error(400, "Task ID is required");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Delete associated submissions first (if not using ON DELETE CASCADE)
    $db->prepare("DELETE FROM task_submissions WHERE task_id = ?")->execute([$taskId]);

    // Delete the task
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);

    if ($stmt->rowCount() > 0) {
        Response::send(200, ["message" => "Task deleted successfully"]);
    } else {
        Response::error(404, "Task not found");
    }
} catch (PDOException $e) {
    Response::error(500, "Database error: " . $e->getMessage());
}

?>