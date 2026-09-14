<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->name) || !isset($data->email) || 
    !isset($data->password) || !isset($data->employmentType)) {
    Response::error(400, "All fields are required");
}

if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    Response::error(400, "Invalid email format");
}

if (strlen($data->password) < 6) {
    Response::error(400, "Password must be at least 6 characters");
}

$validTypes = ['full_time', 'half_time'];
if (!in_array($data->employmentType, $validTypes)) {
    Response::error(400, "Invalid employment type");
}

// NEW: Check role
$role = isset($data->role) && in_array($data->role, ['employee', 'intern', 'student']) ? $data->role : 'employee';

$database = new Database();
$db = $database->getConnection();

try {
    $checkQuery = "SELECT id FROM employees WHERE email = :email LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':email', $data->email);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        Response::error(409, "Email already registered");
    }
    
    $hashedPassword = password_hash($data->password, PASSWORD_BCRYPT);
    $employeeFields = isset($data->employeeFields) ? $data->employeeFields : null;
    
    // NEW: Added role parameter
    $query = "INSERT INTO employees (employeeName, email, password, role, employment_type, employee_fields) 
              VALUES (:employeeName, :email, :password, :role, :employment_type, :employee_fields)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employeeName', $data->name);
    $stmt->bindParam(':email', $data->email);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':role', $role);  // NEW
    $stmt->bindParam(':employment_type', $data->employmentType);
    $stmt->bindParam(':employee_fields', $employeeFields);
    
    if ($stmt->execute()) {
        $userId = $db->lastInsertId();
        
        Response::send(201, [
            "message" => "Registration successful",
            "user" => [
                "id" => $userId,
                "employeeName" => $data->name,
                "email" => $data->email,
                "role" => $role,  // NEW
                "employment_type" => $data->employmentType,
                "employee_fields" => $employeeFields
            ]
        ]);
    } else {
        Response::error(500, "Registration failed");
    }
    
} catch(PDOException $e) {
    Response::error(500, "Registration failed: " . $e->getMessage());
}
?>