<?php
namespace Kma\Component\Eqa\Administrator\Enum;
use Kma\Library\Kma\Enum\EnumHelper;

defined('_JEXEC') or die();

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Enum
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 */


/**
 * Ngữ cảnh (context) xác định đối tượng người nhận của một chiến dịch email.
 *
 * Giá trị INT được lưu vào cột `context_type` (TINYINT) của bảng
 * `#__eqa_mail_templates` và `#__eqa_mail_campaigns`.
 *
 * Các placeholder khả dụng theo từng context:
 *
 * | Context      | Placeholder bổ sung (ngoài {learner_name}, {learner_code}) |
 * |--------------|-------------------------------------------------------------|
 * | Exam         | {exam_name}, {exam_date}, {exam_time}, {room_name}          |
 * | ExamSeason   | {examseason_name}                                           |
 * | Group        | (không có thêm)                                             |
 * | Course       | (không có thêm)                                             |
 * | Manual       | (không có thêm)                                             |
 *
 * @since 2.0.8
 */
enum MailContextType: int
{
	use EnumHelper;

    /** Gửi cho tất cả thí sinh của một môn thi (exam). */
    case Exam       = 1;

    /** Gửi cho tất cả thí sinh trong một kỳ thi (exam season). */
    case ExamSeason = 2;

    /** Gửi cho tất cả người học thuộc một lớp hành chính (group). */
    case Group      = 3;

    /** Gửi cho tất cả người học thuộc một khóa học (course). */
    case Course     = 4;

    /**
     * Gửi cho một danh sách người nhận được chỉ định thủ công.
     * Danh sách learner_id được lưu trong cột `recipient_filter` (JSON)
     * của bảng `#__eqa_mail_campaigns`.
     */
    case Manual     = 5;

    // =========================================================================
    // Methods
    // =========================================================================

    /**
     * Trả về nhãn hiển thị tiếng Việt của context.
     *
     * @return string
     * @since 2.1.0
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Exam       => 'Môn thi',
            self::ExamSeason => 'Kỳ thi',
            self::Group      => 'Lớp hành chính',
            self::Course     => 'Khóa học',
            self::Manual     => 'Danh sách thủ công',
        };
    }

    /**
     * Trả về danh sách placeholder hợp lệ cho context này.
     * Bao gồm cả các placeholder chung (luôn có) và placeholder riêng của context.
     *
     * @return string[]
     * @since 2.1.0
     */
    public function getAvailablePlaceholders(): array
    {
        // Placeholder chung — luôn có ở mọi context
        $common = [
            '{learner_name}',
            '{learner_code}',
        ];

        // Placeholder riêng theo context
        $specific = match ($this) {
            self::Exam => [
                '{exam_name}',
                '{exam_date}',
                '{exam_time}',
                '{room_name}',
            ],
            self::ExamSeason => [
                '{examseason_name}',
            ],
            self::Group, self::Course, self::Manual => [],
        };

        return array_merge($common, $specific);
    }
}
