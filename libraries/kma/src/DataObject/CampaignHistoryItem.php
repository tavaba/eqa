<?php
namespace Kma\Library\Kma\DataObject;

defined('_JEXEC') or die();

/**
 * DTO lưu thông tin một campaign trong lịch sử gửi email.
 *
 * Được tạo bởi MailService::getCampaignHistory() — đã bao gồm:
 *   - Dữ liệu raw từ DB (#__kmail_campaigns JOIN #__kmail_templates JOIN #__users)
 *   - Preprocessing: status_label, status_badge (từ MailCampaignStatus enum)
 *   - created_at_local: đã convert UTC → Local Time
 *
 * Được truyền vào ViewHelper::printCampaignHistory() để render HTML.
 *
 * @since 1.0.3
 */
class CampaignHistoryItem
{
    /** ID của campaign. */
    public int $id = 0;

    /** Giá trị int của MailCampaignStatus enum. */
    public int $status = 0;

    /** Nhãn hiển thị trạng thái, vd: 'Đang chờ', 'Hoàn thành'. */
    public string $statusLabel = '';

    /** CSS class của badge Bootstrap, vd: 'bg-success', 'bg-secondary'. */
    public string $statusBadge = 'bg-secondary';

    /** Tổng số email trong queue của campaign. */
    public int $totalCount = 0;

    /** Số email đã gửi thành công. */
    public int $sentCount = 0;

    /** Số email thất bại. */
    public int $failedCount = 0;

    /** Tên template đã dùng. */
    public string $templateTitle = '';

    /** Tên người tạo campaign. */
    public string $creatorName = '';

    /**
     * Thời gian tạo campaign — đã convert từ UTC sang Local Time.
     * Định dạng: 'YYYY-MM-DD HH:MM:SS' theo timezone của server.
     */
    public string $createdAtLocal = '';
}
