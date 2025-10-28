-- 投票システム用テーブル
-- データベース: xxopfrlp_shinsei

CREATE TABLE IF NOT EXISTS `vote` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '投票項目ID',
    `item_name` VARCHAR(255) NOT NULL UNIQUE COMMENT '投票項目名',
    `vote_count` INT NOT NULL DEFAULT 0 COMMENT '得票数',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    INDEX `idx_vote_count` (`vote_count` DESC),
    INDEX `idx_item_name` (`item_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投票項目テーブル';

-- 初期データ（オプション）
INSERT INTO `vote` (`item_name`, `vote_count`) VALUES
('ラーメン', 0),
('カレー', 0),
('寿司', 0)
ON DUPLICATE KEY UPDATE `item_name` = VALUES(`item_name`);
