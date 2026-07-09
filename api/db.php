<?php
// กำหนด header เพื่อให้รองรับ CORS (สำคัญสำหรับการทำงานแบบ AJAX)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

class Database {
    private $host = 'localhost';
    private $db_name = ''; // ⚠️ แก้ไขชื่อฐานข้อมูลของคุณ
    private $user = '';        // ⚠️ แก้ไขชื่อผู้ใช้ฐานข้อมูลของคุณ
    private $password = '';    // ⚠️ แก้ไขรหัสผ่านของคุณ
    public $conn;

    public function __construct() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, $this->user, $this->password, $options);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Connection failed: " . $e->getMessage()]);
            die();
        }
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Query failed: " . $e->getMessage(), "sql" => $sql, "params" => $params]);
            die();
        }
    }
}