-- Admin Panel Database Setup
-- Add admin-specific columns and tables

-- Update users table to support admin roles
ALTER TABLE `users` ADD COLUMN `role` ENUM('user', 'admin', 'super_admin') NOT NULL DEFAULT 'user';
ALTER TABLE `users` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `users` ADD COLUMN `last_login` TIMESTAMP NULL;
ALTER TABLE `users` ADD COLUMN `status` ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active';

-- Create admin sessions table
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_session_token` (`session_token`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create crawl jobs table
CREATE TABLE IF NOT EXISTS `crawl_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(512) NOT NULL,
  `status` ENUM('pending', 'running', 'completed', 'failed', 'paused') NOT NULL DEFAULT 'pending',
  `priority` ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
  `pages_crawled` int(11) DEFAULT 0,
  `images_found` int(11) DEFAULT 0,
  `errors_count` int(11) DEFAULT 0,
  `started_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  `created_by` int(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create search analytics table
CREATE TABLE IF NOT EXISTS `search_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `search_term` varchar(255) NOT NULL,
  `search_type` ENUM('sites', 'images') NOT NULL DEFAULT 'sites',
  `results_count` int(11) NOT NULL DEFAULT 0,
  `user_ip` varchar(45),
  `user_agent` text,
  `response_time_ms` int(11),
  `clicked_result_id` int(11) NULL,
  `clicked_result_type` ENUM('site', 'image') NULL,
  `search_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_search_term` (`search_term`),
  INDEX `idx_search_type` (`search_type`),
  INDEX `idx_search_date` (`search_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create system logs table
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
  `category` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `context` JSON NULL,
  `user_id` int(11) NULL,
  `ip_address` varchar(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_level` (`level`),
  INDEX `idx_category` (`category`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes to existing tables for better performance
ALTER TABLE `sites` ADD INDEX `idx_title` (`title`(100));
ALTER TABLE `sites` ADD INDEX `idx_clicks` (`clicks`);
ALTER TABLE `sites` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `sites` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `images` ADD INDEX `idx_alt` (`alt`(100));
ALTER TABLE `images` ADD INDEX `idx_clicks` (`clicks`);
ALTER TABLE `images` ADD INDEX `idx_broken` (`broken`);
ALTER TABLE `images` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `images` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Insert default admin user (password: admin123 - change this!)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `status`) VALUES 
('admin', 'admin@doogle.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active')
ON DUPLICATE KEY UPDATE `role` = 'super_admin';

COMMIT;