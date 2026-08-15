<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\CampusListModel;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * List model của 'phòng' (room).
 *
 * Phòng kế thừa cơ sở đào tạo từ tòa nhà (building_id → campus_id), nên điều
 * kiện lọc được áp trên bảng #__eqa_buildings đã JOIN sẵn.
 *
 * @since 1.0.0
 */
class RoomsModel extends CampusListModel
{
    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái (kể cả 'Đã lưu trữ' và 'Thùng rác').
     *
     * @var    int[]
     * @since  2.1.7
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = array('code', 'type', 'capacity', 'state', 'ordering', 'building', 'campus_id');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'building', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * @since 2.1.6
     */
    protected function getCampusColumn(): string
    {
        return 'b.campus_id';
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id', 'a.building_id', 'a.code', 'a.type', 'a.capacity', 'a.state', 'a.ordering', 'b.code'),
            array('id',    'building_id',   'code',   'type',   'capacity',   'state',       'ordering', 'building')
        );
        $query->from('#__eqa_rooms AS a')
            ->leftJoin('#__eqa_buildings AS b', 'a.building_id = b.id')
            ->select($columns);

        // Lọc theo cơ sở đào tạo, suy diễn qua tòa nhà (2.1.6)
        $this->applyCampusScope($query);

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . trim($search) . '%');
            $query->where('a.code LIKE ' . $like);
        }

        $building_id = $this->getState('filter.building_id');
        if (!empty($building_id)) {
            $query->where('a.building_id = ' . (int) $building_id);
        }

        $type = $this->getState('filter.type');
        if (is_numeric($type)) {
            $query->where('a.type = ' . (int) $type);
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'code'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);
        $query->order('code asc');

        return $query;
    }

}
