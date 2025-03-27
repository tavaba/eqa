ALTER TABLE `#__eqa_learners` ADD `debtor` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Có nợ học phí hay không' AFTER `group_id`;
