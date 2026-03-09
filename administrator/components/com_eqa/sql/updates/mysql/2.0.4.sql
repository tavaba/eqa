-- ============================================================
-- Migration 2.0.4: Thay thế academicyear_id (FK) bằng academicyear (INT)
-- trong các bảng classes, examseasons, conducts.
--
-- Chiến lược:
--   1. Thêm cột academicyear INT NULL (cho phép NULL tạm thời để populate)
--   2. script.php::runMigration204() sẽ populate dữ liệu
--   3. script.php tiếp tục: SET NOT NULL, DROP FK, DROP cột cũ, DROP bảng
-- ============================================================

-- Bảng classes
ALTER TABLE `#__eqa_classes`
    ADD COLUMN `academicyear` INT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)'
        AFTER `academicyear_id`;

-- Bảng examseasons
ALTER TABLE `#__eqa_examseasons`
    ADD COLUMN `academicyear` INT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)'
        AFTER `academicyear_id`;

-- Bảng conducts
ALTER TABLE `#__eqa_conducts`
    ADD COLUMN `academicyear` INT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)'
        AFTER `academicyear_id`;