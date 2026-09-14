<?php
define('PUSHER_APP_ID', '2172871');
define('PUSHER_KEY', '540d521fe5fe005cb99d');
define('PUSHER_SECRET', '6252d04d62d5a05792fd');
define('PUSHER_CLUSTER', 'ap2');

// ✅ Create the Pusher object that API files use
require_once __DIR__ . '/../vendor/autoload.php';

$options = [
    'cluster' => PUSHER_CLUSTER,
    'useTLS' => true
];

$pusher = new Pusher\Pusher(
    PUSHER_KEY,
    PUSHER_SECRET,
    PUSHER_APP_ID,
    $options
);
?>