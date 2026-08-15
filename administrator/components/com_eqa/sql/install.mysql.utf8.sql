/**
 * com_eqa — Install SQL Schema
 * Version : 2.1.6
 * 1. Bảng mới #__eqa_campuses   : danh mục cơ sở đào tạo + seed 2 bản ghi
 * 2. Bảng mới #__eqa_campus_user: junction #__eqa_campuses ↔ #__users + seed
 * 3. ADD COLUMN campus_id (FK → #__eqa_campuses) vào 6 bảng:
 *    - units, groups                              : DEFAULT 1 (thuộc tính phân loại)
 *    - classes, examseasons, assessments, buildings: NOT NULL, không DEFAULT
 * 4. #__eqa_buildings: UNIQUE(code) → UNIQUE(campus_id, code)
*/

-- =============================================================================
-- Cơ sở đào tạo (campus)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_campuses`;
CREATE TABLE `#__eqa_campuses`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `code`        VARCHAR(50)  NOT NULL COMMENT 'Ký hiệu cơ sở đào tạo. Ví dụ: HN, HCM',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên cơ sở đào tạo',
    `params`      TEXT COMMENT 'JSON (Registry): tham số cấu hình riêng của cơ sở',
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
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

-- Seed: id = 1 là campus mặc định của dữ liệu hiện có
INSERT INTO `#__eqa_campuses`
(`id`, `code`, `name`, `state`, `ordering`, `created_at`)
VALUES
    (1, 'HN',  'Cơ sở chính Hà Nội',        1, 1, UTC_TIMESTAMP()),
    (2, 'HCM', 'Phân hiệu Tp. Hồ Chí Minh', 1, 2, UTC_TIMESTAMP());

-- =============================================================================
-- Junction: cơ sở đào tạo — tài khoản người dùng (#__users)
-- (KHÔNG liên quan bảng hồ sơ nhân sự #__eqa_employees)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_campus_user`;
CREATE TABLE `#__eqa_campus_user`(
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

-- =============================================================================
-- Tòa nhà
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_buildings`;
CREATE TABLE `#__eqa_buildings`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `campus_id`   INT UNSIGNED NOT NULL COMMENT 'FK: cơ sở đào tạo sở hữu tòa nhà',
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu tòa nhà. Ví dụ: TA1, TB1...',
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_eqa_buildings_campus_code` (`campus_id`, `code`),
    CONSTRAINT fk_eqa_buildings_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Các tòa nhà trong Học viện';

-- =============================================================================
-- Phòng học (vật lý)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_rooms`;
CREATE TABLE `#__eqa_rooms`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu phòng. Ví dụ: 104, 401-TA2...',
    `building_id` INT UNSIGNED NOT NULL,
    `maxcapacity` INT UNSIGNED COMMENT 'Số chỗ ngồi tối đa',
    `capacity`    INT UNSIGNED NOT NULL COMMENT 'Số chỗ ngồi được sử dụng để tổ chức thi',
    `description` TEXT,
    `type`        TINYINT UNSIGNED NOT NULL COMMENT 'Loại phòng: (0) phòng thường, (1) giảng đường có ổ cắm, (2) phòng máy',
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    CONSTRAINT fk_eqa_rooms_building FOREIGN KEY (`building_id`)
        REFERENCES `#__eqa_buildings`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Phòng học (vật lý)';

-- =============================================================================
-- Đơn vị (khoa, phòng, ban, bộ môn...)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_units`;
CREATE TABLE `#__eqa_units` (
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `campus_id`   INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK: cơ sở đào tạo (thuộc tính phân loại)',
    `parent_id`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Đơn vị cấp trên; 0 nếu trực thuộc Học viện',
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu, ví dụ: K.ATTT, BM.ATGDDT',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên đầy đủ, ví dụ: Khoa An toàn thông tin',
    `type`        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Loại đơn vị: (1) Khoa/bộ môn, (2) Phòng/ban',
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_units_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cơ quan, đơn vị trong Học viện (chỉ 2 cấp!!!)';

-- =============================================================================
-- Cán bộ, giảng viên, nhân viên
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_employees`;
CREATE TABLE `#__eqa_employees` (
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `code`        VARCHAR(255) COMMENT 'Mã cán bộ, nhân viên',
    `lastname`    VARCHAR(255) NOT NULL COMMENT 'Họ Đệm',
    `firstname`   VARCHAR(255) NOT NULL COMMENT 'Tên',
    `unit_id`     INT UNSIGNED NOT NULL COMMENT 'Khóa ngoại: cơ quan/đơn vị',
    `email`       VARCHAR(255),
    `mobile`      VARCHAR(255),
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_employees_unit FOREIGN KEY (`unit_id`)
        REFERENCES `#__eqa_units`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cán bộ, giảng viên, nhân viên (Không quản lý tài khoản đăng nhập)';

-- =============================================================================
-- Ngành đào tạo
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_specialities`;
CREATE TABLE `#__eqa_specialities`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên ngành đào tạo',
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Ngành đào tạo';

-- =============================================================================
-- Chương trình đào tạo
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_programs`;
CREATE TABLE `#__eqa_programs`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên của chương trình đào tạo',
    `spec_id`     INT UNSIGNED NOT NULL COMMENT 'FK: Ngành đào tạo',
    `degree`      TINYINT UNSIGNED NOT NULL COMMENT 'Trình độ: (7) Đại học, (8) Thạc sĩ, (9) Tiến sĩ',
    `format`      TINYINT UNSIGNED NOT NULL COMMENT 'Loại hình: Tiêu chuẩn, Liên thông, VB2',
    `approach`    TINYINT UNSIGNED NOT NULL COMMENT 'Hình thức: CQ, VLVH, TX',
    `firstrelease` INT UNSIGNED COMMENT 'Năm ban hành lần đầu',
    `lastupdate`  INT UNSIGNED COMMENT 'Năm sửa đổi gần nhất',
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    CONSTRAINT fk_eqa_programs_spec FOREIGN KEY (`spec_id`)
        REFERENCES `#__eqa_specialities`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Chương trình đào tạo';

-- =============================================================================
-- Khóa đào tạo
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_courses`;
CREATE TABLE `#__eqa_courses` (
    `id`            INT UNSIGNED AUTO_INCREMENT,
    `prog_id`       INT UNSIGNED NOT NULL COMMENT 'FK: Chương trình ĐT',
    `code`          VARCHAR(255) NOT NULL COMMENT 'Ký hiệu. Ví dụ: AT20',
    `admissionyear` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Năm nhập học',
    `description`   VARCHAR(255) COMMENT 'Ví dụ: 9/2023 - 01/2028',
    `state`         TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`      INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME,
    `created_by`    INT UNSIGNED,
    `modified_at`   DATETIME,
    `modified_by`   INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_courses_prog FOREIGN KEY (`prog_id`)
        REFERENCES `#__eqa_programs`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Khóa đào tạo';

-- =============================================================================
-- Lớp hành chính
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_groups`;
CREATE TABLE `#__eqa_groups` (
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `campus_id`   INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK: cơ sở đào tạo (thuộc tính thông tin, không dùng chặn quyền)',
    `course_id`   INT UNSIGNED COMMENT 'FK: Khóa đào tạo. NULL với lớp ngắn hạn...',
    `code`        VARCHAR(255) NOT NULL COMMENT 'Tên lớp. Ví dụ: AT20A',
	`size`		  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Sĩ số',
    `homeroom_id` INT UNSIGNED COMMENT 'FK: Giáo viên chủ nhiệm',
    `adviser_id`  INT UNSIGNED COMMENT 'FK: Cố vấn học tập số 1',
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_groups_course FOREIGN KEY (`course_id`)
        REFERENCES `#__eqa_courses`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_groups_hoomroom FOREIGN KEY (`homeroom_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_groups_adviser FOREIGN KEY (`adviser_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lớp quản lý hành chính';

-- =============================================================================
-- Học viên, sinh viên
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_learners`;
CREATE TABLE `#__eqa_learners` (
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `code`        VARCHAR(255) NOT NULL COMMENT 'Mã HVSV. Ví dụ: AT010101',
    `lastname`    VARCHAR(255) NOT NULL COMMENT 'Họ Đệm',
    `firstname`   VARCHAR(255) NOT NULL COMMENT 'Tên',
    `group_id`    INT UNSIGNED NOT NULL COMMENT 'FK: Lớp hành chính',
    `debtor`      BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Có nợ học phí hay không',
    `description` TEXT,
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_learners_group FOREIGN KEY (`group_id`)
        REFERENCES `#__eqa_groups`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Học viên, sinh viên (Không quản lý tài khoản đăng nhập)';

-- =============================================================================
-- Nhóm người học (cohort)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_cohorts`;
CREATE TABLE `#__eqa_cohorts` (
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `campus_id`   INT UNSIGNED NOT NULL COMMENT 'FK: cơ sở đào tạo',
    `code`        VARCHAR(20) NOT NULL COMMENT 'Ký hiệu nhóm. Ví dụ: H30L',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên nhóm: H30 Lào',
    `state`       TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_cohorts_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Nhóm HVSV';

DROP TABLE IF EXISTS `#__eqa_cohort_learner`;
CREATE TABLE `#__eqa_cohort_learner` (
    `id`         INT UNSIGNED AUTO_INCREMENT,
    `cohort_id`  INT UNSIGNED NOT NULL,
    `learner_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`cohort_id`, `learner_id`),
    CONSTRAINT fk_eqa_cohort_learner_cohort FOREIGN KEY (`cohort_id`)
        REFERENCES `#__eqa_cohorts`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT fk_eqa_cohort_learner_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Junction: nhóm — người học';

-- =============================================================================
-- Môn học
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_subjects`;
CREATE TABLE `#__eqa_subjects` (
    `id`                INT UNSIGNED AUTO_INCREMENT,
    `code`              VARCHAR(255) NOT NULL COMMENT 'Mã môn học',
    `name`              VARCHAR(255) NOT NULL COMMENT 'Tên môn học (tên chính thức, dùng cho hồ sơ thi và giao diện frontend)',
    `display_name`      VARCHAR(255) NULL COMMENT 'Tên phân biệt, chỉ dùng cho giao diện quản trị; NULL = dùng name',
    `degree`            INT UNSIGNED NOT NULL COMMENT 'Bậc học',
    `credits`           REAL COMMENT 'Số tín chỉ (có thể lẻ)',
    `start_year`        INT UNSIGNED NULL COMMENT 'Năm đưa vào sử dụng môn học; NULL = không xác định',
    `unit_id`           INT UNSIGNED COMMENT 'Khóa ngoại: Đơn vị phụ trách môn học',
    `is_pass_fail`      BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Môn điều kiện, không tính điểm',
    `finaltesttype`     INT UNSIGNED NOT NULL COMMENT 'Hình thức thi mặc định (định nghĩa bằng constants)',
    `finaltestduration` INT UNSIGNED COMMENT 'Thời gian làm bài thi, tính bằng phút',
    `finaltestweight`   REAL NOT NULL COMMENT 'Trọng số điểm thi kết thúc học phần',
    `allowed_rooms`     TEXT NULL COMMENT 'JSON: danh sách ID phòng được phép sử dụng; NULL = không giới hạn',
    `testbankyear`      INT UNSIGNED COMMENT 'Năm xây dựng ngân hàng cho hình thức thi mặc định (nếu có)',
    `alltestbanks`      TEXT COMMENT 'JSON String (hoặc NULL) thể hiện các ngân hàng đang có {type:, year:}',
    `allmarkelements`   TEXT COMMENT 'JSON String thể hiện các thành phần đánh giá quá trình {name:, weight:}',
    `programs`          TEXT COMMENT 'Các CTĐT có môn học',
    `kmonitor`          REAL NOT NULL DEFAULT 1.0 COMMENT 'Hệ số tính sản lượng coi thi',
    `kassess`           REAL NOT NULL DEFAULT 1.0 COMMENT 'Hệ số tính sản lượng chấm thi',
    `kquestion`         REAL NOT NULL DEFAULT 0 COMMENT 'Số giờ chuẩn quy đổi cho mỗi đề thi',
    `description`       TEXT,
    `state`             TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác',
    `ordering`          INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        DATETIME,
    `created_by`        INT UNSIGNED,
    `modified_at`       DATETIME,
    `modified_by`       INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_subjects_unit FOREIGN KEY (`unit_id`)
        REFERENCES `#__eqa_units`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Môn học';

-- =============================================================================
-- Lớp học phần
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_classes`;
CREATE TABLE `#__eqa_classes` (
    `id`            INT UNSIGNED AUTO_INCREMENT,
    `campus_id`     INT UNSIGNED NOT NULL COMMENT 'FK: cơ sở đào tạo quản lý lớp học phần',
    `coursegroup`   VARCHAR(255) COMMENT 'Đối tượng người học',
    `code`          CHAR(40) COMMENT 'Mã lớp học phần',
    `name`          VARCHAR(255) NOT NULL COMMENT 'Tên lớp học phần',
    `subject_id`    INT UNSIGNED NOT NULL COMMENT 'Khóa ngoại: Môn học',
    `lecturer_id`   INT UNSIGNED COMMENT 'Khóa ngoại: Giảng viên (phụ trách chính)',
    `lecturers`     TEXT COMMENT 'JSON về tất cả giảng viên, nếu có nhiều hơn 1 GV',
    `academicyear`  INT UNSIGNED NOT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)',
    `term`          TINYINT UNSIGNED NOT NULL COMMENT 'Học kỳ (1, 2)',
    `start`         DATE COMMENT 'Ngày bắt đầu theo TKB',
    `finish`        DATE COMMENT 'Ngày kết thúc theo TKB',
    `size`          INT UNSIGNED COMMENT 'Sĩ số lớp học',
    `npam`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số lượng HVSV có điểm quá trình',
    `topicdeadline` DATE COMMENT 'Hạn gửi chủ đề đồ án/tiểu luận môn học',
    `topicdate`     DATE COMMENT 'Ngày bàn giao chủ đề đồ án/tiểu luận môn học',
    `thesisdate`    DATE COMMENT 'Ngày bàn giao sản phẩm đồ án/tiểu luận',
    `pamdeadline`   DATE COMMENT 'Hạn gửi điểm quá trình (nếu thi lần 1)',
    `pamdate`       DATE COMMENT 'Ngày bàn giao điểm quá trình (nếu thi lần 1)',
    `statistic`     TEXT COMMENT 'JSON thể hiện số liệu thống kê kết quả thi',
    `description`   TEXT,
    `state`         TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng',
    `ordering`      INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME,
    `created_by`    INT UNSIGNED,
    `modified_at`   DATETIME,
    `modified_by`   INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_classes_subject FOREIGN KEY (`subject_id`)
        REFERENCES `#__eqa_subjects`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_classes_lecturer FOREIGN KEY (`lecturer_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_classes_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Các lớp học phần';

-- =============================================================================
-- Chế độ khuyến khích
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_stimulations`;
CREATE TABLE `#__eqa_stimulations`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `subject_id`  INT UNSIGNED NOT NULL COMMENT 'FK: Môn học',
    `learner_id`  INT UNSIGNED NOT NULL COMMENT 'FK: Người học',
    `type`        INT UNSIGNED NOT NULL COMMENT 'Loại hình',
    `value`       FLOAT NOT NULL COMMENT 'Điểm khuyến khích',
    `reason`      TEXT NOT NULL COMMENT 'Lý do khuyến khích',
    `used`        BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`subject_id`, `learner_id`),
    CONSTRAINT fk_eqa_stimulations_subject FOREIGN KEY (`subject_id`)
        REFERENCES `#__eqa_subjects`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_stimulations_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Chế độ khuyến khích';

-- =============================================================================
-- HVSV trong lớp học phần
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_class_learner`;
CREATE TABLE `#__eqa_class_learner` (
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `class_id`    INT UNSIGNED NOT NULL,
    `learner_id`  INT UNSIGNED NOT NULL,
    `pam1`        FLOAT COMMENT 'Điểm quá trình TP1',
    `pam2`        FLOAT COMMENT 'Điểm quá trình TP2',
    `pam`         FLOAT COMMENT 'Điểm quá trình',
    `allowed`     BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Được phép dự thi kết thúc học phần hay không',
    `ntaken`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số lượt đã thi',
    `expired`     BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Hết lượt thi',
    `description` VARCHAR(255),
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `modified_at` DATETIME,
    `modified_by` INT UNSIGNED,
    PRIMARY KEY (`id`),
    UNIQUE (`class_id`, `learner_id`),
    CONSTRAINT fk_eqa_class_learner_class FOREIGN KEY (`class_id`)
        REFERENCES `#__eqa_classes`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT fk_eqa_class_learner_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='HVSV các lớp học phần';

-- =============================================================================
-- Kỳ thi
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_examseasons`;
CREATE TABLE `#__eqa_examseasons`(
    `id`                  INT UNSIGNED AUTO_INCREMENT,
    `campus_id`           INT UNSIGNED NOT NULL COMMENT 'FK: cơ sở đào tạo tổ chức kỳ thi',
    `name`                VARCHAR(255) NOT NULL COMMENT 'Tên đợt thi',
    `academicyear`        INT UNSIGNED NOT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)',
    `term`                TINYINT UNSIGNED COMMENT 'Học kỳ',
    `type`                TINYINT UNSIGNED NOT NULL COMMENT 'Loại kỳ thi: KTHP, Sát hạch, Tốt nghiệp, Khác (định nghĩa bằng constants)',
    `attempt`             TINYINT UNSIGNED NOT NULL COMMENT 'Lượt thi: (1) Thi lần 1, (2) Thi lần 2',
    `default`             TINYINT UNSIGNED NOT NULL DEFAULT FALSE COMMENT 'Là kỳ thi hiện tại (mặc định); per-campus: mỗi cơ sở có tối đa một kỳ thi mặc định',
    `start`               DATE COMMENT 'Ngày thi môn đầu tiên',
    `finish`              DATE COMMENT 'Ngày thi môn sau cùng',
    `ppaa_req_enabled`    BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Được gửi yêu cầu phúc khảo',
    `ppaa_req_deadline`   DATETIME NULL COMMENT 'Thời hạn gửi yêu cầu phúc khảo',
    `bank_napas_code`     VARCHAR(10)  NULL COMMENT 'Mã NAPAS ngân hàng nhận phí phúc khảo',
    `bank_account_number` VARCHAR(50)  NULL COMMENT 'Số tài khoản ngân hàng nhận phí phúc khảo',
    `bank_account_owner`  VARCHAR(255) NULL COMMENT 'Tên chủ tài khoản ngân hàng',
    `completed`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `statistic`           TEXT COMMENT 'JSON: số liệu thống kê về kỳ thi',
    `description`         TEXT,
    `state`               TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng',
    `ordering`            INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`          DATETIME,
    `created_by`          INT UNSIGNED,
    `modified_at`         DATETIME,
    `modified_by`         INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    CONSTRAINT fk_eqa_examseasons_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Đợt/kỳ thi';

-- =============================================================================
-- Thi sát hạch
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_assessments`;
CREATE TABLE `#__eqa_assessments` (
    `id`                    INT UNSIGNED AUTO_INCREMENT,
    `campus_id`             INT UNSIGNED NOT NULL COMMENT 'FK: cơ sở đào tạo tổ chức kỳ sát hạch',
    `title`                 VARCHAR(255) NOT NULL,
    `type`                  TINYINT UNSIGNED NOT NULL COMMENT 'AssessmentType Enum',
    `result_type`           TINYINT UNSIGNED NOT NULL COMMENT 'AssessmentResultType Enum',
    `start_date`            DATE NOT NULL,
    `end_date`              DATE NOT NULL,
    `fee`                   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Phí sát hạch (VNĐ)',
    `bank_napas_code`       VARCHAR(10)  COMMENT 'Mã ngân hàng theo chuẩn NAPAS (dùng với VietQR)',
    `bank_account_number`   VARCHAR(50)  COMMENT 'Số tài khoản ngân hàng thu phí',
    `bank_account_owner`    VARCHAR(255) COMMENT 'Tên chủ tài khoản ngân hàng thu phí',
    `max_candidates`        INT UNSIGNED DEFAULT 0 COMMENT 'Giới hạn số lượng thí sinh (0 = không giới hạn)',
    `registration_start`    DATETIME,
    `registration_end`      DATETIME,
    `allow_registration`    BOOLEAN DEFAULT false,
    `completed`             BOOLEAN DEFAULT false,
    `state`                 TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng',
    `ordering`              INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`            DATETIME,
    `created_by`            INT UNSIGNED,
    `modified_at`           DATETIME,
    `modified_by`           INT UNSIGNED,
    `checked_out`           INT UNSIGNED,
    `checked_out_time`      DATETIME,
    PRIMARY KEY (`id`),
    CONSTRAINT fk_eqa_assessments_campus FOREIGN KEY (`campus_id`)
        REFERENCES `#__eqa_campuses`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Kỳ thi sát hạch';

-- =============================================================================
-- Ca thi
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_examsessions`;
CREATE TABLE `#__eqa_examsessions`(
    `id`            INT UNSIGNED AUTO_INCREMENT,
    `examseason_id` INT UNSIGNED NULL    COMMENT 'Khóa ngoại: Đợt/kỳ thi (NULL nếu là ca thi sát hạch)',
    `assessment_id` INT UNSIGNED NULL    COMMENT 'Khóa ngoại: Kỳ sát hạch (NULL nếu là ca thi KTHP/TN)',
    `name`          VARCHAR(255) NOT NULL COMMENT 'Tên ca thi',
    `start`         DATETIME NOT NULL COMMENT 'Ngày, giờ bắt đầu làm bài thi',
    `flexible`      BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Ca thi linh hoạt về thời gian (thực hành, báo cáo...)',
    `monitor_ids`   TEXT COMMENT 'CSV danh sách đăng ký (id của) CBCT, CBGS',
    `examiner_ids`  TEXT COMMENT 'CSV danh sách đăng ký (id của) CBCTChT',
    `supervisor_ids` TEXT COMMENT 'CSV danh sách phân công (id của) CBGS (trong số đăng ký CBCT, CBGS)',
    `state`         TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng',
    `ordering`      INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME,
    `created_by`    INT UNSIGNED,
    `modified_at`   DATETIME,
    `modified_by`   INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    CONSTRAINT fk_eqa_examsessions_examseason FOREIGN KEY (`examseason_id`)
        REFERENCES `#__eqa_examseasons`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_examsessions_assessment FOREIGN KEY (`assessment_id`)
        REFERENCES `#__eqa_assessments`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Ca thi';

-- =============================================================================
-- Môn thi
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_exams`;
CREATE TABLE `#__eqa_exams`(
    `id`               INT UNSIGNED AUTO_INCREMENT,
    `subject_id`       INT UNSIGNED NOT NULL COMMENT 'Khóa ngoại: Môn học',
    `examseason_id`    INT UNSIGNED NOT NULL COMMENT 'Khóa ngoại: Kỳ thi',
    `is_pass_fail`     BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Môn điều kiện, không tính điểm (copy từ subject)',
    `name`             VARCHAR(255) NOT NULL COMMENT 'Tên môn thi (tên chính thức, copy từ subject)',
    `display_name`     VARCHAR(255) NULL COMMENT 'Tên phân biệt, copy từ subject khi tạo môn thi; NULL = dùng name',
    `code`             VARCHAR(50) NOT NULL COMMENT 'Mã môn thi; duy nhất trong một kỳ thi',
    `status`           TINYINT UNSIGNED COMMENT 'Trạng thái môn thi (ExamStatus enum)',
    `testtype`         INT UNSIGNED NOT NULL COMMENT 'Hình thức thi',
    `duration`         INT UNSIGNED COMMENT 'Thời gian làm bài, tính bằng phút',
    `kmonitor`         DOUBLE NOT NULL DEFAULT 1 COMMENT 'Hệ số sản lượng coi thi',
    `kassess`          DOUBLE NOT NULL DEFAULT 1 COMMENT 'Hệ số sản lượng chấm thi',
    `kquestion`        DOUBLE NOT NULL DEFAULT 0 COMMENT 'Số giờ chuẩn quy đổi cho mỗi đề thi',
    `usetestbank`      BOOLEAN NOT NULL COMMENT 'Có sử dụng ngân hàng đề hay không',
    `allowed_rooms`    TEXT NULL COMMENT 'JSON: danh sách ID phòng được phép sử dụng; NULL = không giới hạn; ghi đè allowed_rooms của subject',
    `questiondeadline` DATE NULL COMMENT 'Thời hạn bàn giao đề thi (nếu có)',
    `questiondate`     DATE NULL COMMENT 'Ngày bàn giao đề thi thực tế (nếu có)',
    `questionsender_id` INT UNSIGNED NULL COMMENT 'Khóa ngoại: người giao đề thi (nếu có)',
    `questionauthor_id` INT UNSIGNED NULL COMMENT 'Khóa ngoại: người ra đề thi (nếu có)',
    `nquestion`        INT UNSIGNED NULL COMMENT 'Số lượng đề thi để tính sản lượng (nếu có)',
    `description`      TEXT,
    `state`            TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng',
    `ordering`         INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME,
    `created_by`       INT UNSIGNED,
    `modified_at`      DATETIME,
    `modified_by`      INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uq_eqa_exams_season_code` (`examseason_id`, `code`),
    CONSTRAINT fk_eqa_exams_subject FOREIGN KEY (`subject_id`)
        REFERENCES `#__eqa_subjects`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exams_examseason FOREIGN KEY (`examseason_id`)
        REFERENCES `#__eqa_examseasons`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exams_questionsender FOREIGN KEY (`questionsender_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exams_questionauthor FOREIGN KEY (`questionauthor_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Môn (bài) thi';

-- =============================================================================
-- Phòng thi (vật lý và logic)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_examrooms`;
CREATE TABLE `#__eqa_examrooms`(
    `id`             INT UNSIGNED AUTO_INCREMENT,
    `name`           VARCHAR(255) NOT NULL COMMENT 'Tên phòng thi',
    `room_id`        INT UNSIGNED NOT NULL COMMENT 'Khóa ngoại: Phòng học (địa điểm thi)',
    `examsession_id` INT UNSIGNED COMMENT 'Khóa ngoại: ca thi',
    `exam_ids`       TEXT COMMENT 'Các môn thi trong phòng thi',
    `monitor1_id`    INT UNSIGNED COMMENT 'CBCT 1',
    `monitor2_id`    INT UNSIGNED COMMENT 'CBCT 2',
    `monitor3_id`    INT UNSIGNED COMMENT 'CBCT 3',
    `examiner1_id`   INT UNSIGNED COMMENT 'CBCTChT 1',
    `examiner2_id`   INT UNSIGNED COMMENT 'CBCTChT 2',
    `anomaly`        TEXT COMMENT 'Bất thường phòng thi',
    `description`    TEXT,
    `state`          TINYINT NOT NULL DEFAULT 1 COMMENT '1=Đang dùng, 0=Tạm ngừng',
    `ordering`       INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`     DATETIME,
    `created_by`     INT UNSIGNED,
    `modified_at`    DATETIME,
    `modified_by`    INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`room_id`, `examsession_id`),
    CONSTRAINT fk_eqa_examrooms_room FOREIGN KEY (`room_id`)
        REFERENCES `#__eqa_rooms`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_examrooms_examsession FOREIGN KEY (`examsession_id`)
        REFERENCES `#__eqa_examsessions`(`id`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Phòng thi (vật lý và logic)';

-- =============================================================================
-- Kết quả thi của thí sinh
-- (junction table — đã bổ sung surrogate key `id` từ v2.0.6)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_exam_learner`;
CREATE TABLE `#__eqa_exam_learner`(
    `id`                 INT UNSIGNED AUTO_INCREMENT,
    `exam_id`            INT UNSIGNED NOT NULL COMMENT 'Khóa ngoại: môn thi',
    `learner_id`         INT UNSIGNED NOT NULL COMMENT 'Khóa ngoại: học viên, sinh viên',
    `class_id`           INT UNSIGNED COMMENT 'Khóa ngoại: lớp học phần',
    `stimulation_id`     INT UNSIGNED COMMENT 'FK: Chế độ khuyến khích',
    `debtor`             BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Có nợ học phí hay không',
    `attempt`            TINYINT UNSIGNED COMMENT 'Lần thi: (1) Thi lần 1, (2) Thi lần 2',
    `examroom_id`        INT UNSIGNED COMMENT 'FK: phòng thi',
    `code`               INT UNSIGNED COMMENT 'Số báo danh',
    `anomaly`            TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Xử lý (const)',
    `mark_orig`          REAL COMMENT 'Điểm thi KTHP (chấm lần 1, chưa xử lý kỷ luật nếu có)',
    `ppaa`               TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Post-Primary Assessment Action',
    `mark_ppaa`          REAL COMMENT 'Điểm thi KTHP sau phúc khảo (chưa xử lý kỷ luật nếu có)',
    `mark_final`         REAL COMMENT 'Điểm thi KTHP sau khi phúc khảo và trừ kỷ luật nếu có',
    `module_mark`        REAL COMMENT 'Điểm HP; nếu là thi lần 2 thì đã áp dụng giới hạn điểm thi lần 2',
    `module_base4_mark`  REAL COMMENT 'Điểm HP quy đổi sang hệ 4',
    `module_grade`       CHAR(2) COMMENT 'Điểm HP bằng chữ',
    `conclusion`         TINYINT UNSIGNED COMMENT 'Kết luận (qua, làm lại bài thi, phải thi lại, phải học lại...); định nghĩa bằng constants',
    `description`        TEXT,
    `created_at`		 DATETIME,
    `created_by`		 INT UNSIGNED,
    `modified_at`        DATETIME,
    `modified_by`        INT UNSIGNED,
    PRIMARY KEY (`id`),
    UNIQUE (`exam_id`, `learner_id`),
    UNIQUE (`exam_id`, `code`),
    CONSTRAINT fk_eqa_exam_learner_exam FOREIGN KEY (`exam_id`)
        REFERENCES `#__eqa_exams`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exam_learner_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exam_learner_class FOREIGN KEY (`class_id`)
        REFERENCES `#__eqa_classes`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exam_learner_stimulation FOREIGN KEY (`stimulation_id`)
        REFERENCES `#__eqa_stimulations`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exam_learner_examroom FOREIGN KEY (`examroom_id`)
        REFERENCES `#__eqa_examrooms`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Kết quả thi của thí sinh';

-- =============================================================================
-- Túi bài thi viết
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_packages`;
CREATE TABLE `#__eqa_packages`(
    `id`                INT UNSIGNED AUTO_INCREMENT,
	`exam_id`			INT UNSIGNED NOT NULL COMMENT 'FK: môn thi',
    `number`            INT UNSIGNED NOT NULL COMMENT 'Số hiệu túi (trong phạm vi 1 môn thi)',
    `examiner1_id`      INT UNSIGNED COMMENT 'Khóa ngoại: CBChT 1',
    `examiner2_id`      INT UNSIGNED COMMENT 'Khóa ngoại: CBChT 2',
    `readydeadline`     DATE COMMENT 'Hạn làm phách xong',
    `readydate`         DATE COMMENT 'Ngày làm phách xong',
    `startdeadline`     DATE COMMENT 'Hạn bắt đầu chấm (bàn giao túi)',
    `startdate`         DATE COMMENT 'Ngày bắt đầu chấm (bàn giao túi)',
    `finishdeadline`    DATE COMMENT 'Hạn chấm xong (bàn giao điểm)',
    `finishdate`        DATE COMMENT 'Ngày chấm xong (bàn giao điểm)',
    `description`       TEXT,
    `created_at`        DATETIME,
    `created_by`        INT UNSIGNED,
    `modified_at`       DATETIME,
    `modified_by`       INT UNSIGNED,
    `checked_out`       INT UNSIGNED,
    `checked_out_time`  DATETIME,
    PRIMARY KEY (`id`),
	UNIQUE(`exam_id`, `number`),
    CONSTRAINT fk_eqa_packages_exam FOREIGN KEY (`exam_id`)
        REFERENCES `#__eqa_exams`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_packages_examiner1 FOREIGN KEY (`examiner1_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_packages_examiner2 FOREIGN KEY (`examiner2_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Túi bài thi viết';

-- =============================================================================
-- Bài thi viết
-- (junction table — đã bổ sung surrogate key `id` từ v2.0.6)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_papers`;
CREATE TABLE `#__eqa_papers`(
    `id`         INT UNSIGNED AUTO_INCREMENT,
    `exam_id`    INT UNSIGNED NOT NULL COMMENT 'FK: môn thi',
    `learner_id` INT UNSIGNED NOT NULL COMMENT 'FK: thí sinh',
    `nsheet`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số tờ giấy thi',
    `mask`       INT UNSIGNED COMMENT 'Số phách',
    `package_id` INT UNSIGNED COMMENT 'FK: Túi bài thi',
    `mark`       REAL COMMENT 'Điểm bài thi',
    PRIMARY KEY (`id`),
    UNIQUE (`exam_id`, `learner_id`),
    CONSTRAINT fk_eqa_papers_exam FOREIGN KEY (`exam_id`)
        REFERENCES `#__eqa_exams`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_papers_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_papers_package FOREIGN KEY (`package_id`)
        REFERENCES `#__eqa_packages`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bài thi viết';

-- =============================================================================
-- Phúc khảo bài thi
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_regradings`;
CREATE TABLE `#__eqa_regradings`(
    `id`           INT UNSIGNED AUTO_INCREMENT,
    `exam_id`      INT UNSIGNED NOT NULL COMMENT 'FK: mã môn thi',
    `learner_id`   INT UNSIGNED NOT NULL COMMENT 'FK: thí sinh',
    `examiner1_id` INT UNSIGNED COMMENT 'FK: CBChT1',
    `examiner2_id` INT UNSIGNED COMMENT 'FK: CBChT2',
    `result`       REAL COMMENT 'Điểm SAU phúc khảo',
    `description`  TEXT COMMENT 'Lý do tăng, giảm điểm (nếu có)',
    `status`       TINYINT UNSIGNED NOT NULL COMMENT 'Tiến độ xử lý',
    `payment_amount`    INT          NOT NULL DEFAULT 0    COMMENT 'Phí phúc khảo (VND)',
    `payment_code`      VARCHAR(8)   NULL      COMMENT 'Mã nộp tiền phúc khảo',
    `payment_completed` BOOLEAN   NOT NULL DEFAULT FALSE    COMMENT 'Đã nộp phí',
    `created_at`   DATETIME,
    `created_by`   INT UNSIGNED,
    `handled_at`   DATETIME,
    `handled_by` INT UNSIGNED NULL COMMENT 'User ID (#__users) của người xử lý phúc khảo',
    `handled_by_username` VARCHAR(255) NULL COMMENT 'Username của người xử lý phúc khảo (giữ lại để tương thích ngược)',
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`exam_id`, `learner_id`),
    CONSTRAINT fk_eqa_regradings_exam FOREIGN KEY (`exam_id`)
        REFERENCES `#__eqa_exams`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_regradings_examiner1 FOREIGN KEY (`examiner1_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_regradings_examiner2 FOREIGN KEY (`examiner2_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_regradings_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Phúc khảo bài thi';

-- =============================================================================
-- Đính chính điểm thi
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_gradecorrections`;
CREATE TABLE `#__eqa_gradecorrections`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `exam_id`     INT UNSIGNED NOT NULL COMMENT 'FK: mã môn thi',
    `learner_id`  INT UNSIGNED NOT NULL COMMENT 'FK: thí sinh',
    `constituent` TINYINT UNSIGNED NOT NULL COMMENT 'Điểm thành phần cần đính chính. Định nghĩa bằng const',
    `reason`      TEXT COMMENT 'Mô tả yêu cầu đính chính',
    `description` TEXT COMMENT 'Mô tả sai sót (nếu có)',
    `status`      TINYINT UNSIGNED NOT NULL COMMENT 'Tiến độ xử lý',
    `created_at`  DATETIME,
    `created_by`  INT UNSIGNED,
    `handled_at`  DATETIME,
    `handled_by`  INT UNSIGNED COMMENT 'User ID (#__users) của người xử lý',
    `handled_by_username` VARCHAR(255) NULL,
    `reviewer_id` INT UNSIGNED COMMENT 'Người xử lý',
    `changed`     BOOLEAN COMMENT 'Có thay đổi điểm sau xử lý yêu cầu hay không',
    `modified_at`  DATETIME,
    `modified_by`  INT UNSIGNED,
    `checked_out`      INT UNSIGNED,
    `checked_out_time` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE (`exam_id`, `learner_id`),
    CONSTRAINT fk_eqa_gradecorrections_exam FOREIGN KEY (`exam_id`)
        REFERENCES `#__eqa_exams`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_gradecorrections_reviewer FOREIGN KEY (`reviewer_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_gradecorrections_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Đính chính điểm thi';

-- =============================================================================
-- Machine Marking Productions
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_mmproductions`;
CREATE TABLE `#__eqa_mmproductions`(
    `id`          INT UNSIGNED AUTO_INCREMENT,
    `exam_id`     INT UNSIGNED NOT NULL COMMENT 'FK: mã môn thi',
    `examiner_id` INT UNSIGNED NOT NULL COMMENT 'FK: CBChT',
    `role`        INT UNSIGNED NOT NULL COMMENT '1: CBChT1, 2: CBChT2',
    `quantity`    REAL COMMENT 'Số lượng bài',
    PRIMARY KEY (`id`),
    CONSTRAINT fk_eqa_mmproductions_exam FOREIGN KEY (`exam_id`)
        REFERENCES `#__eqa_exams`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_mmproductions_examiner FOREIGN KEY (`examiner_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Machine Marking Productions';

-- =============================================================================
-- Đánh giá rèn luyện
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_conducts`;
CREATE TABLE `#__eqa_conducts`(
    `id`                       INT UNSIGNED AUTO_INCREMENT,
    `learner_id`               INT UNSIGNED NOT NULL,
    `academicyear`             INT UNSIGNED NOT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)',
    `term`                     INT UNSIGNED NOT NULL,
    `excused_absence_count`    INT UNSIGNED DEFAULT 0   COMMENT 'Số buổi vắng có phép',
    `unexcused_absence_count`  INT UNSIGNED DEFAULT 0   COMMENT 'Số buổi vắng không phép',
    `resit_count`              INT UNSIGNED DEFAULT 0   COMMENT 'Số môn thi lại',
    `retake_count`             INT UNSIGNED DEFAULT 0   COMMENT 'Số môn học lại',
    `award_count`              INT UNSIGNED DEFAULT 0   COMMENT 'Số lần được khen thưởng',
    `disciplinary_action_count` INT UNSIGNED DEFAULT 0  COMMENT 'Số lần bị xử lý kỷ luật',
    `total_credits`            FLOAT           COMMENT 'Tổng số tín chỉ',
    `academic_score`           DOUBLE COMMENT 'Điểm học tập trung bình',
    `academic_rating`          TINYINT UNSIGNED COMMENT 'Phân loại học tập',
    `conduct_score`            DOUBLE COMMENT 'Điểm rèn luyện bằng số',
    `conduct_rating`           TINYINT UNSIGNED COMMENT 'Phân loại',
    `note`                     VARCHAR(255),
    `description`              TEXT,
    `created_at`               DATETIME,
    `created_by`               INT UNSIGNED,
    `modified_at`              DATETIME,
    `modified_by`              INT UNSIGNED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_learner_academicyear_term` (`learner_id`, `academicyear`, `term`),
    INDEX `idx_eqa_conducts_term` (`term`),
    CONSTRAINT fk_eqa_conducts_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Đánh giá rèn luyện';

-- =============================================================================
-- Danh sách thi lần hai
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_secondattempts`;
CREATE TABLE `#__eqa_secondattempts`(
    `id`                INT UNSIGNED AUTO_INCREMENT,
    `class_id`          INT UNSIGNED NOT NULL,
    `learner_id`        INT UNSIGNED NOT NULL,
    `last_exam_id`      INT UNSIGNED NOT NULL,
    `last_attempt`      INT UNSIGNED NOT NULL,
    `last_conclusion`   INT UNSIGNED,
    `payment_amount`    DOUBLE NOT NULL DEFAULT 0 COMMENT 'Phí thi lần 2 (VNĐ); 0 = miễn phí',
    `payment_completed` BOOLEAN,
    `payment_code`      CHAR(8),
	`description`		TEXT NULL COMMENT 'Nội dung chuyển khoản (trích từ bản sao kê ngân hàng)',
    PRIMARY KEY (`id`),
    INDEX `idx_eqa_secondattempts_learner` (`learner_id`),
    UNIQUE (`class_id`, `learner_id`),
    UNIQUE (`payment_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Danh sách thi lần hai';

-- =============================================================================
-- Danh sách thi sát hạch
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_assessment_learner`;
CREATE TABLE `#__eqa_assessment_learner` (
     `id`                    INT UNSIGNED AUTO_INCREMENT,
     `assessment_id`         INT UNSIGNED NOT NULL,
     `learner_id`            INT UNSIGNED NOT NULL,
     `examroom_id`           INT UNSIGNED   COMMENT 'FK: phòng thi (nếu có)',
     `code`                  INT UNSIGNED   COMMENT 'Số báo danh',
     `payment_amount`        INT UNSIGNED NOT NULL COMMENT 'Phí sát hạch phải nộp',
     `payment_code`          CHAR(8) COMMENT 'Mã nộp tiền (8 ký tự [A-Z0-9])',
     `payment_completed`     BOOLEAN NOT NULL DEFAULT FALSE,
     `anomaly`               TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Bất thường (AnomalyType Enum)',
     `raw_result`            TEXT COMMENT 'JSON: điểm/kết quả thành phần',
     `score`                 FLOAT COMMENT 'Điểm quy đổi (nếu result_type = Score hoặc ScoreAndLevel)',
     `level`                 TINYINT UNSIGNED COMMENT 'Bậc/hạng (AssessmentResultLevel Enum, nếu result_type = Level hoặc ScoreAndLevel)',
     `passed`                BOOLEAN COMMENT 'Đạt chuẩn (nếu result_type = PassFail)',
     `note`                  TEXT COMMENT 'Ghi chú (nếu có)',
     `cancelled`             BOOLEAN DEFAULT FALSE COMMENT 'Đã hủy đăng ký',
     `created_at`            DATETIME,
     `created_by`            INT UNSIGNED,
     `modified_at`           DATETIME,
     `modified_by`           INT UNSIGNED,
     PRIMARY KEY (`id`),
     UNIQUE KEY `uq_assessment_learner` (`assessment_id`, `learner_id`),
     UNIQUE KEY `uq_payment_code` (`payment_code`),
     CONSTRAINT `fk_eqa_assessment_learner_examroom` FOREIGN KEY (`examroom_id`)
         REFERENCES `#__eqa_examrooms`(`id`) ON DELETE RESTRICT,
     CONSTRAINT `fk_eqa_assessment_learner_assessment` FOREIGN KEY (`assessment_id`)
         REFERENCES `#__eqa_assessments`(`id`) ON DELETE RESTRICT,
     CONSTRAINT `fk_eqa_assessment_learner_learner` FOREIGN KEY (`learner_id`)
         REFERENCES `#__eqa_learners`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Thí sinh sát hạch';

-- =============================================================================
-- Logs
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_logs`;
CREATE TABLE `#__eqa_logs` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED        NULL,
    `username`      VARCHAR(150)        NULL,
    `action`        SMALLINT UNSIGNED   NOT NULL,
    `is_success`    TINYINT				NOT NULL DEFAULT 0,
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
