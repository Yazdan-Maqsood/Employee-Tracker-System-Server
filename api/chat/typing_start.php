<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/pusher.php';
require_once __DIR__ . '/../../vendor/pusher/src/Pusher.php';
require_once __DIR__ . '/../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, "Method not allowed");
}
$userData = validateToken();

$data = json_decode(file_get_contents("php://input"));
if (!$data || !isset($data->receiver_id)) {
    Response::error(400, "receiver_id required");
}

try {
    $pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, [
        'cluster' => PUSHER_CLUSTER,
        'useTLS' => true
    ]);
    
    $pusher->trigger('chat', 'typing', [
        'sender_id'   => $userData['user_id'],
        'receiver_id' => $data->receiver_id
    ]);
    Response::send(200, ["message" => "Typing"]);
} catch (Exception $e) {
    Response::error(500, "Typing event failed: " . $e->getMessage());
}
?>