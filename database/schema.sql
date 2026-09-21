CREATE DATABASE IF NOT EXISTS qr_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qr_attendance;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(120) NOT NULL,
  role ENUM('admin','teacher','student') NOT NULL,
  status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL UNIQUE, student_no VARCHAR(50) UNIQUE,
  class_name VARCHAR(30) NULL, year_level TINYINT UNSIGNED NULL,
  device_uuid VARCHAR(100) NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS subjects (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, year_level TINYINT UNSIGNED NULL, teacher_registration_enabled TINYINT(1) NOT NULL DEFAULT 0);
INSERT INTO subjects (code,name,year_level,teacher_registration_enabled) VALUES
('IT41023','Computer Architecture and Organization',4,1),
('IT41033','Operating Systems',4,1),
('IT41032','Advanced Computer Networks',4,1),
('IT41026','Advanced Data Management Techniques',4,1),
('IT41017','Modern Control System',4,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),year_level=VALUES(year_level),teacher_registration_enabled=VALUES(teacher_registration_enabled);
CREATE TABLE IF NOT EXISTS teachers (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL UNIQUE, staff_no VARCHAR(50) UNIQUE,
  subject_id INT NULL, subject_code VARCHAR(30) NULL, year_level TINYINT UNSIGNED NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id)
);
CREATE TABLE IF NOT EXISTS classrooms (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY, subject_id INT NOT NULL, teacher_id INT NOT NULL, classroom_id INT NULL,
  day_of_week VARCHAR(12) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id), FOREIGN KEY (teacher_id) REFERENCES teachers(id), FOREIGN KEY (classroom_id) REFERENCES classrooms(id)
);
CREATE TABLE IF NOT EXISTS attendance_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT NOT NULL, subject_id INT NOT NULL, schedule_id INT NULL,
  title VARCHAR(160) NOT NULL, starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1, FOREIGN KEY (teacher_id) REFERENCES teachers(id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id), FOREIGN KEY (schedule_id) REFERENCES schedules(id)
);
CREATE TABLE IF NOT EXISTS qr_codes (
  id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, token_hash VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY, session_id INT NOT NULL, student_id INT NOT NULL,
  status ENUM('present','absent','late') NOT NULL DEFAULT 'present', recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_record_per_session (session_id, student_id),
  FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message VARCHAR(255) NOT NULL,
  read_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS api_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(128) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
INSERT IGNORE INTO users (id, username, password_hash, full_name, role, status)
VALUES (1, 'admin', '$2y$10$dJS36qo4BQbAIK30HteQ8.jR2gjHHiVz7kHAqQvf0LNwkuo0BUqjC', 'System Administrator', 'admin', 'active');
