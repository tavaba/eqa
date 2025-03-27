CREATE TABLE `#__eqa_mmproductions`(
    `id` INT AUTO_INCREMENT,
    `exam_id` INT NOT NULL COMMENT 'FK: mã môn thi',
	`examiner_id` INT NOT NULL COMMENT 'FK: CBChT',
	`role` INT NOT NULL COMMENT '1: CBChT1, 2: CBChT2',
	`quantity` REAL COMMENT 'Số lượng bài',
	PRIMARY KEY (`id`),
	CONSTRAINT fk_eqa_mmproductions_exam FOREIGN KEY (`exam_id`)
		REFERENCES `#__eqa_exams`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_mmproductions_examiner FOREIGN KEY (`examiner_id`)
		REFERENCES `#__eqa_employees`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Machine Marking Productions';

