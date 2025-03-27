/*
 * COM_EQA update 1.0.3 to 1.0.4
 */
 
 /* Change collation for sum name columns. This is important for sorting (ordering) operation while query data */
ALTER TABLE `#__eqa_units` CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên đầy đủ, ví dụ: Khoa An toàn thông tin';
ALTER TABLE `#__eqa_employees` CHANGE `firstname` `firstname` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên';
ALTER TABLE `#__eqa_employees` CHANGE `lastname` `lastname` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Họ Đệm';
ALTER TABLE `#__eqa_learners` CHANGE `firstname` `firstname` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên';
ALTER TABLE `#__eqa_learners` CHANGE `lastname` `lastname` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Họ Đệm';
ALTER TABLE `#__eqa_specialities` CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên ngành đào tạo';
ALTER TABLE `#__eqa_programs` CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên của chương trình đào tạo';
ALTER TABLE `#__eqa_classes` CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên lớp học phần';
ALTER TABLE `#__eqa_examseasons` CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên đợt thi';
ALTER TABLE `#__eqa_exams` CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vietnamese_ci NOT NULL COMMENT 'Tên môn thi';


/* Table `#__eqa_class_learner` */
ALTER TABLE `#__eqa_class_learner` ADD `ntaken` TINYINT NOT NULL DEFAULT '0' COMMENT 'Số lượt đã thi' AFTER `allowed`;
ALTER TABLE `#__eqa_class_learner` ADD `expired` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Hết lượt thi' AFTER `ntaken`;


/* Table `#__eqa_examseasons`  */
ALTER TABLE `#__eqa_examseasons` ADD `nexamsession` INT NOT NULL DEFAULT '0' COMMENT 'Số lượng ca thi' AFTER `nexam`;

/* Table `#__eqa_exams` */
ALTER TABLE `#__eqa_exams` DROP `nexaminee`;


/* Table `#__eqa_examsessions` */
ALTER TABLE `#__eqa_examsessions` DROP `nroom`;


/* 
 *  Recreate `#__eqa_examrooms` 
 * (This required delete `#__eqa_exam_learner` first, and then recreate it)
*/
DROP TABLE IF EXISTS `#__eqa_exam_learner`;
DROP TABLE IF EXISTS `#__eqa_examrooms`;
CREATE TABLE `#__eqa_examrooms`(
    `id` INT AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL COMMENT 'Tên phòng thi',
	`room_id` INT NOT NULL COMMENT 'Khóa ngoại: Phòng học (địa điểm thi)',
	`examsession_id` INT COMMENT 'Khóa ngoại: ca thi',
	`nmonitor` INT COMMENT 'Số lượng CBCT',
	`nexaminer` INT COMMENT 'Số lượng CBCTChT',
	`monitor1_id` INT COMMENT 'CBCT 1',
	`monitor2_id` INT COMMENT 'CBCT 2',
	`monitor3_id` INT COMMENT 'CBCT 3',
	`examiner1_id` INT COMMENT 'CBCTChT 1',
	`examiner2_id` INT COMMENT 'CBCTChT 2',
	`anomaly` TEXT COMMENT 'Bất thường phòng thi',	
	`description` TEXT,
	`published` BOOLEAN NOT NULL DEFAULT TRUE,
	`ordering` INT NOT NULL DEFAULT 0,
	`created_at` DATETIME,
	`created_by` VARCHAR(255),
	`updated_at` DATETIME,
	`updated_by` VARCHAR(255),
	`checked_out` INT DEFAULT NULL,
	`checked_out_time` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
	UNIQUE(`room_id`, `examsession_id`),
	FOREIGN KEY (`room_id`)
		REFERENCES `#__eqa_rooms`(`id`)
		ON DELETE RESTRICT
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Phòng thi (vật lý và logic)';
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
