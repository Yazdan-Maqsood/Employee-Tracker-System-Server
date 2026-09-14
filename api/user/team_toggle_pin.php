<?php
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/pusher.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error(405, "Method not allowed");
}

$userData = validateToken();

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->message_id) || !isset($data->pinned)) {
    Response::error(400, "message_id and pinned are required");
}

$database = new Database();
$db = $database->getConnection();

try {
    // Update the pinned status on team_messages
    $query = "UPDATE team_messages SET pinned = :pinned WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':pinned', $data->pinned, PDO::PARAM_INT);
    $stmt->bindParam(':id', $data->message_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Get the team_id for this message
        $msgQuery = "SELECT team_id, sender_id FROM team_messages WHERE id = :id";
        $msgStmt = $db->prepare($msgQuery);
        $msgStmt->execute([':id' => $data->message_id]);
        $msgData = $msgStmt->fetch(PDO::FETCH_ASSOC);

        if ($msgData) {
            // Trigger real-time pin update on the team channel
            $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, [
                'cluster' => PUSHER_CLUSTER,
                'useTLS' => true
            ]);

            $pusher->trigger('team-' . $msgData['team_id'], 'pin-updated', [
                'message_id' => (int)$data->message_id,
                'pinned' => (int)$data->pinned,
                'team_id' => (int)$msgData['team_id'],
                'sender_id' => (int)$msgData['sender_id']
            ]);
        }

        Response::send(200, [
            "message" => "Pin toggled",
            "message_id" => $data->message_id,
            "pinned" => $data->pinned
        ]);
    } else {
        Response::error(404, "Message not found");
    }
} catch (PDOException $e) {
    Response::error(500, "Database error: " . $e->getMessage());
}
?>