<?php

namespace Kma\Component\Eqa\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Danh sách chọn 'cơ sở đào tạo' (campus).
 *
 * Được dùng trong form của các thực thể quản lý riêng theo cơ sở (lớp học phần,
 * kỳ thi, kỳ sát hạch, tòa nhà...) và trong các filter form.
 *
 * @since 2.1.6
 */
class CampusField extends ListField
{
    /**
     * @var string
     * @since 2.1.6
     */
    protected $type = 'campus';

    /**
     * Danh sách cơ sở đào tạo đang được kích hoạt.
     *
     * @return  array  Mảng các option của HTMLHelper
     * @since   2.1.6
     */
    protected function getOptions(): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('name')])
            ->from($db->quoteName('#__eqa_campuses'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $rows = $db->setQuery($query)->loadAssocList('id', 'name');

        $options = [];

        // Ở edit form, field thường để readonly và mang một cơ sở cụ thể — khi đó
        // KHÔNG thêm hai lựa chọn phục vụ filter ('cơ sở đang làm việc', 'tất cả
        // cơ sở'), vì chúng chỉ có nghĩa trong bộ lọc danh sách (2.1.6).
        if (!$this->readonly) {
            $options[] = HTMLHelper::_('select.option', null, '- Cơ sở đang làm việc -');
            $options[] = HTMLHelper::_('select.option', 0, '(Tất cả cơ sở)');
        }

        foreach ($rows as $id => $name) {
            $options[] = HTMLHelper::_('select.option', $id, $name);
        }

        return $options;
    }
}
