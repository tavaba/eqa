ALTER TABLE `#__eqa_class_learner` 
	ADD `created_at` DATETIME NULL AFTER `description`,
	ADD `created_by` INT NULL COMMENT 'User ID' AFTER `created_at`, 
	ADD `updated_at` DATETIME NULL AFTER `created_by`, 
	ADD `updated_by` INT NULL COMMENT 'User ID' AFTER `updated_at`;
ALTER TABLE `#__eqa_regradings` 
	ADD `requested_by` INT NULL COMMENT 'User ID' AFTER `status`;
