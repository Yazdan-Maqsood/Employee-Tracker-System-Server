<?php
require_once __DIR__ . '/../config/constants.php';

// Add this robust helper function
function getBearerToken() {
    $headers = null;
    
    // 1. Check all possible Server variables SiteGround might use
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { 
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } else if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        // Very common on FastCGI environments
        $headers = trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
    } 
    // 2. Fallback to apache_request_headers
    elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Normalize keys to title case to avoid case-sensitivity issues
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    // 3. Extract the token from the "Bearer <token>" string
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

function validateToken() {
    // Use the new helper function instead of getallheaders()
    $token = getBearerToken();
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            "status" => 401,
            "message" => "Access denied. No token provided."
        ]);
        exit();
    }
    
    try {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            throw new Exception("Invalid token format");
        }

        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        if (!$payload) {
            throw new Exception("Invalid token payload");
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "message" => "Token has expired"
            ]);
            exit();
        }

        return $payload;
        
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "status" => 401,
            "message" => "Invalid token"
        ]);
        exit();
    }
}
?>