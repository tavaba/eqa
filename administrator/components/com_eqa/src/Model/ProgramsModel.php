<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Library\Kma\Helper\StateHelper;

class ProgramsModel extends ListModel
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
        $config['filter_fields']=array('degree','format','approach','state','ordering','speciality');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'speciality', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id', 'a.spec_id',  'a.name', 'a.degree','a.format','a.approach', 'a.firstrelease', 'a.lastupdate', 'a.description', 'a.state', 'a.ordering', 'b.code'),
            array('id',    'spec_id',    'name',   'degree','format','approach', 'firstrelease', 'lastupdate', 'description', 'state',       'ordering', 'speciality')
        );
        $query->from('#__eqa_programs AS a')
            ->leftJoin('#__eqa_specialities AS b','a.spec_id = b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('a.name LIKE '.$like);
        }

        $spec_id = $this->getState('filter.spec_id');
        if(is_numeric($spec_id)){
            $query->where('a.spec_id = '.(int)$spec_id);
        }

        $degree = $this->getState('filter.degree');
        if(is_numeric($degree)){
            $query->where('a.degree = '.(int)$degree);
        }

        $format = $this->getState('filter.format');
        if(is_numeric($format)){
            $query->where('a.format = '.(int)$format);
        }

        $approach = $this->getState('filter.approach');
        if(is_numeric($approach)){
            $query->where('a.approach = '.(int)$approach);
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','speciality'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
}
