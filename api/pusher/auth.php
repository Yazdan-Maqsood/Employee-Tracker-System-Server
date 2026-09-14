<?php
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/pusher.php';
require_once __DIR__ . '/../vendor/autoload.php';

$userData = validateToken();

$socketId = $_POST['socket_id'] ?? null;
$channelName = $_POST['channel_name'] ?? null;

if (!$socketId || !$channelName) {
    http_response_code(400);
    echo json_encode(["error" => "Missing socket_id or channel_name"]);
    exit;
}

$options = [
    'cluster' => PUSHER_CLUSTER,
    'useTLS' => true,
];
$pusher = new Pusher\Pusher(PUSHER_KEY, PUSHER_SECRET, PUSHER_APP_ID, $options);

// Generate the auth signature
$auth = $pusher->socket_auth($channelName, $socketId);

// Return as JSON
header('Content-Type: application/json');
echo $auth;
?>