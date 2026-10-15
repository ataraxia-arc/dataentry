-- Target: the existing XAMPP MariaDB 10.4+ dataentryg2 database.
-- Export a backup before importing. DDL commits automatically in MariaDB.
-- Safe to import again: existing columns and populated full names are retained.
USE `dataentryg2`;
SET NAMES utf8mb4;

-- Preserve the legacy fields and their values; allow Unicode student names/notes.
ALTER TABLE `entries`
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY COLUMN `name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN `description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    MODIFY COLUMN `category` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS `year_level` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS `section` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS `course` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    ADD COLUMN IF NOT EXISTS `address` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

-- Do not invent missing student details for older records.
UPDATE `entries` SET `full_name` = `name`
WHERE `full_name` IS NULL AND `name` <> '';

-- users, record IDs, ownership, timestamps, dates and foreign keys are unchanged.
