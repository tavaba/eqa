-- ============================================================================
-- com_kmail: Các bảng dùng chung cho hệ thống gửi email thông báo
-- ============================================================================

CREATE TABLE IF NOT EXISTS `#__kmail_templates` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(200)    NOT NULL COMMENT 'Tên template (hiển thị khi chọn)',
    `context_type` TINYINT UNSIGNED NOT NULL COMMENT 'MailContextType enum value',
    `subject`     VARCHAR(500)    NOT NULL COMMENT 'Tiêu đề email (hỗ trợ placeholder)',
    `body`        LONGTEXT        NOT NULL COMMENT 'Nội dung HTML (hỗ trợ placeholder)',
    `published`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`  DATETIME        NOT NULL COMMENT 'UTC',
    `modified_by` INT UNSIGNED    NOT NULL DEFAULT 0,
    `modified_at` DATETIME        NOT NULL COMMENT 'UTC',
    PRIMARY KEY (`id`),
    KEY `idx_context_type_published` (`context_type`, `published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__kmail_campaigns` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `template_id`      INT UNSIGNED    NOT NULL COMMENT 'FK → #__kmail_templates',
    `context_type`     TINYINT UNSIGNED NOT NULL COMMENT 'MailContextType enum value',
    `context_id`       INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'ID đối tượng ngữ cảnh',
    `context_label`    VARCHAR(500)     NOT NULL DEFAULT '' COMMENT 'Nhãn ngữ cảnh tại thời điểm tạo campaign',
    `recipient_filter` JSON            NULL     COMMENT 'Filter bổ sung (vd: {"learner_ids":[1,2,3]})',
    `status`           TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Pending,1=Processing,2=Done,3=Cancelled',
    `total_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `sent_count`       INT UNSIGNED    NOT NULL DEFAULT 0,
    `failed_count`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_by`       INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`       DATETIME        NOT NULL COMMENT 'UTC',
    PRIMARY KEY (`id`),
    KEY `idx_context` (`context_type`, `context_id`),
    KEY `idx_status`  (`status`),
    CONSTRAINT `fk_email_campaign_template`
        FOREIGN KEY (`template_id`)
        REFERENCES `#__kmail_templates` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__kmail_queue` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `campaign_id`     INT UNSIGNED    NOT NULL COMMENT 'FK → #__kmail_campaigns',
    `recipient_type`  TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'MailRecipientType enum',
    `recipient_id`    INT UNSIGNED    NULL     COMMENT 'learner_id hoặc NULL',
    `recipient_email` VARCHAR(200)    NOT NULL,
    `subject`         VARCHAR(500)    NOT NULL COMMENT 'Đã render placeholder',
    `body`            LONGTEXT        NOT NULL COMMENT 'Đã render placeholder',
    `status`          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Pending,1=Sent,2=Failed',
    `attempts`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_attempt_at` DATETIME        NULL     COMMENT 'UTC',
    `sent_at`         DATETIME        NULL     COMMENT 'UTC',
    `error_message`   TEXT            NULL,
    `created_at`      DATETIME        NOT NULL COMMENT 'UTC',
    PRIMARY KEY (`id`),
    KEY `idx_dispatch` (`status`, `attempts`, `last_attempt_at`),
    KEY `idx_campaign` (`campaign_id`),
    CONSTRAINT `fk_email_queue_campaign`
        FOREIGN KEY (`campaign_id`)
        REFERENCES `#__kmail_campaigns` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__kmail_logs` (
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
