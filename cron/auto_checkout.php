<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Karachi'); 

$database = new Database();
$db = $database->getConnection();

try {
    $currentTime = date('Y-m-d H:i:s');
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    
    // ============================================
    // 1. COMPANY CHECKOUT at expected leaving time
    // ============================================
    $companyCheckoutQuery = "
        SELECT a.id, a.user_id, a.check_in, e.expected_leaving, e.employeeName, e.status
        FROM attendance a
        JOIN employees e ON a.user_id = e.id
        WHERE DATE(a.check_in) = CURDATE()
        AND a.check_out IS NULL
        AND a.company_check_out IS NULL
        AND (e.status = 1 OR e.status IS NULL OR e.status = '1')
        AND e.expected_leaving IS NOT NULL
        AND e.expected_leaving != ''
        AND e.expected_leaving <= :current_time_only
    ";
    
    $timeOnly = sprintf('%02d:%02d:00', $currentHour, $currentMinute);
    
    $checkoutStmt = $db->prepare($companyCheckoutQuery);
    $checkoutStmt->bindParam(':current_time_only', $timeOnly);
    $checkoutStmt->execute();
    $pendingCheckouts = $checkoutStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($pendingCheckouts) . " employees for company checkout\n";
    
    foreach ($pendingCheckouts as $record) {
        $companyCheckOutTime = date('Y-m-d') . ' ' . $record['expected_leaving'];
        
        $updateQuery = "
            UPDATE attendance 
            SET company_check_out = :company_check_out
            WHERE id = :id
            AND check_out IS NULL
            AND company_check_out IS NULL
        ";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':company_check_out', $companyCheckOutTime);
        $updateStmt->bindParam(':id', $record['id']);
        $updateStmt->execute();
        
        if ($updateStmt->rowCount() > 0) {
            echo "✅ Company checkout set for: " . $record['employeeName'] . " at " . $companyCheckOutTime . "\n";
        }
    }
    
    // ============================================
    // 2. AUTO CONFIRMATION at 7 PM (No Extra Time)
    // ============================================
    $autoConfirmHour = 19;
    $autoConfirmMinute = 0;
    
    if ($currentHour > $autoConfirmHour || ($currentHour == $autoConfirmHour && $currentMinute >= $autoConfirmMinute)) {
        
        $autoConfirmQuery = "
            SELECT a.id, a.user_id, a.company_check_out, a.check_in, e.employeeName, e.status
            FROM attendance a
            JOIN employees e ON a.user_id = e.id
            WHERE DATE(a.check_in) = CURDATE()
            AND a.company_check_out IS NOT NULL
            AND a.check_out_confirmed = 0
            AND (e.status = 1 OR e.status IS NULL OR e.status = '1')
        ";
        
        $confirmStmt = $db->prepare($autoConfirmQuery);
        $confirmStmt->execute();
        $pendingConfirmations = $confirmStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Found " . count($pendingConfirmations) . " employees for auto confirmation\n";
        
        foreach ($pendingConfirmations as $record) {
            $updateQuery = "
                UPDATE attendance 
                SET check_out_confirmed = 1,
                    check_out = company_check_out,
                    extra_time_minutes = 0,
                    auto_checkout_reason = :reason,
                    status = 'present'
                WHERE id = :id
            ";
            
            $reason = "Auto-confirmed at 7 PM - Extra time not claimed";
            
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':reason', $reason);
            $updateStmt->bindParam(':id', $record['id']);
            $updateStmt->execute();
            
            echo "✅ Auto confirmed for: " . $record['employeeName'] . "\n";
        }
    }

} catch(PDOException $e) {
    error_log("Auto checkout cron failed: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>