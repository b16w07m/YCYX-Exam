<?php
namespace App\Controllers;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use App\Lib\Database;
use App\Lib\RedisClient;

class UserController {
    private array $errors;

    public function __construct() {
        $this->errors = require dirname(__FILE__) . '/../config/errors.php';
    }

    public function index(): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT id, name, company, phone FROM users");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return ['data' => $users];
    }

    public function store(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        $name = $data['name'] ?? '';
        $company = $data['company'] ?? '';
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';
        if (!$name || !$phone || !$password) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return ['error' => $this->errors['invalid_phone'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT id FROM users WHERE phone = ?", [$phone]);
        if ($result->fetch_assoc()) {
            return ['error' => $this->errors['phone_exists'], 'status' => 400];
        }
        $id = uniqid();
        $db->insert(
            "INSERT INTO users (id, name, company, phone, password) VALUES (?, ?, ?, ?, ?)",
            [$id, $name, $company, $phone, password_hash($password, PASSWORD_DEFAULT)]
        );
        return ['message' => '用户创建成功', 'data' => ['id' => $id]];
    }

    public function update(string $id): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT id FROM users WHERE id = ?", [$id]);
        if (!$result->fetch_assoc()) {
            return ['error' => $this->errors['user_not_found'], 'status' => 404];
        }
        $updates = [];
        $params = [];
        if (!empty($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }
        if (!empty($data['company'])) {
            $updates[] = "company = ?";
            $params[] = $data['company'];
        }
        if (!empty($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (empty($updates)) {
            return ['message' => $this->errors['no_update']];
        }
        $params[] = $id;
        $db->insert("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        return ['message' => '用户更新成功'];
    }

    public function delete(string $id): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT id FROM users WHERE id = ?", [$id]);
        if (!$result->fetch_assoc()) {
            return ['error' => $this->errors['user_not_found'], 'status' => 404];
        }
        $db->insert("DELETE FROM users WHERE id = ?", [$id]);
        return ['message' => '用户删除成功'];
    }

    public function batchImport(): array {
        if (!isset($_FILES['file'])) {
            return ['error' => $this->errors['file_not_uploaded'], 'status' => 400];
        }
        $reader = ReaderEntityFactory::createReaderFromFile($_FILES['file']['tmp_name']);
        $reader->open($_FILES['file']['tmp_name']);
        $db = Database::getInstance();
        $failed_rows = [];
        $row_index = 1;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $data = $row->toArray();
                $name = $data[0] ?? '';
                $company = $data[1] ?? '';
                $phone = $data[2] ?? '';
                $password = $data[3] ?? '';
                if (!$name || !$password) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['missing_params']];
                    $row_index++;
                    continue;
                }
                if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['invalid_phone']];
                    $row_index++;
                    continue;
                }
                $result = $db->query("SELECT id FROM users WHERE phone = ?", [$phone]);
                if ($result->fetch_assoc()) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['phone_exists']];
                    $row_index++;
                    continue;
                }
                $id = uniqid();
                try {
                    $db->insert(
                        "INSERT INTO users (id, name, company, phone, password) VALUES (?, ?, ?, ?, ?)",
                        [$id, $name, $company, $phone, password_hash($password, PASSWORD_DEFAULT)]
                    );
                } catch (\Exception $e) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['db_insert_failed'] . ': ' . $e->getMessage()];
                }
                $row_index++;
            }
        }
        $reader->close();
        return ['message' => '批量导入完成', 'failed_rows' => $failed_rows];
    }

    public function search(string $phone): array {
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return ['error' => $this->errors['invalid_phone'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT id, name, company, phone FROM users WHERE phone = ?", [$phone]);
        $user = $result->fetch_assoc();
        if (!$user) {
            return ['error' => $this->errors['user_not_found'], 'status' => 404];
        }
        return ['data' => $user];
    }

    public function batchUpdatePassword(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $new_password = $data['new_password'] ?? '';
        $ids = $data['ids'] ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        if (!$new_password || empty($ids)) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $result = $db->query("SELECT id FROM users WHERE id IN ($placeholders)", $ids);
        $existing_ids = [];
        while ($row = $result->fetch_assoc()) {
            $existing_ids[] = $row['id'];
        }
        if (count($existing_ids) !== count($ids)) {
            return ['error' => $this->errors['user_not_found'], 'status' => 404];
        }
        $db->insert(
            "UPDATE users SET password = ? WHERE id IN ($placeholders)",
            array_merge([password_hash($new_password, PASSWORD_DEFAULT)], $ids)
        );
        return ['message' => '密码批量更新成功'];
    }

    public function confirmBatchUpdatePassword(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids = $data['ids'] ?? [];
        if (empty($ids)) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $result = $db->query("SELECT id FROM users WHERE id IN ($placeholders)", $ids);
        $existing_ids = [];
        while ($row = $result->fetch_assoc()) {
            $existing_ids[] = $row['id'];
        }
        return ['data' => ['ids' => $existing_ids]];
    }

    public function requestPasswordReset(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone = $data['phone'] ?? '';
        if (!$phone || !preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return ['error' => $this->errors['invalid_phone'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT id FROM users WHERE phone = ?", [$phone]);
        if (!$result->fetch_assoc()) {
            return ['error' => $this->errors['user_not_found'], 'status' => 404];
        }
        $id = uniqid();
        $db->insert(
            "INSERT INTO password_requests (id, phone, status) VALUES (?, ?, 'pending')",
            [$id, $phone]
        );
        return ['message' => '密码重置申请已提交'];
    }

    public function getPasswordRequests(): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT id, phone, status, created_at FROM password_requests");
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        return ['data' => $requests];
    }

    public function handlePasswordRequest(string $id): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $data['status'] ?? '';
        $new_password = $data['new_password'] ?? '';
        if (!in_array($status, ['approved', 'rejected'])) {
            return ['error' => $this->errors['invalid_params'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT phone FROM password_requests WHERE id = ?", [$id]);
        $request = $result->fetch_assoc();
        if (!$request) {
            return ['error' => $this->errors['request_not_found'], 'status' => 404];
        }
        if ($status === 'approved' && !$new_password) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $db->insert("UPDATE password_requests SET status = ? WHERE id = ?", [$status, $id]);
        if ($status === 'approved') {
            $db->insert(
                "UPDATE users SET password = ? WHERE phone = ?",
                [password_hash($new_password, PASSWORD_DEFAULT), $request['phone']]
            );
        }
        return ['message' => '密码重置请求处理成功'];
    }
}