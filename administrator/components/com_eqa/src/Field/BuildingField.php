<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Library\Kma\Field\StateAwareListField;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * Danh sách chọn tòa nhà.
 *
 * Chỉ liệt kê tòa nhà thuộc cơ sở đào tạo mà người dùng đang làm việc (2.1.6).
 *
 * @since  1.6
 */
class BuildingField extends StateAwareListField
{
    protected $type = 'building';

    protected string $stateColumn = 'b.state';
    protected string $keyColumn   = 'b.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        $columns = [
            $db->quoteName('b.id',   'id'),
            $db->quoteName('b.code', 'code'),
            $db->quoteName('c.code', 'campusCode'),
        ];

        $query = $db->getQuery(true)
            ->select($columns)
            ->from($db->quoteName('#__eqa_buildings', 'b'))
            ->leftJoin($db->quoteName('#__eqa_campuses', 'c') . ' ON c.id = b.campus_id')
            ->order($db->quoteName('b.code') . ' ASC');

        /**
         * Chỉ liệt kê tòa nhà thuộc các cơ sở người dùng được phép (2.1.6)
         * @var EqaComponent $component
         */
        $component     = ComponentHelper::getComponent();
        $campusService = $component->getCampusService();
        $activeId      = $campusService->getActiveCampusId();
        $allowedIds    = $activeId > 0 ? [$activeId] : [];

        $query->where(
            $db->quoteName('b.campus_id') . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')'
        );

        return $query;
    }

    protected function buildOptionText(object $row): string
    {
        return sprintf('%s (%s)', $row->code, $row->campusCode);
    }
}
