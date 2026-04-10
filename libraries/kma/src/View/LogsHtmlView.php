<?php
namespace Kma\Library\Kma\View;

defined('_JEXEC') or die();

/**
 * @package     Kma\Library\Kma\View
 * @since       1.1.0
 */



use Exception;
use Joomla\CMS\Factory;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Model\LogsModel;

/**
 * Base HtmlView cho view nhật ký hệ thống (Logs).
 *
 * Chứa toàn bộ logic:
 *   - Kiểm tra quyền (core.admin)
 *   - Cấu hình cột hiển thị
 *   - Preprocessing từng item (convert datetime, render badges, truncate + tooltip)
 *   - Toolbar
 *
 * Filter form (bao gồm options động cho 'action' và 'object_type') được sinh
 * hoàn toàn bởi BaseLogsModel::getFilterForm() — View không cần xử lý thêm.
 *
 * Lớp con CÓ THỂ override để tuỳ biến:
 *   - getLogsViewTitle(): string    → tiêu đề toolbar (mặc định: 'Nhật ký hệ thống')
 *   - getTruncateLength(): int      → độ dài tối đa hiển thị old/new value (mặc định: 80)
 *
 * Cách sử dụng:
 * -----------------------------------------------------------------------
 *   namespace Kma\Component\Eqa\Administrator\View\Logs;
 *
 *   class HtmlView extends \Kma\Library\Kma\View\BaseLogsHtmlView
 *   {
 *       protected function getComponentOption(): string
 *       {
 *           return 'com_eqa';
 *       }
 *
 *       // Tuỳ chọn — dùng ToolbarHelper của component thay vì Joomla core
 *       protected function addToolbarForLayoutDefault(): void
 *       {
 *           ToolbarHelper::title($this->getLogsViewTitle());
 *           ToolbarHelper::appendGoHome();
 *       }
 *   }
 * -----------------------------------------------------------------------
 *
 * @since 1.1.0
 */
abstract class LogsHtmlView extends ItemsHtmlView
{
    // =========================================================================
    // Hook — lớp con có thể override để tuỳ biến
    // =========================================================================

    /**
     * Tiêu đề hiển thị trên toolbar.
     *
     * @return string
     * @since 1.1.0
     */
    protected function getLogsViewTitle(): string
    {
        return 'Nhật ký hệ thống';
    }

    /**
     * Số ký tự tối đa hiển thị trực tiếp cho old_value / new_value.
     * Nếu vượt quá → truncate + Bootstrap tooltip.
     *
     * @return int
     * @since 1.1.0
     */
    protected function getTruncateLength(): int
    {
        return 80;
    }

    // =========================================================================
    // Cấu hình cột
    // =========================================================================

    /**
     * Cấu hình cột cho layout default.
     * View Logs là read-only → không dùng field check (checkbox).
     *
     * @since 1.1.0
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $fields = new ListLayoutItemFields();

        // STT — không có checkbox (view read-only)
        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        // $fields->check không được set → không render cột checkbox

        $fields->customFieldset1 = [];

        // Thời gian (UTC → Local Time, sau khi preprocessing)
        $f = new ListLayoutItemFieldOption('creationTime', 'Thời gian', true, false, 'text-nowrap');
        $fields->customFieldset1[] = $f;

        // Người thực hiện (username + tên đầy đủ)
        $f = new ListLayoutItemFieldOption('operator_display', 'Người dùng', false, false, '');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Hành động (action int → badge)
        $f = new ListLayoutItemFieldOption('action_label', 'Hành động', false, false, 'text-center');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Kết quả (is_success → badge)
        $f = new ListLayoutItemFieldOption('is_success_html', 'Kết quả', true, false, 'text-center');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Đối tượng (object_type + object_id + object_title)
        $f = new ListLayoutItemFieldOption('object_display', 'Đối tượng', false, false, '');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Thay đổi (old/new value, truncate + tooltip)
        $f = new ListLayoutItemFieldOption('changes_html', 'Thay đổi', false, false, '');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Thông báo lỗi (chỉ hiển thị khi is_success = 0)
        $f = new ListLayoutItemFieldOption('error_message_html', 'Lỗi', false, false, 'text-danger small');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Địa chỉ IP
        $fields->customFieldset1[] = new ListLayoutItemFieldOption(
            'ipAddress', 'IP', false, false, 'text-center font-monospace small'
        );

        $this->itemFields = $fields;
    }

    // =========================================================================
    // Chuẩn bị dữ liệu
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout default.
     *
     * Thứ tự:
     *   1. Kiểm tra quyền core.admin
     *   2. Gọi parent::prepareDataForLayoutDefault() — nạp items, pagination,
     *      filterForm (đã đầy đủ options từ BaseLogsModel::getFilterForm())
     *   3. Preprocessing từng item
     *
     * @throws Exception
     * @since 1.1.0
     */
    protected function prepareDataForLayoutDefault(): void
    {
        // 1. Kiểm tra quyền — chỉ core.admin mới được xem log
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.admin', ComponentHelper::getName())) {
            throw new Exception('Bạn không có quyền xem nhật ký hệ thống.', 403);
        }

        // 2. Gọi parent — nạp items, pagination, filterForm, activeFilters.
        //    Filter form đã chứa đầy đủ options động cho 'action' và 'object_type'
        //    nhờ BaseLogsModel::getFilterForm() sinh XML trong bộ nhớ.
        parent::prepareDataForLayoutDefault();

        // 3. Preprocessing từng item
        if (!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as &$item) {
                $this->preprocessItem($item);
            }
            unset($item);
        }
    }

    // =========================================================================
    // Preprocessing item
    // =========================================================================

    /**
     * Chuyển đổi các giá trị raw của một log item thành dạng hiển thị HTML.
     *
     * Action label và ObjectType label được lấy từ Model (BaseLogsModel),
     * nơi getActionClass() và getObjectTypeClass() đã được định nghĩa.
     *
     * @param  object  $item  Bản ghi từ DB (pass by reference).
     * @since 1.1.0
     */
    private function preprocessItem(object &$item): void
    {
        // --- Thời gian: UTC DATETIME(3) → Local Time ---
        // Cắt bỏ phần milliseconds (.NNN) trước khi convert để tương thích
        // với các implementation của DatetimeHelper::utcToLocal()
        $rawDatetime            = (string) ($item->creationTime ?? '');
        $datetimeNoMs           = substr($rawDatetime, 0, 19); // 'YYYY-MM-DD HH:MM:SS'
        $item->creationTime     = DatetimeHelper::convertToLocalTime($datetimeNoMs);

        // --- Người dùng ---
        // username: snapshot trong log → không bị mất khi user bị xóa
        // operator_name: từ JOIN #__users → có thể NULL nếu user đã xóa
	    $operatorUsernameEsc = htmlspecialchars($item->operatorUsername ?? '');
	    $operatorNameEsc = htmlspecialchars($item->operatorName ?? $item->operatorOldName);

        $item->operator_display = $operatorUsernameEsc!=''
            ? $operatorNameEsc . '<br><small class="text-muted">' . $operatorUsernameEsc . '</small>'
            : $operatorNameEsc;

        // --- Hành động: int → label ---
        // Lấy Action class từ Model (BaseLogsModel::getActionClass())
        /** @var LogsModel $model */
        $model        = $this->getModel();
        $actionClass  = $model->getActionClass();
        $actionLabel  = $actionClass::getLabel((int) $item->action) ?? ('Action #' . $item->action);
        $item->action_label = '<span class="badge bg-secondary">'
            . htmlspecialchars($actionLabel) . '</span>';

        // --- Kết quả: badge ---
        $item->is_success_html = $item->isSuccess
            ? '<span class="badge bg-success">Thành công</span>'
            : '<span class="badge bg-danger">Thất bại</span>';

        // --- Đối tượng: object_type int → label ---
        // Lấy ObjectType class từ Model (BaseLogsModel::getObjectTypeClass())
        $objectTypeClass = $model->getObjectTypeClass();
        $objectTypeLabel = $objectTypeClass::tryFrom((int) $item->objectType)?->getLabel()
            ?? ('Type #' . $item->objectType);
        $objectId        = (int) $item->objectId;
        $objectTitleEsc  = htmlspecialchars($item->objectTitle ?? '');

        $item->object_display =
            '<span class="badge bg-light text-dark border">'
            . htmlspecialchars($objectTypeLabel) . '</span>'
            . ' <span class="text-muted small">#' . $objectId . '</span>'
            . ($objectTitleEsc !== '' ? '<br><small>' . $objectTitleEsc . '</small>' : '');

        // --- Thay đổi: old_value / new_value ---
        $item->changes_html = $this->buildChangesHtml($item->oldValue, $item->newValue);

        // --- Thông báo lỗi: chỉ hiển thị khi thất bại ---
        $item->error_message_html = (!$item->isSuccess && !empty($item->errorMessage))
            ? htmlspecialchars($item->errorMessage)
            : '';
    }

    // =========================================================================
    // Helper: render old/new value với truncate + tooltip
    // =========================================================================

    /**
     * Xây dựng HTML hiển thị thay đổi old_value / new_value.
     *
     * @param  string|null  $oldValue
     * @param  string|null  $newValue
     * @return string  HTML an toàn để echo raw.
     * @since 1.1.0
     */
    private function buildChangesHtml(?string $oldValue, ?string $newValue): string
    {
        if ($oldValue === null && $newValue === null) {
            return '';
        }

        $parts = [];

        if ($oldValue !== null) {
            $parts[] = '<div class="mb-1">'
                . '<span class="text-muted small me-1">Cũ:</span>'
                . $this->truncateWithTooltip($oldValue)
                . '</div>';
        }

        if ($newValue !== null) {
            $parts[] = '<div>'
                . '<span class="text-muted small me-1">Mới:</span>'
                . $this->truncateWithTooltip($newValue)
                . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * Truncate chuỗi và thêm Bootstrap tooltip nếu vượt quá getTruncateLength().
     *
     * @param  string  $text  Chuỗi gốc (chưa escaped).
     * @return string         HTML an toàn.
     * @since 1.1.0
     */
    private function truncateWithTooltip(string $text): string
    {
        $maxLen = $this->getTruncateLength();

        if (mb_strlen($text) <= $maxLen) {
            return '<code class="small">' . htmlspecialchars($text) . '</code>';
        }

        $truncatedEsc   = htmlspecialchars(mb_substr($text, 0, $maxLen));
        $fullTooltipEsc = htmlspecialchars($text, ENT_QUOTES);

        return '<code class="small"'
            . ' data-bs-toggle="tooltip"'
            . ' data-bs-placement="top"'
            . ' title="' . $fullTooltipEsc . '"'
            . '>' . $truncatedEsc . '…</code>';
    }
	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title($this->getLogsViewTitle());
		ToolbarHelper::appendGoHome();
	}
}
