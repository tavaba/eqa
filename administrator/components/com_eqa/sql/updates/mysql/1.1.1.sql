
/* Recreate the table #__eqa_papers and #__eqa_packages */
DROP TABLE IF EXISTS `#__eqa_papers`;
DROP TABLE IF EXISTS `#__eqa_packages`;
CREATE TABLE `#__eqa_packages`(
    `id` INT AUTO_INCREMENT,
	`number` INT NOT NULL COMMENT 'Số hiệu túi (trong phạm vi 1 môn thi)',
    `examiner1_id` INT COMMENT 'Khóa ngoại: CBChT 1',
    `examiner2_id` INT COMMENT 'Khóa ngoại: CBChT 2',
	`readydeadline` DATE COMMENT 'Hạn làm phách xong',
	`readydate` DATE COMMENT 'Ngày làm phách xong',
	`startdeadline` DATE COMMENT 'Hạn bắt đầu chấm (bàn giao túi)',
	`startdate` DATE COMMENT 'Ngày bắt đầu chấm (bàn giao túi)',
	`finishdeadline` DATE COMMENT 'Hạn chấm xong (bàn giao điểm)',
	`finishdate` DATE COMMENT 'Ngày chấm xong (bàn giao điểm)',
	`description` TEXT,
	`created_at` DATETIME,
	`created_by` VARCHAR(255),
	`updated_at` DATETIME,
	`updated_by` VARCHAR(255),
	`checked_out` INT DEFAULT NULL,
	`checked_out_time` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT fk_eqa_packages_examiner1 FOREIGN KEY (`examiner1_id`)
		REFERENCES `#__eqa_employees`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_packages_examiner2 FOREIGN KEY (`examiner2_id`)
		REFERENCES `#__eqa_employees`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Túi bài thi viết';
CREATE TABLE `#__eqa_papers`(
    `exam_id` INT NOT NULL COMMENT 'FK: môn thi',
	`learner_id` INT NOT NULL COMMENT 'FK: thí sinh',
	`nsheet` INT NOT NULL DEFAULT 0 COMMENT 'Số tờ giấy thi',
	`mask` INT COMMENT 'Số phách',
	`package_id` INT COMMENT 'FK: Túi bài thi',
	`mark` REAL COMMENT 'Điểm bài thi',
	UNIQUE(`exam_id`,`learner_id`),
	CONSTRAINT fk_eqa_papers_exam FOREIGN KEY (`exam_id`)
		REFERENCES `#__eqa_exams`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_papers_learner FOREIGN KEY (`learner_id`)
		REFERENCES `#__eqa_learners`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_papers_package FOREIGN KEY (`package_id`)
		REFERENCES `#__eqa_packages`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Bài thi viết';

