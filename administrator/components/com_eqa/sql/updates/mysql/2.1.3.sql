-- =============================================================================
-- Version  : 2.1.3
-- Date     : 2026
-- =============================================================================
-- Bảng #__eqa_examsessions
-- DROP cột 'description' (không còn được sử dụng ở giao diện lẫn nghiệp vụ)
ALTER TABLE `#__eqa_examsessions`
    DROP COLUMN `description`;