<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Library\Kma\Helper\DatabaseHelper;

class ExamroomsModel extends ListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','start','nexaminee','nanomaly', 'exam_id');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'start', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();
	    $subQueryNumberOfExamExaminees = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_exam_learner AS z')
		    ->where('z.examroom_id = a.id');
	    $subQueryNumberOfExamAnomalies = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_exam_learner AS y')
		    ->where('y.examroom_id = a.id AND y.anomaly <> 0');
	    $subQueryNumberOfAssessmentExaminees = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_assessment_learner AS x')
		    ->where('x.examroom_id = a.id');
	    $subQueryNumberOfAssessmentAnomalies = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_exam_learner AS w')
		    ->where('w.examroom_id = a.id AND w.anomaly <> 0');
		$columns = [
			$db->quoteName('a.id',          'id'),
			$db->quoteName('b.code',        'code'),
			$db->quoteName('c.start',       'start'),
			$db->quoteName('c.name',        'examsessionName'),
			$db->quoteName('a.exam_ids',    'examIds'),
			$db->quoteName('a.monitor1_id', 'monitor1Id'),
			$db->quoteName('a.monitor2_id', 'monitor2Id'),
			$db->quoteName('a.monitor3_id', 'monitor3Id'),
			$db->quoteName('a.examiner1_id','examiner1Id'),
			$db->quoteName('a.examiner2_id','examiner2Id'),
			$db->quoteName('c.id',          'examsessionId'),
			'(' . $subQueryNumberOfExamExaminees . ') AS nExamExaminee',
			'(' . $subQueryNumberOfExamAnomalies . ') AS nExamAnomaly',
			'(' . $subQueryNumberOfAssessmentExaminees . ') AS nAssessmentExaminee',
			'(' . $subQueryNumberOfAssessmentAnomalies . ') AS nAssessmentAnomaly'
		];
        $query =  $db->getQuery(true)
	        ->select($columns)
            ->from('#__eqa_examrooms AS a')
            ->leftJoin('#__eqa_rooms AS b', 'b.id = a.room_id')
            ->leftJoin('#__eqa_examsessions AS c', 'c.id = a.examsession_id');

        //Filtering
	    $exam_id = $this->getState('filter.exam_id');
		if(is_numeric($exam_id)){
			$exam_id = $db->quote($exam_id);
			$query->where('FIND_IN_SET(' . $exam_id . ', a.exam_ids) > 0');
		}

        $examseasonId = $this->getState('filter.examseason_id');
        if(is_numeric($examseasonId)){
            $query->where('c.examseason_id='.(int)$examseasonId);
        }

        $examsessionId = $this->getState('filter.examsession_id');
        if(is_numeric($examsessionId)){
            $query->where('c.id='.(int)$examsessionId);
        }

        $examdate = $this->getState('filter.examdate');
        if(!empty($examdate)){
            $query->where('DATE(`c`.`start`)='.$db->quote($examdate));
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.exam_id');
		$id .= ':' . $this->getState('filter.examseason_id');
		$id .= ':' . $this->getState('filter.examsession_id');
		$id .= ':' . $this->getState('filter.examdate');
		return parent::getStoreId($id);
	}
}