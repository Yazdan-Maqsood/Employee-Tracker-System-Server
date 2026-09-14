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

$database = new Database();
$db = $database->getConnection();

try {
    // --- LOCATION VALIDATION CHECK ---
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->lat) || !isset($data->lng)) {
        Response::error(400, "Location access is required for attendance.");
    }

    // Office coordinates
    $officeLat = 30.661770978901735; 
    $officeLng = 73.13606289934688; 
    $allowedRadius = 180;  // Allowed distance in meters
    $userLat = $data->lat;
    $userLng = $data->lng;

    // Haversine formula calculation
    $earthRadius = 6371000; // in meters
    $dLat = deg2rad($officeLat - $userLat);
    $dLon = deg2rad($officeLng - $userLng);
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($userLat)) * cos(deg2rad($officeLat)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;

    if ($distance > $allowedRadius) {
        $distanceAway = round($distance);
        Response::error(403, "You are $distanceAway meters away from the office. You must be inside to check out.");
    }
    // ---------------------------------

    // Check if there's an active check-in for today
    $checkQuery = "SELECT id, check_in, pause_start_time, total_pause_minutes 
                   FROM attendance 
                   WHERE user_id = :user_id 
                   AND DATE(check_in) = CURDATE() 
                   AND check_out IS NULL 
                   LIMIT 1";
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        Response::error(404, "No active check-in found for today");
    }
    
    $record = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $currentTime = date('Y-m-d H:i:s');
    
    // ✅ Calculate final pause time if currently paused
    $totalPauseMinutes = $record['total_pause_minutes'] ?? 0;
    
    if (!empty($record['pause_start_time'])) {
        $pauseStart = new DateTime($record['pause_start_time']);
        $now = new DateTime($currentTime);
        $pauseDuration = $pauseStart->diff($now);
        $currentPauseMins = ($pauseDuration->h * 60) + $pauseDuration->i;
        $totalPauseMinutes += $currentPauseMins;
    }
    
    // Calculate actual working minutes (total time - pause time)
    $checkIn = new DateTime($record['check_in']);
    $checkOut = new DateTime($currentTime);
    $totalDiff = $checkIn->diff($checkOut);
    $totalMinutes = ($totalDiff->h * 60) + $totalDiff->i;
    $actualWorkingMinutes = $totalMinutes - $totalPauseMinutes;
    
    // Update check-out with pause calculations
    $query = "UPDATE attendance 
              SET check_out = :current_time,
                  pause_start_time = NULL,
                  total_pause_minutes = :total_pause_minutes
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':current_time', $currentTime);
    $stmt->bindParam(':total_pause_minutes', $totalPauseMinutes, PDO::PARAM_INT);
    $stmt->bindParam(':id', $record['id']);
    
    if ($stmt->execute()) {
        // Fetch updated record
        $fetchQuery = "SELECT * FROM attendance WHERE id = :id LIMIT 1";
        $fetchStmt = $db->prepare($fetchQuery);
        $fetchStmt->bindParam(':id', $record['id']);
        $fetchStmt->execute();
        
        $updatedRecord = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        
        Response::send(200, [
            "message" => "Check-out successful",
            "record" => $updatedRecord,
            "total_pause_minutes" => $totalPauseMinutes,
            "actual_working_minutes" => $actualWorkingMinutes
        ]);
    } else {
        Response::error(500, "Check-out failed");
    }
    
} catch(PDOException $e) {
    Response::error(500, "Check-out failed: " . $e->getMessage());
}
?>