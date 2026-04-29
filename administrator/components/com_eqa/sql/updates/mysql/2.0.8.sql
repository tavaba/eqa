-- =============================================================================
-- Migration: Hệ thống gửi email thông báo cho com_eqa
-- Version  : 2.0.8
-- Date     : 2026
-- Bảng #__eqa_examrooms: xóa 2 cột không dùng đến là 'nmonitor' và 'nexaminer'

-- =============================================================================

-- Bảng #__eqa_examrooms
-- DROP 2 cột không dùng đến là 'nmonitor' và 'nexaminer'
ALTER TABLE `#__eqa_examrooms`
    DROP column `nmonitor`;
ALTER TABLE `#__eqa_examrooms`
    DROP column `nexaminer`;
