-- Tắt kiểm tra khóa ngoại để xóa sạch không bị lỗi ràng buộc
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `#__eqa_mail_queue`;
DROP TABLE IF EXISTS `#__eqa_mail_campaigns`;
DROP TABLE IF EXISTS `#__eqa_mail_templates`;
DROP TABLE IF EXISTS `#__eqa_assessment_learner`;
DROP TABLE IF EXISTS `#__eqa_secondattempts`;
DROP TABLE IF EXISTS `#__eqa_conducts`;
DROP TABLE IF EXISTS `#__eqa_mmproductions`;
DROP TABLE IF EXISTS `#__eqa_class_learner`;
DROP TABLE IF EXISTS `#__eqa_cohort_learner`;
DROP TABLE IF EXISTS `#__eqa_cohorts`;
DROP TABLE IF EXISTS `#__eqa_regradings`;
DROP TABLE IF EXISTS `#__eqa_gradecorrections`;
DROP TABLE IF EXISTS `#__eqa_papers`;
DROP TABLE IF EXISTS `#__eqa_packages`;
DROP TABLE IF EXISTS `#__eqa_exam_learner`;
DROP TABLE IF EXISTS `#__eqa_stimulations`;
DROP TABLE IF EXISTS `#__eqa_learners`;
DROP TABLE IF EXISTS `#__eqa_groups`;
DROP TABLE IF EXISTS `#__eqa_courses`;
DROP TABLE IF EXISTS `#__eqa_programs`;
DROP TABLE IF EXISTS `#__eqa_specialities`;
DROP TABLE IF EXISTS `#__eqa_examrooms`;
DROP TABLE IF EXISTS `#__eqa_exams`;
DROP TABLE IF EXISTS `#__eqa_rooms`;
DROP TABLE IF EXISTS `#__eqa_buildings`;
DROP TABLE IF EXISTS `#__eqa_examsessions`;
DROP TABLE IF EXISTS `#__eqa_examseasons`;
DROP TABLE IF EXISTS `#__eqa_assessments`;
DROP TABLE IF EXISTS `#__eqa_classes`;
DROP TABLE IF EXISTS `#__eqa_subjects`;
DROP TABLE IF EXISTS `#__eqa_employees`;
DROP TABLE IF EXISTS `#__eqa_units`;
DROP TABLE IF EXISTS `#__eqa_logs`;

-- Bật lại kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 1;