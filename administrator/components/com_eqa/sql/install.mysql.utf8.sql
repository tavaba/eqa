/**
 * com_eqa — Install SQL Schema
 * Version : 2.0.4
 *
 * Thay đổi so với 2.0.3:
 *   - Xóa bảng #__eqa_academicyears (năm học lưu trực tiếp dưới dạng INT)
 *   - #__eqa_classes    : academicyear_id INT FK → academicyear INT NOT NULL
 *   - #__eqa_examseasons: academicyear_id INT FK → academicyear INT NOT NULL
 *   - #__eqa_conducts   : academicyear_id INT FK → academicyear INT NOT NULL
 *                         UNIQUE(learner_id, academicyear_id, term)
 *                               → UNIQUE(learner_id, academicyear, term)
 *
 * Quy ước mã hóa năm học (academicyear):
 *   Lưu năm đầu tiên của năm học dưới dạng INT 4 chữ số.
 *   Ví dụ: 2025 → năm học 2025-2026.
 *   Giải mã bằng DatetimeHelper::decodeAcademicYear(int $year): string
 */
 
-- Tắt kiểm tra khóa ngoại để xóa sạch không bị lỗi ràng buộc
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- Tòa nhà
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_buildings`;
CREATE TABLE `#__eqa_buildings`(
    `id`          INT AUTO_INCREMENT,
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu tòa nhà. Ví dụ: TA1, TB1...',
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Các tòa nhà trong Học viện';

-- =============================================================================
-- Phòng học (vật lý)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_rooms`;
CREATE TABLE `#__eqa_rooms`(
    `id`          INT AUTO_INCREMENT,
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu phòng. Ví dụ: 104, 401-TA2...',
    `building_id` INT NOT NULL,
    `maxcapacity` INT COMMENT 'Số chỗ ngồi tối đa',
    `capacity`    INT NOT NULL COMMENT 'Số chỗ ngồi được sử dụng để tổ chức thi',
    `description` TEXT,
    `type`        TINYINT NOT NULL COMMENT 'Loại phòng: (0) phòng thường, (1) giảng đường có ổ cắm, (2) phòng máy',
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`          INT AUTO_INCREMENT,
    `parent_id`   INT NOT NULL DEFAULT 0 COMMENT 'Đơn vị cấp trên; 0 nếu trực thuộc Học viện',
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu, ví dụ: K.ATTT, BM.ATGDDT',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên đầy đủ, ví dụ: Khoa An toàn thông tin',
    `type`        TINYINT NOT NULL DEFAULT 0 COMMENT 'Loại đơn vị: (1) Khoa/bộ môn, (2) Phòng/ban',
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cơ quan, đơn vị trong Học viện (chỉ 2 cấp!!!)';

-- =============================================================================
-- Cán bộ, giảng viên, nhân viên
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_employees`;
CREATE TABLE `#__eqa_employees` (
    `id`          INT AUTO_INCREMENT,
    `code`        VARCHAR(255) DEFAULT NULL COMMENT 'Mã cán bộ, nhân viên',
    `lastname`    VARCHAR(255) NOT NULL COMMENT 'Họ Đệm',
    `firstname`   VARCHAR(255) NOT NULL COMMENT 'Tên',
    `unit_id`     INT NOT NULL COMMENT 'Khóa ngoại: cơ quan/đơn vị',
    `email`       VARCHAR(255),
    `mobile`      VARCHAR(255),
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`          INT AUTO_INCREMENT,
    `code`        VARCHAR(255) NOT NULL COMMENT 'Ký hiệu',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên ngành đào tạo',
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Ngành đào tạo';

-- =============================================================================
-- Chương trình đào tạo
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_programs`;
CREATE TABLE `#__eqa_programs`(
    `id`          INT AUTO_INCREMENT,
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên của chương trình đào tạo',
    `spec_id`     INT NOT NULL COMMENT 'FK: Ngành đào tạo',
    `degree`      TINYINT NOT NULL COMMENT 'Trình độ: (7) Đại học, (8) Thạc sĩ, (9) Tiến sĩ',
    `format`      TINYINT NOT NULL COMMENT 'Loại hình: Tiêu chuẩn, Liên thông, VB2',
    `approach`    TINYINT NOT NULL COMMENT 'Hình thức: CQ, VLVH, TX',
    `firstrelease` INT COMMENT 'Năm ban hành lần đầu',
    `lastupdate`  INT COMMENT 'Năm sửa đổi gần nhất',
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`            INT AUTO_INCREMENT,
    `prog_id`       INT NOT NULL COMMENT 'FK: Chương trình ĐT',
    `code`          VARCHAR(255) NOT NULL COMMENT 'Ký hiệu. Ví dụ: AT20',
    `admissionyear` INT NOT NULL DEFAULT 0 COMMENT 'Năm nhập học',
    `description`   VARCHAR(255) COMMENT 'Ví dụ: 9/2023 - 01/2028',
    `published`     BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`      INT NOT NULL DEFAULT 0,
    `created_at`    DATETIME,
    `created_by`    INT,
    `updated_at`    DATETIME,
    `updated_by`    INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`          INT AUTO_INCREMENT,
    `course_id`   INT COMMENT 'FK: Khóa đào tạo. NULL với lớp ngắn hạn...',
    `code`        VARCHAR(255) NOT NULL COMMENT 'Tên lớp. Ví dụ: AT20A',
    `homeroom_id` INT COMMENT 'FK: Giáo viên chủ nhiệm',
    `adviser_id`  INT COMMENT 'FK: Cố vấn học tập số 1',
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`          INT AUTO_INCREMENT,
    `code`        VARCHAR(255) NOT NULL COMMENT 'Mã HVSV. Ví dụ: AT010101',
    `lastname`    VARCHAR(255) NOT NULL COMMENT 'Họ Đệm',
    `firstname`   VARCHAR(255) NOT NULL COMMENT 'Tên',
    `group_id`    INT NOT NULL COMMENT 'FK: Lớp hành chính',
    `debtor`      BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Có nợ học phí hay không',
    `description` TEXT,
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`          INT AUTO_INCREMENT,
    `code`        VARCHAR(20) NOT NULL COMMENT 'Ký hiệu nhóm. Ví dụ: H30L',
    `name`        VARCHAR(255) NOT NULL COMMENT 'Tên nhóm: H30 Lào',
    `published`   BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`    INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Nhóm HVSV';

DROP TABLE IF EXISTS `#__eqa_cohort_learner`;
CREATE TABLE `#__eqa_cohort_learner` (
    `cohort_id`  INT NOT NULL,
    `learner_id` INT NOT NULL,
    PRIMARY KEY (`cohort_id`, `learner_id`),
    CONSTRAINT fk_eqa_cohort_learner_cohort FOREIGN KEY (`cohort_id`)
        REFERENCES `#__eqa_cohorts`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT fk_eqa_cohort_learner_learner FOREIGN KEY (`learner_id`)
        REFERENCES `#__eqa_learners`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- Môn học
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_subjects`;
CREATE TABLE `#__eqa_subjects` (
    `id`                INT AUTO_INCREMENT,
    `code`              VARCHAR(255) NOT NULL COMMENT 'Mã môn học',
    `name`              VARCHAR(255) NOT NULL COMMENT 'Tên môn học',
    `degree`            INT NOT NULL COMMENT 'Bậc học',
    `credits`           REAL COMMENT 'Số tín chỉ (có thể lẻ)',
    `unit_id`           INT COMMENT 'Khóa ngoại: Đơn vị phụ trách môn học',
    `is_pass_fail`      BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Môn điều kiện, không tính điểm',
    `finaltesttype`     INT NOT NULL COMMENT 'Hình thức thi mặc định (định nghĩa bằng constants)',
    `finaltestduration` INT COMMENT 'Thời gian làm bài thi, tính bằng phút',
    `finaltestweight`   REAL NOT NULL COMMENT 'Trọng số điểm thi kết thúc học phần',
    `allowed_rooms`     TEXT NULL DEFAULT NULL COMMENT 'JSON: danh sách ID phòng được phép sử dụng; NULL = không giới hạn',
    `testbankyear`      INT COMMENT 'Năm xây dựng ngân hàng cho hình thức thi mặc định (nếu có)',
    `alltestbanks`      TEXT COMMENT 'JSON String (hoặc NULL) thể hiện các ngân hàng đang có {type:, year:}',
    `allmarkelements`   TEXT COMMENT 'JSON String thể hiện các thành phần đánh giá quá trình {name:, weight:}',
    `programs`          TEXT COMMENT 'Các CTĐT có môn học',
    `kmonitor`          REAL NOT NULL DEFAULT 1.0 COMMENT 'Hệ số tính sản lượng coi thi',
    `kassess`           REAL NOT NULL DEFAULT 1.0 COMMENT 'Hệ số tính sản lượng chấm thi',
    `description`       TEXT,
    `published`         BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`          INT NOT NULL DEFAULT 0,
    `created_at`        DATETIME,
    `created_by`        INT,
    `updated_at`        DATETIME,
    `updated_by`        INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_subjects_unit FOREIGN KEY (`unit_id`)
        REFERENCES `#__eqa_units`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Môn học';

-- =============================================================================
-- Lớp học phần
-- Thay đổi 2.0.4: academicyear_id INT FK → academicyear INT NOT NULL
--   Lưu trực tiếp năm đầu tiên của năm học (ví dụ: 2025 cho 2025-2026)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_classes`;
CREATE TABLE `#__eqa_classes` (
    `id`            INT AUTO_INCREMENT,
    `coursegroup`   VARCHAR(255) COMMENT 'Đối tượng người học',
    `code`          CHAR(40) COMMENT 'Mã lớp học phần',
    `name`          VARCHAR(255) NOT NULL COMMENT 'Tên lớp học phần',
    `subject_id`    INT NOT NULL COMMENT 'Khóa ngoại: Môn học',
    `lecturer_id`   INT COMMENT 'Khóa ngoại: Giảng viên (phụ trách chính)',
    `lecturers`     TEXT COMMENT 'JSON về tất cả giảng viên, nếu có nhiều hơn 1 GV',
    `academicyear`  INT NOT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)',
    `term`          TINYINT NOT NULL COMMENT 'Học kỳ (1, 2)',
    `start`         DATE COMMENT 'Ngày bắt đầu theo TKB',
    `finish`        DATE COMMENT 'Ngày kết thúc theo TKB',
    `size`          INT COMMENT 'Sĩ số lớp học',
    `npam`          INT NOT NULL DEFAULT 0 COMMENT 'Số lượng HVSV có điểm quá trình',
    `topicdeadline` DATE COMMENT 'Hạn gửi chủ đề đồ án/tiểu luận môn học',
    `topicdate`     DATE COMMENT 'Ngày bàn giao chủ đề đồ án/tiểu luận môn học',
    `thesisdate`    DATE COMMENT 'Ngày bàn giao sản phẩm đồ án/tiểu luận',
    `pamdeadline`   DATE COMMENT 'Hạn gửi điểm quá trình (nếu thi lần 1)',
    `pamdate`       DATE COMMENT 'Ngày bàn giao điểm quá trình (nếu thi lần 1)',
    `statistic`     TEXT COMMENT 'JSON thể hiện số liệu thống kê kết quả thi',
    `description`   TEXT,
    `published`     BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`      INT NOT NULL DEFAULT 0,
    `created_at`    DATETIME,
    `created_by`    INT,
    `updated_at`    DATETIME,
    `updated_by`    INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`code`),
    CONSTRAINT fk_eqa_classes_subject FOREIGN KEY (`subject_id`)
        REFERENCES `#__eqa_subjects`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_classes_lecturer FOREIGN KEY (`lecturer_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Các lớp học phần';

-- =============================================================================
-- Chế độ khuyến khích
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_stimulations`;
CREATE TABLE `#__eqa_stimulations`(
    `id`          INT AUTO_INCREMENT,
    `subject_id`  INT NOT NULL COMMENT 'FK: Môn học',
    `learner_id`  INT NOT NULL COMMENT 'FK: Người học',
    `type`        INT NOT NULL COMMENT 'Loại hình',
    `value`       FLOAT NOT NULL COMMENT 'Điểm khuyến khích',
    `reason`      TEXT NOT NULL COMMENT 'Lý do khuyến khích',
    `used`        BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`          INT AUTO_INCREMENT,
    `class_id`    INT NOT NULL,
    `learner_id`  INT NOT NULL,
    `pam1`        FLOAT COMMENT 'Điểm quá trình TP1',
    `pam2`        FLOAT COMMENT 'Điểm quá trình TP2',
    `pam`         FLOAT COMMENT 'Điểm quá trình',
    `allowed`     BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Được phép dự thi kết thúc học phần hay không',
    `ntaken`      TINYINT NOT NULL DEFAULT 0 COMMENT 'Số lượt đã thi',
    `expired`     BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Hết lượt thi',
    `description` VARCHAR(255),
    `created_at`  DATETIME,
    `created_by`  INT,
    `updated_at`  DATETIME,
    `updated_by`  INT,
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
-- Thay đổi 2.0.4: academicyear_id INT FK → academicyear INT NOT NULL
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_examseasons`;
CREATE TABLE `#__eqa_examseasons`(
    `id`                  INT AUTO_INCREMENT,
    `name`                VARCHAR(255) NOT NULL COMMENT 'Tên đợt thi',
    `academicyear`        INT NOT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)',
    `term`                TINYINT COMMENT 'Học kỳ',
    `type`                TINYINT NOT NULL COMMENT 'Loại kỳ thi: KTHP, Sát hạch, Tốt nghiệp, Khác (định nghĩa bằng constants)',
    `attempt`             TINYINT NOT NULL COMMENT 'Lượt thi: (1) Thi lần 1, (2) Thi lần 2',
    `default`             TINYINT NOT NULL DEFAULT FALSE COMMENT 'Là kỳ thi hiện tại (mặc định)',
    `start`               DATE COMMENT 'Ngày thi môn đầu tiên',
    `finish`              DATE COMMENT 'Ngày thi môn sau cùng',
    `ppaa_req_enabled`    BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Được gửi yêu cầu phúc khảo',
    `ppaa_req_deadline`   DATETIME NULL COMMENT 'Thời hạn gửi yêu cầu phúc khảo',
    `completed`           TINYINT NOT NULL DEFAULT 0,
    `statistic`           TEXT COMMENT 'JSON: số liệu thống kê về kỳ thi',
    `description`         TEXT,
    `published`           BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`            INT NOT NULL DEFAULT 0,
    `created_at`          DATETIME,
    `created_by`          INT,
    `updated_at`          DATETIME,
    `updated_by`          INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Đợt/kỳ thi';

-- =============================================================================
-- Ca thi
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_examsessions`;
CREATE TABLE `#__eqa_examsessions`(
    `id`            INT AUTO_INCREMENT,
    `examseason_id` INT NULL    COMMENT 'Khóa ngoại: Đợt/kỳ thi (NULL nếu là ca thi sát hạch)',
    `assessment_id` INT NULL    COMMENT 'Khóa ngoại: Kỳ sát hạch (NULL nếu là ca thi KTHP/TN)',
    `name`          VARCHAR(255) NOT NULL COMMENT 'Tên ca thi',
    `start`         DATETIME NOT NULL COMMENT 'Ngày, giờ bắt đầu làm bài thi',
    `flexible`      BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Ca thi linh hoạt về thời gian (thực hành, báo cáo...)',
    `monitor_ids`   TEXT COMMENT 'CSV danh sách (id của) CBGS, CBCT',
    `examiner_ids`  TEXT COMMENT 'CSV danh sách (id của) CBCTChT',
    `description`   TEXT,
    `published`     BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`      INT NOT NULL DEFAULT 0,
    `created_at`    DATETIME,
    `created_by`    INT,
    `updated_at`    DATETIME,
    `updated_by`    INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
    `id`            INT AUTO_INCREMENT,
    `subject_id`    INT NOT NULL COMMENT 'Khóa ngoại: Môn học',
    `examseason_id` INT NOT NULL COMMENT 'Khóa ngoại: Kỳ thi',
    `name`          VARCHAR(255) NOT NULL COMMENT 'Tên môn thi',
    `code`          VARCHAR(50) NOT NULL COMMENT 'Mã môn thi; duy nhất trong một kỳ thi',
    `is_pass_fail`  BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Môn điều kiện, không tính điểm',
    `testtype`      INT COMMENT 'Hình thức thi',
    `duration`      INT COMMENT 'Thời gian làm bài, tính bằng phút',
    `allowed_rooms` TEXT NULL DEFAULT NULL COMMENT 'JSON: danh sách ID phòng được phép sử dụng; NULL = không giới hạn; ghi đè allowed_rooms của subject',
    `description`   TEXT,
    `published`     BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`      INT NOT NULL DEFAULT 0,
    `created_at`    DATETIME,
    `created_by`    INT,
    `updated_at`    DATETIME,
    `updated_by`    INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uq_eqa_exams_season_code` (`examseason_id`, `code`),
    CONSTRAINT fk_eqa_exams_subject FOREIGN KEY (`subject_id`)
        REFERENCES `#__eqa_subjects`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_exams_examseason FOREIGN KEY (`examseason_id`)
        REFERENCES `#__eqa_examseasons`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Môn (bài) thi';

-- =============================================================================
-- Phòng thi (vật lý và logic)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_examrooms`;
CREATE TABLE `#__eqa_examrooms`(
    `id`             INT AUTO_INCREMENT,
    `name`           VARCHAR(255) NOT NULL COMMENT 'Tên phòng thi',
    `room_id`        INT NOT NULL COMMENT 'Khóa ngoại: Phòng học (địa điểm thi)',
    `examsession_id` INT COMMENT 'Khóa ngoại: ca thi',
    `exam_ids`       TEXT COMMENT 'Các môn thi trong phòng thi',
    `nmonitor`       INT COMMENT 'Số lượng CBCT',
    `nexaminer`      INT COMMENT 'Số lượng CBCTChT',
    `monitor1_id`    INT COMMENT 'CBCT 1',
    `monitor2_id`    INT COMMENT 'CBCT 2',
    `monitor3_id`    INT COMMENT 'CBCT 3',
    `examiner1_id`   INT COMMENT 'CBCTChT 1',
    `examiner2_id`   INT COMMENT 'CBCTChT 2',
    `anomaly`        TEXT COMMENT 'Bất thường phòng thi',
    `description`    TEXT,
    `published`      BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`       INT NOT NULL DEFAULT 0,
    `created_at`     DATETIME,
    `created_by`     INT,
    `updated_at`     DATETIME,
    `updated_by`     INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
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
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_exam_learner`;
CREATE TABLE `#__eqa_exam_learner`(
    `exam_id`            INT NOT NULL COMMENT 'Khóa ngoại: môn thi',
    `learner_id`         INT NOT NULL COMMENT 'Khóa ngoại: học viên, sinh viên',
    `class_id`           INT COMMENT 'Khóa ngoại: lớp học phần',
    `stimulation_id`     INT COMMENT 'FK: Chế độ khuyến khích',
    `debtor`             BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Có nợ học phí hay không',
    `attempt`            TINYINT COMMENT 'Lần thi: (1) Thi lần 1, (2) Thi lần 2',
    `examroom_id`        INT COMMENT 'FK: phòng thi',
    `code`               INT COMMENT 'Số báo danh',
    `anomaly`            TINYINT NOT NULL DEFAULT 0 COMMENT 'Xử lý (const)',
    `mark_orig`          REAL COMMENT 'Điểm thi KTHP (chấm lần 1, chưa xử lý kỷ luật nếu có)',
    `ppaa`               TINYINT NOT NULL DEFAULT 0 COMMENT 'Post-Primary Assessment Action',
    `mark_ppaa`          REAL COMMENT 'Điểm thi KTHP sau phúc khảo (chưa xử lý kỷ luật nếu có)',
    `mark_final`         REAL COMMENT 'Điểm thi KTHP sau khi phúc khảo và trừ kỷ luật nếu có',
    `module_mark`        REAL COMMENT 'Điểm HP; nếu là thi lần 2 thì đã áp dụng giới hạn điểm thi lần 2',
    `module_base4_mark`  REAL COMMENT 'Điểm HP quy đổi sang hệ 4',
    `module_grade`       CHAR(2) COMMENT 'Điểm HP bằng chữ',
    `conclusion`         TINYINT COMMENT 'Kết luận (qua, làm lại bài thi, phải thi lại, phải học lại...); định nghĩa bằng constants',
    `description`        TEXT,
    `created_at`         DATETIME,
    `created_by`         INT,
    `updated_at`         DATETIME,
    `updated_by`         INT,
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
    `id`            INT AUTO_INCREMENT,
    `number`        INT NOT NULL COMMENT 'Số hiệu túi (trong phạm vi 1 môn thi)',
    `examiner1_id`  INT COMMENT 'Khóa ngoại: CBChT 1',
    `examiner2_id`  INT COMMENT 'Khóa ngoại: CBChT 2',
    `readydeadline`  DATE COMMENT 'Hạn làm phách xong',
    `readydate`      DATE COMMENT 'Ngày làm phách xong',
    `startdeadline`  DATE COMMENT 'Hạn bắt đầu chấm (bàn giao túi)',
    `startdate`      DATE COMMENT 'Ngày bắt đầu chấm (bàn giao túi)',
    `finishdeadline` DATE COMMENT 'Hạn chấm xong (bàn giao điểm)',
    `finishdate`     DATE COMMENT 'Ngày chấm xong (bàn giao điểm)',
    `description`   TEXT,
    `created_at`    DATETIME,
    `created_by`    INT,
    `updated_at`    DATETIME,
    `updated_by`    INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT fk_eqa_packages_examiner1 FOREIGN KEY (`examiner1_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT,
    CONSTRAINT fk_eqa_packages_examiner2 FOREIGN KEY (`examiner2_id`)
        REFERENCES `#__eqa_employees`(`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Túi bài thi viết';

-- =============================================================================
-- Bài thi viết
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_papers`;
CREATE TABLE `#__eqa_papers`(
    `exam_id`    INT NOT NULL COMMENT 'FK: môn thi',
    `learner_id` INT NOT NULL COMMENT 'FK: thí sinh',
    `nsheet`     INT NOT NULL DEFAULT 0 COMMENT 'Số tờ giấy thi',
    `mask`       INT COMMENT 'Số phách',
    `package_id` INT COMMENT 'FK: Túi bài thi',
    `mark`       REAL COMMENT 'Điểm bài thi',
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
    `id`           INT AUTO_INCREMENT,
    `exam_id`      INT NOT NULL COMMENT 'FK: mã môn thi',
    `learner_id`   INT NOT NULL COMMENT 'FK: thí sinh',
    `examiner1_id` INT COMMENT 'FK: CBChT1',
    `examiner2_id` INT COMMENT 'FK: CBChT2',
    `result`       REAL COMMENT 'Điểm SAU phúc khảo',
    `description`  TEXT COMMENT 'Lý do tăng, giảm điểm (nếu có)',
    `status`       TINYINT NOT NULL COMMENT 'Tiến độ xử lý',
    `created_at`   DATETIME,
    `handled_at`   DATETIME,
    `handled_by`   VARCHAR(255),
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
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
    `id`          INT AUTO_INCREMENT,
    `exam_id`     INT NOT NULL COMMENT 'FK: mã môn thi',
    `learner_id`  INT NOT NULL COMMENT 'FK: thí sinh',
    `constituent` TINYINT NOT NULL COMMENT 'Điểm thành phần cần đính chính. Định nghĩa bằng const',
    `reason`      TEXT COMMENT 'Mô tả yêu cầu đính chính',
    `description` TEXT COMMENT 'Mô tả sai sót (nếu có)',
    `status`      TINYINT NOT NULL COMMENT 'Tiến độ xử lý',
    `created_at`  DATETIME,
    `handled_at`  DATETIME,
    `handled_by`  VARCHAR(255),
    `reviewer_id` INT DEFAULT NULL COMMENT 'Người xử lý',
    `changed`     BOOLEAN COMMENT 'Có thay đổi điểm sau xử lý yêu cầu hay không',
    `modified_at`  DATETIME,
    `modified_by`  INT,
    `checked_out`      INT DEFAULT NULL,
    `checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
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
    `id`          INT AUTO_INCREMENT,
    `exam_id`     INT NOT NULL COMMENT 'FK: mã môn thi',
    `examiner_id` INT NOT NULL COMMENT 'FK: CBChT',
    `role`        INT NOT NULL COMMENT '1: CBChT1, 2: CBChT2',
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
-- Thay đổi 2.0.4: academicyear_id INT FK → academicyear INT NOT NULL
--   UNIQUE(learner_id, academicyear_id, term) → UNIQUE(learner_id, academicyear, term)
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_conducts`;
CREATE TABLE `#__eqa_conducts`(
    `id`                       INT AUTO_INCREMENT,
    `learner_id`               INT NOT NULL,
    `academicyear`             INT NOT NULL COMMENT 'Năm học (encoded: năm đầu tiên, ví dụ 2025 cho 2025-2026)',
    `term`                     INT NOT NULL,
    `excused_absence_count`    INT DEFAULT 0   COMMENT 'Số buổi vắng có phép',
    `unexcused_absence_count`  INT DEFAULT 0   COMMENT 'Số buổi vắng không phép',
    `resit_count`              INT DEFAULT 0   COMMENT 'Số môn thi lại',
    `retake_count`             INT DEFAULT 0   COMMENT 'Số môn học lại',
    `award_count`              INT DEFAULT 0   COMMENT 'Số lần được khen thưởng',
    `disciplinary_action_count` INT DEFAULT 0  COMMENT 'Số lần bị xử lý kỷ luật',
    `total_credits`            FLOAT           COMMENT 'Tổng số tín chỉ',
    `academic_score`           REAL            COMMENT 'Điểm học tập trung bình',
    `academic_rating`          TINYINT         COMMENT 'Phân loại học tập',
    `conduct_score`            REAL            COMMENT 'Điểm rèn luyện bằng số',
    `conduct_rating`           TINYINT         COMMENT 'Phân loại',
    `note`                     VARCHAR(255),
    `description`              TEXT,
    `created_at`               DATETIME,
    `created_by`               INT,
    `updated_at`               DATETIME,
    `updated_by`               INT,
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
    `id`                INT AUTO_INCREMENT,
    `class_id`          INT NOT NULL,
    `learner_id`        INT NOT NULL,
    `last_exam_id`      INT NOT NULL,
    `last_attempt`      INT NOT NULL,
    `last_conclusion`   INT,
    `payment_amount`    FLOAT NOT NULL,
    `payment_completed` BOOLEAN,
    `payment_code`      CHAR(8),
    PRIMARY KEY (`id`),
    INDEX `idx_eqa_secondattempts_learner` (`learner_id`),
    UNIQUE (`class_id`, `learner_id`),
    UNIQUE (`payment_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Danh sách thi lần hai';


-- =============================================================================
-- Thi sát hạch
-- =============================================================================
DROP TABLE IF EXISTS `#__eqa_assessments`;
CREATE TABLE `#__eqa_assessments` (
    `id`                    INT AUTO_INCREMENT,
    `title`                 VARCHAR(255) NOT NULL,
    `type`                  TINYINT NOT NULL COMMENT 'AssessmentType Enum',
    `result_type`           TINYINT NOT NULL COMMENT 'AssessmentResultType Enum',
    `start_date`            DATE NOT NULL,
    `end_date`              DATE NOT NULL,
    `fee`                   INT NOT NULL DEFAULT 0 COMMENT 'Phí sát hạch (VNĐ)',
    `bank_napas_code`       VARCHAR(10)  DEFAULT NULL COMMENT 'Mã ngân hàng theo chuẩn NAPAS (dùng với VietQR)',
    `bank_account_number`   VARCHAR(50)  DEFAULT NULL COMMENT 'Số tài khoản ngân hàng thu phí',
    `bank_account_owner`    VARCHAR(255) DEFAULT NULL COMMENT 'Tên chủ tài khoản ngân hàng thu phí',
    `max_candidates`        INT DEFAULT 0 COMMENT 'Giới hạn số lượng thí sinh (0 = không giới hạn)',
    `registration_start`    DATETIME DEFAULT NULL,
    `registration_end`      DATETIME DEFAULT NULL,
    `allow_registration`    BOOLEAN DEFAULT false,
    `completed`             BOOLEAN DEFAULT false,
    `published`             BOOLEAN NOT NULL DEFAULT TRUE,
    `ordering`              INT NOT NULL DEFAULT 0,
    `created_at`            DATETIME,
    `created_by`            INT,
    `updated_at`            DATETIME,
    `updated_by`            INT,
    `checked_out`           INT DEFAULT NULL,
    `checked_out_time`      DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Kỳ thi sát hạch';

DROP TABLE IF EXISTS `#__eqa_assessment_learner`;
CREATE TABLE `#__eqa_assessment_learner` (
     `id`                    INT AUTO_INCREMENT,
     `assessment_id`         INT NOT NULL,
     `learner_id`            INT NOT NULL,
     `examroom_id`           INT DEFAULT NULL   COMMENT 'FK: phòng thi (nếu có)',
     `code`                  INT DEFAULT NULL   COMMENT 'Số báo danh',
     `payment_amount`        INT NOT NULL COMMENT 'Phí sát hạch phải nộp',
     `payment_code`          CHAR(8) DEFAULT NULL COMMENT 'Mã nộp tiền (8 ký tự [A-Z0-9])',
     `payment_completed`     BOOLEAN NOT NULL DEFAULT FALSE,
     `anomaly`               TINYINT NOT NULL DEFAULT 0 COMMENT 'Bất thường (AnomalyType Enum)',
     `raw_result`            TEXT DEFAULT NULL COMMENT 'JSON: điểm/kết quả thành phần',
     `score`                 FLOAT DEFAULT NULL COMMENT 'Điểm quy đổi (nếu result_type = Score hoặc ScoreAndLevel)',
     `level`                 TINYINT DEFAULT NULL COMMENT 'Bậc/hạng (AssessmentResultLevel Enum, nếu result_type = Level hoặc ScoreAndLevel)',
     `passed`                BOOLEAN DEFAULT NULL COMMENT 'Đạt chuẩn (nếu result_type = PassFail)',
     `note`                  TEXT DEFAULT NULL COMMENT 'Ghi chú (nếu có)',
     `cancelled`             BOOLEAN DEFAULT FALSE COMMENT 'Đã hủy đăng ký',
     `created_at`            DATETIME,
     `created_by`            INT,
     `updated_at`            DATETIME,
     `updated_by`            INT,
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

-- Bật lại kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 1;