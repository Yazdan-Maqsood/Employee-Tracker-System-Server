<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/pusher.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$adminId = $userData['user_id'];

$data = json_decode(file_get_contents("php://input"));
$submissionId = $data->submission_id ?? null;
$status = $data->status ?? null;

if (!$submissionId || !$status) {
    Response::error(400, "Submission ID and status are required");
}

if (!in_array($status, ['approved', 'rejected'])) {
    Response::error(400, "Status must be 'approved' or 'rejected'");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Update submission status
    $query = "UPDATE task_submissions SET status = :status WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $submissionId, PDO::PARAM_INT);
    $stmt->execute();

    // Get submission and task details for notification
    $subQuery = "SELECT ts.*, t.title as task_title, t.assigned_to 
                 FROM task_submissions ts 
                 JOIN tasks t ON ts.task_id = t.id 
                 WHERE ts.id = :id";
    $subStmt = $db->prepare($subQuery);
    $subStmt->bindParam(':id', $submissionId, PDO::PARAM_INT);
    $subStmt->execute();
    $submission = $subStmt->fetch(PDO::FETCH_ASSOC);

    // Update main task status
    $taskQuery = "UPDATE tasks SET status = :status WHERE id = :task_id";
    $taskStmt = $db->prepare($taskQuery);
    $taskStmt->bindParam(':status', $status);
    $taskStmt->bindParam(':task_id', $submission['task_id'], PDO::PARAM_INT);
    $taskStmt->execute();

    // Send notification
    $notifType = $status === 'approved' ? 'task_approved' : 'task_rejected';
    $notifTitle = $status === 'approved' ? '✅ Task Approved' : '❌ Task Rejected';
    $notifMessage = $status === 'approved' 
        ? "Your submission for '" . $submission['task_title'] . "' has been approved!"
        : "Your submission for '" . $submission['task_title'] . "' was rejected.";

    $notificationData = [
        'type' => $notifType,
        'title' => $notifTitle,
        'message' => $notifMessage,
        'task_id' => (int)$submission['task_id'],
        'task_title' => $submission['task_title'],
        'submission_id' => (int)$submissionId,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Send via Pusher
    try {
        $pusher->trigger('employee-' . $submission['assigned_to'], 'notification', $notificationData);
    } catch (Exception $e) {
        error_log("Pusher notification failed: " . $e->getMessage());
    }

    // Store in database
    $notifQuery = "INSERT INTO notifications (user_id, type, title, message, task_id, is_read) 
                   VALUES (:user_id, :type, :title, :message, :task_id, 0)";
    $notifStmt = $db->prepare($notifQuery);
    $notifStmt->bindParam(':user_id', $submission['assigned_to'], PDO::PARAM_INT);
    $notifStmt->bindParam(':type', $notifType);
    $notifStmt->bindParam(':title', $notifTitle);
    $notifStmt->bindParam(':message', $notifMessage);
    $notifStmt->bindParam(':task_id', $submission['task_id'], PDO::PARAM_INT);
    $notifStmt->execute();

    Response::send(200, ["message" => "Submission " . $status . " successfully"]);

} catch(PDOException $e) {
    error_log("Review error: " . $e->getMessage());
    Response::error(500, "Failed to review submission");
}
?>