-- =============================================================================
-- Version  : 2.1.7
-- Date     : 04/08/2026
--
-- Tên gọi "phân biệt" cho môn học và môn thi
-- Năm đưa vào sử dụng của môn học
--
-- Bối cảnh: nhiều môn học có cùng tên chính thức nhưng khác mã và khác nội dung
-- (ví dụ "Đồ án 1" ở các chương trình đào tạo khác nhau). Trên giao diện quản trị
-- điều này gây nhầm lẫn.
--
-- Giải pháp: bổ sung cột `display_name` (NULL-able) vào #__eqa_subjects và
-- #__eqa_exams.
--   - NULL  → dùng `name` (tên chính thức). Đây là trạng thái mặc định của
--             toàn bộ dữ liệu hiện có, do đó KHÔNG cần migrate dữ liệu.
--   - Khác NULL → giá trị này được dùng thay cho `name` trên giao diện quản trị.
--
-- Quy ước đọc thống nhất trong mã nguồn: COALESCE(display_name, name),
-- đóng gói trong DatabaseHelper::displayNameExpr().
--
-- `name` giữ nguyên ngữ nghĩa "tên chính thức" và tiếp tục được dùng cho:
--   - mọi hồ sơ/biểu mẫu thi xuất ra (Excel, Word)
--   - toàn bộ giao diện frontend (người học, giảng viên)
--
-- LƯU Ý VẬN HÀNH: backup CSDL trước khi update; DDL của MySQL không nằm trong
-- transaction nên không thể rollback tự động.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Môn học
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_subjects`
    ADD COLUMN `display_name` VARCHAR(255) NULL
        COMMENT 'Tên phân biệt, chỉ dùng cho giao diện quản trị; NULL = dùng name'
        AFTER `name`;
ALTER TABLE `#__eqa_subjects`
    ADD COLUMN `start_year` INT UNSIGNED NULL
        COMMENT 'Năm đưa vào sử dụng môn học; NULL = không xác định'
        AFTER `credits`;

-- -----------------------------------------------------------------------------
-- 2. Môn thi
--    Giá trị được copy từ môn học tại thời điểm tạo môn thi, giống như cách cột
--    `name` đang được copy. Nhờ vậy môn thi giữ được snapshot lịch sử: đổi tên
--    phân biệt của môn học về sau không làm thay đổi các kỳ thi đã tổ chức.
-- -----------------------------------------------------------------------------
ALTER TABLE `#__eqa_exams`
    ADD COLUMN `display_name` VARCHAR(255) NULL
        COMMENT 'Tên phân biệt, copy từ môn học khi tạo môn thi; NULL = dùng name'
        AFTER `name`;

-- =============================================================================
-- 3. Thiết kế lại cơ chế quản lý trạng thái: 2 trạng thái -> 4 trạng thái
-- =============================================================================
--
-- Bối cảnh: cột `published` được khai báo BOOLEAN nên chỉ biểu diễn được
-- bật/tắt. Hệ quả là các đối tượng danh mục đã thôi sử dụng (ví dụ môn học của
-- chương trình đào tạo cũ) không có cách nào để vừa ẩn khỏi dropdown chọn dữ
-- liệu mới, vừa giữ được dữ liệu lịch sử.
--
-- Giải pháp: đổi tên cột thành `state` và đổi kiểu sang TINYINT, dùng đúng bộ
-- mã trạng thái của Joomla core (#__content.state):
--     1  = Đang dùng   (Published)
--     0  = Tạm ngừng   (Unpublished)
--     2  = Đã lưu trữ  (Archived)
--    -2  = Thùng rác   (Trashed)
--
-- Phân nhóm thực thể:
--   - Nhóm DANH MỤC (12 bảng): dùng đủ 4 trạng thái.
--   - Nhóm VẬN HÀNH (6 bảng) : đổi tên cột cho nhất quán nhưng chỉ nhận 0/1,
--                              vì các thực thể này đã có vòng đời nghiệp vụ
--                              riêng theo kỳ thi.
--
-- KHÔNG MẤT DỮ LIỆU: MySQL lưu BOOLEAN dưới dạng TINYINT(1) có dấu, nên toàn bộ
-- giá trị 0/1 hiện có được giữ nguyên ý nghĩa sau khi đổi kiểu.
--
-- Tên cột `state` được đăng ký alias 'published' trong Kma\Library\Kma\Table\Table
-- để Joomla core (Table::publish(), AdminModel::publish()) tiếp tục hoạt động.
--
-- LƯU Ý VẬN HÀNH: backup CSDL trước khi update; DDL của MySQL không nằm trong
-- transaction nên không thể rollback tự động.
-- -----------------------------------------------------------------------------

-- 3.1. Nhóm DANH MỤC — đủ 4 trạng thái
ALTER TABLE `#__eqa_campuses`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_buildings`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_rooms`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_units`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_employees`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_specialities`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_programs`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_courses`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_groups`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_learners`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_cohorts`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';
ALTER TABLE `#__eqa_subjects`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng, 2=Đã lưu trữ, -2=Thùng rác';

-- 3.2. Nhóm VẬN HÀNH — chỉ 2 trạng thái
ALTER TABLE `#__eqa_classes`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng';
ALTER TABLE `#__eqa_examseasons`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng';
ALTER TABLE `#__eqa_assessments`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng';
ALTER TABLE `#__eqa_examsessions`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng';
ALTER TABLE `#__eqa_exams`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng';
ALTER TABLE `#__eqa_examrooms`
    CHANGE COLUMN `published` `state` TINYINT NOT NULL DEFAULT 1
        COMMENT '1=Đang dùng, 0=Tạm ngừng';
