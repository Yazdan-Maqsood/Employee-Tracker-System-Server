<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error(405, "Method not allowed");

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->cnic) || !isset($data->email) || !isset($data->password)) {
    Response::error(400, "CNIC, email, and password are required");
}
if (strlen($data->password) < 6) {
    Response::error(400, "Password must be at least 6 characters");
}

$database = new Database();
$db = $database->getConnection();

// Return debug info in the response
$debug = [];

try {
    // 1. Check if the cnic column exists
    $stmt = $db->query("SHOW COLUMNS FROM employees LIKE 'cnic'");
    $debug['cnic_column_exists'] = $stmt->rowCount() > 0 ? "yes" : "no";

    // 2. Find employee
    $stmt = $db->prepare("SELECT id, password FROM employees WHERE cnic = :cnic AND email = :email LIMIT 1");
    $stmt->bindParam(':cnic', $data->cnic);
    $stmt->bindParam(':email', $data->email);
    $stmt->execute();
    
    $debug['rows_found'] = $stmt->rowCount();
    
    if ($stmt->rowCount() === 0) {
        $debug['error'] = "No employee found with this CNIC and email";
        echo json_encode(["status" => 404, "debug" => $debug]);
        exit;
    }
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug['employee_id'] = $employee['id'];
    $debug['current_password'] = $employee['password'] === null ? "NULL" : "NOT NULL";
    
    if ($employee['password'] !== null) {
        $debug['error'] = "Account already activated";
        echo json_encode(["status" => 409, "debug" => $debug]);
        exit;
    }

    // 3. Update password
    $hash = password_hash($data->password, PASSWORD_BCRYPT);
    $update = $db->prepare("UPDATE employees SET password = :password WHERE id = :id");
    $update->bindParam(':password', $hash);
    $update->bindParam(':id', $employee['id']);
    $result = $update->execute();
    
    $debug['update_success'] = $result ? "yes" : "no";
    $debug['rows_affected'] = $update->rowCount();
    
    if ($result && $update->rowCount() > 0) {
        echo json_encode(["status" => 200, "message" => "Password updated!", "debug" => $debug]);
    } else {
        echo json_encode(["status" => 500, "message" => "Update did not affect any rows", "debug" => $debug]);
    }
    exit;
    
} catch(PDOException $e) {
    echo json_encode(["status" => 500, "message" => "Exception: " . $e->getMessage(), "debug" => $debug]);
    exit;
}
?>