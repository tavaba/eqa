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
use Kma\Library\Kma\Table\Table;

/**
 * Base HtmlView cho view nhật ký hệ thống (Logs).
 *
 * Chứa toàn bộ logic:
 *   - Kiểm tra quyền (core.admin)
 *   - Cấu hình cột hiển thị
 *   - Preprocessing từng item (convert datetime, render badges, truncate + tooltip)
 *   - Tính toán và hiển thị KHÁC BIỆT giữa old_value và new_value
 *   - Toolbar
 *
 * Filter form (bao gồm options động cho 'action' và 'object_type') được sinh
 * hoàn toàn bởi BaseLogsModel::getFilterForm() — View không cần xử lý thêm.
 *
 * Lớp con CÓ THỂ override để tuỳ biến:
 *   - getLogsViewTitle(): string        → tiêu đề toolbar (mặc định: 'Nhật ký hệ thống')
 *   - getTruncateLength(): int          → độ dài tối đa hiển thị old/new value (mặc định: 80)
 *   - getDiffTruncateLength(): int      → độ dài tối đa mỗi giá trị trong cột Khác biệt
 *   - getIgnoredDiffFields(): array     → danh sách cột bị loại khỏi phép so sánh
 *   - getMaxDiffRows(): int             → số dòng khác biệt hiển thị trực tiếp
 *
 * Cách sử dụng:
 * -----------------------------------------------------------------------
 *   namespace Kma\Component\Eqa\Administrator\View\Logs;
 *
 *   class HtmlView extends \Kma\Library\Kma\View\LogsHtmlView
 *   {
 *       protected function getComponentOption(): string
 *       {
 *           return 'com_eqa';
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

    /**
     * Số ký tự tối đa hiển thị cho MỖI giá trị (cũ hoặc mới) trong cột 'Khác biệt'.
     * Ngắn hơn getTruncateLength() vì mỗi dòng chứa cả hai giá trị.
     *
     * @return int
     * @since 1.2.0
     */
    protected function getDiffTruncateLength(): int
    {
        return 40;
    }

    /**
     * Số dòng khác biệt được hiển thị trực tiếp.
     * Phần vượt quá được gói trong thẻ <details> thu gọn.
     *
     * @return int
     * @since 1.2.0
     */
    protected function getMaxDiffRows(): int
    {
        return 8;
    }

    /**
     * Danh sách các cột bị bỏ qua khi tính khác biệt.
     *
     * Đây là các cột kỹ thuật gần như luôn thay đổi ở mọi thao tác EDIT
     * (dấu thời gian, thông tin check-out...) nên không mang ý nghĩa nghiệp vụ
     * và chỉ gây nhiễu cho người đọc log.
     *
     * Danh sách tên cột timestamp KHÔNG được khai báo lại ở đây mà lấy trực tiếp từ
     * Table::getTimestampColumnNames() — nguồn duy nhất của lib_kma. Nhờ vậy mọi biến thể
     * ('modified', 'modified_at', 'updated_at', 'created_on', 'creator_id'...) đều được
     * loại bỏ, và nếu sau này bổ sung biến thể mới vào Table thì view Logs tự cập nhật theo.
     *
     * Lớp con có thể override để bổ sung/loại bớt, ví dụ:
     *
     *   // Thêm cột nghiệp vụ gây nhiễu
     *   protected function getIgnoredDiffFields(): array
     *   {
     *       return array_merge(parent::getIgnoredDiffFields(), ['hits', 'version']);
     *   }
     *
     *   // Hoặc: chỉ ẩn nhóm 'modified', vẫn hiển thị thay đổi bất thường của 'created'
     *   protected function getIgnoredDiffFields(): array
     *   {
     *       return array_merge(
     *           Table::getTimestampColumnNames(['modified', 'modified_by']),
     *           ['checked_out', 'checked_out_time']
     *       );
     *   }
     *
     * @return string[]
     * @since 1.2.0
     */
    protected function getIgnoredDiffFields(): array
    {
        return array_merge(
            // created, created_at, created_on, created_by, creator_id,
            // modified, updated, modified_at, updated_at, modified_on, updated_on,
            // modified_by, updated_by, modifier_id
            Table::getTimestampColumnNames(),
            // Cột check-out của Joomla — thay đổi khi mở/đóng form, không phải dữ liệu nghiệp vụ
            ['checked_out', 'checked_out_time', 'checked_out_by']
        );
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
        $f = new ListLayoutItemFieldOption('changes_html', 'Thay đổi', false, false, 'align-top');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Khác biệt — chỉ có giá trị khi bản ghi có ĐỦ cả old_value và new_value
        $f = new ListLayoutItemFieldOption('diff_html', 'Khác biệt', false, false, 'align-top');
        $f->printRaw   = true;
        $f->titleDesc  = 'Các trường dữ liệu thực sự thay đổi giữa giá trị cũ và giá trị mới';
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

        // --- Khác biệt: chỉ tính khi có ĐỦ cả old_value và new_value ---
        $item->diff_html = $this->buildDiffHtml($item->oldValue, $item->newValue);

        // --- Thay đổi: old_value / new_value ---
        $item->changes_html = $this->buildChangesHtml($item->oldValue, $item->newValue);

        // --- Thông báo lỗi: chỉ hiển thị khi thất bại ---
        $item->error_message_html = (!$item->isSuccess && !empty($item->errorMessage))
            ? htmlspecialchars($item->errorMessage)
            : '';
    }

    // =========================================================================
    // Tính toán và hiển thị khác biệt (diff)
    // =========================================================================

    /**
     * Xây dựng HTML mô tả sự khác biệt giữa giá trị cũ và giá trị mới.
     *
     * Quy tắc:
     *   - Nếu thiếu một trong hai vế → trả về chuỗi rỗng (không đủ dữ liệu để so sánh).
     *   - Nếu cả hai vế đều là JSON object/array (snapshot bản ghi) → so sánh theo
     *     từng trường, chỉ liệt kê những trường thực sự thay đổi.
     *   - Nếu là chuỗi thường → so sánh trực tiếp toàn chuỗi.
     *
     * @param  string|null  $oldValue  Giá trị cũ (raw, lấy từ cột old_value).
     * @param  string|null  $newValue  Giá trị mới (raw, lấy từ cột new_value).
     * @return string  HTML an toàn để echo raw.
     * @since  1.2.0
     */
    private function buildDiffHtml(?string $oldValue, ?string $newValue): string
    {
        // Chỉ xử lý khi có đủ cả hai vế
        if ($oldValue === null || $newValue === null) {
            return '';
        }

        $oldDecoded = $this->decodeLogValue($oldValue);
        $newDecoded = $this->decodeLogValue($newValue);

        $rows = (is_array($oldDecoded) && is_array($newDecoded))
            ? $this->computeStructuredDiff($oldDecoded, $newDecoded)
            : $this->computeScalarDiff((string) $oldValue, (string) $newValue);

        if ($rows === []) {
            return '<span class="badge bg-light text-muted border">Không có thay đổi</span>';
        }

        return $this->renderDiffRows($rows);
    }

    /**
     * Giải mã giá trị log.
     *
     * Trả về mảng nếu nội dung là JSON object/array hợp lệ; ngược lại trả về
     * nguyên chuỗi ban đầu (giá trị được ghi log dưới dạng plain text).
     *
     * @param  string  $raw
     * @return array|string
     * @since  1.2.0
     */
    private function decodeLogValue(string $raw): array|string
    {
        $trimmed = trim($raw);

        // Nhận diện nhanh: chỉ thử json_decode với chuỗi bắt đầu bằng '{' hoặc '['
        if ($trimmed === '' || (!str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '['))) {
            return $raw;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : $raw;
    }

    /**
     * So sánh hai snapshot dạng mảng và trả về danh sách các trường thay đổi.
     *
     * @param  array  $old
     * @param  array  $new
     * @return array<int, array{field: string, old: string, new: string, type: string}>
     * @since  1.2.0
     */
    private function computeStructuredDiff(array $old, array $new): array
    {
        $ignored = $this->getIgnoredDiffFields();
        $keys    = array_keys($old + $new);   // hợp của hai tập khoá, giữ thứ tự của $old
        $rows    = [];

        foreach ($keys as $key) {
            $keyName = (string) $key;

            if (in_array($keyName, $ignored, true)) {
                continue;
            }

            $oldNorm = $this->normalizeDiffValue($old[$key] ?? null);
            $newNorm = $this->normalizeDiffValue($new[$key] ?? null);

            if ($this->diffValuesEqual($oldNorm, $newNorm)) {
                continue;
            }

            $rows[] = [
                'field' => $keyName,
                'old'   => $oldNorm,
                'new'   => $newNorm,
                'type'  => $oldNorm === '' ? 'added' : ($newNorm === '' ? 'removed' : 'modified'),
            ];
        }

        return $rows;
    }

    /**
     * So sánh hai giá trị dạng chuỗi thường (không phải JSON snapshot).
     *
     * @param  string  $old
     * @param  string  $new
     * @return array<int, array{field: string, old: string, new: string, type: string}>
     * @since  1.2.0
     */
    private function computeScalarDiff(string $old, string $new): array
    {
        $oldNorm = $this->normalizeDiffValue($old);
        $newNorm = $this->normalizeDiffValue($new);

        if ($this->diffValuesEqual($oldNorm, $newNorm)) {
            return [];
        }

        return [[
            'field' => '',
            'old'   => $oldNorm,
            'new'   => $newNorm,
            'type'  => 'modified',
        ]];
    }

    /**
     * Chuẩn hoá một giá trị về dạng chuỗi để so sánh và hiển thị.
     *
     * NULL và chuỗi rỗng đều quy về '' (coi là "trống") nhằm loại bỏ các khác biệt
     * giả do cách MySQL trả về NULL so với giá trị mặc định rỗng.
     *
     * @param  mixed  $value
     * @return string
     * @since  1.2.0
     */
    private function normalizeDiffValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return trim((string) $value);
    }

    /**
     * Kiểm tra hai giá trị đã chuẩn hoá có được coi là bằng nhau hay không.
     *
     * Với hai giá trị số, so sánh theo trị số thay vì theo chuỗi để tránh báo
     * khác biệt giả giữa '10' và '10.00' (cột DECIMAL của MySQL).
     *
     * @param  string  $old
     * @param  string  $new
     * @return bool
     * @since  1.2.0
     */
    private function diffValuesEqual(string $old, string $new): bool
    {
        if ($old === $new) {
            return true;
        }

        if ($old !== '' && $new !== '' && is_numeric($old) && is_numeric($new)) {
            return abs((float) $old - (float) $new) < 1e-9;
        }

        return false;
    }

    /**
     * Render danh sách thay đổi thành HTML.
     *
     * Chỉ hiển thị trực tiếp getMaxDiffRows() dòng đầu tiên; phần còn lại được
     * gói trong thẻ <details> để người dùng chủ động mở xem.
     *
     * @param  array<int, array{field: string, old: string, new: string, type: string}>  $rows
     * @return string
     * @since  1.2.0
     */
    private function renderDiffRows(array $rows): string
    {
        $maxRows  = $this->getMaxDiffRows();
        $total    = count($rows);
        $visible  = array_slice($rows, 0, $maxRows);
        $hidden   = array_slice($rows, $maxRows);

        $html = '<div class="small">';

        foreach ($visible as $row) {
            $html .= $this->renderDiffRow($row);
        }

        if ($hidden !== []) {
            $html .= '<details class="mt-1">'
                . '<summary class="text-primary small" style="cursor:pointer">'
                . 'Xem thêm ' . count($hidden) . ' thay đổi'
                . '</summary>'
                . '<div class="mt-1">';

            foreach ($hidden as $row) {
                $html .= $this->renderDiffRow($row);
            }

            $html .= '</div></details>'
                . '<div class="text-muted mt-1" style="font-size:.75rem">'
                . 'Tổng: ' . $total . ' trường thay đổi'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render một dòng khác biệt: "tên_cột: giá_trị_cũ → giá_trị_mới".
     *
     * @param  array{field: string, old: string, new: string, type: string}  $row
     * @return string
     * @since  1.2.0
     */
    private function renderDiffRow(array $row): string
    {
        $maxLen = $this->getDiffTruncateLength();

        $fieldHtml = $row['field'] !== ''
            ? '<span class="fw-semibold font-monospace">'
                . htmlspecialchars($row['field']) . '</span>'
                . '<span class="text-muted">:</span> '
            : '';

        $oldHtml = $row['old'] !== ''
            ? '<span class="text-danger text-decoration-line-through">'
                . $this->truncateWithTooltip($row['old'], $maxLen) . '</span>'
            : '<em class="text-muted">(trống)</em>';

        $newHtml = $row['new'] !== ''
            ? '<span class="text-success fw-semibold">'
                . $this->truncateWithTooltip($row['new'], $maxLen) . '</span>'
            : '<em class="text-muted">(trống)</em>';

        $badge = match ($row['type']) {
            'added'   => ' <span class="badge bg-success-subtle text-success border border-success-subtle">thêm</span>',
            'removed' => ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle">xóa</span>',
            default   => '',
        };

        return '<div class="mb-1">'
            . $fieldHtml
            . $oldHtml
            . ' <span class="text-muted">&rarr;</span> '
            . $newHtml
            . $badge
            . '</div>';
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
     * Truncate chuỗi và thêm Bootstrap tooltip nếu vượt quá độ dài cho phép.
     *
     * @param  string    $text    Chuỗi gốc (chưa escaped).
     * @param  int|null  $maxLen  Độ dài tối đa; NULL → dùng getTruncateLength().
     * @return string             HTML an toàn.
     * @since 1.1.0
     */
    private function truncateWithTooltip(string $text, ?int $maxLen = null): string
    {
        $maxLen ??= $this->getTruncateLength();

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
