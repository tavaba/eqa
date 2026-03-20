-- =============================================================================
-- com_eqa — Schema Update 2.0.5 → 2.0.6
-- =============================================================================
-- Phần 1: Thêm cột created_by vào bảng 'gradecorrections' và 'regradings'
ALTER TABLE `#__eqa_regradings` ADD `created_by` INT UNSIGNED DEFAULT NULL AFTER `status`;
ALTER TABLE `#__eqa_gradecorrections` ADD `created_by` INT UNSIGNED DEFAULT NULL AFTER `status`;

-- Phần 2: Khớp lại cấu trúc bảng trong file install với CSDL ở production server
-- Phần 3: Đổi kiểu INT thành INT UNSIGNED
-- Tất cả thay đổi Phần 2 và Phần 3 đòi hỏi kiểm tra trạng thái
-- hiện tại của CSDL trước khi thực hiện (idempotent), nên được xử lý hoàn
-- toàn bởi script.php (runMigration206) trong bước postflight().
--
-- File này được giữ trống có chủ ý để tránh lỗi "Unknown column" khi Joomla
-- chạy SQL trước postflight() trên các production server có trạng thái cột
-- khác nhau (tuỳ lịch sử nâng cấp).
-- =============================================================================
