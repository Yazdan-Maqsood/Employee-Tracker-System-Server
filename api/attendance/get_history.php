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
    $query = "SELECT a.*, e.expected_arrival, e.expected_leaving, e.role, e.status as employee_status
              FROM attendance a
              JOIN employees e ON a.user_id = e.id
              WHERE a.user_id = :user_id";

    $params = [':user_id' => $userId];

    // Add date filtering parameters
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

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Get all holidays
    $holidaysQuery = "SELECT holiday_date, holiday_name FROM holidays ORDER BY holiday_date ASC";
    $holidaysStmt = $db->prepare($holidaysQuery);
    $holidaysStmt->execute();
    $holidays = $holidaysStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::send(200, [
        "records" => $records,
        "holidays" => $holidays
    ]);
} catch(PDOException $e) {
    Response::error(500, "Failed to fetch history: " . $e->getMessage());
}
?>