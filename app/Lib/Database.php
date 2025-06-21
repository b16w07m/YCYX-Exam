<?php
namespace App\Lib;

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new \mysqli(
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASSWORD'] ?? '',
            $_ENV['DB_NAME'] ?? 'exam_system'
        );
        if ($this->conn->connect_error) {
            die("数据库连接失败: " . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function query(string $sql, array $params = []) {
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new \Exception("准备语句失败: " . $this->conn->error);
        }
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            return $stmt;
        }
        return $result;
    }

    public function insert(string $sql, array $params = []): void {
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new \Exception("准备语句失败: " . $this->conn->error);
        }
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            throw new \Exception("执行语句失败: " . $stmt->error);
        }
        $stmt->close();
    }
}