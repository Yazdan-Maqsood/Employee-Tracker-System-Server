<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/JWT.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->email) || !isset($data->password)) {
    Response::error(400, "Email and password are required");
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT id, employeeName, email, password, role, employment_type, employee_fields, status 
          FROM employees 
          WHERE email = :email 
          LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $data->email);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        Response::error(401, "Invalid email or password");
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ NEW: Check if account is deactivated
    if ($user['status'] === 'deactive') {
        Response::error(403, "Your account is deactivated. Please contact admin.");
    }
    
    if (!password_verify($data->password, $user['password'])) {
        Response::error(401, "Invalid email or password");
    }
    
    $token = JWT::generateToken($user['id'], $user['email'], $user['role']);
    
    unset($user['password']);
    
    Response::send(200, [
        "message" => "Login successful",
        "user" => $user,
        "token" => $token
    ]);
    
} catch(PDOException $e) {
    Response::error(500, "Login failed: " . $e->getMessage());
}
?>