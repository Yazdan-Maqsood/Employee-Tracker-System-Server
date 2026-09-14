<?php
class Response {
    public static function send($status, $data = null, $message = "") {
        http_response_code($status);
        $response = ["status" => $status];
        
        if ($message) {
            $response["message"] = $message;
        }
        
        if ($data) {
            $response = array_merge($response, $data);
        }
        
        echo json_encode($response);
        exit();
    }

    public static function error($status, $message) {
        self::send($status, null, $message);
    }
}
?>