<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->gps_enabled)) {
    Response::error(400, "GPS status is required");
}

$database = new Database();
$db = $database->getConnection();

try {
    $currentTime = date('Y-m-d H:i:s');
    $gpsEnabled = $data->gps_enabled ? true : false;
    
    // ✅ Check working hours (9 AM - 5 PM)
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    
    if ($currentHour < 9 || $currentHour >= 17) {
        Response::send(200, [
            "status" => "outside_working_hours",
            "message" => "Outside working hours (9 AM - 5 PM)"
        ]);
        return;
    }
    
    // ✅ Check break time (1:30 PM - 2:00 PM)
    $currentTotalMinutes = ($currentHour * 60) + $currentMinute;
    $breakStartMinutes = (13 * 60) + 30;
    $breakEndMinutes = (14 * 60) + 0;
    
    if ($currentTotalMinutes >= $breakStartMinutes && $currentTotalMinutes <= $breakEndMinutes) {
        Response::send(200, [
            "status" => "break_time",
            "message" => "Break time (1:30 PM - 2:00 PM). GPS tracking paused."
        ]);
        return;
    }
    
    // ✅ Find today's active attendance
    $attendanceQuery = "SELECT id, check_in, check_out, pause_start_time, total_pause_minutes 
                        FROM attendance 
                        WHERE user_id = :user_id 
                        AND DATE(check_in) = CURDATE() 
                        ORDER BY id DESC 
                        LIMIT 1";
    
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $attendanceStmt->execute();
    
    if ($attendanceStmt->rowCount() === 0) {
        Response::send(200, [
            "status" => "no_active_attendance",
            "message" => "No active attendance record for today"
        ]);
        return;
    }
    
    $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
    $attendanceId = $attendance['id'];
    
    if (!empty($attendance['check_out'])) {
        Response::send(200, [
            "status" => "already_checked_out",
            "message" => "Already checked out for today"
        ]);
        return;
    }
    
    if (!$gpsEnabled) {
        // ========== GPS OFF ==========
        if (empty($attendance['pause_start_time'])) {
            // Start pause
            $pauseQuery = "UPDATE attendance 
                          SET pause_start_time = :pause_start
                          WHERE id = :id";
            
            $pauseStmt = $db->prepare($pauseQuery);
            $pauseStmt->bindParam(':pause_start', $currentTime);
            $pauseStmt->bindParam(':id', $attendanceId, PDO::PARAM_INT);
            $pauseStmt->execute();
            
            Response::send(200, [
                "status" => "paused",
                "message" => "GPS OFF - Timer paused at " . $currentTime,
                "paused_at" => $currentTime,
                "total_pause_minutes" => $attendance['total_pause_minutes'] ?? 0
            ]);
        } else {
            // Already paused
            $pauseStart = new DateTime($attendance['pause_start_time']);
            $now = new DateTime($currentTime);
            $pauseDuration = $pauseStart->diff($now);
            $currentPauseMinutes = ($pauseDuration->h * 60) + $pauseDuration->i;
            
            Response::send(200, [
                "status" => "already_paused",
                "message" => "GPS OFF - Already paused",
                "paused_at" => $attendance['pause_start_time'],
                "current_pause_minutes" => $currentPauseMinutes,
                "total_pause_minutes" => $attendance['total_pause_minutes'] ?? 0
            ]);
        }
        
    } else {
        // ========== GPS ON ==========
        if (!empty($attendance['pause_start_time'])) {
            // Calculate pause duration
            $pauseStart = new DateTime($attendance['pause_start_time']);
            $now = new DateTime($currentTime);
            $pauseDuration = $pauseStart->diff($now);
            
            $pauseMinutes = ($pauseDuration->h * 60) + $pauseDuration->i;
            if ($pauseDuration->days > 0) {
                $pauseMinutes += ($pauseDuration->days * 24 * 60);
            }
            
            // Add to total
            $newTotalPause = ($attendance['total_pause_minutes'] ?? 0) + $pauseMinutes;
            
            // Resume
            $resumeQuery = "UPDATE attendance 
                           SET pause_start_time = NULL,
                               total_pause_minutes = :total_pause
                           WHERE id = :id";
            
            $resumeStmt = $db->prepare($resumeQuery);
            $resumeStmt->bindParam(':total_pause', $newTotalPause, PDO::PARAM_INT);
            $resumeStmt->bindParam(':id', $attendanceId, PDO::PARAM_INT);
            $resumeStmt->execute();
            
            Response::send(200, [
                "status" => "resumed",
                "message" => "GPS ON - Timer resumed. Pause added: {$pauseMinutes} min",
                "pause_minutes_added" => $pauseMinutes,
                "total_pause_minutes" => $newTotalPause
            ]);
        } else {
            Response::send(200, [
                "status" => "active",
                "message" => "GPS ON - Already active",
                "total_pause_minutes" => $attendance['total_pause_minutes'] ?? 0
            ]);
        }
    }
    
} catch(PDOException $e) {
    error_log("GPS status DB error: " . $e->getMessage());
    Response::error(500, "Server error");
} catch(Exception $e) {
    error_log("GPS status error: " . $e->getMessage());
    Response::error(500, "Server error");
}
?>