<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/rate_limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

$platform = 'web';
if (isset($_SERVER['HTTP_X_PLATFORM']) && $_SERVER['HTTP_X_PLATFORM'] === 'mobile') {
    $platform = 'mobile';
}
if (isset($_SERVER['HTTP_USER_AGENT']) && (
    stripos($_SERVER['HTTP_USER_AGENT'], 'flutter') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'dart') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'okhttp') !== false
)) {
    $platform = 'mobile';
}

$maxRequests = ($platform === 'mobile') ? 4 : 2;
if (!rateLimit($userId, '/attendance/track_location', $maxRequests, 60, $platform)) {
    Response::send(429, [
        "status" => "rate_limited",
        "message" => "Too many requests. Please wait.",
        "platform" => $platform,
        "retry_after" => 15
    ]);
    return;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->lat) || !isset($data->lng)) {
    Response::error(400, "Location data is required");
}

$database = new Database();
$db = $database->getConnection();

try {
    // ✅ Check if employee is active (status = 1)
    $statusQuery = "SELECT status FROM employees WHERE id = :user_id";
    $statusStmt = $db->prepare($statusQuery);
    $statusStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $statusStmt->execute();
    $employeeStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);

    if ($employeeStatus && $employeeStatus['status'] == 0) {
        Response::error(403, "Your account is deactivated. Please contact admin.");
    }

    $currentTime = date('Y-m-d H:i:s');
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    
    $officeLat = 30.661770978901735;
    $officeLng = 73.13606289934688;
    $allowedRadius = 180;
    $userLat = floatval($data->lat);
    $userLng = floatval($data->lng);
    $accuracy = isset($data->accuracy) ? floatval($data->accuracy) : null;
    
    $earthRadius = 6371000;
    $dLat = deg2rad($officeLat - $userLat);
    $dLon = deg2rad($officeLng - $userLng);
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($userLat)) * cos(deg2rad($officeLat)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    $isInsideOffice = $distance <= $allowedRadius;
    $distanceRounded = round($distance);
    
    try {
        $isInsideInt = $isInsideOffice ? 1 : 0;
        $upsertQuery = "INSERT INTO employee_locations (user_id, latitude, longitude, accuracy, is_inside, last_updated) 
                        VALUES (:user_id, :lat, :lng, :accuracy, :is_inside, NOW())
                        ON DUPLICATE KEY UPDATE 
                            latitude = :lat2, 
                            longitude = :lng2, 
                            accuracy = :accuracy2, 
                            is_inside = :is_inside2,
                            last_updated = NOW()";
        
        $upsertStmt = $db->prepare($upsertQuery);
        $upsertStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $upsertStmt->bindParam(':lat', $userLat);
        $upsertStmt->bindParam(':lng', $userLng);
        $upsertStmt->bindParam(':accuracy', $accuracy);
        $upsertStmt->bindParam(':is_inside', $isInsideInt, PDO::PARAM_INT);
        $upsertStmt->bindParam(':lat2', $userLat);
        $upsertStmt->bindParam(':lng2', $userLng);
        $upsertStmt->bindParam(':accuracy2', $accuracy);
        $upsertStmt->bindParam(':is_inside2', $isInsideInt, PDO::PARAM_INT);
        $upsertStmt->execute();
    } catch (Exception $e) {
        error_log("Location save error (non-critical): " . $e->getMessage());
    }
    
    if ($currentHour < 9 || $currentHour >= 17) {
        Response::send(200, [
            "status" => "outside_working_hours",
            "distance" => $distanceRounded,
            "is_inside" => $isInsideOffice,
            "message" => "Outside working hours (9 AM - 5 PM)"
        ]);
        return;
    }
    
    $currentTotalMinutes = ($currentHour * 60) + $currentMinute;
    $breakStartMinutes = (13 * 60) + 30;
    $breakEndMinutes = (14 * 60) + 0;
    
    if ($currentTotalMinutes >= $breakStartMinutes && $currentTotalMinutes <= $breakEndMinutes) {
        Response::send(200, [
            "status" => "break_time",
            "distance" => $distanceRounded,
            "is_inside" => $isInsideOffice,
            "message" => "Break time (1:30 PM - 2:00 PM). Location tracking paused."
        ]);
        return;
    }
    
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
            "status" => "no_attendance",
            "distance" => $distanceRounded,
            "is_inside" => $isInsideOffice,
            "message" => "No attendance record for today"
        ]);
        return;
    }
    
    $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
    $attendanceId = $attendance['id'];
    
    if (!empty($attendance['check_out'])) {
        Response::send(200, [
            "status" => "already_checked_out",
            "distance" => $distanceRounded,
            "is_inside" => $isInsideOffice,
            "message" => "Already checked out for today"
        ]);
        return;
    }
    
    $isPaused = !empty($attendance['pause_start_time']);
    
    if ($isInsideOffice) {
        if ($isPaused) {
            $pauseStart = new DateTime($attendance['pause_start_time']);
            $now = new DateTime($currentTime);
            $pauseDuration = $pauseStart->diff($now);
            $pauseMinutes = ($pauseDuration->h * 60) + $pauseDuration->i;
            $newTotalPause = ($attendance['total_pause_minutes'] ?? 0) + $pauseMinutes;
            
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
                "message" => "Timer resumed! Pause: {$pauseMinutes} min",
                "distance" => $distanceRounded,
                "is_inside" => true,
                "pause_minutes_added" => $pauseMinutes,
                "total_pause_minutes" => $newTotalPause
            ]);
        } else {
            Response::send(200, [
                "status" => "active",
                "message" => "Inside office",
                "distance" => $distanceRounded,
                "is_inside" => true,
                "total_pause_minutes" => $attendance['total_pause_minutes'] ?? 0
            ]);
        }
    } else {
        if (!$isPaused) {
            $pauseQuery = "UPDATE attendance 
                          SET pause_start_time = :pause_start
                          WHERE id = :id";
            
            $pauseStmt = $db->prepare($pauseQuery);
            $pauseStmt->bindParam(':pause_start', $currentTime);
            $pauseStmt->bindParam(':id', $attendanceId, PDO::PARAM_INT);
            $pauseStmt->execute();
            
            Response::send(200, [
                "status" => "paused",
                "message" => "Timer paused! Distance: {$distanceRounded}m",
                "distance" => $distanceRounded,
                "is_inside" => false,
                "paused_at" => $currentTime,
                "total_pause_minutes" => $attendance['total_pause_minutes'] ?? 0
            ]);
        } else {
            $pauseStart = new DateTime($attendance['pause_start_time']);
            $now = new DateTime($currentTime);
            $currentPauseMinutes = ($pauseStart->diff($now)->h * 60) + $pauseStart->diff($now)->i;
            
            Response::send(200, [
                "status" => "paused",
                "message" => "Still outside. {$currentPauseMinutes} min",
                "distance" => $distanceRounded,
                "is_inside" => false,
                "paused_at" => $attendance['pause_start_time'],
                "current_pause_minutes" => $currentPauseMinutes,
                "total_pause_minutes" => $attendance['total_pause_minutes'] ?? 0
            ]);
        }
    }
    
} catch(PDOException $e) {
    error_log("Track location DB error: " . $e->getMessage());
    Response::error(500, "Server error");
} catch(Exception $e) {
    error_log("Track location error: " . $e->getMessage());
    Response::error(500, "Server error");
}
?>