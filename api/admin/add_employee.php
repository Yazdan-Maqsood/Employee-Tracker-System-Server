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
    Response::error(403, "Admin only");
}

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->name) || !isset($data->email) || !isset($data->cnic) || !isset($data->employmentType)) {
    Response::error(400, "Name, email, CNIC, and employment type are required");
}

if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    Response::error(400, "Invalid email format");
}

$validTypes = ['full_time', 'half_time'];
if (!in_array($data->employmentType, $validTypes)) {
    Response::error(400, "Invalid employment type");
}

$validRoles = ['employee', 'intern', 'student'];
$role = isset($data->role) && in_array($data->role, $validRoles) ? $data->role : 'employee';
$employeeFields = isset($data->employeeFields) ? $data->employeeFields : null;

// ✅ New fields
$expectedArrival = isset($data->expectedArrival) ? $data->expectedArrival : null;
$expectedLeaving = isset($data->expectedLeaving) ? $data->expectedLeaving : null;

$database = new Database();
$db = $database->getConnection();

try {
    $check = $db->prepare("SELECT id FROM employees WHERE email = :email OR cnic = :cnic LIMIT 1");
    $check->bindParam(':email', $data->email);
    $check->bindParam(':cnic', $data->cnic);
    $check->execute();
    if ($check->rowCount() > 0) {
        Response::error(409, "Email or CNIC already exists");
    }

    $query = "INSERT INTO employees (employeeName, email, cnic, password, role, employment_type, employee_fields, expected_arrival, expected_leaving) 
              VALUES (:name, :email, :cnic, NULL, :role, :employment_type, :fields, :expected_arrival, :expected_leaving)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $data->name);
    $stmt->bindParam(':email', $data->email);
    $stmt->bindParam(':cnic', $data->cnic);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':employment_type', $data->employmentType);
    $stmt->bindParam(':fields', $employeeFields);
    $stmt->bindParam(':expected_arrival', $expectedArrival);
    $stmt->bindParam(':expected_leaving', $expectedLeaving);
    $stmt->execute();

    Response::send(201, ["message" => "Employee added. They can now activate their account."]);
} catch(PDOException $e) {
    Response::error(500, "Error: " . $e->getMessage());
}
?>