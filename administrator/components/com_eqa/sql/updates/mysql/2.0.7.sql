-- =============================================================================
-- com_eqa v2.0.7
-- =============================================================================

-- 1. Thay đổi với bảng #__eqa_regradings
-- a) Thêm cột payment_amount, payment_code, payment_completed vào #__eqa_regradings
--    (hỗ trợ sinh mã thanh toán phúc khảo)
ALTER TABLE `#__eqa_regradings`
    ADD COLUMN `payment_amount`    INT         NOT NULL DEFAULT 0    COMMENT 'Phí phúc khảo (VND)' AFTER `status`,
    ADD COLUMN `payment_code`      VARCHAR(8)  NULL     DEFAULT NULL  COMMENT 'Mã nộp tiền phúc khảo (8 ký tự [A-Z0-9])' AFTER `payment_amount`,
    ADD COLUMN `payment_completed` BOOLEAN  NOT NULL DEFAULT FALSE    COMMENT 'Đã nộp phí phúc khảo' AFTER `payment_code`;

-- b) Tái cấu trúc cột handled_by:
--    - Đổi tên cột 'handled_by' (VARCHAR, username) thành 'handled_by_username'
--    - Thêm cột 'handled_by' mới (INT UNSIGNED, user id trong #__users)
ALTER TABLE `#__eqa_regradings`
    CHANGE COLUMN `handled_by` `handled_by_username` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Username của người xử lý phúc khảo (giữ lại để tương thích ngược)';

ALTER TABLE `#__eqa_regradings`
    ADD COLUMN `handled_by` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'User ID (#__users) của người xử lý phúc khảo' AFTER `handled_at`;

-- c) Thêm ràng buộc UNIQUE
ALTER TABLE `#__eqa_regradings` ADD UNIQUE(`exam_id`, `learner_id`);

-- 2. Điều chỉnh bảng #__eqa_gradecorrections:
-- a) Tái cấu trúc cột handled_by
--    - Đổi tên cột 'handled_by' (VARCHAR, username) thành 'handled_by_username'
--    - Thêm cột 'handled_by' mới (INT UNSIGNED, user id trong #__users)
ALTER TABLE `#__eqa_gradecorrections`
    CHANGE COLUMN `handled_by` `handled_by_username` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Username của người xử lý phúc khảo (giữ lại để tương thích ngược)';

ALTER TABLE `#__eqa_gradecorrections`
    ADD COLUMN `handled_by` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'User ID (#__users) của người xử lý phúc khảo' AFTER `handled_at`;

-- c) Thêm ràng buộc UNIQUE
ALTER TABLE `#__eqa_gradecorrections` ADD UNIQUE(`exam_id`, `learner_id`);

-- 3. Thêm cột thông tin ngân hàng thu phí phúc khảo vào #__eqa_examseasons
ALTER TABLE `#__eqa_examseasons`
    ADD COLUMN `bank_napas_code`     VARCHAR(10)  NULL DEFAULT NULL COMMENT 'Mã NAPAS ngân hàng nhận phí phúc khảo' AFTER `ppaa_req_deadline`,
    ADD COLUMN `bank_account_number` VARCHAR(50)  NULL DEFAULT NULL COMMENT 'Số tài khoản ngân hàng nhận phí phúc khảo' AFTER `bank_napas_code`,
    ADD COLUMN `bank_account_owner`  VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tên chủ tài khoản ngân hàng' AFTER `bank_account_number`;

-- 4. Bảng #__eqa_class_learner: Sửa tên cột 'updated_by' thành 'modified_by'
-- (Vì lý do nào đó mà cột này bị bỏ sót khi update lên 2.0.6)
ALTER TABLE `#__eqa_class_learner` CHANGE `updated_by` `modified_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'User ID';
