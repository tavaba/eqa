DROP TABLE IF EXISTS `#__survey_logs`;
CREATE TABLE `#__survey_logs` (
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
