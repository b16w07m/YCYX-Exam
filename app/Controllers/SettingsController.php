<?php
namespace App\Controllers;

use App\Lib\Database;
use App\Lib\RedisClient;

class SettingsController {
    private array $errors;

    public function __construct() {
        $this->errors = require dirname(__FILE__) . '/../config/errors.php';
    }

    public function get(): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM settings WHERE id = 1");
        $settings = $result->fetch_assoc();
        return ['data' => $settings];
    }

    public function update(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        $db = Database::getInstance();
        $updates = [];
        $params = [];
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        if (isset($data['exam_enabled'])) {
            $updates[] = "exam_enabled = ?";
            $params[] = $data['exam_enabled'] ? 1 : 0;
        }
        if (empty($updates)) {
            return ['message' => $this->errors['no_update']];
        }
        $db->insert("UPDATE settings SET " . implode(', ', $updates) . " WHERE id = 1", $params);
        return ['message' => '设置更新成功'];
    }
}