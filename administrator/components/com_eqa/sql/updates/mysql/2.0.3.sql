-- Migration 2.0.3
-- Bổ sung cột description vào #__eqa_secondattempts để lưu nội dung chuyển khoản
-- khi đối chiếu với bản sao kê ngân hàng.

ALTER TABLE `#__eqa_secondattempts`
    ADD COLUMN `description` TEXT NULL DEFAULT NULL
        COMMENT 'Nội dung chuyển khoản (trích từ bản sao kê ngân hàng)'
    AFTER `payment_code`;
