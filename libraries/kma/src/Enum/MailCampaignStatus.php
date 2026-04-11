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
 * Trạng thái của một chiến dịch gửi email (#__eqa_mail_campaigns.status).
 *
 * Vòng đời thông thường:
 *   Pending → Processing → Done
 *   Pending → Cancelled  (hủy trước khi Task Scheduler xử lý)
 *
 * @since 1.0.3
 */
enum MailCampaignStatus: int
{
	use EnumHelper;
    /**
     * Đã tạo queue, chờ Task Scheduler lấy để xử lý.
     * Đây là trạng thái khởi tạo ngay sau khi campaign được tạo.
     */
    case Pending    = 0;

    /**
     * Task Scheduler đang trong quá trình gửi batch.
     * Trạng thái này tránh trường hợp nhiều Task instance cùng
     * xử lý một campaign song song.
     */
    case Processing = 1;

    /**
     * Đã xử lý xong toàn bộ hàng đợi.
     * Điều kiện chuyển sang Done: sent_count + failed_count = total_count.
     * Một số email trong queue có thể vẫn ở trạng thái failed.
     */
    case Done       = 2;

    /**
     * Đã hủy trước khi Task Scheduler xử lý.
     * Các bản ghi pending trong queue tương ứng sẽ không được gửi.
     */
    case Cancelled  = 3;

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
            self::Pending    => 'Chờ gửi',
            self::Processing => 'Đang gửi',
            self::Done       => 'Hoàn tất',
            self::Cancelled  => 'Đã hủy',
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
            self::Pending    => 'bg-secondary',
            self::Processing => 'bg-primary',
            self::Done       => 'bg-success',
            self::Cancelled  => 'bg-danger',
        };
    }

    /**
     * Kiểm tra campaign có thể bị hủy không.
     * Chỉ hủy được khi đang ở trạng thái Pending.
     *
     * @return bool
     * @since 1.0.3
     */
    public function isCancellable(): bool
    {
        return $this === self::Pending;
    }
}
