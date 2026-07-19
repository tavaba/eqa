-- =============================================================================
-- Version  : 2.1.6
-- Date     : 19/07/2026
-- Cơ chế đa cơ sở đào tạo (multi-campus) — Phase 1: Schema
--
-- 1. Tạo bảng #__eqa_campuses (danh mục cơ sở đào tạo) + seed 2 bản ghi:
--    (1) Cơ sở chính Hà Nội, (2) Phân hiệu Tp. Hồ Chí Minh
-- 2. Tạo bảng #__eqa_campus_user (junction #__eqa_campuses ↔ #__users)
--    + seed: gán toàn bộ user hiện có vào campus 1 (bảo toàn nguyên trạng)
-- 3. Thêm cột campus_id (FK → #__eqa_campuses, backfill = 1) vào 6 bảng:
--    - #__eqa_units, #__eqa_groups      : thuộc tính phân loại/thông tin,
--      GIỮ DEFAULT 1 để bảo toàn các luồng import hiện có
--    - #__eqa_classes, #__eqa_examseasons, #__eqa_assessments,
--      #__eqa_buildings                 : phân vùng quản lý, DROP DEFAULT sau
--      khi backfill (fail-fast: code phải gán campus_id tường minh khi INSERT)
-- 4. #__eqa_buildings: thay UNIQUE(code) bằng UNIQUE(campus_id, code)
--    (mã tòa nhà được phép trùng giữa hai cơ sở)
--
-- LƯU Ý VẬN HÀNH: backup CSDL trước khi update; DDL của MySQL không nằm trong
-- transaction nên không thể rollback tự động.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Danh mục cơ sở đào tạo
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__eqa_campuses`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `code`        VARCHAR(50)  NOT NULL COMMENT 'Ký hiệu cơ sở đào tạo. Ví dụ: HN, HCM',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên cơ sở đào tạo',
    `params`      TEXT COMMENT 'JSON (Registry): tham số cấu hình riêng của cơ sở',
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cơ sở đào tạo (campus)';

-- Seed: id = 1 là giá trị backfill cho toàn bộ dữ liệu hiện có
INSERT IGNORE INTO `#__eqa_campuses`
    (`id`, `code`, `name`, `published`, `ordering`, `created_at`)
VALUES
    (1, 'HN',  'Cơ sở chính Hà Nội',        TRUE, 1, UTC_TIMESTAMP()),
    (2, 'HCM', 'Phân hiệu Tp. Hồ Chí Minh', TRUE, 2, UTC_TIMESTAMP());

-- -----------------------------------------------------------------------------
-- 2. Junction: cơ sở đào tạo — tài khoản người dùng (#__users)
--    (KHÔNG liên quan bảng hồ sơ nhân sự #__eqa_employees)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__eqa_campus_user`(
    `id`        INT UNSIGNED AUTO_INCREMENT,
    `user_id`   INT NOT NULL COMMENT 'FK: tài khoản đăng nhập (#__users); INT signed khớp kiểu cột của Joomla core',
    `campus_id` INT UNSIGNED NOT NULL COMMENT 'FK: cơ sở đào tạo',
    PRIMARY KEY (`id`),
    UNIQUE (`user_id`, `campus_id`),
    CONSTRAINT fk_eqa_campus_user_user FOREIGN KEY (`user_id`)
        REFERENCES `#__users`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT fk_eqa_campus_user_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Junction: cơ sở đào tạo — tài khoản người dùng';

-- Seed: gán Super User (id = 407) vào cả 2 campus
INSERT IGNORE INTO `#__eqa_campus_user` (`user_id`, `campus_id`)
VALUES
    (407,1),
    (407,2);

-- -----------------------------------------------------------------------------
-- 3a. #__eqa_units — thuộc tính phân loại (employee suy campus qua unit_id).
--     GIỮ DEFAULT 1: units được quản lý tập trung, các luồng tạo/import
--     hiện có tiếp tục hoạt động; đơn vị của Phân hiệu sẽ được gán campus 2
--     qua giao diện quản trị.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_units`
    ADD COLUMN `campus_id` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK: cơ sở đào tạo (thuộc tính phân loại; employee suy campus qua unit_id)'
        AFTER `id`,
    ADD CONSTRAINT fk_eqa_units_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT;

-- -----------------------------------------------------------------------------
-- 3b. #__eqa_groups, #__eqa_cohorts — thuộc tính thông tin (lớp hành chính học ở cơ sở nào);
--     là nguồn campus cho biểu mẫu đánh giá rèn luyện. GIỮ DEFAULT 1.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_groups`
    ADD COLUMN `campus_id` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK: cơ sở đào tạo (thuộc tính thông tin, không dùng chặn quyền)'
        AFTER `id`,
    ADD CONSTRAINT fk_eqa_groups_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT;
ALTER TABLE `#__eqa_cohorts`
    ADD COLUMN `campus_id` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK: cơ sở đào tạo (thuộc tính thông tin, không dùng chặn quyền)'
        AFTER `id`,
    ADD CONSTRAINT fk_eqa_cohorts_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT;
-- -----------------------------------------------------------------------------
-- 3c. #__eqa_classes — phân vùng quản lý. DROP DEFAULT sau backfill (fail-fast).
--     Giữ nguyên UNIQUE(code) toàn cục: mã lớp học phần không trùng giữa 2 cơ sở.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_classes`
    ADD COLUMN `campus_id` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK: cơ sở đào tạo quản lý lớp học phần'
        AFTER `id`,
    ADD CONSTRAINT fk_eqa_classes_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT;

ALTER TABLE `#__eqa_classes`
    ALTER COLUMN `campus_id` DROP DEFAULT;

-- -----------------------------------------------------------------------------
-- 3d. #__eqa_examseasons — phân vùng quản lý; toàn bộ cây tổ chức thi
--     (exams, examsessions, examrooms, packages, papers, exam_learner,
--     regradings, gradecorrections...) kế thừa campus qua examseason_id.
--     LƯU Ý (Phase 4): cờ `default` đổi ngữ nghĩa thành per-campus.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_examseasons`
    ADD COLUMN `campus_id` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK: cơ sở đào tạo tổ chức kỳ thi'
        AFTER `id`,
    ADD CONSTRAINT fk_eqa_examseasons_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT;

ALTER TABLE `#__eqa_examseasons`
    ALTER COLUMN `campus_id` DROP DEFAULT;

-- -----------------------------------------------------------------------------
-- 3e. #__eqa_assessments — phân vùng quản lý; assessment_learner và các ca thi
--     sát hạch kế thừa campus qua assessment_id.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_assessments`
    ADD COLUMN `campus_id` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK: cơ sở đào tạo tổ chức kỳ sát hạch'
        AFTER `id`,
    ADD CONSTRAINT fk_eqa_assessments_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT;

ALTER TABLE `#__eqa_assessments`
    ALTER COLUMN `campus_id` DROP DEFAULT;

-- -----------------------------------------------------------------------------
-- 3f. #__eqa_buildings — phân vùng quản lý cơ sở vật chất; rooms kế thừa
--     campus qua building_id.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_buildings`
    ADD COLUMN `campus_id` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK: cơ sở đào tạo sở hữu tòa nhà'
        AFTER `id`,
    ADD CONSTRAINT fk_eqa_buildings_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT;

ALTER TABLE `#__eqa_buildings`
    ALTER COLUMN `campus_id` DROP DEFAULT;

-- -----------------------------------------------------------------------------
-- 4. #__eqa_buildings: UNIQUE(code) → UNIQUE(campus_id, code).
--    Index cần drop có tên 'code' (tên tự sinh của MySQL cho UNIQUE (`code`)
--    khai báo không tên trong install.mysql.utf8.sql). Nếu lệnh DROP INDEX
--    báo lỗi trên môi trường cụ thể, kiểm tra tên thực tế bằng:
--    SHOW INDEX FROM #__eqa_buildings;
--    Dữ liệu hiện có đều thuộc campus 1 và code đã duy nhất, nên ràng buộc
--    mới luôn thỏa mãn ngay khi tạo.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_buildings`
    DROP INDEX `code`;

ALTER TABLE `#__eqa_buildings`
    ADD UNIQUE INDEX `uq_eqa_buildings_campus_code` (`campus_id`, `code`);
