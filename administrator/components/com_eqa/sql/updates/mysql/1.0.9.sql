ALTER TABLE `#__eqa_examrooms` ADD CONSTRAINT `eqa_examrooms_fk_examsession` FOREIGN KEY (`examsession_id`) REFERENCES `#__eqa_examsessions`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
