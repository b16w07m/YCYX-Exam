<?php
namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    private array $errors;
    private string $jwtSecret;

    public function __construct() {
        $this->errors = require dirname(__FILE__) . '/../config/errors.php';
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    }

    public function restrict(array $allowedRoles): void {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->respond(['error' => $this->errors['missing_token'], 'status' => 401]);
        }
        $token = $matches[1];
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            if (!in_array($decoded->role, $allowedRoles)) {
                $this->respond(['error' => $this->errors['unauthorized'], 'status' => 403]);
            }
            $_SERVER['HTTP_X_USER_ID'] = $decoded->user_id;
            $_SERVER['HTTP_X_PHONE'] = $decoded->phone;
            $_SERVER['HTTP_X_ROLE'] = $decoded->role;
        } catch (\Exception $e) {
            $this->respond(['error' => $this->errors['invalid_token'], 'status' => 401]);
        }
    }

    private function respond(array $data): void {
        header('Content-Type: application/json');
        http_response_code($data['status'] ?? 200);
        echo json_encode($data);
        exit;
    }
}