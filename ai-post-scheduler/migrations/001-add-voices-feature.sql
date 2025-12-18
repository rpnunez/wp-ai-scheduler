-- AI Post Scheduler Migration: Add Voices Feature
-- Version: 1.0 -> 1.1
-- Description: Adds voice management system and batch post generation support
-- 
-- INSTRUCTIONS:
-- Replace {wp_prefix} with your actual WordPress table prefix (default: wp_)
-- You can find your prefix in wp-config.php: $table_prefix = 'wp_';

-- Create voices table if it doesn't exist
CREATE TABLE IF NOT EXISTS `{wp_prefix}aips_voices` (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    title_prompt text NOT NULL,
    content_instructions text NOT NULL,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add voice_id column to templates table if it doesn't exist
-- If column already exists, this will be safely ignored
ALTER TABLE `{wp_prefix}aips_templates` ADD COLUMN `voice_id` bigint(20) DEFAULT NULL AFTER `title_prompt`;

-- Add post_quantity column to templates table if it doesn't exist
-- If column already exists, this will be safely ignored
ALTER TABLE `{wp_prefix}aips_templates` ADD COLUMN `post_quantity` int DEFAULT 1 AFTER `voice_id`;

-- Add excerpt_instructions column to voices table if it doesn't exist
-- If column already exists, this will be safely ignored
ALTER TABLE `{wp_prefix}aips_voices` ADD COLUMN `excerpt_instructions` text AFTER `content_instructions`;
