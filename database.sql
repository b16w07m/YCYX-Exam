CREATE DATABASE IF NOT EXISTS exam_system;
USE exam_system;

CREATE TABLE users (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    phone VARCHAR(11) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
    id VARCHAR(255) PRIMARY KEY,
    phone VARCHAR(11) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_super TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_requests (
    id VARCHAR(255) PRIMARY KEY,
    phone VARCHAR(11) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE questions (
    id VARCHAR(255) PRIMARY KEY,
    type ENUM('single_choice', 'multiple_choice', 'true_false') NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL,
    content TEXT NOT NULL,
    options JSON,
    correct_answer JSON,
    score INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE exams (
    id VARCHAR(255) PRIMARY KEY,
    status ENUM('pending', 'started', 'ended') DEFAULT 'pending',
    question_ids JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE exams_exam1 (
    id VARCHAR(255) PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    exam_id VARCHAR(255) NOT NULL,
    score INT DEFAULT 0,
    answers JSON,
    duration INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rank INT
);

CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    description TEXT,
    exam_enabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO settings (id, name, description, exam_enabled) VALUES (1, '考试系统', '在线考试系统', 0);