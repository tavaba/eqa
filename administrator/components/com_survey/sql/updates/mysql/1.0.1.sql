ALTER TABLE `#__survey_campaigns` DROP `performance_mode`;
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
	
	
