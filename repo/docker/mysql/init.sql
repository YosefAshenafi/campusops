-- CampusOps Database Initialization
-- This runs automatically on first container start

CREATE DATABASE IF NOT EXISTS campusops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campusops;

-- Set names
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Users & Auth Tables
-- ----------------------------
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `permissions` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `salt` varchar(32) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'regular_user',
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `violation_points` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Activities Tables
-- ----------------------------
DROP TABLE IF EXISTS `activity_change_logs`;
DROP TABLE IF EXISTS `activity_signups`;
DROP TABLE IF EXISTS `activity_versions`;
DROP TABLE IF EXISTS `activity_groups`;

CREATE TABLE `activity_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text,
  `tags` text,
  `state` varchar(16) NOT NULL DEFAULT 'draft',
  `max_headcount` int(11) NOT NULL DEFAULT 0,
  `signup_start` datetime DEFAULT NULL,
  `signup_end` datetime DEFAULT NULL,
  `eligibility_tags` text,
  `required_supplies` text,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_signups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'confirmed',
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_change_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `from_version` int(11) NOT NULL,
  `to_version` int(11) NOT NULL,
  `changes` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Orders Tables
-- ----------------------------
DROP TABLE IF EXISTS `order_state_history`;
DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `team_lead_id` int(11) DEFAULT NULL,
  `state` varchar(16) NOT NULL DEFAULT 'placed',
  `items` text,
  `notes` text,
  `payment_method` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ticket_number` varchar(50) DEFAULT NULL,
  `auto_cancel_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `invoice_address` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `order_state_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `from_state` varchar(16) DEFAULT NULL,
  `to_state` varchar(16) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Shipments Tables
-- ----------------------------
DROP TABLE IF EXISTS `shipment_exceptions`;
DROP TABLE IF EXISTS `scan_events`;
DROP TABLE IF EXISTS `shipments`;

CREATE TABLE `shipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `carrier` varchar(64) DEFAULT NULL,
  `tracking_number` varchar(64) DEFAULT NULL,
  `package_contents` text,
  `weight` decimal(10,2) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'created',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `scan_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `scan_code` varchar(64) NOT NULL,
  `location` varchar(64) DEFAULT NULL,
  `scanned_by` int(11) DEFAULT NULL,
  `result` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `shipment_exceptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Violations Tables
-- ----------------------------
DROP TABLE IF EXISTS `user_group_members`;
DROP TABLE IF EXISTS `user_groups`;
DROP TABLE IF EXISTS `violation_appeals`;
DROP TABLE IF EXISTS `violation_evidence`;
DROP TABLE IF EXISTS `violations`;
DROP TABLE IF EXISTS `violation_rules`;

CREATE TABLE `violation_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `description` text,
  `points` int(11) NOT NULL,
  `category` varchar(32) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `violations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `rule_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `notes` text,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `rule_id` (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `violation_evidence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `violation_id` int(11) NOT NULL,
  `filename` varchar(128) NOT NULL,
  `sha256` varchar(64) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `violation_id` (`violation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `violation_appeals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `violation_id` int(11) NOT NULL,
  `appellant_notes` text,
  `reviewer_id` int(11) DEFAULT NULL,
  `decision` varchar(16) DEFAULT NULL,
  `reviewer_notes` text,
  `final_notes` text,
  `created_at` datetime NOT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `violation_id` (`violation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY `id` (`id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Tasks Tables
-- ----------------------------
DROP TABLE IF EXISTS `staffing`;
DROP TABLE IF EXISTS `checklist_items`;
DROP TABLE IF EXISTS `checklists`;
DROP TABLE IF EXISTS `tasks`;

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `assigned_to` int(11) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_by` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `checklist_id` (`checklist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `staffing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL,
  `role` varchar(64) NOT NULL,
  `required_count` int(11) NOT NULL DEFAULT 1,
  `assigned_users` text,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Search & Dashboard Tables
-- ----------------------------
DROP TABLE IF EXISTS `dashboard_favorites`;
DROP TABLE IF EXISTS `dashboards`;
DROP TABLE IF EXISTS `search_index`;

CREATE TABLE `search_index` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(32) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text,
  `tags` text,
  `normalized_text` text,
  `pinyin_text` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `dashboards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `widgets` text,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `dashboard_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `dashboard_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `dashboard_id` (`dashboard_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Notifications Tables
-- ----------------------------
DROP TABLE IF EXISTS `user_preferences`;
DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(32) NOT NULL,
  `title` varchar(128) NOT NULL,
  `body` text,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `arrival_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `activity_alerts` tinyint(1) NOT NULL DEFAULT 1,
  `order_alerts` tinyint(1) NOT NULL DEFAULT 1,
  `violation_alerts` tinyint(1) NOT NULL DEFAULT 1,
  `dashboard_layout` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Audit & Files Tables
-- ----------------------------
DROP TABLE IF EXISTS `file_uploads`;
DROP TABLE IF EXISTS `audit_trail`;

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `entity_type` varchar(32) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `old_state` text,
  `new_state` text,
  `metadata` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `file_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uploaded_by` int(11) DEFAULT NULL,
  `filename` varchar(128) NOT NULL,
  `original_name` varchar(128) NOT NULL,
  `sha256` varchar(64) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `category` varchar(32) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------
-- Seed Data
-- ----------------------------
INSERT INTO `roles` (`name`, `description`, `permissions`, `created_at`) VALUES
('administrator', 'Full system access', '["users.*","activities.*","orders.*","shipments.*","violations.*","tasks.*","staffing.*","dashboard.*","audit.read","index.rebuild"]', NOW()),
('operations_staff', 'Manage activities and orders', '["activities.read","activities.create","activities.update","orders.*","shipments.*","search.read"]', NOW()),
('team_lead', 'Lead activities and manage team', '["activities.read","tasks.*","staffing.*","checklists.*"]', NOW()),
('reviewer', 'Review violations and appeals', '["violations.review","violations.read","audit.read"]', NOW()),
('regular_user', 'Basic user access', '["activities.read","activities.signup","orders.read","notifications.read"]', NOW());

-- Users (password: Password123 for all)
INSERT INTO `users` (`username`, `password_hash`, `salt`, `role`, `status`, `created_at`, `updated_at`) VALUES
('admin', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'adminsalt1234', 'administrator', 'active', NOW(), NOW()),
('ops1', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'ops1salt1234', 'operations_staff', 'active', NOW(), NOW()),
('lead1', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'lead1salt1234', 'team_lead', 'active', NOW(), NOW()),
('reviewer1', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'rev1salt1234', 'reviewer', 'active', NOW(), NOW()),
('user1', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'usr1salt1234', 'regular_user', 'active', NOW(), NOW()),
('user2', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'usr2salt1234', 'regular_user', 'active', NOW(), NOW()),
('user3', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'usr3salt1234', 'regular_user', 'active', NOW(), NOW()),
('user4', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'usr4salt1234', 'regular_user', 'active', NOW(), NOW()),
('user5', '$2y$12$woAtiKC.ZLWJphXjJlXQO.Zf30dNprLM9C3iStOhiMTReW.cIT27y', 'usr5salt1234', 'regular_user', 'active', NOW(), NOW());

-- Violation Rules
INSERT INTO `violation_rules` (`name`, `description`, `points`, `category`, `created_at`, `updated_at`) VALUES
('Late Arrival', 'Arriving late to activity', 2, 'attendance', NOW(), NOW()),
('Early Departure', 'Leaving early without approval', 2, 'attendance', NOW(), NOW()),
('No Show', 'Not showing up for signed-up activity', 5, 'attendance', NOW(), NOW()),
('Missing Supplies', 'Failed to bring required supplies', 3, 'equipment', NOW(), NOW()),
('Positive Contribution', 'Extra help or contribution', -5, 'bonus', NOW(), NOW());

-- Sample Activities
INSERT INTO `activity_groups` (`created_by`, `created_at`) VALUES (1, NOW());
INSERT INTO `activity_versions` (`group_id`, `version_number`, `title`, `body`, `tags`, `state`, `max_headcount`, `signup_start`, `signup_end`, `eligibility_tags`, `required_supplies`, `published_at`, `created_at`) VALUES
(1, 1, 'Campus Cleanup Day', 'Join us for a campus-wide cleanup event! Volunteers needed for grounds cleanup, litter removal, and planting.', '["volunteer","outdoor"]', 'published', 50, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), '[]', '["gloves","bags"]', NOW(), NOW());

INSERT INTO `activity_groups` (`created_by`, `created_at`) VALUES (1, NOW());
INSERT INTO `activity_versions` (`group_id`, `version_number`, `title`, `body`, `tags`, `state`, `max_headcount`, `eligibility_tags`, `required_supplies`, `created_at`) VALUES
(2, 1, 'Welcome Orientation', 'New member orientation session. Learn about our organization and policies.', '["orientation"]', 'draft', 100, '[]', '[]', NOW());

INSERT INTO `activity_groups` (`created_by`, `created_at`) VALUES (1, NOW());
INSERT INTO `activity_versions` (`group_id`, `version_number`, `title`, `body`, `tags`, `state`, `max_headcount`, `eligibility_tags`, `required_supplies`, `created_at`) VALUES
(3, 1, 'Summer Festival Planning', 'Annual summer festival planning meeting', '["festival","planning"]', 'completed', 20, '[]', '[]', NOW());

-- Sample Orders
INSERT INTO `orders` (`created_by`, `state`, `amount`, `items`, `created_at`, `updated_at`) VALUES
(1, 'placed', 150.00, '["T-Shirt x 10","Badge x 10"]', NOW(), NOW()),
(1, 'paid', 500.00, '["Banner x 2","Flyers x 500"]', NOW(), NOW()),
(1, 'ticketed', 200.00, '["Sound System rental"]', NOW(), NOW()),
(1, 'closed', 350.00, '["Catering for 50"]', NOW(), NOW()),
(1, 'canceled', 75.00, '["Extra supplies"]', NOW(), NOW());

-- Grant privileges
GRANT ALL PRIVILEGES ON campusops.* TO 'campusops'@'%';
FLUSH PRIVILEGES;