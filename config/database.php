<?php
date_default_timezone_set('Asia/Karachi');
class Database {
    private $host = "localhost";
    private $db_name = "dbnbmdkfm1hetr";
    private $username = "uxx0ca1cgrs8a";
    private $password = "Desired@671_zie";
    public $conn;   

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET time_zone = '+05:00';");

        } catch(PDOException $e) {
            echo json_encode([
                "status" => 500,
                "message" => "Connection error: " . $e->getMessage()
            ]);
        }
        return $this->conn;
    }
}
?>