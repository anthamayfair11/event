-- 質問掲示板テーブル
CREATE TABLE IF NOT EXISTS question_board (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_text TEXT NOT NULL,
  category ENUM('participation', 'venue', 'content', 'other') DEFAULT 'other',
  answer_text TEXT NULL,
  answered_by VARCHAR(50) NULL,
  is_published BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  answered_at TIMESTAMP NULL,
  INDEX idx_created (created_at DESC),
  INDEX idx_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
