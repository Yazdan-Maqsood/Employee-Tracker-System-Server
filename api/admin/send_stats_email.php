<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
if ($userData['role'] !== 'admin') {
    Response::error(403, "Access denied. Admin only");
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->employee_id) || !isset($data->month) || !isset($data->year)) {
    Response::error(400, "Employee ID, month, and year are required");
}

$employeeId = intval($data->employee_id);
$month = intval($data->month);
$year = intval($data->year);

$database = new Database();
$db = $database->getConnection();

// Helper functions
function formatHoursMins($totalMinutes) {
    $hours = floor($totalMinutes / 60);
    $mins = $totalMinutes % 60;
    return "{$hours}h {$mins}m";
}

function formatDuration($totalMinutes) {
    $hours = floor($totalMinutes / 60);
    $mins = $totalMinutes % 60;
    if ($hours === 0) return "{$mins}m";
    if ($mins === 0) return "{$hours}h";
    return "{$hours}h {$mins}m";
}

function getWorkingMinutes($checkIn, $checkOut) {
    if (!$checkIn || !$checkOut) return 0;
    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);
    $diff = $start->diff($end);
    return ($diff->h * 60) + $diff->i + ($diff->days * 24 * 60);
}

function getExpectedDailyMinutes($expectedArrival, $expectedLeaving) {
    if (!$expectedArrival || !$expectedLeaving) return 0;
    $arr = explode(':', $expectedArrival);
    $lev = explode(':', $expectedLeaving);
    $arrMin = (intval($arr[0]) * 60) + intval($arr[1]);
    $levMin = (intval($lev[0]) * 60) + intval($lev[1]);
    return $levMin - $arrMin;
}

function getArrivalLateMinutes($checkInStr, $expectedArrivalStr) {
    if (!$checkInStr || !$expectedArrivalStr) return 0;
    $checkInTime = date('H:i', strtotime($checkInStr));
    $expectedArrival = substr($expectedArrivalStr, 0, 5);
    if ($checkInTime > $expectedArrival) {
        $arr = explode(':', $expectedArrival);
        $expectedMinutes = (intval($arr[0]) * 60) + intval($arr[1]);
        $actualArr = explode(':', $checkInTime);
        $actualMinutes = (intval($actualArr[0]) * 60) + intval($actualArr[1]);
        return $actualMinutes - $expectedMinutes;
    }
    return 0;
}

function getEarlyDepartureMinutes($checkOutStr, $expectedLeavingStr) {
    if (!$checkOutStr || !$expectedLeavingStr) return 0;
    $checkOutTime = date('H:i', strtotime($checkOutStr));
    $expectedLeaving = substr($expectedLeavingStr, 0, 5);
    if ($checkOutTime < $expectedLeaving) {
        $lev = explode(':', $expectedLeaving);
        $expectedMinutes = (intval($lev[0]) * 60) + intval($lev[1]);
        $actualLev = explode(':', $checkOutTime);
        $actualMinutes = (intval($actualLev[0]) * 60) + intval($actualLev[1]);
        return $expectedMinutes - $actualMinutes;
    }
    return 0;
}

function getExtraTimeMinutes($checkOutStr, $expectedLeavingStr) {
    if (!$checkOutStr || !$expectedLeavingStr) return 0;
    $checkOutTime = date('H:i', strtotime($checkOutStr));
    $expectedLeaving = substr($expectedLeavingStr, 0, 5);
    if ($checkOutTime > $expectedLeaving) {
        $lev = explode(':', $expectedLeaving);
        $expectedMinutes = (intval($lev[0]) * 60) + intval($lev[1]);
        $actualLev = explode(':', $checkOutTime);
        $actualMinutes = (intval($actualLev[0]) * 60) + intval($actualLev[1]);
        return $actualMinutes - $expectedMinutes;
    }
    return 0;
}

function isOffDay($dayOfWeek, $role) {
    $normalizedRole = strtolower($role ?? '');
    if ($dayOfWeek == 0) return true;
    if ($dayOfWeek == 6 && ($normalizedRole == 'intern' || $normalizedRole == 'student')) return true;
    return false;
}

$monthNames = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April",
    5 => "May", 6 => "June", 7 => "July", 8 => "August",
    9 => "September", 10 => "October", 11 => "November", 12 => "December"
];

try {
    // Get employee details
    $empQuery = "SELECT employeeName, email, role, expected_arrival, expected_leaving, employment_type, employee_fields 
                 FROM employees WHERE id = :id";
    $empStmt = $db->prepare($empQuery);
    $empStmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
    $empStmt->execute();
    
    if ($empStmt->rowCount() === 0) {
        Response::error(404, "Employee not found");
    }
    
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
    $monthName = $monthNames[$month] ?? 'Unknown';
    $employeeRole = strtolower($employee['role'] ?? 'employee');
    
    // Get attendance records for the month
    $firstDay = sprintf('%04d-%02d-01', $year, $month);
    $lastDay = date('Y-m-t', strtotime($firstDay));
    
    $attQuery = "SELECT * FROM attendance 
                 WHERE user_id = :user_id 
                 AND DATE(check_in) >= :first_day 
                 AND DATE(check_in) <= :last_day 
                 ORDER BY check_in ASC";
    
    $attStmt = $db->prepare($attQuery);
    $attStmt->bindParam(':user_id', $employeeId, PDO::PARAM_INT);
    $attStmt->bindParam(':first_day', $firstDay);
    $attStmt->bindParam(':last_day', $lastDay);
    $attStmt->execute();
    $attendanceRecords = $attStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get holidays
    $holidayQuery = "SELECT holiday_date FROM holidays 
                     WHERE holiday_date >= :first_day AND holiday_date <= :last_day";
    $holidayStmt = $db->prepare($holidayQuery);
    $holidayStmt->bindParam(':first_day', $firstDay);
    $holidayStmt->bindParam(':last_day', $lastDay);
    $holidayStmt->execute();
    $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);
    $holidayDates = array_column($holidays, 'holiday_date');
    
    // Calculate stats
    $totalDaysInMonth = date('t', strtotime($firstDay));
    $isCurrentMonth = (date('Y') == $year && date('m') == $month);
    $currentDayOfMonth = $isCurrentMonth ? date('j') : $totalDaysInMonth;
    
    $workingDaysPassed = 0;
    $totalWorkingDaysInMonth = 0;
    $holidayDaysPassed = 0;
    $totalHolidaysInMonth = 0;
    $absentDates = [];
    $presentDates = [];
    
    foreach ($attendanceRecords as $record) {
        if (!empty($record['check_in'])) {
            $presentDates[] = date('Y-m-d', strtotime($record['check_in']));
        }
    }
    
    for ($d = 1; $d <= $totalDaysInMonth; $d++) {
        $dateObj = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $d));
        $dayOfWeek = $dateObj->format('w');
        $dateStr = $dateObj->format('Y-m-d');
        
        if (!isOffDay($dayOfWeek, $employeeRole)) {
            if (in_array($dateStr, $holidayDates)) {
                $totalHolidaysInMonth++;
                if ($d <= $currentDayOfMonth) {
                    $holidayDaysPassed++;
                }
            } else {
                $totalWorkingDaysInMonth++;
                if ($d <= $currentDayOfMonth) {
                    $workingDaysPassed++;
                    if (!in_array($dateStr, $presentDates)) {
                        $absentDates[] = $dateObj->format('D j');
                    }
                }
            }
        }
    }
    
    $totalLeaves = count($absentDates);
    $actualDaysWorked = $workingDaysPassed - $totalLeaves;
    
    $expectedDailyMinutes = getExpectedDailyMinutes($employee['expected_arrival'], $employee['expected_leaving']);
    $totalExpectedMonthMinutes = $totalWorkingDaysInMonth * $expectedDailyMinutes;
    $currentExpectedMinutes = $actualDaysWorked * $expectedDailyMinutes;
    
    $totalWorkedMinutes = 0;
    $totalPauseMinutesMonth = 0;
    $totalLateMinutes = 0;
    $totalEarlyDepartureMinutes = 0;
    $totalExtraTimeMinutes = 0;
    
    foreach ($attendanceRecords as $record) {
        if (!empty($record['check_in']) && !empty($record['check_out'])) {
            $grossMinutes = getWorkingMinutes($record['check_in'], $record['check_out']);
            $pauseMinutes = intval($record['total_pause_minutes'] ?? 0);
            $totalWorkedMinutes += max(0, $grossMinutes - $pauseMinutes);
            $totalPauseMinutesMonth += $pauseMinutes;
        }
        
        $totalLateMinutes += getArrivalLateMinutes($record['check_in'], $employee['expected_arrival']);
        $totalEarlyDepartureMinutes += getEarlyDepartureMinutes($record['check_out'], $employee['expected_leaving']);
        $totalExtraTimeMinutes += getExtraTimeMinutes($record['check_out'], $employee['expected_leaving']);
    }
    
    $leaveTime = $totalLeaves * $expectedDailyMinutes;
    $lateTime = $totalLateMinutes;
    $outsideTime = $totalPauseMinutesMonth;
    $earlyDepartureTime = $totalEarlyDepartureMinutes;
    $extraTime = $totalExtraTimeMinutes;
    $totalTimeBreakdown = max(0, $leaveTime + $lateTime + $outsideTime + $earlyDepartureTime - $extraTime);
    
    $roleLabel = ucfirst($employeeRole);
    $typeLabel = ($employee['employment_type'] == 'full_time') ? 'Full Time' : 'Half Time';
    
    // Build email HTML
    $emailSubject = "Attendance Report - {$monthName} {$year} - {$employee['employeeName']}";
    
    $emailHTML = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
            .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; padding: 30px; text-align: center; }
            .header h2 { margin: 0 0 5px 0; font-size: 24px; }
            .header p { margin: 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 30px; }
            .section { margin: 25px 0; }
            .section-title { font-size: 16px; font-weight: 700; color: #4f46e5; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
            .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-box { background: #f8fafc; border-radius: 10px; padding: 15px; text-align: center; border: 1px solid #e2e8f0; }
            .stat-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
            .stat-value { font-size: 20px; font-weight: 700; color: #1e293b; margin-top: 5px; }
            .stat-value.green { color: #10b981; }
            .stat-value.red { color: #ef4444; }
            .stat-value.orange { color: #f59e0b; }
            .stat-value.purple { color: #8b5cf6; }
            .absent-section { background: #fef2f2; border-radius: 10px; padding: 15px; border: 1px solid #fecaca; }
            .absent-badge { display: inline-block; background: #ef4444; color: white; padding: 5px 12px; border-radius: 20px; margin: 3px; font-size: 12px; font-weight: 600; }
            .perfect-section { background: #f0fdf4; border-radius: 10px; padding: 20px; text-align: center; border: 1px solid #bbf7d0; }
            .perfect-section h4 { color: #166534; margin: 0; font-size: 16px; }
            .time-breakdown { background: #f8fafc; border-radius: 10px; padding: 20px; border: 1px solid #e2e8f0; margin-top: 15px; }
            .breakdown-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
            .breakdown-row:last-child { border-bottom: none; }
            .breakdown-label { font-size: 13px; color: #64748b; }
            .breakdown-value { font-size: 14px; font-weight: 600; color: #1e293b; }
            .footer { text-align: center; padding: 20px; color: #94a3b8; font-size: 11px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
            @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Monthly Attendance Report</h2>
                <p>{$employee['employeeName']} | {$monthName} {$year}</p>
                <p style='font-size: 12px; opacity: 0.8; margin-top: 5px;'>{$roleLabel} | {$typeLabel} | {$employee['employee_fields']}</p>
            </div>
            
            <div class='content'>
                <div class='section'>
                    <div class='section-title'>Monthly Summary</div>
                    <div class='stats-grid'>
                        <div class='stat-box'>
                            <div class='stat-label'>Working Days</div>
                            <div class='stat-value'>{$totalWorkingDaysInMonth} days</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Total Expected Hours</div>
                            <div class='stat-value'>" . formatHoursMins($totalExpectedMonthMinutes) . "</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Holidays</div>
                            <div class='stat-value purple'>{$totalHolidaysInMonth} days</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Daily Expected</div>
                            <div class='stat-value'>" . formatDuration($expectedDailyMinutes) . "</div>
                        </div>
                    </div>
                </div>
                
                <div class='section'>
                    <div class='section-title'>Time Details</div>
                    <div class='stats-grid'>
                        <div class='stat-box'>
                            <div class='stat-label'>Days Worked</div>
                            <div class='stat-value orange'>{$actualDaysWorked} / {$workingDaysPassed}</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Actual Hours</div>
                            <div class='stat-value'>" . formatHoursMins($totalWorkedMinutes) . "</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Expected Hours</div>
                            <div class='stat-value'>" . formatHoursMins($currentExpectedMinutes) . "</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-label'>Total Leaves</div>
                            <div class='stat-value " . ($totalLeaves > 0 ? 'red' : 'green') . "'>{$totalLeaves} days</div>
                        </div>
                    </div>
                </div>";
    
    // Absent dates or perfect attendance
    if ($totalLeaves > 0) {
        $emailHTML .= "
                <div class='section'>
                    <div class='section-title'>Absent Dates ({$totalLeaves} days)</div>
                    <div class='absent-section'>";
        foreach ($absentDates as $absentDate) {
            $emailHTML .= "<span class='absent-badge'>{$absentDate}</span>";
        }
        $emailHTML .= "
                    </div>
                </div>";
    } else if ($workingDaysPassed > 0) {
        $emailHTML .= "
                <div class='section'>
                    <div class='perfect-section'>
                        <h4>Perfect Attendance!</h4>
                        <p style='color: #166534; margin: 5px 0 0 0;'>No absences this month. Keep up the great work!</p>
                    </div>
                </div>";
    }
    
    // Time breakdown
    $emailHTML .= "
                <div class='section'>
                    <div class='section-title'>Time Breakdown</div>
                    <div class='time-breakdown'>
                        <div class='breakdown-row'>
                            <span class='breakdown-label'>Leaves</span>
                            <span class='breakdown-value' style='color: #ef4444;'>" . formatHoursMins($leaveTime) . "</span>
                        </div>
                        <div class='breakdown-row'>
                            <span class='breakdown-label'>Late</span>
                            <span class='breakdown-value' style='color: #f59e0b;'>" . formatHoursMins($lateTime) . "</span>
                        </div>
                        <div class='breakdown-row'>
                            <span class='breakdown-label'>Outside Office</span>
                            <span class='breakdown-value' style='color: #8b5cf6;'>" . formatHoursMins($outsideTime) . "</span>
                        </div>
                        <div class='breakdown-row'>
                            <span class='breakdown-label'>Early Departure</span>
                            <span class='breakdown-value' style='color: #f97316;'>" . formatHoursMins($earlyDepartureTime) . "</span>
                        </div>
                        <div class='breakdown-row'>
                            <span class='breakdown-label'>Extra Time</span>
                            <span class='breakdown-value' style='color: #10b981;'>-" . formatHoursMins($extraTime) . "</span>
                        </div>
                        <div class='breakdown-row' style='font-weight: 700;'>
                            <span class='breakdown-label'>Total</span>
                            <span class='breakdown-value' style='color: #1e293b; font-size: 16px;'>" . formatHoursMins($totalTimeBreakdown) . "</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='footer'>
                This is an automated report from DT Attendance System.<br>
                Generated on " . date('Y-m-d H:i:s') . " | Desired Technologies
            </div>
        </div>
    </body>
    </html>";
    
    // Send email
    $to = $employee['email'];
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: DT Attendance System <noreply@desired-techs.com>" . "\r\n";
    $headers .= "Reply-To: noreply@desired-techs.com" . "\r\n";
    
    $mailSent = @mail($to, $emailSubject, $emailHTML, $headers);
    
    if ($mailSent) {
        Response::send(200, [
            "message" => "Attendance report sent to {$employee['email']} successfully",
            "email" => $employee['email'],
            "employee_name" => $employee['employeeName'],
            "month" => $monthName,
            "year" => $year
        ]);
    } else {
        error_log("Failed to send email to: " . $employee['email']);
        Response::error(500, "Failed to send email. Server email not configured properly.");
    }
    
} catch(PDOException $e) {
    error_log("Send stats email DB error: " . $e->getMessage());
    Response::error(500, "Failed to send stats email: " . $e->getMessage());
}
?>