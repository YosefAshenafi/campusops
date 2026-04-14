-- CampusOps Database Initialization
-- This runs automatically on first container start

-- Ensure proper character set
ALTER DATABASE campusops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant full privileges to app user
GRANT ALL PRIVILEGES ON campusops.* TO 'campusops'@'%';
FLUSH PRIVILEGES;
