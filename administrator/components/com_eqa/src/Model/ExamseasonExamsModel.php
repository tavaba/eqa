<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Library\Kma\Model\ListModel;

class ExamseasonExamsModel extends ListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('nexaminee','nexamroom','testtype','duration','kmonitor','kassess','status');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
		//Determin the examseason id from state. This must be set in the view.
	    $examseasonId = $this->getState('filter.examseason_id');
		if (empty($examseasonId))
			throw new Exception('Không xác định được kỳ thi');

        $db = $this->getDatabase();
	    $subExamineeCount = 'SELECT COUNT(learner_id) FROM #__eqa_exam_learner WHERE exam_id=a.id';
	    $subExamroomCount = 'SELECT COUNT(DISTINCT examroom_id) FROM #__eqa_exam_learner WHERE examroom_id IS NOT NULL AND exam_id=a.id';

	    // Số thí sinh có quyền dự thi thực sự (loại trừ: không được phép thi,
	    // nợ học phí, được miễn thi hoặc quy đổi điểm)
	    $subEligibleCount = 'SELECT COUNT(1)'
		    . ' FROM #__eqa_exam_learner AS el'
		    . ' INNER JOIN #__eqa_class_learner AS cl'
		    .   ' ON cl.class_id = el.class_id AND cl.learner_id = el.learner_id'
		    . ' LEFT JOIN #__eqa_stimulations AS st'
		    .   ' ON st.id = el.stimulation_id'
		    . ' WHERE el.exam_id = a.id'
		    .   ' AND cl.allowed <> 0'
		    .   ' AND el.debtor = 0'
		    .   ' AND (el.stimulation_id IS NULL'
		    .     ' OR st.type NOT IN ('
		    .       StimulationHelper::TYPE_EXEMPT . ',' . StimulationHelper::TYPE_TRANS
		    .     '))';

	    $columns = $db->quoteName(
            array('a.id','b.name',  'a.code', 'a.name','a.testtype','a.status', 'a.usetestbank', 'a.questiondeadline',  'a.description'),
            array('id', 'examseason', 'code', 'name',  'testtype',  'status',   'usetestbank',  'questiondeadline',    'description')
        );
        $query =  parent::getListQuery();
        $query->from('#__eqa_exams AS a')
            ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
            ->select($columns)
	        ->select('('.$subExamineeCount.') AS nexaminee')
	        ->select('('.$subEligibleCount.') AS neligible')
	        ->select('('.$subExamroomCount.') AS nexamroom')
            ->where('a.examseason_id = '.(int)$examseasonId);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('a.name LIKE '.$like);
        }
		$subjectId = $this->getState('filter.subject_id');
		if(is_numeric($subjectId))
			$query->where('a.subject_id = '.(int)$subjectId);

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
        $id .= ':' . $this->getState('filter.testtype');
        $id .= ':' . $this->getState('filter.usetestbank');
        $id .= ':' . $this->getState('filter.status');
        return parent::getStoreId($id);
    }
}