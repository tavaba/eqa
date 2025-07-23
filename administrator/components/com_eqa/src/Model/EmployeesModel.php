<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class EmployeesModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','unit_code','firstname','published', 'ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'firstname', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id','b.code','b.name','a.code', 'a.lastname', 'a.firstname', 'a.email', 'a.mobile', 'a.published', 'a.ordering'),
            array('id','unit_code','unit_name','code','lastname','firstname', 'email','mobile', 'published',  'ordering')
        );
        $query->from('#__eqa_employees AS a')
            ->leftJoin('#__eqa_units AS b','a.unit_id = b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('(concat_ws(" ",`lastname`,`firstname`) LIKE '.$like.' OR a.code LIKE '.$like .')');
        }

        $unit_id = $this->getState('filter.unit_id');
        if(!empty($unit_id)){
            $query->where('a.unit_id = '.(int)$unit_id);
        }

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }

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
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.unit_id');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }
}