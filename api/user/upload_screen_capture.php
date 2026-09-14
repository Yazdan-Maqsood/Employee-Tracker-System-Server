<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();
$userId = $userData['user_id'];

$file = isset($_FILES['screenshot']) ? $_FILES['screenshot'] : (isset($_FILES['video']) ? $_FILES['video'] : null);

if (!$file) {
    Response::error(400, "No file uploaded");
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    Response::error(400, "File upload error code: " . $file['error']);
}

$captureId = $_POST['capture_id'] ?? null;
$captureType = $_POST['capture_type'] ?? 'screenshot';

$database = new Database();
$db = $database->getConnection();

try {
    // Create uploads directory
    $uploadDir = __DIR__ . '/../../uploads/screenshots/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create directory: " . $uploadDir);
            Response::error(500, "Failed to create upload directory");
        }
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        error_log("Directory not writable: " . $uploadDir);
        Response::error(500, "Upload directory is not writable");
    }

    // Determine file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($extension)) {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm'
        ];
        $extension = $mimeToExt[$file['type']] ?? 'jpg';
    }

    // Generate unique filename
    $fileName = 'capture_' . $userId . '_' . time() . '_' . substr(uniqid(), -6) . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $dbPath = 'uploads/screenshots/' . $fileName;
        
        // Update database
        if ($captureId) {
            $updateQuery = "UPDATE screen_captures 
                           SET image_path = :image_path, 
                               status = 'captured',
                               captured_at = NOW() 
                           WHERE id = :id AND employee_id = :user_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':image_path', $dbPath);
            $updateStmt->bindParam(':id', $captureId, PDO::PARAM_INT);
            $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            if ($updateStmt->execute()) {
                if ($updateStmt->rowCount() === 0) {
                    error_log("No rows updated for capture_id: " . $captureId . ", user_id: " . $userId);
                }
            }
        }

        $fileSizeKB = round($file['size'] / 1024, 2);
        error_log("Upload successful: " . $fileName . " (" . $fileSizeKB . " KB)");

        Response::send(200, [
            'message' => 'Capture uploaded successfully',
            'capture_id' => $captureId,
            'path' => $dbPath,
            'url' => 'https://dt-attendance-system.desired-techs.com/dt-backend/' . $dbPath,
            'file_type' => $file['type'],
            'file_size_kb' => $fileSizeKB
        ]);
    } else {
        error_log("move_uploaded_file failed from " . $file['tmp_name'] . " to " . $filePath);
        Response::error(500, "Failed to save file to server");
    }
    
} catch(PDOException $e) {
    error_log("Upload DB error: " . $e->getMessage());
    Response::error(500, "Database error during upload");
} catch(Exception $e) {
    error_log("Upload general error: " . $e->getMessage());
    Response::error(500, "Upload failed: " . $e->getMessage());
}
?>