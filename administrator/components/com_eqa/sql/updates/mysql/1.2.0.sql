CREATE TABLE `#__eqa_cohorts` (
    `id` INT AUTO_INCREMENT,
	`code` VARCHAR(20) NOT NULL COMMENT 'Ký hiệu nhóm. Ví dụ: H30L',
	`name` VARCHAR(255) NOT NULL COMMENT 'Tên nhóm: H30 Lào',
	`published` BOOLEAN NOT NULL DEFAULT TRUE,
	`ordering` INT NOT NULL DEFAULT 0,
	`created_at` DATETIME,
	`created_by` VARCHAR(255),
	`updated_at` DATETIME,
	`updated_by` VARCHAR(255),
	`checked_out` INT DEFAULT NULL,
	`checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
	UNIQUE(`code`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Nhóm HVSV';

CREATE TABLE `#__eqa_cohort_learner` (
    `cohort_id` INT  NOT NULL,
    `learner_id` INT  NOT NULL,
	PRIMARY KEY (`cohort_id`,`learner_id`),
	CONSTRAINT fk_eqa_cohort_learner_cohort FOREIGN KEY(`cohort_id`)
		REFERENCES `#__eqa_cohorts`(`id`)
		ON DELETE CASCADE,
	CONSTRAINT fk_eqa_cohort_learner_learner FOREIGN KEY(`learner_id`)
		REFERENCES `#__eqa_learners`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4;
