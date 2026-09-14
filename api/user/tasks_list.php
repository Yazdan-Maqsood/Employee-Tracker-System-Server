<?php
    require_once __DIR__ . '/../../middleware/cors.php';
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/Response.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error(405, "Method not allowed");
    }

    $userData = validateToken();

    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT t.*, 
                    a.employeeName as assigned_by_name
            FROM tasks t 
            JOIN employees a ON t.assigned_by = a.id
            WHERE t.assigned_to = :user_id 
            ORDER BY t.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userData['user_id']);
    $stmt->execute();

    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, ["tasks" => $tasks]);
?>