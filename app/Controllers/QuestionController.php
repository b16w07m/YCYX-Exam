<?php
namespace App\Controllers;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use App\Lib\Database;
use App\Lib\RedisClient;
use App\Enums\QuestionType;
use App\Enums\QuestionDifficulty;

class QuestionController {
    private array $errors;

    public function __construct() {
        $this->errors = require dirname(__FILE__) . '/../config/errors.php';
    }

    public function index(): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM questions");
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $row['options'] = json_decode($row['options'], true);
            $row['correct_answer'] = json_decode($row['correct_answer'], true);
            $questions[] = $row;
        }
        return ['data' => $questions];
    }

    public function store(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        if (strlen($data['content'] ?? '') > 1000) {
            return ['error' => $this->errors['question_content_too_long'], 'status' => 400];
        }
        if (!in_array($data['type'] ?? '', ['single_choice', 'multiple_choice', 'true_false'])) {
            return ['error' => $this->errors['invalid_type'], 'status' => 400];
        }
        if (!in_array($data['difficulty'] ?? '', ['easy', 'medium', 'hard'])) {
            return ['error' => $this->errors['invalid_params'], 'status' => 400];
        }
        $options = $data['options'] ?? [];
        if ($data['type'] !== 'true_false' && (count($options) < 2 || count($options) > 9)) {
            return ['error' => $this->errors['options_exceed'], 'status' => 400];
        }
        $correct_answer = $data['correct_answer'] ?? [];
        if ($data['type'] === 'multiple_choice' && empty($correct_answer)) {
            return ['error' => $this->errors['invalid_answer_format'], 'status' => 400];
        }
        $db = Database::getInstance();
        $id = uniqid();
        $db->insert(
            "INSERT INTO questions (id, type, difficulty, content, options, correct_answer, score) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $id,
                $data['type'],
                $data['difficulty'],
                $data['content'],
                json_encode($options),
                json_encode($correct_answer),
                $data['score'] ?? 1
            ]
        );
        $redis->del('questions:exam_1');
        return ['message' => '题目创建成功', 'data' => ['id' => $id]];
    }

    public function update(string $id): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM questions WHERE id = ?", [$id]);
        if (!$result->fetch_assoc()) {
            return ['error' => $this->errors['question_not_found'], 'status' => 404];
        }
        if (strlen($data['content'] ?? '') > 1000) {
            return ['error' => $this->errors['question_content_too_long'], 'status' => 400];
        }
        $updates = [];
        $params = [];
        if (isset($data['content'])) {
            $updates[] = "content = ?";
            $params[] = $data['content'];
        }
        if (isset($data['type']) && in_array($data['type'], ['single_choice', 'multiple_choice', 'true_false'])) {
            $updates[] = "type = ?";
            $params[] = $data['type'];
        }
        if (isset($data['difficulty']) && in_array($data['difficulty'], ['easy', 'medium', 'hard'])) {
            $updates[] = "difficulty = ?";
            $params[] = $data['difficulty'];
        }
        if (isset($data['options'])) {
            $updates[] = "options = ?";
            $params[] = json_encode($data['options']);
        }
        if (isset($data['correct_answer'])) {
            $updates[] = "correct_answer = ?";
            $params[] = json_encode($data['correct_answer']);
        }
        if (isset($data['score'])) {
            $updates[] = "score = ?";
            $params[] = $data['score'];
        }
        if (empty($updates)) {
            return ['message' => $this->errors['no_update']];
        }
        $params[] = $id;
        $db->insert("UPDATE questions SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        $redis->del('questions:exam_1');
        return ['message' => '题目更新成功'];
    }

    public function delete(string $id): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT * FROM questions WHERE id = ?", [$id]);
        if (!$result->fetch_assoc()) {
            return ['error' => $this->errors['question_not_found'], 'status' => 404];
        }
        $db->insert("DELETE FROM questions WHERE id = ?", [$id]);
        $redis = RedisClient::getInstance()->getRedis();
        $redis->del('questions:exam_1');
        return ['message' => '题目删除成功'];
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
                $content = $data[0] ?? '';
                $type = $data[1] ?? '';
                $difficulty = $data[2] ?? '';
                $options = json_decode($data[3] ?? '[]', true);
                $correct_answer = json_decode($data[4] ?? '[]', true);
                $score = $data[5] ?? 1;
                if (strlen($content) > 1000) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['question_content_too_long']];
                    $row_index++;
                    continue;
                }
                if (!in_array($type, ['single_choice', 'multiple_choice', 'true_false'])) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['invalid_type']];
                    $row_index++;
                    continue;
                }
                if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['invalid_params']];
                    $row_index++;
                    continue;
                }
                if ($type !== 'true_false' && (count($options) < 2 || count($options) > 9)) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['options_exceed']];
                    $row_index++;
                    continue;
                }
                $id = uniqid();
                try {
                    $db->insert(
                        "INSERT INTO questions (id, type, difficulty, content, options, correct_answer, score) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$id, $type, $difficulty, $content, json_encode($options), json_encode($correct_answer), $score]
                    );
                } catch (\Exception $e) {
                    $failed_rows[] = ['row' => $row_index, 'error' => $this->errors['db_insert_failed'] . ': ' . $e->getMessage()];
                }
                $row_index++;
            }
        }
        $reader->close();
        $redis = RedisClient::getInstance()->getRedis();
        $redis->del('questions:exam_1');
        return ['message' => '批量导入完成', 'failed_rows' => $failed_rows];
    }
}