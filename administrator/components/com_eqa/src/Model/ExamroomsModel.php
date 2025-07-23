<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class ExamroomsModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','start','nexaminee','nanomaly');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'start', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
	    $subQueryNumberOfExaminees = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_exam_learner AS z')
		    ->where('z.examroom_id = a.id');
	    $subQueryNumberOfAnomalies = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_exam_learner AS z')
		    ->where('z.examroom_id = a.id AND z.anomaly <> 0');
        $columns = $db->quoteName(
            array('a.id', 'b.code', 'c.start', 'c.name',      'a.exam_ids'),
            array('id',   'code',   'start',   'examsession', 'exam_ids')
        );
        $query =  $db->getQuery(true)
            ->from('#__eqa_examrooms AS a')
            ->leftJoin('#__eqa_rooms AS b', 'a.room_id=b.id')
            ->leftJoin('#__eqa_examsessions AS c', 'a.examsession_id=c.id')
	        ->select($columns)
	        ->select('(' . $subQueryNumberOfExaminees . ') AS nexaminee')
	        ->select('(' . $subQueryNumberOfAnomalies . ') AS nanomaly');
            //->order($db->quoteName('start'). ' DESC');

        //Filtering
	    $exam_id = $this->getState('filter.exam_id');
		if(is_numeric($exam_id)){
			$exam_id = $db->quote($exam_id);
			$query->where('FIND_IN_SET(' . $exam_id . ', exam_ids) > 0');
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