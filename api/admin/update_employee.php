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

$employeeId = $_GET['employee_id'] ?? null;
if (!$employeeId) {
    Response::error(400, "Employee ID is required");
}

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->name) || !isset($data->email) || !isset($data->cnic) || !isset($data->employmentType)) {
    Response::error(400, "Name, email, CNIC, and employment type are required");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check email uniqueness
    $checkQuery = "SELECT id FROM employees WHERE email = :email AND id != :id LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':email', $data->email);
    $checkStmt->bindParam(':id', $employeeId);
    $checkStmt->execute();
    if ($checkStmt->rowCount() > 0) {
        Response::error(409, "Email already used by another employee");
    }

    // Check CNIC uniqueness
    $cnicCheckQuery = "SELECT id FROM employees WHERE cnic = :cnic AND id != :id LIMIT 1";
    $cnicCheckStmt = $db->prepare($cnicCheckQuery);
    $cnicCheckStmt->bindParam(':cnic', $data->cnic);
    $cnicCheckStmt->bindParam(':id', $employeeId);
    $cnicCheckStmt->execute();
    if ($cnicCheckStmt->rowCount() > 0) {
        Response::error(409, "CNIC already used by another employee");
    }

    $validRoles = ['employee', 'intern', 'student'];
    $role = isset($data->role) && in_array($data->role, $validRoles) ? $data->role : 'employee';
    $employeeFields = isset($data->employeeFields) ? $data->employeeFields : null;
    $expectedArrival = isset($data->expectedArrival) ? $data->expectedArrival : null;
    $expectedLeaving = isset($data->expectedLeaving) ? $data->expectedLeaving : null;

    if (!empty($data->password)) {
        if (strlen($data->password) < 6) {
            Response::error(400, "Password must be at least 6 characters");
        }
        $hashedPassword = password_hash($data->password, PASSWORD_BCRYPT);
        $query = "UPDATE employees 
                  SET employeeName = :name, 
                      email = :email, 
                      cnic = :cnic,
                      password = :password, 
                      role = :role, 
                      employment_type = :employment_type, 
                      employee_fields = :employee_fields,
                      expected_arrival = :expected_arrival,
                      expected_leaving = :expected_leaving
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
    } else {
        $query = "UPDATE employees 
                  SET employeeName = :name, 
                      email = :email, 
                      cnic = :cnic,
                      role = :role, 
                      employment_type = :employment_type, 
                      employee_fields = :employee_fields,
                      expected_arrival = :expected_arrival,
                      expected_leaving = :expected_leaving
                  WHERE id = :id";
        $stmt = $db->prepare($query);
    }

    $stmt->bindParam(':name', $data->name);
    $stmt->bindParam(':email', $data->email);
    $stmt->bindParam(':cnic', $data->cnic);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':employment_type', $data->employmentType);
    $stmt->bindParam(':employee_fields', $employeeFields);
    $stmt->bindParam(':expected_arrival', $expectedArrival);
    $stmt->bindParam(':expected_leaving', $expectedLeaving);
    $stmt->bindParam(':id', $employeeId);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            Response::send(200, ["message" => "Employee updated successfully"]);
        } else {
            Response::send(200, ["message" => "No changes were made"]);
        }
    } else {
        Response::error(500, "Failed to update employee");
    }
} catch(PDOException $e) {
    Response::error(500, "Failed: " . $e->getMessage());
}
?>