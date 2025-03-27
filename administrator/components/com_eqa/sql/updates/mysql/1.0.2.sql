ALTER TABLE `#__eqa_classes` CHANGE `code` `code` CHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'Mã lớp học phần';

ALTER TABLE `#__eqa_classes` ADD `coursegroup` VARCHAR(255) NULL COMMENT 'Đối tượng người học' AFTER `id`;

ALTER TABLE `#__eqa_classes` ADD `testtype` TINYINT NULL COMMENT 'Hình thức thi' AFTER `subject_id`;

DROP TABLE IF EXISTS `#__eqa_class_learner`;
CREATE TABLE `#__eqa_class_learner` (
    `class_id` INT NOT NULL,
	`learner_id` INT NOT NULL,
	UNIQUE(`class_id`,`learner_id`),
	FOREIGN KEY(`class_id`)
		REFERENCES `#__eqa_classes`(`id`)
		ON DELETE CASCADE,
	FOREIGN KEY(`learner_id`)
		REFERENCES `#__eqa_learners`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'HVSV các lớp học phần' ;
