<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
if ($userData['role'] !== 'admin') {
    Response::error(403, "Admin only");
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT t.*, 
                 a.employeeName as assigned_by_name,
                 u.employeeName as assigned_to_name,
                 ls.file_path as submission_file_path,
                 ls.status as submission_status
          FROM tasks t
          JOIN employees a ON t.assigned_by = a.id
          JOIN employees u ON t.assigned_to = u.id
          LEFT JOIN (
              SELECT task_id, file_path, status
              FROM task_submissions
              WHERE id IN (
                  SELECT MAX(id) 
                  FROM task_submissions 
                  GROUP BY task_id
              )
          ) ls ON t.id = ls.task_id
          WHERE 1=1";

$params = [];

if (isset($_GET['assigned_to']) && $_GET['assigned_to'] !== '') {
    $query .= " AND t.assigned_to = :assigned_to";
    $params[':assigned_to'] = $_GET['assigned_to'];
}
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $query .= " AND t.status = :status";
    $params[':status'] = $_GET['status'];
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::send(200, ["tasks" => $tasks]);