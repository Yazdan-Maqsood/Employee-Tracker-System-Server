<?php
require_once __DIR__ . '/../../utils/Response.php';
date_default_timezone_set('Asia/Karachi'); 
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
    // Check if there's a pending company checkout for today
    $checkQuery = "
        SELECT id, company_check_out, check_in 
        FROM attendance 
        WHERE user_id = :user_id 
        AND DATE(check_in) = CURDATE() 
        AND company_check_out IS NOT NULL 
        AND check_out_confirmed = 0 
        LIMIT 1
    ";
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        Response::error(404, "No pending company checkout found for today");
    }
    
    $record = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $currentTime = date('Y-m-d H:i:s');
    
    // Calculate extra time (company_check_out se lekar abhi tak ka time)
    $companyCheckOut = new DateTime($record['company_check_out']);
    $now = new DateTime($currentTime);
    
    if ($now <= $companyCheckOut) {
        // Agar abhi bhi company_check_out se pehle hai (shouldn't happen normally)
        Response::error(400, "Cannot confirm before company checkout time");
    }
    
    $interval = $companyCheckOut->diff($now);
    $extraMinutes = ($interval->h * 60) + $interval->i;
    $extraDays = $interval->days;
    if ($extraDays > 0) {
        $extraMinutes += ($extraDays * 24 * 60);
    }
    
    // Update: check_out = current time (abhi ka time), check_out_confirmed = 1, extra_time_minutes calculate
    $updateQuery = "
        UPDATE attendance 
        SET check_out = :current_time,
            check_out_confirmed = 1,
            extra_time_minutes = :extra_minutes,
            status = 'present'
        WHERE id = :id
    ";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':current_time', $currentTime);
    $updateStmt->bindParam(':extra_minutes', $extraMinutes, PDO::PARAM_INT);
    $updateStmt->bindParam(':id', $record['id'], PDO::PARAM_INT);
    
    if ($updateStmt->execute()) {
        // Fetch updated record
        $fetchQuery = "
            SELECT a.*, e.employeeName, e.expected_arrival, e.expected_leaving 
            FROM attendance a 
            JOIN employees e ON a.user_id = e.id 
            WHERE a.id = :id 
            LIMIT 1
        ";
        
        $fetchStmt = $db->prepare($fetchQuery);
        $fetchStmt->bindParam(':id', $record['id'], PDO::PARAM_INT);
        $fetchStmt->execute();
        $updatedRecord = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        
        Response::send(200, [
            "message" => "Checkout confirmed successfully",
            "record" => $updatedRecord,
            "extra_time_minutes" => $extraMinutes,
            "total_extra_time" => floor($extraMinutes / 60) . "h " . ($extraMinutes % 60) . "m"
        ]);
    } else {
        Response::error(500, "Failed to confirm checkout");
    }
    
} catch(PDOException $e) {
    Response::error(500, "Confirm checkout failed: " . $e->getMessage());
}
?>