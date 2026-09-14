<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
if ($userData['role'] !== 'admin') {
    Response::error(403, "Access denied. Admin only");
}

if (!isset($_GET['employee_id'])) {
    Response::error(400, "Employee ID is required");
}

$employeeId = intval($_GET['employee_id']);
$data = json_decode(file_get_contents("php://input"));

// ✅ Accept 0/1 or 'active'/'deactive'
$status = null;
if (isset($data->status)) {
    if ($data->status === 1 || $data->status === '1' || $data->status === 'active') {
        $status = 1;
    } elseif ($data->status === 0 || $data->status === '0' || $data->status === 'deactive') {
        $status = 0;
    }
}

if ($status === null) {
    Response::error(400, "Valid status is required (1/0 or active/deactive)");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if employee exists
    $checkQuery = "SELECT id, employeeName FROM employees WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
    $checkStmt->execute();
   
    if ($checkStmt->rowCount() === 0) {
        Response::error(404, "Employee not found");
    }

    $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // Update status (1 = Active, 0 = Deactive)
    $query = "UPDATE employees SET status = :status WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status, PDO::PARAM_INT);
    $stmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
   
    if ($stmt->execute()) {
        $statusText = $status == 1 ? 'activated' : 'deactivated';
        Response::send(200, [
            "message" => "Employee " . $employee['employeeName'] . " " . $statusText . " successfully",
            "employee_id" => $employeeId,
            "status" => $status
        ]);
    } else {
        Response::error(500, "Failed to update status");
    }
} catch(PDOException $e) {
    Response::error(500, "Failed to update status: " . $e->getMessage());
}
?>