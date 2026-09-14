<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT br.*, a.check_in 
              FROM break_records br 
              JOIN attendance a ON br.attendance_id = a.id 
              WHERE br.user_id = :user_id 
              AND DATE(a.check_in) = CURDATE() 
              ORDER BY br.break_out DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $breaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current break status
    $currentBreakQuery = "SELECT id, break_out FROM break_records 
                          WHERE user_id = :user_id 
                          AND break_in IS NULL 
                          AND DATE(break_out) = CURDATE() 
                          LIMIT 1";
    
    $currentBreakStmt = $db->prepare($currentBreakQuery);
    $currentBreakStmt->bindParam(':user_id', $userId);
    $currentBreakStmt->execute();
    $currentBreak = $currentBreakStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's total break minutes
    $totalBreakQuery = "SELECT COALESCE(total_break_minutes, 0) as total_break_minutes,
                        COALESCE(break_count, 0) as break_count
                        FROM attendance 
                        WHERE user_id = :user_id 
                        AND DATE(check_in) = CURDATE() 
                        LIMIT 1";
    
    $totalBreakStmt = $db->prepare($totalBreakQuery);
    $totalBreakStmt->bindParam(':user_id', $userId);
    $totalBreakStmt->execute();
    $totalBreak = $totalBreakStmt->fetch(PDO::FETCH_ASSOC);
    
    Response::send(200, [
        "breaks" => $breaks,
        "current_break" => $currentBreak ? true : false,
        "current_break_start" => $currentBreak ? $currentBreak['break_out'] : null,
        "total_break_minutes" => $totalBreak ? $totalBreak['total_break_minutes'] : 0,
        "break_count" => $totalBreak ? $totalBreak['break_count'] : 0
    ]);
    
} catch(PDOException $e) {
    Response::error(500, "Failed to get break history: " . $e->getMessage());
}
?>