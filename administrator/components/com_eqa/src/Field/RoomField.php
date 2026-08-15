<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Component\Eqa\Administrator\Helper\RoomHelper;
use Kma\Library\Kma\Field\StateAwareListField;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * Danh sách chọn phòng.
 *
 * @since  1.6
 */
class RoomField extends StateAwareListField
{
    protected $type = 'room';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        $columns = $db->quoteName(
            array('a.id', 'b.code',   'a.code', 'a.maxcapacity', 'a.capacity', 'a.type'),
            array('id',   'building', 'code',   'maxcapacity',   'capacity',   'type')
        );

        $query = $db->getQuery(true)
            ->select($columns)
            ->from($db->quoteName('#__eqa_rooms', 'a'))
            ->leftJoin($db->quoteName('#__eqa_buildings', 'b') . ' ON a.building_id = b.id')
            ->order($db->quoteName('building') . ' ASC')
            ->order($db->quoteName('code') . ' ASC');

        /*
         * Trạng thái của tòa nhà đặt ở mệnh đề WHERE thường (xem GroupField để
         * biết lý do), riêng trạng thái của phòng do lớp cơ sở tự thêm.
         */
        $query->where($db->quoteName('b.state') . ' = ' . StateHelper::STATE_PUBLISHED);

        return $query;
    }

    protected function buildOptionText(object $row): string
    {
        return $row->building . '-' . $row->code
            . ' (' . $row->capacity . ', ' . RoomHelper::roomType($row->type) . ')';
    }
}
