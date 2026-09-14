<?php
require_once __DIR__ . '/../config/constants.php';

class JWT {
    public static function generateToken($userId, $email, $role) {
        $header = base64_encode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));

        $payload = base64_encode(json_encode([
            'user_id' => $userId,
            'email' => $email,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY
        ]));

        $signature = base64_encode(hash_hmac('sha256', 
            "$header.$payload", 
            JWT_SECRET, 
            true
        ));

        return "$header.$payload.$signature";
    }
}
?>