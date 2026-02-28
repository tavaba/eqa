-- =============================================================================
-- COM_EQA v2.0.1 — Schema Update
-- =============================================================================
-- Các thay đổi:
--   1. #__eqa_subjects : Thêm cột `allowed_rooms` TEXT NULL DEFAULT NULL
--   2. #__eqa_exams    : Xóa cột `statistic`
--                        Thêm cột `code` VARCHAR(50) NOT NULL DEFAULT ''
--                        Thêm cột `allowed_rooms` TEXT NULL DEFAULT NULL
--
-- Lưu ý quan trọng:
--   - Cột `code` được tạo với DEFAULT '' tạm thời.
--     script.php (runMigration201) sẽ populate dữ liệu, sau đó:
--       a) MODIFY COLUMN để bỏ DEFAULT và enforce NOT NULL thực sự.
--       b) Thêm UNIQUE INDEX (examseason_id, code).
-- =============================================================================

-- 1. Bảng #__eqa_subjects — Thêm cột `allowed_rooms`
ALTER TABLE `#__eqa_subjects`
    ADD COLUMN `allowed_rooms` TEXT NULL DEFAULT NULL
        COMMENT 'JSON: danh sách ID phòng được phép sử dụng cho môn thi tương ứng; NULL = không giới hạn'
        AFTER `finaltestweight`;

-- 2. Bảng #__eqa_exams — Xóa cột `statistic`
ALTER TABLE `#__eqa_exams`
    DROP COLUMN `statistic`;

-- 3. Bảng #__eqa_exams — Thêm cột `code`
--    DEFAULT '' tạm thời; script.php sẽ populate rồi enforce NOT NULL
ALTER TABLE `#__eqa_exams`
    ADD COLUMN `code` VARCHAR(50) NOT NULL DEFAULT ''
        COMMENT 'Mã môn thi (copy từ mã môn học); bắt buộc; duy nhất trong một kỳ thi'
        AFTER `name`;

-- 4. Bảng #__eqa_exams — Thêm cột `allowed_rooms`
ALTER TABLE `#__eqa_exams`
    ADD COLUMN `allowed_rooms` TEXT NULL DEFAULT NULL
        COMMENT 'JSON: danh sách ID phòng được phép sử dụng; NULL = không giới hạn; ghi đè allowed_rooms của subject'
        AFTER `is_pass_fail`;

-- 5. Bảng #__eqa_secondattempts — Thêm cột `last_attempt`
ALTER TABLE `#__eqa_secondattempts`
    ADD COLUMN `last_attempt` INT NOT NULL
        AFTER `last_exam_id`;
