<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class ExamsModel extends EqaListModel{
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('nexaminee','nexamroom','testtype','duration','kmonitor','kassess','status');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
	    $subExamineeCount = 'SELECT COUNT(learner_id) FROM #__eqa_exam_learner WHERE exam_id=a.id';
	    $subExamroomCount = 'SELECT COUNT(DISTINCT examroom_id) FROM #__eqa_exam_learner WHERE examroom_id IS NOT NULL AND exam_id=a.id';


	    $columns = $db->quoteName(
            array('a.id','b.name',   'a.name','a.testtype','a.status', 'a.usetestbank', 'a.questiondeadline',  'a.description'),
            array('id', 'examseason', 'name',  'testtype',  'status',   'usetestbank',  'questiondeadline',    'description')
        );
        $query =  parent::getListQuery();
        $query->from('#__eqa_exams AS a')
            ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
            ->select($columns)
	        ->select('('.$subExamineeCount.') AS nexaminee')
	        ->select('('.$subExamroomCount.') AS nexamroom');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('a.name LIKE '.$like);
        }

        $examseasonId = $this->getState('filter.examseason_id');
        if(is_numeric($examseasonId))
            $query->where('a.examseason_id = '.(int)$examseasonId);

        $academicyear_id = $this->getState('filter.academicyear_id');
        if(is_numeric($academicyear_id)){
            $query->where('b.academicyear_id = '.(int)$academicyear_id);
        }

        $term = $this->getState('filter.term');
        if(is_numeric($term)){
            $query->where('b.term = '.(int)$term);
        }

        $testtype = $this->getState('filter.testtype');
        if(is_numeric($testtype)){
            $query->where('a.testtype = '.(int)$testtype);
        }

        $usetestbank = $this->getState('filter.usetestbank');
        if(is_numeric($usetestbank)){
            $query->where('a.usetestbank = '.(int)$usetestbank);
        }

        $status = $this->getState('filter.status');
        if(is_numeric($status)){
            $query->where('a.status = '.(int)$status);
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
        $id .= ':' . $this->getState('filter.examseason_id');
        $id .= ':' . $this->getState('filter.academicyear_id');
        $id .= ':' . $this->getState('filter.term');
        $id .= ':' . $this->getState('filter.testtype');
        $id .= ':' . $this->getState('filter.usetestbank');
        $id .= ':' . $this->getState('filter.status');
        return parent::getStoreId($id);
    }
}