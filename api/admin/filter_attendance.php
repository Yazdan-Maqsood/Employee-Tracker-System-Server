<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
if ($userData['role'] !== 'admin') {
    Response::error(403, "Access denied. Admin only");
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT a.*, 
                 e.employeeName as employee_name, 
                 e.email as employee_email, 
                 e.employment_type, 
                 e.employee_fields,
                 e.expected_arrival,
                 e.expected_leaving,
                 e.status as employee_status
          FROM attendance a 
          JOIN employees e ON a.user_id = e.id 
          WHERE 1=1";
    
    $params = [];

    if (isset($_GET['employee_id']) && $_GET['employee_id'] !== '') {
        $query .= " AND a.user_id = :employee_id";
        $params[':employee_id'] = $_GET['employee_id'];
    }

    if (isset($_GET['start_date']) && $_GET['start_date'] !== '') {
        $query .= " AND DATE(a.check_in) >= :start_date";
        $params[':start_date'] = $_GET['start_date'];
    }
    if (isset($_GET['end_date']) && $_GET['end_date'] !== '') {
        $query .= " AND DATE(a.check_in) <= :end_date";
        $params[':end_date'] = $_GET['end_date'];
    }

    $query .= " ORDER BY a.check_in DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Get holidays for the date range
    $holidaysQuery = "SELECT holiday_date, holiday_name FROM holidays WHERE 1=1";
    $holidaysParams = [];
    
    if (isset($_GET['start_date']) && $_GET['start_date'] !== '') {
        $holidaysQuery .= " AND holiday_date >= :start_date";
        $holidaysParams[':start_date'] = $_GET['start_date'];
    }
    if (isset($_GET['end_date']) && $_GET['end_date'] !== '') {
        $holidaysQuery .= " AND holiday_date <= :end_date";
        $holidaysParams[':end_date'] = $_GET['end_date'];
    }
    
    $holidaysStmt = $db->prepare($holidaysQuery);
    foreach ($holidaysParams as $key => $value) {
        $holidaysStmt->bindValue($key, $value);
    }
    $holidaysStmt->execute();
    $holidays = $holidaysStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, [
        "attendances" => $attendances,
        "holidays" => $holidays
    ]);
} catch(PDOException $e) {
    Response::error(500, "Failed to filter attendance: " . $e->getMessage());
}
?>