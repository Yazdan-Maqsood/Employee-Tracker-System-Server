<?php
    require_once __DIR__ . '/../../middleware/cors.php';
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/Response.php';

    $userData = validateToken();
    $taskId = $_GET['task_id'] ?? null;
    if (!$taskId) Response::error(400, "task_id required");

    $db = (new Database())->getConnection();
    // verify task ownership
    $check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
    $check->execute([$taskId, $userData['user_id']]);
    if ($check->rowCount() === 0) Response::error(403, "Not your task");

    // File upload
    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/tasks/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = time() . '_' . basename($_FILES['file']['name']);
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename);
        $filePath = 'uploads/tasks/' . $filename;
    }

    $comment = $_POST['comment'] ?? '';
    $stmt = $db->prepare("INSERT INTO task_submissions (task_id, submitted_by, file_path, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$taskId, $userData['user_id'], $filePath, $comment]);

    // Mark task as submitted
    $db->prepare("UPDATE tasks SET status = 'submitted' WHERE id = ?")->execute([$taskId]);

    Response::send(201, ["message" => "Submitted successfully"]);
?>