<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Traits\CampusScopedList;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * List model của 'lớp hành chính' (group).
 *
 * Lớp hành chính là dữ liệu DÙNG CHUNG; cột campus_id chỉ là thuộc tính thông
 * tin (cho biết lớp học ở cơ sở nào) và là nguồn xác định cơ sở cho biểu mẫu
 * đánh giá rèn luyện. Không dùng để chặn quyền.
 *
 * @since 1.0.0
 */
class GroupsModel extends ListModel
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
        $config['filter_fields'] = array('id', 'code', 'course', 'admissionyear', 'size', 'state', 'ordering', 'campus_id', 'campus_name');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id', 'a.code','a.size','a.homeroom_id','a.adviser_id', 'a.description', 'a.state', 'a.ordering', 'b.code','b.admissionyear', 'c.name'),
            array('id',    'code',   'size','homeroom',      'adviser',      'description',   'state',      'ordering', 'course','admissionyear', 'campus_name')
        );

        $query =  parent::getListQuery();
        $query->from('#__eqa_groups AS a')
            ->leftJoin('#__eqa_courses AS b','a.course_id = b.id')
            ->leftJoin('#__eqa_campuses AS c','a.campus_id = c.id')
            ->select($columns)
            ->where('b.state = ' . StateHelper::STATE_PUBLISHED);

        // Bộ lọc cơ sở đào tạo dạng tùy chọn (2.1.6)
        $this->applyOptionalCampusFilter($query, 'a.campus_id');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('a.name LIKE '.$like);
        }

        $course_id = $this->getState('filter.course');
        if(is_numeric($course_id)){
            $query->where('a.course_id = '.(int)$course_id);
        }

        $homeroom_id = $this->getState('filter.homeroom');
        if(is_numeric($homeroom_id)){
            $query->where('a.homeroom_id = '.(int)$homeroom_id);
        }

        $adviser_id = $this->getState('filter.adviser');
        if(is_numeric($adviser_id)){
            $query->where('a.adviser_id = '.(int)$adviser_id);
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
}
