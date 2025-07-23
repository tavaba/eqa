<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class MonitoringexamsModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('name', 'nexaminee', 'nexamroom', 'npaper', 'nnopaper', 'npackage');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id', 'a.name', 'a.testtype', 'a.usetestbank', 'a.questiondate', 'a.status', 'a.description'),
            array('id',   'name',   'testtype',    'usetestbank',  'questiondate',   'status',   'description')
        );

	    $subQueryNExaminee = 'SELECT COUNT(1) FROM #__eqa_exam_learner WHERE examroom_id IS NOT NULL AND exam_id = a.id';
	    $subQueryNExamroom = 'SELECT COUNT(DISTINCT examroom_id) FROM #__eqa_exam_learner WHERE examroom_id IS NOT NULL AND exam_id = a.id';

        $query =  parent::getListQuery();
        $query->from('#__eqa_exams AS a')
            ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
            ->select($columns)
	        ->select('('. $subQueryNExaminee . ') AS nexaminee')
	        ->select('('. $subQueryNExamroom . ') AS nexamroom');

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

		$examStatus = $this->getState('filter.exam_status');
		if(is_numeric($examStatus))
			$query->where('a.status=' . (int)$examStatus);

		$examId = $this->getState('filter.exam_id');
		if(is_numeric($examId))
			$query->where('a.id='.(int)$examId);

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