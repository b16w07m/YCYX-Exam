<?php
namespace App\Controllers;

use App\Lib\Database;
use App\Lib\RedisClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController {
    private array $errors;
    private string $jwtSecret;

    public function __construct() {
        $this->errors = require dirname(__FILE__) . '/../config/errors.php';
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    }

    public function login(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';
        if (!$phone || !$password) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM users WHERE phone = ?", [$phone]);
        $user = $result->fetch_assoc();
        if (!$user || !password_verify($password, $user['password'])) {
            return ['error' => $this->errors['invalid_credentials'], 'status' => 401];
        }
        $payload = [
            'user_id' => $user['id'],
            'phone' => $user['phone'],
            'role' => 'user',
            'exp' => time() + 3600
        ];
        $token = JWT::encode($payload, $this->jwtSecret, 'HS256');
        $refreshToken = bin2hex(random_bytes(32));
        $redis = RedisClient::getInstance()->getRedis();
        $redis->setEx("refresh_token:$refreshToken", 604800, $user['id']);
        return ['data' => ['token' => $token, 'refresh_token' => $refreshToken]];
    }

    public function adminLogin(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';
        if (!$phone || !$password) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM admins WHERE phone = ?", [$phone]);
        $admin = $result->fetch_assoc();
        if (!$admin || !password_verify($password, $admin['password'])) {
            return ['error' => $this->errors['invalid_credentials'], 'status' => 401];
        }
        $payload = [
            'user_id' => $admin['id'],
            'phone' => $admin['phone'],
            'role' => $admin['is_super'] ? 'super_admin' : 'admin',
            'exp' => time() + 3600
        ];
        $token = JWT::encode($payload, $this->jwtSecret, 'HS256');
        $refreshToken = bin2hex(random_bytes(32));
        $redis = RedisClient::getInstance()->getRedis();
        $redis->setEx("refresh_token:$refreshToken", 604800, $admin['id']);
        return ['data' => ['token' => $token, 'refresh_token' => $refreshToken]];
    }

    public function refreshToken(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $refreshToken = $data['refresh_token'] ?? '';
        if (!$refreshToken) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $redis = RedisClient::getInstance()->getRedis();
        $userId = $redis->get("refresh_token:$refreshToken");
        if (!$userId) {
            return ['error' => $this->errors['invalid_refresh_token'], 'status' => 401];
        }
        $db = Database::getInstance();
        $isAdmin = false;
        $result = $db->query("SELECT * FROM admins WHERE id = ?", [$userId]);
        $user = $result->fetch_assoc();
        if (!$user) {
            $result = $db->query("SELECT * FROM users WHERE id = ?", [$userId]);
            $user = $result->fetch_assoc();
            if (!$user) {
                return ['error' => $this->errors['user_not_found'], 'status' => 404];
            }
        } else {
            $isAdmin = true;
        }
        $payload = [
            'user_id' => $user['id'],
            'phone' => $user['phone'],
            'role' => $isAdmin ? ($user['is_super'] ? 'super_admin' : 'admin') : 'user',
            'exp' => time() + 3600
        ];
        $token = JWT::encode($payload, $this->jwtSecret, 'HS256');
        $newRefreshToken = bin2hex(random_bytes(32));
        $redis->del("refresh_token:$refreshToken");
        $redis->setEx("refresh_token:$newRefreshToken", 604800, $user['id']);
        return ['data' => ['token' => $token, 'refresh_token' => $newRefreshToken]];
    }
}