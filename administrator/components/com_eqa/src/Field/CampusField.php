<?php

namespace Kma\Component\Eqa\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Field\StateAwareListField;

/**
 * Danh sách chọn 'cơ sở đào tạo' (campus).
 *
 * Được dùng trong form của các thực thể quản lý riêng theo cơ sở (lớp học phần,
 * kỳ thi, kỳ sát hạch, tòa nhà...) và trong các filter form.
 *
 * @since 2.1.6
 */
class CampusField extends StateAwareListField
{
    /**
     * @var string
     * @since 2.1.6
     */
    protected $type = 'campus';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    /**
     * Field này tự sinh toàn bộ option, không lấy từ thẻ <option> của form XML.
     *
     * @return  bool
     * @since   2.1.7
     */
    protected function useXmlOptions(): bool
    {
        return false;
    }

    /**
     * Hai lựa chọn phục vụ BỘ LỌC danh sách.
     *
     * Ở edit form, field thường để readonly và mang một cơ sở cụ thể — khi đó
     * KHÔNG thêm hai lựa chọn này vì chúng chỉ có nghĩa trong bộ lọc (2.1.6).
     *
     * @return  array
     * @since   2.1.7
     */
    protected function getLeadingOptions(): array
    {
        if ($this->readonly) {
            return [];
        }

        return [
            HTMLHelper::_('select.option', null, '- Cơ sở đang làm việc -'),
            HTMLHelper::_('select.option', 0, '(Tất cả cơ sở)'),
        ];
    }

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->select([$db->quoteName('a.id'), $db->quoteName('a.name')])
            ->from($db->quoteName('#__eqa_campuses', 'a'))
            ->order($db->quoteName('a.ordering') . ' ASC');
    }

    protected function buildOptionText(object $row): string
    {
        return (string) $row->name;
    }
}
