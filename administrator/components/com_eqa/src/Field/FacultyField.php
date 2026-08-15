<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;
use Kma\Library\Kma\Field\StateAwareListField;

/**
 * Danh sách chọn khoa (đơn vị đào tạo cấp 1).
 *
 * @since  1.6
 */
class FacultyField extends StateAwareListField
{
    protected $type = 'faculty';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    /**
     * Field này cố ý KHÔNG dùng các thẻ <option> khai báo trong form XML;
     * mục dẫn hướng được sinh cứng ở getLeadingOptions().
     *
     * @return  bool
     * @since   2.1.7
     */
    protected function useXmlOptions(): bool
    {
        return false;
    }

    protected function getLeadingOptions(): array
    {
        return [HTMLHelper::_('select.option', '', '- Khoa phụ trách -')];
    }

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->select('a.id, a.code, a.name')
            ->from($db->quoteName('#__eqa_units', 'a'))
            ->where($db->quoteName('a.parent_id') . ' = 0')
            ->where($db->quoteName('a.type') . ' = ' . (int) UnitHelper::UNIT_TYPE_EDUCATION)
            ->order($db->quoteName('a.code') . ' ASC');
    }

    protected function buildOptionText(object $row): string
    {
        return $row->code . ' - ' . $row->name;
    }
}
