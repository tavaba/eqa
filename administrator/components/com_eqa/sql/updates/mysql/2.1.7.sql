-- =============================================================================
-- Version  : 2.1.7
-- Date     : 04/08/2026
--
-- Tên gọi "phân biệt" cho môn học và môn thi
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
