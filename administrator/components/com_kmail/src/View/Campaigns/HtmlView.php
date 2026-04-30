<?php
namespace Kma\Component\Kmail\Administrator\View\Campaigns;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Enum\MailCampaignStatus;
use Kma\Library\Kma\Enum\MailContextType;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Model\MailCampaignsModel;
use Kma\Library\Kma\View\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutData;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * HtmlView danh sách và delivery log chiến dịch email.
 *
 * Là view duy nhất quản lý campaigns — không còn base class trừu tượng
 * trong lib_kma vì không có component nào khác cần kế thừa.
 *
 * Hỗ trợ 2 layout:
 *   - default : Danh sách campaign với filter, tiến độ gửi, nút Hủy
 *   - log     : Delivery log chi tiết (queue items) của một campaign
 *
 * @since 1.0.0
 */
class HtmlView extends ItemsHtmlView
{
    // =========================================================================
    // Cấu hình cột — layout default
    // =========================================================================

    /**
     * Cấu hình cột cho layout default.
     * View này là read-only → checkbox chỉ dùng cho action Hủy.
     *
     * @since 1.0.0
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $fields = new ListLayoutItemFields();

        $fields->sequence        = ListLayoutItemFields::defaultFieldSequence();
        $fields->check           = ListLayoutItemFields::defaultFieldCheck();
        $fields->customFieldset1 = [];

        // Thời gian tạo (UTC → Local Time sau preprocessing)
        $fields->customFieldset1[] = new ListLayoutItemFieldOption(
            'created_at_local', 'Thời gian', true, false, 'text-nowrap'
        );

        // Người tạo
        $f           = new ListLayoutItemFieldOption('creator_display', 'Người tạo', false, false, '');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Ngữ cảnh (context_type label + context_label từ model)
        $f           = new ListLayoutItemFieldOption('context_display', 'Ngữ cảnh', false, false, '');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Tên template đã dùng
        $fields->customFieldset1[] = new ListLayoutItemFieldOption(
            'template_title', 'Template', false, false, ''
        );

        // Tiến độ gửi (progress bar)
        $f           = new ListLayoutItemFieldOption('progress_html', 'Tiến độ', false, false, 'text-center');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Trạng thái (badge)
        $f           = new ListLayoutItemFieldOption('status_html', 'Trạng thái', true, false, 'text-center');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Nút xem log
        $f           = new ListLayoutItemFieldOption('actions_html', '', false, false, 'text-end text-nowrap');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        $this->itemFields = $fields;
    }

    // =========================================================================
    // Chuẩn bị dữ liệu — layout default
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout default.
     *
     * @throws Exception
     * @since  1.0.0
     */
    protected function prepareDataForLayoutDefault(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_kmail')) {
            throw new Exception('Bạn không có quyền thực hiện chức năng này.', 403);
        }

        parent::prepareDataForLayoutDefault();

        /** @var MailCampaignsModel $model */
        $model = $this->getModel();
        $model->enrichItems($this->layoutData->items);

        foreach ($this->layoutData->items as &$item) {
            $this->preprocessCampaignItem($item);
        }
        unset($item);
    }

    // =========================================================================
    // Toolbar — layout default
    // =========================================================================

    /**
     * @since 1.0.0
     */
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Chiến dịch email');
        ToolbarHelper::back();
        ToolbarHelper::appendConfirmButton(
            'Bạn có chắc muốn hủy chiến dịch?',
            'ban-circle',
            'Hủy chiến dịch',
            'campaigns.cancelCampaign',
            true,
            'btn btn-danger'
        );
    }

    // =========================================================================
    // Preprocessing — layout default
    // =========================================================================

    /**
     * Chuyển đổi các giá trị raw của một campaign item thành dạng hiển thị HTML.
     *
     * @param  object  $item  Campaign item (pass by reference)
     * @since  1.0.0
     */
    private function preprocessCampaignItem(object &$item): void
    {
        // Thời gian: UTC → Local Time
        $item->created_at_local = !empty($item->created_at)
            ? DatetimeHelper::convertToLocalTime((string) $item->created_at)
            : '—';

        // Người tạo
        $creatorName           = htmlspecialchars($item->creator_name     ?? '');
        $creatorUser           = htmlspecialchars($item->creator_username ?? '');
        $item->creator_display = $creatorUser !== ''
            ? $creatorName . '<br><small class="text-muted">' . $creatorUser . '</small>'
            : ($creatorName ?: '—');

        // Ngữ cảnh — MailContextType đã ở lib_kma, gọi trực tiếp
        $contextTypeLabel      = htmlspecialchars(
            MailContextType::tryFrom((int) $item->context_type)?->getLabel() ?? '?'
        );
        $contextLabel          = htmlspecialchars($item->context_label ?? ('ID: ' . $item->context_id));
        $item->context_display =
            '<span class="badge bg-light text-dark border">' . $contextTypeLabel . '</span>'
            . '<br><small>' . $contextLabel . '</small>';

        // Tiến độ: progress bar Bootstrap
        $total  = (int) $item->total_count;
        $sent   = (int) $item->sent_count;
        $failed = (int) $item->failed_count;

        if ($total === 0) {
            $item->progress_html = '<span class="text-muted small">—</span>';
        } else {
            $sentPct             = (int) round($sent   / $total * 100);
            $failedPct           = (int) round($failed / $total * 100);
            $item->progress_html =
                '<div class="progress" style="height:16px; min-width:100px;">'
                . '<div class="progress-bar bg-success" style="width:' . $sentPct . '%"'
                .   ' title="Đã gửi: ' . $sent . '"></div>'
                . '<div class="progress-bar bg-danger"  style="width:' . $failedPct . '%"'
                .   ' title="Thất bại: ' . $failed . '"></div>'
                . '</div>'
                . '<small class="text-muted">' . ($sent + $failed) . '/' . $total . '</small>';
        }

        // Trạng thái: badge (status_label, status_badge đã enrich bởi model)
        $item->status_html =
            '<span class="badge ' . htmlspecialchars($item->status_badge ?? 'bg-secondary') . '">'
            . htmlspecialchars($item->status_label ?? '?')
            . '</span>';

        // Actions: nút Xem log
        $logUrl = Route::_(
            'index.php?option=com_kmail&view=campaigns&layout=log&campaign_id=' . (int) $item->id,
            false
        );

        // Nút Hủy: chỉ hiển thị khi campaign còn có thể hủy
        $cancelButton = '';
        if (MailCampaignStatus::tryFrom((int) $item->status)?->isCancellable()) {
            $cancelUrl    = Route::_(
                'index.php?option=com_kmail&task=campaigns.cancelCampaign&campaign_id=' . (int) $item->id,
                false
            );
            $cancelButton =
                ' <a href="' . $cancelUrl . '" class="btn btn-sm btn-outline-danger"'
                . ' onclick="return confirm(\'Bạn có chắc muốn hủy chiến dịch này?\');">'
                . 'Hủy</a>';
        }

        $item->actions_html =
            '<a href="' . $logUrl . '" class="btn btn-sm btn-outline-secondary">Xem log</a>'
            . $cancelButton;
    }

    // =========================================================================
    // Layout: log — delivery log của một campaign
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout log.
     *
     * @throws Exception
     * @since  1.0.0
     */
    protected function prepareDataForLayoutLog(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_kmail')) {
            throw new Exception('Bạn không có quyền thực hiện chức năng này.', 403);
        }

        $campaignId = Factory::getApplication()->input->getInt('campaign_id');
        if ($campaignId <= 0) {
            throw new Exception('Không xác định được campaign_id.');
        }

        /** @var MailCampaignsModel $model */
        $model    = $this->getModel();
        $campaign = $model->getCampaignById($campaignId);

        if ($campaign === null) {
            throw new Exception('Không tìm thấy campaign có id = ' . $campaignId . '.');
        }

        // Enrich campaign (bổ sung status_label, status_badge, context_label)
        $campaignArr = [$campaign];
        $model->enrichItems($campaignArr);

        // Preprocessing queue items
        $queueItems = $model->getQueueItems($campaignId);
        foreach ($queueItems as &$qItem) {
            $this->preprocessQueueItem($qItem);
        }
        unset($qItem);

        $this->layoutData        = new ListLayoutData();
        $this->layoutData->item  = $campaign;
        $this->layoutData->items = $queueItems;
    }

    // =========================================================================
    // Toolbar — layout log
    // =========================================================================

    /**
     * @since 1.0.0
     */
    protected function addToolbarForLayoutLog(): void
    {
        ToolbarHelper::title('Chiến dịch email — Chi tiết gửi');
        ToolbarHelper::back();
    }

    // =========================================================================
    // Preprocessing — layout log (queue items)
    // =========================================================================

    /**
     * Chuyển đổi giá trị raw của một queue item thành dạng hiển thị HTML.
     *
     * @param  object  $item  Queue item (pass by reference)
     * @since  1.0.0
     */
    private function preprocessQueueItem(object &$item): void
    {
        // Trạng thái: badge
        $item->status_html =
            '<span class="badge ' . htmlspecialchars($item->status_badge ?? 'bg-secondary') . '">'
            . htmlspecialchars($item->status_label ?? '?')
            . '</span>';

        // Thời gian lần thử gần nhất: UTC → Local Time
        $item->last_attempt_at_local = !empty($item->last_attempt_at)
            ? DatetimeHelper::convertToLocalTime((string) $item->last_attempt_at)
            : '—';

        // Thời gian gửi thành công: UTC → Local Time
        $item->sent_at_local = !empty($item->sent_at)
            ? DatetimeHelper::convertToLocalTime((string) $item->sent_at)
            : '—';

        // Thông báo lỗi
        $item->error_html = !empty($item->error_message)
            ? '<small class="text-danger">' . htmlspecialchars($item->error_message) . '</small>'
            : '';
    }
}
