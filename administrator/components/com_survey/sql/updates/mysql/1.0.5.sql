-- =============================================================================
-- Version  : 1.0.5
-- Date     : 14/08/2026
--
-- Thiết kế lại cơ chế quản lý trạng thái: 2 trạng thái -> 4 trạng thái
-- =============================================================================
--
-- Đồng bộ với thay đổi tương ứng trong lib_kma 1.0.5 và com_eqa 2.1.7.
--
-- Bộ mã trạng thái thống nhất (bám sát #__content.state của Joomla core):
--     1  = Đang dùng   (Published)
--     0  = Tạm ngừng   (Unpublished)
--     2  = Đã lưu trữ  (Archived)
--    -2  = Thùng rác   (Trashed)
--
-- Trước thay đổi này, com_survey dùng SONG SONG hai tên cột cho cùng một khái
-- niệm: `state` (bảng campaigns, surveys) và `published` (5 bảng còn lại).
-- Nay thống nhất về `state` trên toàn bộ.
--
-- KHÔNG MẤT DỮ LIỆU: MySQL lưu BOOLEAN dưới dạng TINYINT(1) có dấu nên toàn bộ
-- giá trị 0/1 hiện có giữ nguyên ý nghĩa sau khi đổi kiểu.
--
-- Tên cột `state` được đăng ký alias 'published' trong Kma\Library\Kma\Table\Table
-- để Joomla core (Table::publish(), AdminModel::publish()) tiếp tục hoạt động.
-- Nhờ vậy, hai lời gọi setColumnAlias() trong SurveyTable và CampaignTable đã
-- được gỡ bỏ.
--
-- LƯU Ý VẬN HÀNH: backup CSDL trước khi update; DDL của MySQL không nằm trong
-- transaction nên không thể rollback tự động.
-- -----------------------------------------------------------------------------

-- 1. Năm bảng đang dùng cột `published`
--    Index của hai bảng dưới đây phải bỏ TRƯỚC khi đổi tên cột, rồi tạo lại.
ALTER TABLE `#__survey_units`
    DROP INDEX `idx_units_published`;
ALTER TABLE `#__survey_units`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__survey_units`
    ADD INDEX `idx_units_state` (`state`);

ALTER TABLE `#__survey_respondents`
    DROP INDEX `idx_respondents_published`;
ALTER TABLE `#__survey_respondents`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__survey_respondents`
    ADD INDEX `idx_respondents_state` (`state`);

ALTER TABLE `#__survey_respondentgroups`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__survey_forms`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__survey_topics`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';

-- 2. Hai bảng vốn đã dùng cột `state`: chỉ chuẩn hóa kiểu và chú thích
ALTER TABLE `#__survey_campaigns`
    MODIFY COLUMN `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__survey_surveys`
    MODIFY COLUMN `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
