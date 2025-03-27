<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class SubjectsModel extends EqaListModel{
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('department_code','code','credits','finaltesttype','testbankyear','published', 'ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id','b.code','b.name','a.code', 'a.name','a.degree', 'a.credits', 'a.finaltesttype', 'a.testbankyear', 'a.published', 'a.ordering'),
            array('id','department_code','department_name','code','name','degree','credits', 'finaltesttype','testbankyear', 'published',  'ordering')
        );
        $query->from('#__eqa_subjects AS a')
            ->leftJoin('#__eqa_units AS b','a.unit_id = b.id')
            ->select($columns);

        /*
         * Special filter
         * Filter này được set và unset trong view 'Examseason' khi layout là 'addexams'
         * Ở đó, cần lấy danh sách các môn học (cũng tức là môn thi) để người dùng lựa chọn
         * nhằm thêm vào kỳ thi. Cần loại bớt những môn đã có sẵn trong kỳ thi đó.
         */
        $limitSubjectIds = $this->getState('filter.limit_subject_ids');
        if(is_array($limitSubjectIds)){
            $query->where($db->quoteName('a.id') . ' IN (' . implode(',', array_map('intval', $limitSubjectIds)) . ')');
        }

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('(a.code LIKE '.$like.' OR a.name LIKE '.$like.')');
        }

        $unit_id = $this->getState('filter.department_id');
        if(!empty($unit_id)){
            $query->where('a.unit_id = '.(int)$unit_id);
        }

        $degree = $this->getState('filter.degree');
        if(is_numeric($degree)){
            $query->where('a.degree = '.(int)$degree);
        }

        $finaltesttype = $this->getState('filter.testtype_code');
        if(is_numeric($finaltesttype)){
            $query->where('a.finaltesttype = '.(int)$finaltesttype);
        }

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','department_code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.department_id');
        $id .= ':' . $this->getState('filter.degree');
        $id .= ':' . $this->getState('filter.testtype_code');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }
}