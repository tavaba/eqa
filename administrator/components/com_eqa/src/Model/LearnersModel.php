<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class LearnersModel extends EqaListModel {
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','lastname','firstname','group','course','published');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'course', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id','a.group_id', 'a.code', 'a.lastname', 'a.firstname', 'a.debtor', 'a.published', 'b.code', 'c.code','c.admissionyear'),
            array('id',   'group_id',   'code',    'lastname',   'firstname', 'debtor',   'published',   'group', 'course','admissionyear')
        );
        $query->from('#__eqa_learners AS a')
            ->leftJoin('#__eqa_groups AS b','a.group_id = b.id')
            ->leftJoin('#__eqa_courses AS c', 'b.course_id=c.id')
            ->select($columns)
            ->where('(b.published>0 AND c.published>0)');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
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

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }

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

    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.course_id');
        $id .= ':' . $this->getState('filter.group_id');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }

}