DROP TABLE IF EXISTS `#__eqa_reviews`;
CREATE TABLE `#__eqa_regradings`(
    `id` INT AUTO_INCREMENT,
    `exam_id` INT NOT NULL COMMENT 'FK: mã môn thi',
	`learner_id` INT NOT NULL COMMENT 'FK: thí sinh',
	`examiner1_id` INT COMMENT 'FK: CBChT1',
	`examiner2_id` INT COMMENT 'FK: CBChT2',
	`result` REAL COMMENT 'Điểm SAU phúc khảo',
	`description` TEXT COMMENT 'Lý do tăng, giảm điểm (nếu có)',
	`status` TINYINT NOT NULL COMMENT 'Tiến độ xử lý',
	`checked_out` INT DEFAULT NULL,
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
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Phúc khảo bài thi';

CREATE TABLE `#__eqa_gradecorrections`(
    `id` INT AUTO_INCREMENT,
    `exam_id` INT NOT NULL COMMENT 'FK: mã môn thi',
	`learner_id` INT NOT NULL COMMENT 'FK: thí sinh',
	`constituent` TINYINT NOT NULL COMMENT 'Điểm thành phần cần đính chính. Định nghĩa bằng const',
	`reason` TEXT COMMENT 'Mô tả yêu cầu đính chính',
	`examiner1_id` INT COMMENT 'FK: CBChT1',
	`examiner2_id` INT COMMENT 'FK: CBChT2',
	`result` REAL COMMENT 'Điểm SAU đính chính',
	`description` TEXT COMMENT 'Mô tả sai sót (nếu có)',
	`status` TINYINT NOT NULL COMMENT 'Tiến độ xử lý',
	`checked_out` INT DEFAULT NULL,
	`checked_out_time` DATETIME DEFAULT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT fk_eqa_gradecorrections_exam FOREIGN KEY (`exam_id`)
		REFERENCES `#__eqa_exams`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_gradecorrections_examiner1 FOREIGN KEY (`examiner1_id`)
		REFERENCES `#__eqa_employees`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_gradecorrections_examiner2 FOREIGN KEY (`examiner2_id`)
		REFERENCES `#__eqa_employees`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_gradecorrections_learner FOREIGN KEY (`learner_id`)
		REFERENCES `#__eqa_learners`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Đính chính điểm thi';

