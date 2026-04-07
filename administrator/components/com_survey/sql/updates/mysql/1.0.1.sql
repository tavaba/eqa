/*
 * Bỏ cột 'performance_mode' trong bảng #__survey_campaigns
 */
ALTER TABLE `#__survey_campaigns` DROP `performance_mode`;

/*
 * Thêm bảng #__survey_campaign_respondent để quản lý
 * các giá trị đếm 'survey_count', 'response_count'.
 * Đồng thời migrate số liệu hiện có sang bảng mới tạo này
 */
CREATE TABLE IF NOT EXISTS `#__survey_campaign_respondent`(
	`campaign_id` 				INT NOT NULL,
	`respondent_id`				INT NOT NULL,
	`survey_count`				INT NOT NULL		COMMENT 'Số lượng cuộc khảo sát của respondent_id trong campaign_id',
	`response_count`			INT NOT NULL		COMMENT 'Số lượng phản hồi đã gửi',
    PRIMARY KEY (`campaign_id`, `respondent_id`),
	INDEX idx_campaign_respondent_campaign(`campaign_id`),
	INDEX idx_campaign_respondent_respondent(`respondent_id`)
) ENGINE=InnoDB default charset = utf8mb4;

INSERT INTO `#__survey_campaign_respondent` 
    (`campaign_id`, `respondent_id`, `survey_count`, `response_count`)
SELECT 
    a.campaign_id,
    b.respondent_id,
    COUNT(*) as survey_count,
    SUM(CASE WHEN b.responded = 1 THEN 1 ELSE 0 END) as response_count
FROM `#__survey_surveys` AS a
INNER JOIN `#__survey_survey_respondent` AS b ON a.id = b.survey_id
WHERE a.campaign_id IS NOT NULL
GROUP BY a.campaign_id, b.respondent_id
ON DUPLICATE KEY UPDATE
    survey_count = VALUES(survey_count),
    response_count = VALUES(response_count);

/*
 * Thay đổi cấu trúc bảng log, tương thích với LogService của lib_kma
 * Chấp nhận mất hết log đang có.
 */
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
