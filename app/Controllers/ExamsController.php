<?php
namespace App\Controllers;

use App\Lib\Database;
use App\Lib\RedisClient;

class ExamsController {
    private array $errors;

    public function __construct() {
        $this->errors = require dirname(__FILE__) . '/../config/errors.php';
    }

    public function index(): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM exams");
        $exams = [];
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
        return ['data' => $exams];
    }

    public function generate(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM questions WHERE type IN ('single_choice', 'multiple_choice', 'true_false') ORDER BY RAND() LIMIT 10");
        if ($result->num_rows < 5) {
            return ['error' => $this->errors['insufficient_questions'], 'status' => 400];
        }
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row['id'];
        }
        $exam_id = 'exam_' . uniqid();
        $db->insert(
            "INSERT INTO exams (id, status, question_ids) VALUES (?, ?, ?)",
            [$exam_id, 'pending', json_encode($questions)]
        );
        $redis->del('questions:exam_1');
        return ['message' => '考试生成成功', 'data' => ['exam_id' => $exam_id]];
    }

    public function preview(): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM exams WHERE id = 'exam_1'");
        $exam = $result->fetch_assoc();
        if (!$exam) {
            return ['error' => $this->errors['exam_not_found'], 'status' => 404];
        }
        $question_ids = json_decode($exam['question_ids'], true);
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $result = $db->query("SELECT * FROM questions WHERE id IN ($placeholders)", $question_ids);
        $html = '<html><body>';
        while ($row = $result->fetch_assoc()) {
            $html .= '<div>' . htmlspecialchars($row['content']) . '</div>';
            $options = json_decode($row['options'], true);
            foreach ($options as $index => $option) {
                $html .= '<div>' . ($index + 1) . '. ' . htmlspecialchars($option) . '</div>';
            }
        }
        $html .= '</body></html>';
        return ['data' => ['html' => $html]];
    }

    public function export(): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT u.name, u.phone, e.score, e.rank FROM exams_exam1 e JOIN users u ON e.user_id = u.id");
        $data = "name,phone,score,rank\n";
        while ($row = $result->fetch_assoc()) {
            $data .= implode(',', [$row['name'], $row['phone'], $row['score'], $row['rank']]) . "\n";
        }
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'your-encryption-key';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        $output = base64_encode($iv . $encrypted);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="results_encrypted.csv"');
        echo $output;
        exit;
    }

    public function start(string $id): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        $db = Database::getInstance();
        $db->insert("UPDATE exams SET status = 'started' WHERE id = ?", [$id]);
        $redis->setEx("exam:start_time:$id", 86400, time());
        return ['message' => '考试开始成功'];
    }

    public function end(string $id): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        $db = Database::getInstance();
        $db->insert("UPDATE exams SET status = 'ended' WHERE id = ?", [$id]);
        $redis->del("exam:start_time:$id");
        return ['message' => '考试结束成功'];
    }
}