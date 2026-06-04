-- =============================================================================
-- Version  : 2.0.9
-- Date     : 2026

-- =============================================================================
-- Bảng #__eqa_subjects
-- ADD 1 cột 'kquestion' (đặt sau 'kassess'): số giờ chuẩn quy đổi trên mỗi đề thi
ALTER TABLE `#__eqa_subjects`
    ADD COLUMN `kquestion` REAL NOT NULL DEFAULT 0
        COMMENT 'Số giờ chuẩn quy đổi cho mỗi đề thi'
        AFTER `kassess`;

-- Bảng #__eqa_exams
-- DROP 1 cột không dùng đến là 'anomaly'
-- ADD 1 cột 'kquestion' (đặt sau 'kassess'): số giờ chuẩn quy đổi trên mỗi đề thi
ALTER TABLE `#__eqa_exams`
    DROP column `anomaly`;
ALTER TABLE `#__eqa_exams`
    ADD COLUMN `kquestion` DOUBLE NOT NULL DEFAULT 0
        COMMENT 'Số giờ chuẩn quy đổi cho mỗi đề thi'
        AFTER `kassess`;

-- Bảng #__eqa_packages`
-- Thêm ràng buộc khóa ngoại cho cột 'exam_id'
ALTER TABLE `#__eqa_packages`
    ADD CONSTRAINT fk_eqa_packages_exam FOREIGN KEY (`exam_id`)
        REFERENCES `#__eqa_exams`(`id`)
        ON DELETE RESTRICT;

-- Bảng #__eqa_exam_learner
-- DROP 1 cột không dùng đến là 'ppaa_status'
ALTER TABLE `#__eqa_exam_learner`
    DROP column `ppaa_status`,
	DROP column `updated_at`,
	DROP column `updated_by`;
	
-- Bảng #__eqa_units
-- DROP 1 cột không dùng đến là 'size'
ALTER TABLE `#__eqa_units`
    DROP column `size`;

-- Bảng #__eqa_class_learner
-- ADD surrogate key
ALTER TABLE `#__eqa_class_learner` 
	ADD `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`);
