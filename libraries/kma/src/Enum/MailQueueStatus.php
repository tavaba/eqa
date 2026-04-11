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
 * Trạng thái của một email trong hàng đợi (#__eqa_mail_queue.status).
 *
 * Vòng đời thông thường:
 *   Pending → Sent    (gửi thành công)
 *   Pending → Pending (retry, attempts tăng dần, last_attempt_at được cập nhật)
 *   Pending → Failed  (sau khi attempts >= MAX_ATTEMPTS mà vẫn thất bại)
 *
 * @since 1.0.3
 */
enum MailQueueStatus: int
{
	use EnumHelper;

    /**
     * Chờ gửi (hoặc đang chờ retry).
     * Task Scheduler chỉ lấy các bản ghi có status = Pending để xử lý.
     * Với bản ghi đang retry (attempts > 0), Task Scheduler cần kiểm tra
     * last_attempt_at để đảm bảo đã chờ đủ khoảng thời gian retry.
     */
    case Pending = 0;

    /**
     * Đã gửi thành công.
     * Trường sent_at được set tại thời điểm gửi thành công.
     */
    case Sent    = 1;

    /**
     * Gửi thất bại sau khi đã thử đủ số lần tối đa (attempts >= MAX_ATTEMPTS).
     * Trường error_message lưu thông báo lỗi của lần thử cuối cùng.
     * Task Scheduler sẽ không retry thêm các bản ghi có status = Failed.
     */
    case Failed  = 2;

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
            self::Pending => 'Chờ gửi',
            self::Sent    => 'Đã gửi',
            self::Failed  => 'Thất bại',
        };
    }

    /**
     * Trả về CSS class Bootstrap badge tương ứng với trạng thái.
     *
     * @return string
     * @since 1.0.3
     */
    public function getBadgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::Sent    => 'bg-success',
            self::Failed  => 'bg-danger',
        };
    }

    /**
     * Kiểm tra email có thể được retry không.
     * Chỉ có thể retry khi đang ở trạng thái Pending với attempts > 0.
     * (Kiểm tra attempts thực hiện ở tầng gọi, không phải ở đây.)
     *
     * @return bool
     * @since 1.0.3
     */
    public function isRetryable(): bool
    {
        return $this === self::Pending;
    }
}
