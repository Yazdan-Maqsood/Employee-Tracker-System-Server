<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

// Try to load Pusher
$pusher = null;
try {
    if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $pusher = new Pusher\Pusher(
            '540d521fe5fe005cb99d',
            '6252d04d62d5a05792fd',
            '2172871',
            ['cluster' => 'ap2', 'useTLS' => true]
        );
    }
} catch (Exception $e) {
    error_log("Pusher init failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$adminId = $userData['user_id'];

if ($userData['role'] !== 'admin') {
    Response::error(403, "Only admins can request screen recordings");
}

$data = json_decode(file_get_contents("php://input"));
$employeeId = $data->employee_id ?? null;
$duration = $data->duration ?? 10;

if (!$employeeId) {
    Response::error(400, "Employee ID is required");
}

if ($duration < 5) $duration = 5;
if ($duration > 60) $duration = 60;

$database = new Database();
$db = $database->getConnection();

try {
    $checkQuery = "SELECT id, employeeName FROM employees WHERE id = :employee_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        Response::error(404, "Employee not found");
    }
    
    $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    $query = "INSERT INTO screen_captures (employee_id, requested_by, capture_type, status) 
              VALUES (:employee_id, :admin_id, 'video', 'pending')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
    $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
    
    $captureId = $db->lastInsertId();
    
    $pusherSent = false;
    if ($pusher) {
        try {
            $pusher->trigger('employee-' . $employeeId, 'screen-capture-request', [
                'capture_id' => $captureId,
                'capture_type' => 'video',
                'duration' => $duration,
                'requested_by' => $adminId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            $pusherSent = true;
        } catch (Exception $e) {
            error_log("Pusher trigger failed: " . $e->getMessage());
        }
    }
    
    Response::send(200, [
        'message' => 'Screen recording requested successfully',
        'capture_id' => $captureId,
        'duration' => $duration,
        'employee_name' => $employee['employeeName'],
        'pusher_sent' => $pusherSent
    ]);
    
} catch(PDOException $e) {
    error_log("Screen record DB error: " . $e->getMessage());
    Response::error(500, "Database error: " . $e->getMessage());
}
?>