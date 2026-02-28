ALTER TABLE `#__eqa_subjects` ADD `is_pass_fail` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Môn điều kiện, không tính điểm ' AFTER `unit_id`;
ALTER TABLE `#__eqa_exams` ADD `is_pass_fail` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Môn điều kiện, không tính điểm (copy từ subject)' AFTER `examseason_id`;
ALTER TABLE `#__eqa_exam_learner` ADD `module_base4_mark` REAL NULL COMMENT 'Điểm HP quy đổi sang hệ 4' AFTER `module_mark`;


CREATE TABLE `#__eqa_conducts`(
    `id` 						INT AUTO_INCREMENT,
    `learner_id`				INT NOT NULL,
	`academicyear_id` 			INT NOT NULL,
	`term` 						INT NOT NULL,
	`excused_absence_count`		INT DEFAULT 0	COMMENT 'Số buổi vắng có phép',
	`unexcused_absence_count`	INT DEFAULT 0	COMMENT 'Số buổi vắng không phép',
	`resit_count`				INT DEFAULT 0	COMMENT 'Số môn thi lại',                 
	`retake_count`				INT DEFAULT 0	COMMENT	'Số môn học lại',                
	`award_count`				INT DEFAULT 0	COMMENT	'Số lần được khen thưởng',                
	`disciplinary_action_count`	INT DEFAULT 0	COMMENT	'Số lần bị xử lý kỷ luật',   
	`academic_score` 			REAL 			COMMENT 'Điểm học tập trung bình',
	`academic_rating`			TINYINT 		COMMENT 'Phân loại học tập',
	`conduct_score` 			REAL 			COMMENT 'Điểm rèn luyện bằng số',
	`conduct_rating`			TINYINT 		COMMENT 'Phân loại',
	`note`						VARCHAR(255),
	`description`				TEXT,
	`created_at` DATETIME,
	`created_by` VARCHAR(255),
	`updated_at` DATETIME,
	`updated_by` VARCHAR(255),
	PRIMARY KEY (`id`),
	CONSTRAINT fk_eqa_conducts_learner FOREIGN KEY (`learner_id`)
		REFERENCES `#__eqa_learners`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_eqa_conducts_academicyear FOREIGN KEY (`academicyear_id`)
		REFERENCES `#__eqa_academicyears`(`id`)
		ON DELETE RESTRICT,
	INDEX idx_eqa_conducts_term(`term`),
	UNIQUE(`learner_id`,`academicyear_id`,`term`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Đánh giá rèn luyện';