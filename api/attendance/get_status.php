<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/rate_limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

// ✅ Detect platform
$platform = 'web';
if (isset($_SERVER['HTTP_X_PLATFORM']) && $_SERVER['HTTP_X_PLATFORM'] === 'mobile') {
    $platform = 'mobile';
}
// Detect from User-Agent (Flutter/Dart = mobile app)
if (isset($_SERVER['HTTP_USER_AGENT']) && (
    stripos($_SERVER['HTTP_USER_AGENT'], 'flutter') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'dart') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'okhttp') !== false
)) {
    $platform = 'mobile';
}

// ✅ RATE LIMIT with platform-specific limits
// Web: 4 requests per 60 seconds | Mobile: 8 requests per 60 seconds
$maxRequests = ($platform === 'mobile') ? 8 : 4;
if (!rateLimit($userId, '/attendance/get_status', $maxRequests, 60, $platform)) {
    Response::send(429, [
        "status" => "rate_limited",
        "message" => "Too many requests. Please wait.",
        "platform" => $platform,
        "retry_after" => 10
    ]);
    return;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Fetch today's attendance record
    $query = "SELECT a.*, e.employeeName, e.expected_arrival, e.expected_leaving 
              FROM attendance a 
              JOIN employees e ON a.user_id = e.id 
              WHERE a.user_id = :user_id 
              AND DATE(a.check_in) = CURDATE() 
              ORDER BY a.id DESC 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $todayRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$todayRecord) {
        $empQuery = "SELECT expected_arrival, expected_leaving FROM employees WHERE id = :user_id";
        $empStmt = $db->prepare($empQuery);
        $empStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $empStmt->execute();
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        Response::send(200, [
            "status" => "not_checked_in",
            "todayRecord" => null,
            "expected_arrival" => $employee['expected_arrival'] ?? null,
            "expected_leaving" => $employee['expected_leaving'] ?? null
        ]);
        return;
    }
    
    // Determine status
    $status = "not_checked_in";
    
    if ($todayRecord['check_out_confirmed'] == 1) {
        $status = "checked_out";
    } elseif ($todayRecord['company_check_out'] !== null && $todayRecord['check_out_confirmed'] == 0) {
        $status = "company_checkout_pending";
    } elseif ($todayRecord['check_out'] === null && $todayRecord['company_check_out'] === null) {
        $status = "checked_in";
    } elseif ($todayRecord['check_out'] !== null && $todayRecord['company_check_out'] === null) {
        $status = "checked_out";
    }
    
    // Calculate actual working minutes
    $checkIn = new DateTime($todayRecord['check_in']);
    $checkOut = !empty($todayRecord['check_out']) 
        ? new DateTime($todayRecord['check_out']) 
        : new DateTime();
    
    $totalDiff = $checkIn->diff($checkOut);
    $totalMinutes = ($totalDiff->h * 60) + $totalDiff->i + ($totalDiff->days * 24 * 60);
    $pauseMinutes = $todayRecord['total_pause_minutes'] ?? 0;
    $actualWorkingMinutes = max(0, $totalMinutes - $pauseMinutes);
    
    // Pause information
    $isPaused = !empty($todayRecord['pause_start_time']);
    $pauseInfo = null;
    
    if ($isPaused) {
        $pauseStart = new DateTime($todayRecord['pause_start_time']);
        $now = new DateTime();
        $pauseDuration = $pauseStart->diff($now);
        $currentPauseMinutes = ($pauseDuration->h * 60) + $pauseDuration->i;
        
        $pauseInfo = [
            'is_paused' => true,
            'paused_at' => $todayRecord['pause_start_time'],
            'current_pause_minutes' => $currentPauseMinutes,
            'total_pause_minutes' => $todayRecord['total_pause_minutes'] ?? 0
        ];
    } else {
        $pauseInfo = [
            'is_paused' => false,
            'total_pause_minutes' => $todayRecord['total_pause_minutes'] ?? 0
        ];
    }
    
    // Add calculated times
    $todayRecord['gross_minutes'] = $totalMinutes;
    $todayRecord['actual_working_minutes'] = $actualWorkingMinutes;
    
    Response::send(200, [
        "status" => $status,
        "todayRecord" => $todayRecord,
        "expected_arrival" => $todayRecord['expected_arrival'] ?? null,
        "expected_leaving" => $todayRecord['expected_leaving'] ?? null,
        "company_check_out" => $todayRecord['company_check_out'] ?? null,
        "check_out_confirmed" => $todayRecord['check_out_confirmed'] ?? 0,
        "extra_time_minutes" => $todayRecord['extra_time_minutes'] ?? 0,
        "pause_info" => $pauseInfo,
        "gross_minutes" => $totalMinutes,
        "actual_working_minutes" => $actualWorkingMinutes,
        "platform" => $platform  // ✅ Return platform for debugging
    ]);
    
} catch(PDOException $e) {
    error_log("Get status DB error: " . $e->getMessage());
    Response::error(500, "Server error");
}
?>