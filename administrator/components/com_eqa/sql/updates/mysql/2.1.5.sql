-- =============================================================================
-- Version  : 2.1.5
-- Date     : 08/07/2026
-- Bổ sung danh sách cán bộ giám sát (giám sát ngoài các phòng thi) cho ca thi
-- Sửa lại COMMENT của cột 'monitor_ids' cho đúng nghĩa (chỉ CBCT, không phải CBGS)
-- =============================================================================

-- Bảng #__eqa_examsessions
-- Sửa lại COMMENT của cột 'monitor_ids' cho đúng nghĩa (đăng ký CBCT, CBGS)
ALTER TABLE `#__eqa_examsessions`
    MODIFY COLUMN `monitor_ids` TEXT
        COMMENT 'CSV danh sách đăng ký (id của) CBCT, CBGS';
-- Sửa lại COMMENT của cột 'examiner_ids' cho đúng nghĩa (đăng ký CBCTChT)
ALTER TABLE `#__eqa_examsessions`
    MODIFY COLUMN `examiner_ids` TEXT
        COMMENT 'CSV danh sách đăng ký (id của) CBCTChT';
-- Bổ sung danh sách phân công CBGS cho ca thi (trong số đăng ký CBCT, CBGS)
ALTER TABLE `#__eqa_examsessions`
    ADD COLUMN `supervisor_ids` TEXT NULL
        COMMENT 'CSV danh sách phân công (id của) CBGS (trong số đăng ký CBCT, CBGS)'
        AFTER `examiner_ids`;
