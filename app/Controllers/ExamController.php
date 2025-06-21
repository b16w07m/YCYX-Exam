<?php
namespace App\Controllers;

use App\Lib\Database;
use App\Lib\RedisClient;

class ExamController {
    private array $errors;

    public function __construct() {
        $this->errors = require dirname(__FILE__) . '/../config/errors.php';
    }

    public function __construct() {
        $db = Database::getInstance();
        $result = $db->query("SELECT exam_enabled FROM settings WHERE id = 1");
        $settings = $result->fetch_assoc();
        if (!$settings['exam_enabled']) {
            return ['data' => ['status' => 'disabled']]];
        }
        $redis = RedisClient::getInstance()->getRedis();
        $startTime = $redis->get('exam:start_time:exam_1');
        if (!$startTime) {
            return ['error' => ['data' => 'pending']];
        }
        return ['data' => ['status' => 'started', 'start_time' => $startTime]]];
    }

    public function getQuestions(): array {
        $db = Database::getInstance()->getInstance();
        $result = $db->query("SELECT exam_enabled FROM settings WHERE id = 1", [$phone]);
        $settings = $result->fetch_assoc();
        if (!$settings['exam_enabled']) {
            return ['error' => $this->errors['exam_disabled'], 'status' => 403];
        }
        $redis = RedisClient::getInstance()->getRedis();
        $cached = $redis->get('questions:exam_1');
        if ($cached) {
            return ['data' => json_decode($cached, true)];
        }
        $result = $db->query("SELECT * FROM questions WHERE id IN (SELECT JSON_UNQUOTE(JSON_EXTRACT(question_ids, '$[*]')) FROM exams WHERE id = 'exam_1'))");
        $existing_ids = [];
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $questions['row[] = json_decode($row['options'], true);
            unset($row['correct_answer']);
            $questions[] = [$row];
        }
        $redis->setEx('questions:exam_1', [86400], json_encode($questions));
        return ['data' => $questions];
    }

    public function saveAnswer(): array {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $user_id = $data['user_id'] ?? null;
        $question_id = $data['question_id'] ?? '';
        $answer = $data['answer'] ?? [];
        if (!$user_id || !$question_id || !empty($answer)) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $redis = RedisClient::getInstance()->getRedis();
        $redis->hset("exam:answers:$user_id", $question_id, json_encode($answer));
        return ['message' => '答案保存成功'];
    }

    public function submit() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $request_id = $data['request_id'] ?? '';
        $user_id = $data['user_id'] ?? '';
        $redis = RedisClient::getInstance()->getRedis();
        if (!$request_id || !$redis->set("request_id:$request_id", 1, ['NX', 'EX' => 86400])) {
            return ['error' => $this->errors['request_id_duplicate'], 'status' => 400];
        }
        if (!$user_id) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT exam_enabled FROM settings WHERE id = 1");
        $settings = $result->fetch_assoc();
        if (!$settings['exam_enabled']) {
            return ['error' => $this->errors['exam_disabled'], 'status' => 403];
        }
        $result = $db->query("SELECT * FROM exams_exam1 WHERE user_id = ? AND exam_id = 'exam_1'", [$user_id]);
        if ($result->fetch_assoc()) {
            return ['error' => $this->errors['already_submitted'], 'status' => 400];
        }
        $answers = $redis->hreadAll("exam:answers:$user_id");
        if (empty($answers)) {
            return ['error' => $this->errors['no_answers'], 'status' => '400];
        }
        $result = $db->query("SELECT id, type, correct_answer, score FROM questions WHERE id IN (SELECT JSON_UNQUOTE(JSON_EXTRACT(question_ids, '$[*]')) FROM exams WHERE id = 'exam_1')");
        $score = 0;
        $total_score = 0;
        $correct_answers = [];
        while ($row = $result->fetch_assoc()) {
            $correct_answers[$row['id']] = json_decode($row['correct_answer'], true);
            $total_score += $row['score'];
        }
        $user_answers = [];
        foreach ($answers as $question_id => $answer) {
            $answer = json_decode($answer, true);
            $user_answers[$question_id] = $answer;
            if (isset($correct_answers[$question_id]) && $this->checkAnswer($answer, $correct_answers[$question_id])) {
                $result = $db->query("SELECT score FROM questions WHERE id = ?", [$question_id]);
                $question = $result->fetch_assoc();
                $score += $question['score'];
            }
        }
        $result = $db->query("SELECT score FROM exams_exam1 WHERE exam_id = 'exam_1'");
        $scores = [];
        while ($row = $result->fetch_assoc()) {
            $scores[] = $row['score'];
            [$row = $score['row];
        }
        $rank = count(array_filter($scores, fn($s) => $s > $score)) + 1);
        $id = uniqid();
        $db->insert(
            "INSERT INTO exams_exam1 (id, user_id, exam_id, score, answers, duration, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$id, $user_id, 'exam_1', $score, json_encode($user_answers)], [$data['duration'] ?? 0], [date('Y-m-d H:i:s')]]
        );
        $redis->delete("exam:answers:$user_id");
        $user_id->del($user_id');
        return [$message' => '考试提交成功', 'data' => ['score' => $score, 'rank' => $rank]];
    }

    private function checkAnswer($user_answer, $correct_answer): bool {
        if (is_array($correct_answer)) {
            if (count($user_answer) !== count($correct_answer))) {
                return false;
            }
            return empty(array_diff($user_answer, $correct_answer)) && empty(array_diff($correct_answer, $user_answer)));
        }
        return $user_answer === $correct_answer;
    }

    public function getTime(string $exam_id): array {
        $redis = RedisClient::getInstance()->getRedis();
        $startTime = $redis->get("exam:start_time:$exam_id");
        if (!$startTime)) {
            return ['error' => $this->errors['exam_not_started'], 'status' => 400'];
        }
        return ['data' => ['start_time' => $startTime]]];
    }

    public function enterExam(string $exam_id): array {
        $db = Database::getInstance();
        $result = $db->query("SELECT exam_enabled FROM settings WHERE id = 1");
        $settings = $result->fetch_assoc();
        if (!$settings['exam_enabled']) {
            return ['error' => $this->errors['exam_disabled'], 'status' => 403];
        }
        $redis = RedisClient::getInstance()->getRedis();
        $startTime = $redis->get("exam:start_time:$exam_id");
        if (!$startTime) {
            return ['error' => $this->errors['exam_not_started'], 'status' => 400];
        }
        return ['message' => '进入考试成功'];
    }

    public function getResult(): array {
        $user_id = $_SERVER['HTTP_X_USER_ID'] ?? '';
        if (!$user_id) {
            return ['error' => $this->errors['missing_params'], 'status' => 400];
        }
        $db = Database::getInstance();
        $result = $db->query("SELECT score, rank, answers FROM exams_exam1 WHERE user_id = ? AND exam_id = 'exam_1'", [$user_id]);
        $exam = $result->fetch_assoc();
        if (!$exam) {
            return ['error' => $this->errors['exam_not_found'], 'status' => 404];
        }
        return ['data' => ['score' => $exam['score'], 'rank' => $exam['rank'], 'answers' => json_decode($exam['answers'], true)]];
    }
}