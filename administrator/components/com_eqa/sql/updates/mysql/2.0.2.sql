/*
 * COM_EQA update 2.0.1 → 2.0.2
 *
 * Thay đổi bảng #__eqa_secondattempts:
 *   - Thêm cột `payment_amount` DOUBLE NOT NULL DEFAULT 0
 *     (số tiền lệ phí thi lần 2, đơn vị VNĐ; 0 = miễn phí)
 *
 * Lưu ý: Cột `payment_required` CHƯA bị xóa ở bước này.
 * Nó sẽ được xóa trong postflight() sau khi đã migrate dữ liệu sang
 * `payment_amount` (xem script.php → runMigration202()).
 */

ALTER TABLE `#__eqa_secondattempts`
    ADD COLUMN `payment_amount` DOUBLE NOT NULL DEFAULT 0
        COMMENT 'Số tiền lệ phí thi lần 2 (VNĐ); 0 = miễn phí'
        AFTER `last_conclusion`;
