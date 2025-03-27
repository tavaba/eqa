/*
 * COM_EQA update 1.0.2 to 1.0.3
 */

ALTER TABLE `#__eqa_academicyears` 		ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_buildings` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_rooms` 				ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_units` 				ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_employees` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_specialities` 		ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_programs` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_courses` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_groups` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_learners` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_subjects` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_classes` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_examseasons`		ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_examsessions`		ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_exams`				ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_examrooms`			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_packages` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_papers` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;
ALTER TABLE `#__eqa_reviews` 			ADD `check_out` INT NULL DEFAULT NULL AFTER `updated_by`, ADD `check_out_time` DATETIME NULL DEFAULT NULL AFTER `check_out`;



ALTER TABLE `#__eqa_class_learner` ADD `pam1` FLOAT NULL COMMENT 'Điểm QT TP1' AFTER `learner_id`, ADD `pam2` FLOAT NULL COMMENT 'Điểm QT TP2' AFTER `pam1`, ADD `pam` FLOAT NULL COMMENT 'Điểm QT' AFTER `pam2`, ADD `allowed` BOOLEAN NOT NULL DEFAULT FALSE AFTER `pam`;


ALTER TABLE `#__eqa_examseasons` CHANGE `status` `completed` TINYINT NOT NULL DEFAULT '0' COMMENT 'Trạng thái hoàn thành';
ALTER TABLE `#__eqa_examseasons` CHANGE `isdefault` `default` TINYINT NOT NULL DEFAULT '0' COMMENT 'Là kỳ thi hiện tại (mặc định)';
ALTER TABLE `#__eqa_examseasons` ADD `nexam` INT NULL DEFAULT '0' COMMENT 'Số lượng môn thi' AFTER `attempt`;


ALTER TABLE `#__eqa_exams` CHANGE `season_id` `examseason_id` INT NOT NULL COMMENT 'Khóa ngoại: Kỳ thi';
ALTER TABLE `#__eqa_exams` DROP INDEX `season_id`, ADD INDEX `examseason_id` (`examseason_id`) USING BTREE;
ALTER TABLE `#__eqa_exams` ADD `name` VARCHAR(255) NOT NULL COMMENT 'Tên môn thi' AFTER `subject_id`;
ALTER TABLE `#__eqa_exams` ADD `usetestbank` BOOLEAN NOT NULL COMMENT 'Có sử dụng ngân hàng đề hay không' AFTER `status`;
ALTER TABLE `#__eqa_exams` CHANGE `questionsdeadline` `questiondeadline` DATE NULL DEFAULT NULL COMMENT 'Thời hạn bàn giao đề thi (nếu có)';
ALTER TABLE `#__eqa_exams` CHANGE `questionsender` `questionsender_id` INT NULL DEFAULT NULL COMMENT 'Khóa ngoại: người giao đề thi (nếu có)';
ALTER TABLE `#__eqa_exams` CHANGE `questionauthor` `questionauthor_id` INT NULL DEFAULT NULL COMMENT 'Khóa ngoại: người ra đề thi (nếu có)';
ALTER TABLE `#__eqa_exams` CHANGE `questionnumber` `nquestion` INT(11) NULL DEFAULT NULL COMMENT 'Số lượng đề thi để tính sản lượng (nếu có)';
ALTER TABLE `#__eqa_exams` ADD `nexaminee` INT NOT NULL COMMENT 'Số lượng thí sinh' AFTER `nquestion`;
ALTER TABLE `#__eqa_exams` ADD CONSTRAINT FOREIGN KEY (`questionsender_id`) REFERENCES `#__eqa_employees`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT; 
ALTER TABLE `#__eqa_exams` ADD CONSTRAINT FOREIGN KEY (`questionauthor_id`) REFERENCES `#__eqa_employees`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;


DROP TABLE `#__eqa_results`;
CREATE TABLE `#__eqa_exam_learner`(
    `exam_id` INT NOT NULL COMMENT 'Khóa ngoại: môn thi',
	`learner_id` INT NOT NULL COMMENT 'Khóa ngoại: học viên, sinh viên',
	`class_id` INT COMMENT 'Khóa ngoại: lớp học phần',
	`attempt` TINYINT COMMENT 'Lần thi: (1) Thi lần 1, (2) Thi lần 2',
	`examroom_id` INT COMMENT 'FK: phòng thi',
	`code` INT COMMENT 'Số báo danh',
	`penalty` TINYINT NOT NULL DEFAULT 0 COMMENT 'Xử lý: None (0), Trừ 25%; Trừ 50%; Đình chỉ thi; Vắng thi; Hoãn thi; Hủy bài và thi lại',
	`anomaly` TEXT COMMENT 'Mô tả tình huống bất thường',
	`mark_orig` REAL COMMENT 'Điểm thi KTHP (chấm lần 1, chưa xử lý kỷ luật nếu có)',
	`ppaa` TINYINT NOT NULL DEFAULT 0 COMMENT 'Post-Primary Assessment Action',
	`mark_ppaa` REAL COMMENT 'Điểm thi KTHP sau phúc khảo (chưa xử lý kỷ luật nếu có)',
	`mark_final` REAL COMMENT 'Điểm thi KTHP sau khi phúc khảo và trừ kỷ luật nếu có',
	`module_mark` REAL COMMENT 'Điểm HP; nếu là thi lần 2 thì đã áp dụng giới hạn điểm thi lần 2',
	`module_grade` CHAR(2) COMMENT 'Điểm HP bằng chữ',
	`conclusion` TINYINT COMMENT 'Kết luận (qua, làm lại bài thi, phải thi lại, phải học lại...); định nghĩa bằng constants',
	`description` TEXT,
	`created_at` DATETIME,
	`created_by` VARCHAR(255),
	`updated_at` DATETIME,
	`updated_by` VARCHAR(255),
	UNIQUE (`exam_id`,`learner_id`),
	FOREIGN KEY (`exam_id`)
		REFERENCES `#__eqa_exams`(`id`)
		ON DELETE RESTRICT,
	FOREIGN KEY (`learner_id`)
		REFERENCES `#__eqa_learners`(`id`)
		ON DELETE RESTRICT,
	FOREIGN KEY (`class_id`)
		REFERENCES `#__eqa_classes`(`id`)
		ON DELETE RESTRICT,
	FOREIGN KEY (`examroom_id`)
		REFERENCES `#__eqa_examrooms`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Kết quả thi của thí sinh';
