ALTER TABLE `#__eqa_examseasons` DROP `nexam`;
ALTER TABLE `#__eqa_examseasons` DROP `nexamsession`;

UPDATE `#__eqa_class_learner`
SET `pam1`=-25, `pam2`=-25, `pam`=-25, `description` = NULL
WHERE `description` LIKE 'N25%';

UPDATE `#__eqa_class_learner`
SET `pam1`=-100, `pam2`=-100, `pam`=-100, `description` = NULL
WHERE `description` LIKE 'N100%';

UPDATE `#__eqa_class_learner`
SET `pam1`=-10, `pam2`=-10, `pam`=-10, `description` = NULL
WHERE `description` LIKE 'TK%';