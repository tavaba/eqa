<?php
namespace Kma\Library\Kma\Enum;
defined('_JEXEC') or die();

/**
 * @package     Kma.Library.Kma
 * @subpackage  Enum
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 */


/**
 * Loại đối tượng nhận email trong hàng đợi gửi mail (#__eqa_mail_queue).
 *
 * Giá trị INT được lưu vào cột `recipient_type` (TINYINT).
 * Cột `recipient_id` lưu ID tương ứng trong bảng của từng loại:
 *   - Learner  → #__eqa_learners.id
 *   - Employee → #__eqa_employees.id  (dùng trong tương lai)
 *   - External → NULL (chỉ dùng recipient_email)
 *
 * @since 1.0.3
 */
enum MailRecipientType: int
{
	use EnumHelper;

    /** Người học (học viên / sinh viên). */
    case Learner  = 0;

    /** Người lao động (cán bộ / giảng viên / nhân viên). */
    case Employee = 1;

    /**
     * Địa chỉ email bên ngoài hệ thống (không có bản ghi trong CSDL nội bộ).
     * `recipient_id` = NULL khi dùng loại này.
     */
    case External = 99;

    // =========================================================================
    // Methods
    // =========================================================================

    /**
     * Trả về nhãn hiển thị tiếng Việt.
     *
     * @return string
     * @since 1.0.3
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Learner  => 'Người học',
            self::Employee => 'Người lao động',
            self::External => 'Email ngoài hệ thống',
        };
    }
}
