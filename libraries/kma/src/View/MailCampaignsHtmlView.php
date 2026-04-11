<?php

/**
 * @package     Kma.Library.Kma
 * @subpackage  View
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 *
 * @since       1.0.3
 */

namespace Kma\Library\Kma\View;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Enum\MailCampaignStatus;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Model\MailCampaignsModel;

/**
 * Base HtmlView cho view quản lý chiến dịch email (Mail Campaigns).
 *
 * Hỗ trợ 2 layout:
 *   - default : Danh sách campaign với filter và tiến độ gửi
 *   - log     : Delivery log chi tiết của một campaign (danh sách queue items)
 *
 * Chứa toàn bộ logic:
 *   - Kiểm tra quyền
 *   - Cấu hình cột hiển thị cho từng layout
 *   - Preprocessing item (convert datetime, render badge status, progress bar)
 *   - Toolbar cho từng layout
 *
 * Lớp con BẮT BUỘC override:
 *   - getName(): string
 *       Tên view của component trong URL, ví dụ 'mailcampaigns'.
 *   - getContextTypeLabel(int $contextType): string
 *       Trả về nhãn hiển thị của context_type, ví dụ 'Môn thi', 'Kỳ thi'.
 *       lib_kma không biết enum MailContextType của component — lớp con cung cấp.
 *
 * Lớp con CÓ THỂ override:
 *   - getViewTitle(): string
 *       Tiêu đề toolbar (mặc định: 'Chiến dịch email').
 *   - getRequiredPermission(): string
 *       Quyền kiểm tra (mặc định: 'core.manage').
 *   - getCancelTaskName(): string
 *       Tên task hủy campaign (mặc định: 'mailcampaign.cancel').
 *   - addToolbarForLayoutDefault(): void
 *   - addToolbarForLayoutLog(): void
 *
 * Ví dụ lớp con (com_eqa):
 * -----------------------------------------------------------------------
 *   namespace Kma\Component\Eqa\Administrator\View\Mailcampaigns;
 *
 *   class HtmlView extends \Kma\Library\Kma\View\MailCampaignsHtmlView
 *   {
 *       protected function getName(): string
 *       {
 *           return 'mailcampaigns';
 *       }
 *
 *       protected function getContextTypeLabel(int $contextType): string
 *       {
 *           return MailContextType::tryFrom($contextType)?->getLabel() ?? '?';
 *       }
 *   }
 * -----------------------------------------------------------------------
 *
 * @since 1.0.3
 */
abstract class MailCampaignsHtmlView extends ItemsHtmlView
{
    // =========================================================================
    // Abstract — bắt buộc override ở lớp con
    // =========================================================================
    /**
     * Trả về nhãn hiển thị của context_type.
     *
     * lib_kma không biết enum MailContextType của component — lớp con
     * cung cấp nhãn tương ứng với giá trị int của context_type.
     *
     * Ví dụ (com_eqa):
     *   return MailContextType::tryFrom($contextType)?->getLabel() ?? '?';
     *
     * @param  int  $contextType  Giá trị int của MailContextType enum
     *
     * @return string
     * @since  1.0.3
     */
    abstract protected function getContextTypeLabel(int $contextType): string;

    // =========================================================================
    // Overridable hooks
    // =========================================================================

    /**
     * Tiêu đề hiển thị trên toolbar.
     *
     * @return string
     * @since  1.0.3
     */
    protected function getViewTitle(): string
    {
        return 'Chiến dịch email';
    }

    /**
     * Quyền tối thiểu để truy cập view này.
     *
     * @return string
     * @since  1.0.3
     */
    protected function getRequiredPermission(): string
    {
        return 'core.manage';
    }

    /**
     * Tên task hủy campaign.
     * Dùng tên 'cancelCampaign' thay vì 'cancel' để tránh nhầm lẫn với
     * task 'cancel' tiêu chuẩn của Joomla FormController (vốn chỉ redirect
     * về list mà không thay đổi dữ liệu).
     * Lớp con override nếu controller dùng tên task khác.
     *
     * @return string  Ví dụ: 'mailcampaign.cancelCampaign'
     * @since  1.0.3
     */
    protected function getCancelTaskName(): string
    {
        return 'mailcampaign.cancelCampaign';
    }

    // =========================================================================
    // Cấu hình cột — layout default (danh sách campaign)
    // =========================================================================

    /**
     * Cấu hình cột cho layout default.
     * View này là read-only → không dùng checkbox.
     *
     * @since 1.0.3
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $fields = new ListLayoutItemFields();

        $fields->sequence        = ListLayoutItemFields::defaultFieldSequence();
        $fields->customFieldset1 = [];

        // Thời gian tạo (UTC → Local Time sau preprocessing)
        $f = new ListLayoutItemFieldOption('created_at_local', 'Thời gian', true, false, 'text-nowrap');
        $fields->customFieldset1[] = $f;

        // Người tạo
        $f            = new ListLayoutItemFieldOption('creator_display', 'Người tạo', false, false, '');
        $f->printRaw  = true;
        $fields->customFieldset1[] = $f;

        // Ngữ cảnh (context_type label + context_label từ model)
        $f            = new ListLayoutItemFieldOption('context_display', 'Ngữ cảnh', false, false, '');
        $f->printRaw  = true;
        $fields->customFieldset1[] = $f;

        // Tên template đã dùng
        $fields->customFieldset1[] = new ListLayoutItemFieldOption('template_title', 'Template', false, false, '');

        // Tiến độ gửi (progress bar)
        $f            = new ListLayoutItemFieldOption('progress_html', 'Tiến độ', false, false, 'text-center');
        $f->printRaw  = true;
        $fields->customFieldset1[] = $f;

        // Trạng thái (badge)
        $f            = new ListLayoutItemFieldOption('status_html', 'Trạng thái', true, false, 'text-center');
        $f->printRaw  = true;
        $fields->customFieldset1[] = $f;

        // Nút xem log + hủy
        $f            = new ListLayoutItemFieldOption('actions_html', '', false, false, 'text-end text-nowrap');
        $f->printRaw  = true;
        $fields->customFieldset1[] = $f;

        $this->itemFields = $fields;
    }

    // =========================================================================
    // Chuẩn bị dữ liệu — layout default
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout default.
     *
     * Thứ tự:
     *   1. Kiểm tra quyền
     *   2. Gọi parent — nạp items, pagination, filterForm, activeFilters
     *   3. Gọi enrichItems() trên model để bổ sung context_label, status_badge...
     *   4. Preprocessing từng item → tạo HTML fields
     *
     * @throws Exception
     * @since  1.0.3
     */
    protected function prepareDataForLayoutDefault(): void
    {
        // 1. Kiểm tra quyền
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise($this->getRequiredPermission(), ComponentHelper::getName())) {
            throw new Exception('Bạn không có quyền thực hiện chức năng này.', 403);
        }

        // 2. Gọi parent — nạp items, pagination, filterForm, activeFilters
        parent::prepareDataForLayoutDefault();

        // 3. Enrich items (bổ sung status_label, status_badge, context_label, progress_pct)
        /** @var MailCampaignsModel $model */
        $model = $this->getModel();
        $model->enrichItems($this->layoutData->items);

        // 4. Preprocessing từng item
        foreach ($this->layoutData->items as &$item) {
            $this->preprocessCampaignItem($item);
        }
        unset($item);
    }

    // =========================================================================
    // Preprocessing — layout default
    // =========================================================================

    /**
     * Chuyển đổi các giá trị raw của một campaign item thành dạng hiển thị HTML.
     *
     * Không dùng trực tiếp bất kỳ enum nào của component:
     *   - context_type label → lấy qua getContextTypeLabel() (abstract, lớp con cung cấp)
     *   - status label/badge → đã được enrichItems() bổ sung vào item từ model
     *
     * @param  object  $item  Campaign item (pass by reference)
     * @since  1.0.3
     */
    private function preprocessCampaignItem(object &$item): void
    {
        // --- Thời gian: UTC → Local Time ---
        $item->created_at_local = !empty($item->created_at)
            ? DatetimeHelper::convertToLocalTime((string) $item->created_at)
            : '—';

        // --- Người tạo ---
        $creatorName           = htmlspecialchars($item->creator_name     ?? '');
        $creatorUser           = htmlspecialchars($item->creator_username ?? '');
        $item->creator_display = $creatorUser !== ''
            ? $creatorName . '<br><small class="text-muted">' . $creatorUser . '</small>'
            : ($creatorName ?: '—');

        // --- Ngữ cảnh ---
        // context_type label: lấy qua abstract method (lớp con cung cấp)
        // context_label: đã được enrichItems() bổ sung từ model (getContextLabel lớp con)
        $contextTypeLabel  = htmlspecialchars($this->getContextTypeLabel((int) $item->context_type));
        $contextLabel      = htmlspecialchars($item->context_label ?? ('ID: ' . $item->context_id));
        $item->context_display =
            '<span class="badge bg-light text-dark border">' . $contextTypeLabel . '</span>'
            . '<br><small>' . $contextLabel . '</small>';

        // --- Tiến độ: progress bar Bootstrap ---
        $total = (int) $item->total_count;
        $sent  = (int) $item->sent_count;
        $failed = (int) $item->failed_count;

        if ($total === 0) {
            $item->progress_html = '<span class="text-muted small">—</span>';
        }
        else {
            $sentPct   = (int) round($sent   / $total * 100);
            $failedPct = (int) round($failed / $total * 100);
            $item->progress_html =
                '<div class="progress" style="height:16px; min-width:100px;">'
                . '<div class="progress-bar bg-success" style="width:' . $sentPct . '%"'
                . ' title="Đã gửi: ' . $sent . '"></div>'
                . '<div class="progress-bar bg-danger"  style="width:' . $failedPct . '%"'
                . ' title="Thất bại: ' . $failed . '"></div>'
                . '</div>'
                . '<small class="text-muted">' . ($sent + $failed) . '/' . $total . '</small>';
        }

        // --- Trạng thái: badge (status_label, status_badge đã enrich bởi model) ---
        $item->status_html =
            '<span class="badge ' . htmlspecialchars($item->status_badge ?? 'bg-secondary') . '">'
            . htmlspecialchars($item->status_label ?? '?')
            . '</span>';

        // --- Actions: nút Xem log + Hủy ---
        $option = ComponentHelper::getName();
        $view   = $this->getName();

        $logUrl = Route::_(
            'index.php?option=' . $option
            . '&view=' . $view
            . '&layout=log&campaign_id=' . (int) $item->id,
            false
        );

        // Nút Hủy: chỉ hiển thị khi campaign còn Pending
        // Dùng MailCampaignStatus::tryFrom() — enum này thuộc lib_kma, không vi phạm
        $cancelButton = '';
        $statusEnum   = MailCampaignStatus::tryFrom((int) $item->status);
        if ($statusEnum?->isCancellable()) {
            $cancelUrl = Route::_(
                'index.php?option=' . $option
                . '&task=' . $this->getCancelTaskName()
                . '&campaign_id=' . (int) $item->id,
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
    // Toolbar — layout default
    // =========================================================================

    /**
     * Toolbar cho layout default.
     * Lớp con override để dùng ToolbarHelper riêng của component.
     *
     * @since 1.0.3
     */
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title($this->getViewTitle());
        ToolbarHelper::appendGoHome();
    }

    // =========================================================================
    // Layout: log — delivery log của một campaign
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout log.
     *
     * @throws Exception
     * @since  1.0.3
     */
    protected function prepareDataForLayoutLog(): void
    {
        // 1. Kiểm tra quyền
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise($this->getRequiredPermission(), ComponentHelper::getName())) {
            throw new Exception('Bạn không có quyền thực hiện chức năng này.', 403);
        }

        // 2. Lấy campaign_id từ request
        $campaignId = Factory::getApplication()->input->getInt('campaign_id');
        if ($campaignId <= 0) {
            throw new Exception('Không xác định được campaign_id.');
        }

        /** @var MailCampaignsModel $model */
        $model = $this->getModel();

        // 3. Lấy thông tin campaign
        $campaign = $model->getCampaignById($campaignId);
        if ($campaign === null) {
            throw new Exception('Không tìm thấy campaign có id = ' . $campaignId);
        }

        // 4. Enrich campaign (bổ sung status_label, status_badge, context_label)
        $campaignArr = [$campaign];
        $model->enrichItems($campaignArr);

        // 5. Lấy và preprocessing queue items
        $queueItems = $model->getQueueItems($campaignId);
        foreach ($queueItems as &$qItem) {
            $this->preprocessQueueItem($qItem);
        }
        unset($qItem);

        // 6. Gán vào layoutData để template sử dụng
        $this->layoutData           = new ListLayoutData();
        $this->layoutData->item     = $campaign;
        $this->layoutData->items    = $queueItems;
    }

    /**
     * Toolbar cho layout log.
     * Lớp con override để dùng ToolbarHelper riêng của component.
     *
     * @since 1.0.3
     */
    protected function addToolbarForLayoutLog(): void
    {
        ToolbarHelper::title($this->getViewTitle() . ' — Chi tiết gửi');
        ToolbarHelper::appendGoHome();

        $backUrl = Route::_(
            'index.php?option=' . ComponentHelper::getName()
            . '&view=' . $this->getName(),
            false
        );
        ToolbarHelper::appendLink(
            $this->getRequiredPermission(),
            $backUrl,
            'Danh sách',
            'arrow-up-2'
        );
    }

    // =========================================================================
    // Preprocessing — layout log (queue items)
    // =========================================================================

    /**
     * Chuyển đổi giá trị raw của một queue item thành dạng hiển thị HTML.
     *
     * status_label và status_badge đã được getQueueItems() bổ sung từ
     * MailQueueStatus enum (thuộc lib_kma) — không vi phạm nguyên tắc.
     *
     * @param  object  $item  Queue item (pass by reference)
     * @since  1.0.3
     */
    private function preprocessQueueItem(object &$item): void
    {
        // --- Trạng thái: badge ---
        $item->status_html =
            '<span class="badge ' . htmlspecialchars($item->status_badge ?? 'bg-secondary') . '">'
            . htmlspecialchars($item->status_label ?? '?')
            . '</span>';

        // --- Thời gian lần thử gần nhất: UTC → Local Time ---
        $item->last_attempt_at_local = !empty($item->last_attempt_at)
            ? DatetimeHelper::convertToLocalTime((string) $item->last_attempt_at)
            : '—';

        // --- Thời gian gửi thành công: UTC → Local Time ---
        $item->sent_at_local = !empty($item->sent_at)
            ? DatetimeHelper::convertToLocalTime((string) $item->sent_at)
            : '—';

        // --- Thông báo lỗi ---
        $item->error_html = '';
        if (!empty($item->error_message)) {
            $item->error_html =
                '<small class="text-danger">'
                . htmlspecialchars($item->error_message)
                . '</small>';
        }
    }
}
