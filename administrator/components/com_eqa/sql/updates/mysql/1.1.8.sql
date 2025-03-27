ALTER TABLE `#__eqa_regradings` ADD `requested_at` DATETIME NULL AFTER `status`;
ALTER TABLE `#__eqa_regradings` ADD `handled_by` VARCHAR(255) NULL AFTER `requested_at`;
ALTER TABLE `#__eqa_regradings` ADD `handled_at` DATETIME NULL AFTER `handled_by`;

ALTER TABLE `#__eqa_gradecorrections` ADD `requested_at` DATETIME NULL AFTER `status`;
ALTER TABLE `#__eqa_gradecorrections` ADD `handled_by` VARCHAR(255) NULL AFTER `requested_at`;
ALTER TABLE `#__eqa_gradecorrections` ADD `handled_at` DATETIME NULL AFTER `handled_by`;
