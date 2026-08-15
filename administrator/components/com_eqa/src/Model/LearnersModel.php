<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Library\Kma\Helper\StateHelper;

class LearnersModel extends ListModel {
    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái (kể cả 'Đã lưu trữ' và 'Thùng rác').
     *
     * @var    int[]
     * @since  2.1.7
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','lastname','firstname','group','course','state');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'course', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id','a.group_id', 'a.code', 'a.lastname', 'a.firstname', 'a.debtor', 'a.state', 'b.code', 'c.code','c.admissionyear'),
            array('id',   'group_id',   'code',    'lastname',   'firstname', 'debtor',   'state',       'group', 'course','admissionyear')
        );
        $query->from('#__eqa_learners AS a')
            ->leftJoin('#__eqa_groups AS b','a.group_id = b.id')
            ->leftJoin('#__eqa_courses AS c', 'b.course_id=c.id')
            ->select($columns)
            ->where('(b.state = ' . StateHelper::STATE_PUBLISHED
                . ' AND c.state = ' . StateHelper::STATE_PUBLISHED . ')');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('(CONCAT(`a`.`lastname`, " ", `a`.`firstname`) LIKE ' . $like .' OR `a`.`code` LIKE ' . $like . ')');
        }

        $admissionyear = $this->getState('filter.admissionyear');
        if(is_numeric($admissionyear)){
            $query->where('c.admissionyear = '.(int)$admissionyear);
        }

	    $isDebtor = $this->getState('filter.debtor');
	    if(is_numeric($isDebtor)){
		    $query->where('a.debtor = '.(int)$isDebtor);
	    }

	    $course_id = $this->getState('filter.course_id');
        if(is_numeric($course_id)){
            $query->where('b.course_id = '.(int)$course_id);
        }

        $group_id = $this->getState('filter.group_id');
        if(is_numeric($group_id)){
            $query->where('a.group_id = '.(int)$group_id);
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Sorting
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);
        //Trong mọi trường hợp, sắp theo tên nữa cho đẹp
        if($orderingCol != 'firstname')
            $query->order('firstname '.$orderingDir);
        $query->order('lastname '.$orderingDir);

        return $query;
    }

}