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
    Response::error(403, "Only admins can view employee locations");
}

$database = new Database();
$db = $database->getConnection();

try {
    $officeLat = 30.661770978901735;
    $officeLng = 73.13606289934688;
    $allowedRadius = 180;

    // Get employees with their latest known location
    $query = "SELECT 
                e.id,
                e.employeeName,
                e.role,
                e.employee_fields,
                el.latitude,
                el.longitude,
                el.accuracy,
                el.is_inside,
                el.last_updated,
                a.check_in,
                a.check_out
              FROM employees e
              LEFT JOIN employee_locations el ON e.id = el.user_id
              LEFT JOIN attendance a ON e.id = a.user_id 
                AND DATE(a.check_in) = CURDATE() AND a.check_out IS NULL
              WHERE e.role IN ('employee', 'intern', 'student')
              ORDER BY e.employeeName";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($employees as $emp) {
        $result[] = [
            'id' => (int)$emp['id'],
            'employeeName' => $emp['employeeName'],
            'role' => $emp['role'],
            'field' => $emp['employee_fields'] ?? '',
            'latitude' => $emp['latitude'] ? (float)$emp['latitude'] : null,
            'longitude' => $emp['longitude'] ? (float)$emp['longitude'] : null,
            'accuracy' => $emp['accuracy'] ? (float)$emp['accuracy'] : null,
            'insideOffice' => $emp['is_inside'] ? true : false,
            'isCheckedIn' => !empty($emp['check_in']) && empty($emp['check_out']),
            'lastUpdated' => $emp['last_updated'] ?? null
        ];
    }

    $insideCount = count(array_filter($result, fn($e) => $e['insideOffice']));
    $outsideCount = count($result) - $insideCount;
    $hasLocation = count(array_filter($result, fn($e) => $e['latitude'] !== null));

    Response::send(200, [
        'employees' => $result,
        'office' => [
            'lat' => $officeLat,
            'lng' => $officeLng,
            'radius' => $allowedRadius
        ],
        'stats' => [
            'total' => count($result),
            'inside' => $insideCount,
            'outside' => $outsideCount,
            'hasLocation' => $hasLocation,
            'noLocation' => count($result) - $hasLocation
        ]
    ]);

} catch(PDOException $e) {
    error_log("Employee locations error: " . $e->getMessage());
    Response::error(500, "Failed to fetch employee locations");
}
?>