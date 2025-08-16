<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class GroupsModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id','code','course','admissionyear','size','published','ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id', 'a.code','a.size','a.homeroom_id','a.adviser_id', 'a.description', 'a.published', 'a.ordering', 'b.code','b.admissionyear'),
            array('id',    'code',   'size','homeroom',      'adviser',      'description',   'published',  'ordering', 'course','admissionyear')
        );

        $query =  parent::getListQuery();
        $query->from('#__eqa_groups AS a')
            ->leftJoin('#__eqa_courses AS b','a.course_id = b.id')
            ->select($columns)
            ->where('b.published >0');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
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

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.course');
        $id .= ':' . $this->getState('filter.homeroom');
        $id .= ':' . $this->getState('filter.adviser');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }
}