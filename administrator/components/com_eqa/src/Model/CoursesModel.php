<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Library\Kma\Helper\StateHelper;

class CoursesModel extends ListModel{
    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái (kể cả 'Đã lưu trữ' và 'Thùng rác').
     *
     * @var    int[]
     * @since  2.1.7
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','admissionyear','degree','state','ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id','a.code','a.admissionyear', 'a.description', 'a.state', 'a.ordering', 'b.spec_id', 'b.name', 'b.degree'),
            array('id',   'code','admissionyear',    'description', 'state',      'ordering', 'spec_id', 'program', 'degree')
        );

        $query =  parent::getListQuery();
        $query->from('#__eqa_courses AS a')
            ->leftJoin('#__eqa_programs AS b','a.prog_id = b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('a.description LIKE '.$like);
        }

        $spec_id = $this->getState('filter.spec_id');
        if(is_numeric($spec_id)){
            $query->where('b.spec_id = '.(int)$spec_id);
        }

        $prog_id = $this->getState('filter.prog_id');
        if(is_numeric($prog_id)){
            $query->where('a.prog_id = '.(int)$prog_id);
        }

        $degree = $this->getState('filter.degree');
        if(is_numeric($degree)){
            $query->where('b.degree = '.(int)$degree);
        }

        $admissionyear = $this->getState('filter.admissionyear');
        if(is_numeric($admissionyear)){
            $query->where('a.admissionyear = '.(int)$admissionyear);
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);


        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
}