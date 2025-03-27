ALTER TABLE `#__eqa_classes` ADD `npam` INT NOT NULL DEFAULT '0' COMMENT 'Số lượng HVSV có ĐQT' AFTER `size`;
ALTER TABLE `#__eqa_class_learner` ADD `description` VARCHAR(255) NULL DEFAULT NULL AFTER `expired`;
ALTER TABLE `#__eqa_exam_learner` ADD UNIQUE(`exam_id`, `code`);
