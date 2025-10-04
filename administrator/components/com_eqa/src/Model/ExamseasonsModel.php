<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class ExamseasonsModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'academicyear','term','type','attermpt','nexam','default','completed');
        parent::__construct($config, $factory);
    }
    public function populateState($ordering = 'id', $direction = 'DESC')
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
	    $subQueryNumberOfEntries = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_exam_learner AS x')
		    ->leftJoin('#__eqa_exams AS w','w.id=x.exam_id')
		    ->where('w.examseason_id = a.id');
        $columns = $db->quoteName(
            array('a.id','b.code', 'a.term', 'a.type', 'a.name', 'a.attempt', 'a.default', 'a.start', 'a.finish', 'a.ppaa_req_enabled', 'a.ppaa_req_deadline', 'a.statistic', 'a.description', 'a.completed'),
            array('id', 'academicyear','term', 'type',  'name',  'attempt',   'default',   'start',   'finish',   'ppaa_req_enabled',   'ppaa_req_deadline',   'statistic', 'description','completed')
        );

        $query =  parent::getListQuery();
        $query->from('#__eqa_examseasons AS a')
            ->leftJoin('#__eqa_academicyears AS b','a.academicyear_id = b.id')
            ->select($columns)
	        ->select('(' . $subQueryNumberOfExamsessions . ') AS nexamsession')
	        ->select('(' . $subQueryNumberOfExams . ') AS nexam')
	        ->select('(' . $subQueryNumberOfEntries . ') AS nentry');

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
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
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

	/**
	 * Get a list of examinees who have failed exams and/ or deferred them.
	 * The examinees must still retain their eligibility to take exams.
	 * Search is performed on all exams in all exam seasons.
	 *
	 * @return array of stdClass objects
	 *
	 * @since 1.1.2
	 */
	public function getUnpassedExaminees(): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('c.id')                  . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('c.code')                . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('c.lastname')            . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('c.firstname')           . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('e.id')                  . ' AS ' . $db->quoteName('subjectId'),
			$db->quoteName('e.code')                . ' AS ' . $db->quoteName('subjectCode'),
			$db->quoteName('e.name')                . ' AS ' . $db->quoteName('subjectName'),
			$db->quoteName('e.finaltesttype')       . ' AS ' . $db->quoteName('testType'),
			$db->quoteName('e.finaltestduration')   . ' AS ' . $db->quoteName('testDuration'),
			$db->quoteName('d.term')                . ' AS ' . $db->quoteName('term'),
			$db->quoteName('f.code')                . ' AS ' . $db->quoteName('academicyear'),
			$db->quoteName('a.exam_id')             . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('a.class_id')            . ' AS ' . $db->quoteName('classId'),
			$db->quoteName('b.ntaken')              . ' AS ' . $db->quoteName('ntaken'),
			$db->quoteName('a.conclusion')          . ' AS ' . $db->quoteName('conclusion'),
		];

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->leftJoin('#__eqa_classes AS d', 'd.id=a.class_id')
			->leftJoin('#__eqa_subjects AS e', 'e.id=d.subject_id')
			->leftJoin('#__eqa_academicyears AS f', 'f.id=d.academicyear_id')
			->where('a.conclusion IN (' . implode(',', [ExamHelper::CONCLUSION_FAILED, ExamHelper::CONCLUSION_DEFERRED]) . ')')
			->where('b.expired=0')
			// Add condition to keep only records with maximum exam_id per (code, subject_id)
			// (maximum exam_id means last exam for given subject)
			->where('a.exam_id = (
            SELECT MAX(a2.exam_id) 
            FROM #__eqa_exam_learner AS a2
            LEFT JOIN #__eqa_class_learner AS b2 ON b2.class_id=a2.class_id AND b2.learner_id=a2.learner_id
            LEFT JOIN #__eqa_learners AS c2 ON c2.id=a2.learner_id
            LEFT JOIN #__eqa_classes AS d2 ON d2.id=a2.class_id
            LEFT JOIN #__eqa_subjects AS e2 ON e2.id=d2.subject_id
            WHERE c2.code = c.code 
            AND e2.id = e.id
            AND a2.conclusion IN (' . implode(',', [ExamHelper::CONCLUSION_FAILED, ExamHelper::CONCLUSION_DEFERRED]) . ')
            AND b2.expired=0
        )');

		$db->setQuery($query);
		return $db->loadObjectList();
	}

}