<?php
namespace Kma\Library\Kma\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * Lớp cơ sở cho mọi dropdown CHỌN ĐỐI TƯỢNG được lấy từ CSDL (môn học, khóa học,
 * phòng, đơn vị, cán bộ...) mà bảng nguồn có cột trạng thái.
 *
 * BÀI TOÁN
 * --------
 * Khi một đối tượng chuyển sang trạng thái 'Đã lưu trữ', nó phải biến mất khỏi
 * dropdown để quản trị viên không chọn nhầm cho dữ liệu mới. Nhưng các bản ghi
 * CŨ vẫn đang trỏ tới nó. Nếu dropdown không sinh option tương ứng thì:
 *   - form sửa hiển thị ô trống (quản trị viên tưởng dữ liệu bị mất);
 *   - bấm Lưu sẽ ghi đè khóa ngoại về 0 → MẤT DỮ LIỆU IM LẶNG.
 *
 * CÁCH GIẢI
 * ---------
 * getOptions() chạy hai bước:
 *   1. Nạp các đối tượng có trạng thái nằm trong tập cho phép (mặc định: chỉ
 *      'Đang dùng').
 *   2. Nếu giá trị hiện tại của field trỏ tới (các) id không có trong kết quả
 *      bước 1, nạp bù đúng (các) id đó — BỎ QUA điều kiện trạng thái — và thêm
 *      vào cuối danh sách kèm hậu tố phân biệt.
 *
 * CÁCH DÙNG
 * ---------
 * Lớp con chỉ cần cài đặt hai phương thức:
 *
 *   protected function buildQuery(DatabaseInterface $db): QueryInterface
 *   {
 *       return $db->getQuery(true)
 *           ->select('a.id, a.code, a.name')
 *           ->from('#__eqa_subjects AS a')
 *           ->order('a.code');
 *   }
 *
 *   protected function buildOptionText(object $row): string
 *   {
 *       return $row->code . ' - ' . $row->name;
 *   }
 *
 * Truy vấn KHÔNG được chứa điều kiện trạng thái của bảng chính — lớp cơ sở tự
 * thêm vào. Bảng chính phải mang alias khớp với $stateColumn và $keyColumn.
 *
 * Cơ chế:
 *
 * 1. Lớp con cài `buildQuery(DatabaseInterface $db): QueryInterface` (truy vấn gốc,
 *    KHÔNG chứa điều kiện trạng thái của bảng chính) và buildOptionText(object $row): string.
 * 2. getOptions() chạy truy vấn với điều kiện state IN (<states>) — mặc định chỉ 1.
 * 3. Bước bù: nếu giá trị hiện tại của field trỏ tới id không có trong kết quả, gọi lại
 *    buildQuery(), xóa toàn bộ mệnh đề WHERE rồi lọc theo đúng id đó, và thêm option kèm hậu tố
 *    "(không còn hiệu lực)".
 * 4. Hook getLeadingOptions() cho lớp con chèn option đứng đầu;
 * 5. Hook useXmlOptions() để bỏ qua các thẻ <option> trong XML.
 *
 * Ghi chú: Việc xóa toàn bộ WHERE ở bước 3 là có chủ ý: nhờ vậy giá trị đang lưu
 * vẫn tìm lại được kể cả khi "bảng cha" đã bị lưu trữ (ví dụ lớp hành chính thuộc
 * khóa học đã lưu trữ), hoặc khi bản ghi nằm ngoài phạm vi lọc nghiệp vụ (cơ sở đào tạo,
 * loại đơn vị).
 *
 * @since 1.0.5
 */
abstract class StateAwareListField extends ListField
{
    /**
     * Tên cột trạng thái của BẢNG CHÍNH, có đủ alias.
     *
     * @var string
     * @since 1.0.5
     */
    protected string $stateColumn = 'a.state';

    /**
     * Tên cột khóa chính của BẢNG CHÍNH, có đủ alias.
     *
     * @var string
     * @since 1.0.5
     */
    protected string $keyColumn = 'a.id';

    /**
     * Tên thuộc tính chứa khóa chính trong các bản ghi mà truy vấn trả về.
     *
     * @var string
     * @since 1.0.5
     */
    protected string $keyProperty = 'id';

    /**
     * Hậu tố gắn vào nhãn của option được nạp bù.
     *
     * @var string
     * @since 1.0.5
     */
    protected string $orphanSuffix = ' (không còn hiệu lực)';

    /**
     * Xây truy vấn gốc lấy danh sách đối tượng.
     *
     * KHÔNG thêm điều kiện trạng thái của bảng chính vào đây.
     *
     * @param   DatabaseInterface  $db
     *
     * @return  QueryInterface
     * @since   1.0.5
     */
    abstract protected function buildQuery(DatabaseInterface $db): QueryInterface;

    /**
     * Dựng nhãn hiển thị của một option từ bản ghi tương ứng.
     *
     * @param   object  $row  Một bản ghi do buildQuery() trả về.
     *
     * @return  string
     * @since   1.0.5
     */
    abstract protected function buildOptionText(object $row): string;

    /**
     * Các option được chèn lên ĐẦU danh sách, trước dữ liệu lấy từ CSDL.
     *
     * Lớp con ghi đè khi cần một mục dẫn hướng riêng, ví dụ '- Khoa phụ trách -'.
     *
     * @return  array  Mảng option của HTMLHelper.
     * @since   1.0.5
     */
    protected function getLeadingOptions(): array
    {
        return [];
    }

    /**
     * Có gộp các option khai báo bằng thẻ <option> trong form XML hay không.
     *
     * @return  bool
     * @since   1.0.5
     */
    protected function useXmlOptions(): bool
    {
        return true;
    }

    /**
     * Tập trạng thái được hiển thị.
     *
     * Mặc định chỉ 'Đang dùng'. Form XML có thể mở rộng, ví dụ states="1,2" cho
     * bộ lọc danh sách khi cần tra cứu dữ liệu lịch sử.
     *
     * @return  int[]
     * @since   1.0.5
     */
    protected function getVisibleStates(): array
    {
        return StateHelper::normalizeStates(
            $this->element['states'] ?? null,
            StateHelper::getSelectableStates()
        );
    }

    /**
     * Các giá trị đang được chọn của field, quy về mảng id (int).
     *
     * @return  int[]
     * @since   1.0.5
     */
    protected function getSelectedIds(): array
    {
        $value = $this->value;

        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $ids = [];
        foreach ((array) $value as $item) {
            if (is_numeric($item) && (int) $item > 0) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Method to get the field options.
     *
     * @return  array  An array of HTMLHelper options.
     * @since   1.0.5
     */
    protected function getOptions(): array
    {
        $db = $this->getDatabase();

        $options = $this->useXmlOptions() ? parent::getOptions() : [];

        foreach ($this->getLeadingOptions() as $leadingOption) {
            $options[] = $leadingOption;
        }

        // 1. Danh sách chính, lọc theo trạng thái
        $states = $this->getVisibleStates();

        $query = $this->buildQuery($db);
        $query->where(
            $this->stateColumn . ' IN (' . implode(',', array_map('intval', $states)) . ')'
        );
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $loadedIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row->{$this->keyProperty} ?? 0);
            $loadedIds[] = $id;
            $options[] = HTMLHelper::_('select.option', $id, $this->buildOptionText($row));
        }

        // 2. Nạp bù các giá trị đang được chọn nhưng đã bị loại vì trạng thái
        $missingIds = array_diff($this->getSelectedIds(), $loadedIds);

        if ($missingIds) {
            foreach ($this->loadOrphanRows($db, $missingIds) as $row) {
                $options[] = HTMLHelper::_(
                    'select.option',
                    (int) ($row->{$this->keyProperty} ?? 0),
                    $this->buildOptionText($row) . $this->orphanSuffix
                );
            }
        }

        return $options;
    }

    /**
     * Nạp các bản ghi đang được field trỏ tới nhưng không thỏa điều kiện trạng thái.
     *
     * Cố ý KHÔNG áp bất kỳ điều kiện trạng thái nào — kể cả trạng thái của bảng
     * cha trong các truy vấn có JOIN — mục đích duy nhất là hiển thị lại đúng
     * nhãn của giá trị đang lưu.
     *
     * @param   DatabaseInterface  $db
     * @param   int[]              $ids
     *
     * @return  array  Mảng bản ghi.
     * @since   1.0.5
     */
    protected function loadOrphanRows(DatabaseInterface $db, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $query = $this->buildQuery($db);
        $query->clear('where');
        $query->where(
            $this->keyColumn . ' IN (' . implode(',', array_map('intval', $ids)) . ')'
        );

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }
}
