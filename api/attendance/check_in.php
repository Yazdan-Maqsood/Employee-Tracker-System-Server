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
    // ✅ Check if employee is active (status = 1)
    $statusQuery = "SELECT status FROM employees WHERE id = :user_id";
    $statusStmt = $db->prepare($statusQuery);
    $statusStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $statusStmt->execute();
    $employeeStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);

    if ($employeeStatus && $employeeStatus['status'] == 0) {
        Response::error(403, "Your account is deactivated. Please contact admin.");
    }

    // --- LOCATION VALIDATION CHECK ---
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->lat) || !isset($data->lng)) {
        Response::error(400, "Location access is required for attendance.");
    }

    // Office coordinates
    $officeLat = 30.661770978901735; 
    $officeLng = 73.13606289934688; 
    $allowedRadius = 180;
    $userLat = $data->lat;
    $userLng = $data->lng;

    // Haversine formula calculation
    $earthRadius = 6371000;
    $dLat = deg2rad($officeLat - $userLat);
    $dLon = deg2rad($officeLng - $userLng);
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($userLat)) * cos(deg2rad($officeLat)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;

    if ($distance > $allowedRadius) {
        $distanceAway = round($distance);
        Response::error(403, "You are $distanceAway meters away from the office. You must be inside to check in.");
    }

    // --- TIME RESTRICTION CHECK ---
    $currentHour = (int)date('H');
    
    if ($currentHour < 9 || $currentHour >= 17) {
        Response::error(403, "Check-in is only allowed between 9:00 AM and 5:00 PM.");
    }

    // Check if already checked in today
    $checkQuery = "SELECT id, check_out FROM attendance 
                   WHERE user_id = :user_id 
                   AND DATE(check_in) = CURDATE() 
                   LIMIT 1";
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord['check_out'] !== null) {
            Response::error(409, "You have already completed attendance for today");
        } else {
            Response::error(409, "You have already checked in. Please check out first");
        }
    }
    
    $status = "present";
    $currentTime = date('Y-m-d H:i:s');
    
    // Insert check-in record
    $query = "INSERT INTO attendance (user_id, check_in, status, created_at) 
              VALUES (:user_id, :current_time, :status, :current_time)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':current_time', $currentTime);
    $stmt->bindParam(':status', $status);
    
    if ($stmt->execute()) {
        $attendanceId = $db->lastInsertId();
        
        $fetchQuery = "SELECT * FROM attendance WHERE id = :id LIMIT 1";
        $fetchStmt = $db->prepare($fetchQuery);
        $fetchStmt->bindParam(':id', $attendanceId);
        $fetchStmt->execute();
        
        $record = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        
        Response::send(201, [
            "message" => "Check-in successful",
            "record" => $record
        ]);
    } else {
        Response::error(500, "Check-in failed");
    }
    
} catch(PDOException $e) {
    Response::error(500, "Check-in failed: " . $e->getMessage());
}
?>