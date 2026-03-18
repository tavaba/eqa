
/*-------------------------------*\
 *    THI SÁT HẠCH               *
 *-------------------------------*/
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
    `modified_at`           DATETIME,
    `modified_by`           INT,
    `checked_out`           INT DEFAULT NULL,
    `checked_out_time`      DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Kỳ thi sát hạch';

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
    `modified_at`           DATETIME,
    `modified_by`           INT,
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
-- Bổ sung cột assessment_id vào #__eqa_examsessions
-- Cho phép ca thi thuộc một kỳ sát hạch thay vì một kỳ thi KTHP.
-- Đúng một trong hai (examseason_id, assessment_id) phải có giá trị.
-- =============================================================================
ALTER TABLE `#__eqa_examsessions`
    ADD COLUMN `assessment_id` INT NULL DEFAULT NULL
        COMMENT 'FK: Kỳ sát hạch (nếu là ca thi sát hạch)'
        AFTER `examseason_id`,
    ADD CONSTRAINT `fk_eqa_examsessions_assessment`
        FOREIGN KEY (`assessment_id`)
            REFERENCES `#__eqa_assessments` (`id`)
            ON DELETE RESTRICT;