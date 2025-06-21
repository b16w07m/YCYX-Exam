<?php
require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ExamController;
use App\Controllers\QuestionController;
use App\Controllers\ExamsController;
use App\Controllers\SettingsController;
use App\Middleware\AuthMiddleware;

header('Content-Type: application/json');

$dotenv = Dotenv::createImmutable(dirname(__FILE__) . '/..');
$dotenv->load();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$authMiddleware = new AuthMiddleware();

$routes = [
    'POST' => [
        '/api/login' => fn() => (new AuthController())->login(),
        '/api/admin/login' => fn() => (new AuthController())->adminLogin(),
        '/api/refresh-token' => fn() => (new AuthController())->refreshToken(),
        '/api/users' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->store(),
        '/api/users/batch-import' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->batchImport(),
        '/api/users/batch-update-password' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->batchUpdatePassword(),
        '/api/users/confirm-batch-update-password' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->confirmBatchUpdatePassword(),
        '/api/users/reset-password-request' => fn() => (new UserController())->requestPasswordReset(),
        '/api/users/reset-password/{id}' => fn($id) => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->handlePasswordRequest($id),
        '/api/exams/save-answer' => fn() => $authMiddleware->restrict(['user']) || (new ExamController())->saveAnswer(),
        '/api/exams/submit' => fn() => $authMiddleware->restrict(['user']) || (new ExamController())->submit(),
        '/api/exams/generate' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new ExamsController())->generate(),
        '/api/exams/start/{id}' => fn($id) => $authMiddleware->restrict(['super_admin', 'admin']) || (new ExamsController())->start($id),
        '/api/exams/end/{id}' => fn($id) => $authMiddleware->restrict(['super_admin', 'admin']) || (new ExamsController())->end($id),
        '/api/questions' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new QuestionController())->store(),
        '/api/questions/batch-import' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new QuestionController())->batchImport(),
        '/api/settings' => fn() => $authMiddleware->restrict(['super_admin']) || (new SettingsController())->update(),
    ],
    'GET' => [
        '/api/users' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->index(),
        '/api/users/search/{phone}' => fn($phone) => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->search($phone),
        '/api/users/reset-password-requests' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->getPasswordRequests(),
        '/api/exams/status' => fn() => $authMiddleware->restrict(['user']) || (new ExamController())->getStatus(),
        '/api/exams/questions' => fn() => $authMiddleware->restrict(['user']) || (new ExamController())->getQuestions(),
        '/api/exams/time/{id}' => fn($id) => $authMiddleware->restrict(['user']) || (new ExamController())->getTime($id),
        '/api/exams/enter/{id}' => fn($id) => $authMiddleware->restrict(['user']) || (new ExamController())->enterExam($id),
        '/api/exams/result' => fn() => $authMiddleware->restrict(['user']) || (new ExamController())->getResult(),
        '/api/exams' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new ExamsController())->index(),
        '/api/exams/preview' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new ExamsController())->preview(),
        '/api/exams/export' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new ExamsController())->export(),
        '/api/questions' => fn() => $authMiddleware->restrict(['super_admin', 'admin']) || (new QuestionController())->index(),
        '/api/settings' => fn() => $authMiddleware->restrict(['super_admin']) || (new SettingsController())->get(),
    ],
    'PUT' => [
        '/api/users/{id}' => fn($id) => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->update($id),
        '/api/questions/{id}' => fn($id) => $authMiddleware->restrict(['super_admin', 'admin']) || (new QuestionController())->update($id),
    ],
    'DELETE' => [
        '/api/users/{id}' => fn($id) => $authMiddleware->restrict(['super_admin', 'admin']) || (new UserController())->delete($id),
        '/api/questions/{id}' => fn($id) => $authMiddleware->restrict(['super_admin', 'admin']) || (new QuestionController())->delete($id),
    ],
];

function handleRoute($method, $uri, $routes) {
    if (!isset($routes[$method])) {
        http_response_code(405);
        echo json_encode(['error' => '方法不允许']);
        exit;
    }
    foreach ($routes[$method] as $route => $handler) {
        $routePattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route);
        if (preg_match("#^$routePattern$#", $uri, $matches)) {
            array_shift($matches);
            $response = call_user_func_array($handler, $matches);
            echo json_encode($response);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => '路由不存在']);
}

handleRoute($method, $uri, $routes);