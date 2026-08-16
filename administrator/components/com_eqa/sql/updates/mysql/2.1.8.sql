-- =============================================================================
-- Version  : 2.1.8
-- Date     : 15/08/2026

-- =============================================================================

-- =============================================================================
-- Tổ chức lại quy trình thi lần 2 theo 'đợt thi lại' (resit)
--
-- Trước thay đổi: `#__eqa_secondattempts` là MỘT danh sách phẳng duy nhất, phải
-- reset trước mỗi đợt tổ chức thi nên mất thông tin của đợt trước.
-- Sau thay đổi : trước mỗi kỳ thi lần 2, quản trị viên lập một 'đợt thi lại'
-- (`#__eqa_resits`); thí sinh của đợt nằm trong `#__eqa_resit_learner`. Dữ liệu
-- của các đợt trước được giữ nguyên.
-- Mỗi cơ sở đào tạo có tối đa MỘT đợt đang kích hoạt (`active` = 1) tại một thời
-- điểm; mọi thao tác nghiệp vụ (đăng ký thi lại ở front-end, sinh môn thi lần 2,
-- rà soát nộp phí) đều làm việc với đợt đang kích hoạt của cơ sở đó.
--
-- Đổi tên bảng:
--   `#__eqa_secondattempts`      → `#__eqa_resit_learner`  (thí sinh của đợt)
--   (bảng mới)                   → `#__eqa_resits`         (đợt thi lại)
--
-- LƯU Ý TRƯỚC KHI CHẠY: bước 6 xóa ràng buộc UNIQUE (class_id, learner_id) cũ.
-- MySQL tự đặt tên cho ràng buộc này (thường là `class_id`). Hãy kiểm tra bằng
--     SHOW INDEX FROM `#__eqa_secondattempts`;
-- và sửa lại tên trong lệnh DROP INDEX nếu khác.
-- =============================================================================

-- 1. Bảng đợt thi lại ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__eqa_resits`(
    `id`               INT UNSIGNED AUTO_INCREMENT,
    `campus_id`        INT UNSIGNED NOT NULL COMMENT 'FK: cơ sở đào tạo',
    `name`             VARCHAR(255) NOT NULL COMMENT 'Tên đợt thi lại, ví dụ: Thi lần 2. HK1 2026-2027. Đợt 1',
    `active`           BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Đang kích hoạt; per-campus: mỗi cơ sở có tối đa một đợt kích hoạt (ràng buộc được bảo đảm ở tầng ứng dụng)',
    -- Tham số cổng thu phí thi lại, hiển thị cho người học ở front-end.
    -- Trước 2.1.8 các tham số này nằm ở params của menu item; nay thuộc về đợt.
    `bank_napas_code`       VARCHAR(10)  NULL COMMENT 'Mã NAPAS ngân hàng thu phí thi lại (dùng với VietQR)',
    `bank_account_number`   VARCHAR(50)  NULL COMMENT 'Số tài khoản thu phí thi lại',
    `bank_account_owner`    VARCHAR(255) NULL COMMENT 'Tên chủ tài khoản thu phí thi lại',
    `payment_open_from`     DATETIME NULL COMMENT 'Thời điểm bắt đầu thu phí (UTC); NULL = không giới hạn',
    `payment_deadline`      DATETIME NULL COMMENT 'Hạn chót nộp phí (UTC); NULL = không giới hạn',
    `payment_gate_open`     BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Cổng thu phí đang mở',
    `last_statement_update` DATETIME NULL COMMENT 'Thời điểm đối chiếu sao kê gần nhất (UTC); hệ thống tự ghi',
    `description`      TEXT NULL,
    `state`            TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng',
    `ordering`         INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME,
    `created_by`       INT UNSIGNED,
    `modified_at`      DATETIME,
    `modified_by`      INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_eqa_resits_campus` (`campus_id`, `active`),
    CONSTRAINT fk_eqa_resits_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Đợt thi lại (thi lần hai)';

-- 2. Đổi tên bảng thí sinh ----------------------------------------------------
RENAME TABLE `#__eqa_secondattempts` TO `#__eqa_resit_learner`;

-- 3. Cột resit_id (tạm cho phép NULL để migrate) ------------------------------
ALTER TABLE `#__eqa_resit_learner`
    ADD COLUMN `resit_id` INT UNSIGNED NULL COMMENT 'FK: đợt thi lại' AFTER `id`;

-- 4. Migration: mỗi cơ sở đào tạo đang có dữ liệu -> một đợt khởi tạo ---------
--    Đợt khởi tạo được đặt active = 1 để quy trình hiện hành chạy tiếp ngay sau
--    khi nâng cấp mà không cần thao tác thủ công.
INSERT INTO `#__eqa_resits`(`campus_id`, `name`, `active`, `description`, `state`, `created_at`)
SELECT c.`campus_id`,
       'Đợt khởi tạo (chuyển đổi từ dữ liệu trước nâng cấp)',
       1,
       'Được tạo tự động bởi script nâng cấp 2.1.8; chứa toàn bộ bản ghi thi lần 2 tồn tại trước khi chuyển sang mô hình đợt thi lại.',
       1,
       UTC_TIMESTAMP()
FROM `#__eqa_resit_learner` rl
INNER JOIN `#__eqa_classes` c ON c.`id` = rl.`class_id`
GROUP BY c.`campus_id`;

-- 5. Gán bản ghi hiện có vào đợt khởi tạo của cơ sở đào tạo tương ứng ---------
UPDATE `#__eqa_resit_learner` rl
INNER JOIN `#__eqa_classes` c ON c.`id` = rl.`class_id`
INNER JOIN `#__eqa_resits` r
        ON r.`campus_id` = c.`campus_id`
       AND r.`name` = 'Đợt khởi tạo (chuyển đổi từ dữ liệu trước nâng cấp)'
SET rl.`resit_id` = r.`id`;

-- 6. Siết ràng buộc trên bảng thí sinh ----------------------------------------
ALTER TABLE `#__eqa_resit_learner`
    MODIFY COLUMN `resit_id` INT UNSIGNED NOT NULL COMMENT 'FK: đợt thi lại';

ALTER TABLE `#__eqa_resit_learner`
    ADD CONSTRAINT fk_eqa_resit_learner_resit FOREIGN KEY (`resit_id`)
        REFERENCES `#__eqa_resits`(`id`)
        ON DELETE RESTRICT;

-- Cùng một cặp (lớp học phần, người học) được phép xuất hiện ở NHIỀU đợt,
-- nhưng không được trùng trong cùng một đợt.
ALTER TABLE `#__eqa_resit_learner`
    ADD UNIQUE KEY `uq_eqa_resit_learner_resit_class_learner` (`resit_id`, `class_id`, `learner_id`);

-- Bỏ ràng buộc UNIQUE (class_id, learner_id) cũ (xem ghi chú ở đầu file).
ALTER TABLE `#__eqa_resit_learner` DROP INDEX `class_id`;

-- 7. Đổi tên chỉ mục cũ cho nhất quán (không bắt buộc về mặt chức năng) -------
ALTER TABLE `#__eqa_resit_learner`
    RENAME INDEX `idx_eqa_secondattempts_learner` TO `idx_eqa_resit_learner_learner`;
