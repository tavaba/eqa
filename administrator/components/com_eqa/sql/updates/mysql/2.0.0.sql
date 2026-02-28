CREATE TABLE IF NOT EXISTS `#__eqa_secondattempts`(
    `id` 				INT AUTO_INCREMENT,
	`class_id`			INT NOT NULL,
	`learner_id`		INT NOT NULL,
	`last_exam_id`		INT NOT NULL,
	`last_conclusion`	INT,
	`payment_required`	BOOLEAN NOT NULL,
	`payment_completed`	BOOLEAN,
	`payment_code`		CHAR(8),
	PRIMARY KEY (`id`),
	INDEX idx_eqa_secondattempts_learner(`learner_id`),
	UNIQUE(`class_id`, `learner_id`),
	UNIQUE(`payment_code`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Danh sách thi lần 2';