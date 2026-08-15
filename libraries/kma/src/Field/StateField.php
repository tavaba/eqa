<?php
namespace Kma\Library\Kma\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * Custom form field quản lý trạng thái của đối tượng, dùng chung cho mọi
 * component xây trên lib_kma.
 *
 * Khai báo trong form XML:
 *
 *   <!-- Form sửa item, thực thể danh mục (đủ 4 trạng thái) -->
 *   <field name="state" type="state" label="Trạng thái" default="1" required="true"/>
 *
 *   <!-- Form sửa item, thực thể vận hành (chỉ bật/tắt) -->
 *   <field name="state" type="state" states="1,0" label="Trạng thái" default="1"/>
 *
 *   <!-- Bộ lọc danh sách -->
 *   <field name="state" type="state" mode="filter" label="Trạng thái"
 *          onchange="this.form.submit();"/>
 *
 * Lưu ý: form XML sử dụng field này phải khai báo prefix của lib_kma, ví dụ:
 *   <fields name="filter" addfieldprefix="Kma\Library\Kma\Field">
 * Nếu form đã khai một prefix khác (của component), Joomla cho phép liệt kê
 * nhiều prefix cách nhau bởi dấu phẩy.
 *
 * @since 1.0.5
 */
class StateField extends ListField
{
    /**
     * The form field type.
     *
     * @var string
     * @since 1.0.5
     */
    protected $type = 'state';

    /**
     * Chế độ hiển thị: 'edit' (form sửa item) hoặc 'filter' (bộ lọc danh sách).
     *
     * @return  string
     * @since   1.0.5
     */
    protected function getMode(): string
    {
        $mode = (string) ($this->element['mode'] ?? 'edit');

        return $mode === 'filter' ? 'filter' : 'edit';
    }

    /**
     * Tập trạng thái được phép chọn, lấy từ thuộc tính XML 'states'.
     *
     * @return  int[]
     * @since   1.0.5
     */
    protected function getStates(): array
    {
        return StateHelper::normalizeStates(
            $this->element['states'] ?? null,
            StateHelper::STATES_FULL
        );
    }

    /**
     * Ở chế độ 'filter', có sinh thêm option 'Tất cả' hay không.
     *
     * @return  bool
     * @since   1.0.5
     */
    protected function showAllOption(): bool
    {
        $value = $this->element['showall'] ?? null;

        if ($value === null) {
            return true;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Method to get the field options.
     *
     * Ở chế độ 'filter', option rỗng đứng đầu ứng với hành vi mặc định của
     * {@see \Kma\Library\Kma\Model\ListModel::applyStateFilter()} — chỉ hiển thị
     * các bản ghi đang được sử dụng. Option 'Tất cả' (giá trị '*') bỏ hoàn toàn
     * điều kiện lọc.
     *
     * @return  array  An array of HTMLHelper options.
     * @since   1.0.5
     */
    protected function getOptions(): array
    {
        $isFilter = $this->getMode() === 'filter';

        // Các option khai báo trực tiếp bằng thẻ <option> trong XML (nếu có)
        $options = parent::getOptions();

        if ($isFilter) {
            $prompt = (string) ($this->element['prompt'] ?? Text::_('JOPTION_SELECT_PUBLISHED'));

            array_unshift(
                $options,
                HTMLHelper::_('select.option', '', $prompt)
            );
        }

        foreach (StateHelper::getOptions($this->getStates()) as $option) {
            $options[] = $option;
        }

        if ($isFilter && $this->showAllOption()) {
            $options[] = HTMLHelper::_(
                'select.option',
                StateHelper::FILTER_ALL,
                Text::_('JALL')
            );
        }

        return $options;
    }
}
