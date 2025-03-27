/* Create new table: #__eqa_stimulations */
CREATE TABLE `#__eqa_stimulations`(
    `id` INT AUTO_INCREMENT,
    `subject_id` INT NOT NULL COMMENT 'FK: Môn học',
	`learner_id` INT NOT NULL COMMENT 'FK: Người học',
	`type` INT NOT NULL COMMENT 'Loại hình',
	`value` FLOAT NOT NULL COMMENT 'Điểm khuyến khích',
	`reason` TEXT NOT NULL COMMENT 'Lý do khuyến khích',
	`created_at` DATETIME,
	`created_by` VARCHAR(255),
	`updated_at` DATETIME,
	`updated_by` VARCHAR(255),
	`checked_out` INT DEFAULT NULL,
	`checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
	CONSTRAINT fk_eqa_stimulations_subject FOREIGN KEY (`subject_id`)
		REFERENCES `#__eqa_subjects`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_stimulations_learner FOREIGN KEY (`learner_id`)
		REFERENCES `#__eqa_learners`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT unique_subject_learner UNIQUE(`subject_id`, `learner_id`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Chế độ khuyến khích';

/* Table #__eqa_exam_learner  */
ALTER TABLE `#__eqa_exam_learner` DROP `anomaly`;
ALTER TABLE `#__eqa_exam_learner` CHANGE `penalty` `anomaly` TINYINT NOT NULL DEFAULT '0' COMMENT 'Bất thường (const)';
ALTER TABLE `#__eqa_exam_learner` ADD `stimulation_id` INT NULL COMMENT 'FK: Chế độ khuyến khích' AFTER `class_id`;
ALTER TABLE `#__eqa_exam_learner` ADD CONSTRAINT `fk_eqa_exam_learner_stimulation` FOREIGN KEY (`stimulation_id`) REFERENCES `#__eqa_stimulations`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

/* Table #__eqa_papers   */
ALTER TABLE `#__eqa_papers` ADD `nsheet` INT NOT NULL DEFAULT '0' COMMENT 'Số tờ giấy thi' AFTER `code`;
