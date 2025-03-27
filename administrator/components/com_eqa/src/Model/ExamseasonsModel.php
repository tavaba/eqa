<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class ExamseasonsModel extends EqaListModel{
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('academicyear','term','type','attermpt','nexam','default','completed');
        parent::__construct($config, $factory);
    }
    public function populateState($ordering = 'academicyear', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
		$subQueryNumberOfExamsessions = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_examsessions AS z')
			->where('z.examseason_id = a.id');
	    $subQueryNumberOfExams = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_exams AS y')
		    ->where('y.examseason_id = a.id');
        $columns = $db->quoteName(
            array('a.id','b.code', 'a.term', 'a.type', 'a.name', 'a.attempt', 'a.default', 'a.start', 'a.finish', 'a.ppaa_req_enabled', 'a.ppaa_req_deadline', 'a.statistic', 'a.description', 'a.completed'),
            array('id', 'academicyear','term', 'type',  'name',  'attempt',   'default',   'start',   'finish',   'ppaa_req_enabled',   'ppaa_req_deadline',   'statistic', 'description','completed')
        );

        $query =  parent::getListQuery();
        $query->from('#__eqa_examseasons AS a')
            ->leftJoin('#__eqa_academicyears AS b','a.academicyear_id = b.id')
            ->select($columns)
	        ->select('(' . $subQueryNumberOfExamsessions . ') AS nexamsession')
	        ->select('(' . $subQueryNumberOfExams . ') AS nexam');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('a.name LIKE '.$like);
        }

        $academicyear_id = $this->getState('filter.academicyear_id');
        if(is_numeric($academicyear_id)){
            $query->where('a.academicyear_id = '.(int)$academicyear_id);
        }

        $term = $this->getState('filter.term');
        if(is_numeric($term)){
            $query->where('a.term = '.(int)$term);
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type)){
            $query->where('a.type = '.(int)$type);
        }

        $completed = $this->getState('filter.completed');
        if(is_numeric($completed)){
            $query->where('a.completed = '.(int)$completed);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','academicyear'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.schoolyear');
        $id .= ':' . $this->getState('filter.type_code');
        $id .= ':' . $this->getState('filter.completed');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }
}