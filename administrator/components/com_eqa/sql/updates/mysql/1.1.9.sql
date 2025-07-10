ALTER TABLE `#__eqa_gradecorrections` DROP FOREIGN KEY `fk_eqa_gradecorrections_examiner1`;
ALTER TABLE `#__eqa_gradecorrections` DROP FOREIGN KEY `fk_eqa_gradecorrections_examiner2`;
ALTER TABLE `#__eqa_gradecorrections` DROP `examiner1_id`;
ALTER TABLE `#__eqa_gradecorrections` DROP `examiner2_id`;
ALTER TABLE `#__eqa_gradecorrections` DROP `result`;
ALTER TABLE `#__eqa_gradecorrections` ADD `reviewer_id` INT NULL DEFAULT NULL COMMENT 'Người xử lý' AFTER `handled_at`;
ALTER TABLE `#__eqa_gradecorrections` ADD CONSTRAINT `fk_eqa_gradecorrections_reviewer` FOREIGN KEY (`reviewer_id`) 
	REFERENCES `#__eqa_employees`(`id`) 
	ON DELETE RESTRICT 
	ON UPDATE RESTRICT;
ALTER TABLE `#__eqa_gradecorrections` ADD `changed` BOOLEAN COMMENT 'Có thay đổi điểm sau xử lý yêu cầu hay không' AFTER `reviewer_id`;
ALTER TABLE `#__eqa_gradecorrections` ADD `updated_at` DATETIME AFTER `changed`;
ALTER TABLE `#__eqa_gradecorrections` ADD `updated_by` VARCHAR(255) AFTER `updated_at`;
