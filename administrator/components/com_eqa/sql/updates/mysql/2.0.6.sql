-- =============================================================================
-- com_eqa — Schema Update 2.0.5 → 2.0.6
-- =============================================================================
-- Phần 1: Thêm cột created_by vào bảng 'gradecorrections' và 'regradings'
ALTER TABLE `#__eqa_regradings` ADD `created_by` INT UNSIGNED DEFAULT NULL AFTER `status`;
ALTER TABLE `#__eqa_gradecorrections` ADD `created_by` INT UNSIGNED DEFAULT NULL AFTER `status`;

-- Phần 2: Bổ sung bảng ghi log
CREATE TABLE IF NOT EXISTS `#__eqa_logs` (
     `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
     `user_id`       INT UNSIGNED        NULL,
     `username`      VARCHAR(150)        NULL,
     `action`        SMALLINT UNSIGNED   NOT NULL,
     `is_success`    TINYINT(1)          NOT NULL DEFAULT 0,
     `error_message` VARCHAR(500)        NULL,
     `object_type`   SMALLINT UNSIGNED   NOT NULL,
     `object_id`     BIGINT UNSIGNED     NOT NULL,
     `object_title`  VARCHAR(500)        NULL,
     `old_value`     TEXT                NULL,
     `new_value`     TEXT                NULL,
     `extra_data`    TEXT                NULL,
     `ip_address`    BINARY(16)          NULL,
     `created_at`    DATETIME(3)         NOT NULL,

     PRIMARY KEY (`id`),
     INDEX `idx_action`      (`action`),
     INDEX `idx_object`      (`object_type`, `object_id`),
     INDEX `idx_user`        (`user_id`),
     INDEX `idx_created_at`  (`created_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='Nhật ký thao tác người dùng';


-- Phần 3: Khớp lại cấu trúc bảng trong file install với CSDL ở production server
-- Phần 4: Đổi kiểu INT thành INT UNSIGNED
-- Tất cả thay đổi Phần 3 và Phần 4 đòi hỏi kiểm tra trạng thái
-- hiện tại của CSDL trước khi thực hiện (idempotent), nên được xử lý hoàn
-- toàn bởi script.php (runMigration206) trong bước postflight().
--
-- File này được giữ trống có chủ ý để tránh lỗi "Unknown column" khi Joomla
-- chạy SQL trước postflight() trên các production server có trạng thái cột
-- khác nhau (tuỳ lịch sử nâng cấp).
-- =============================================================================
