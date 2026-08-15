<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Traits\CampusScopedList;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * List model của 'người lao động' (employee).
 *
 * Người lao động là dữ liệu DÙNG CHUNG; cơ sở đào tạo được suy diễn qua đơn vị
 * công tác (unit_id → units.campus_id). Danh sách tra cứu KHÔNG bị lọc mặc
 * định theo cơ sở đang làm việc.
 *
 * LƯU Ý: việc phân công coi thi/chấm thi (cụm 4) thì NGƯỢC LẠI — danh sách ứng
 * viên phải lọc cứng theo cơ sở của kỳ thi.
 *
 * @since 1.0.0
 */
class EmployeesModel extends ListModel
{
    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái (kể cả 'Đã lưu trữ' và 'Thùng rác').
     *
     * @var    int[]
     * @since  2.1.7
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    use CampusScopedList;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = array('code','unit_code','firstname','state', 'ordering', 'campus_id', 'campus_name');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'firstname', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id','b.code','b.name','a.code', 'a.lastname', 'a.firstname', 'a.email', 'a.mobile', 'a.state', 'a.ordering', 'c.name'),
            array('id','unit_code','unit_name','code','lastname','firstname', 'email','mobile', 'state',      'ordering', 'campus_name')
        );
        $query->from('#__eqa_employees AS a')
            ->leftJoin('#__eqa_units AS b','a.unit_id = b.id')
            ->leftJoin('#__eqa_campuses AS c','b.campus_id = c.id')
            ->select($columns);

        // Bộ lọc cơ sở đào tạo dạng tùy chọn, suy diễn qua đơn vị (2.1.6)
        $this->applyOptionalCampusFilter($query, 'b.campus_id');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('(concat_ws(" ",`lastname`,`firstname`) LIKE '.$like.' OR a.code LIKE '.$like .')');
        }

        $unit_id = $this->getState('filter.unit_id');
        if(!empty($unit_id)){
            $query->where('a.unit_id = '.(int)$unit_id);
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','unit_code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);
        //Trong mọi trường hợp, sắp theo tên nữa cho đẹp
        if($orderingCol != 'firstname')
            $query->order('firstname '.$orderingDir);
        $query->order('lastname '.$orderingDir);

        return $query;
    }

}
