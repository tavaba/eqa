<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\RatingHelper;

class ConductsModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('learnerCode', 'firstname', 'excusedAbsenceCount','unexcusedAbsenceCount', 'retakeCount',
	        'resitCount', 'awardCount', 'disciplinaryCount', 'totalCredits',
	        'academicScore', 'academicRating', 'conductScore', 'conductRating');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'firstname', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
	public function getListQuery()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.id')                      . ' AS ' . $db->quoteName('id'),
			$db->quoteName('e.code')                    . ' AS ' . $db->quoteName('academicyear'),
			$db->quoteName('a.term')                    . ' AS ' . $db->quoteName('termCode'),
			$db->quoteName('d.code')                    . ' AS ' . $db->quoteName('course'),
			$db->quoteName('c.code')                    . ' AS ' . $db->quoteName('group'),
			$db->quoteName('b.code')                    . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('b.firstname')               . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('b.lastname')                . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('a.excused_absence_count')   . ' AS ' . $db->quoteName('excusedAbsenceCount'),
			$db->quoteName('a.unexcused_absence_count') . ' AS ' . $db->quoteName('unexcusedAbsenceCount'),
			$db->quoteName('a.resit_count')             . ' AS ' . $db->quoteName('resitCount'),
			$db->quoteName('a.retake_count')            . ' AS ' . $db->quoteName('retakeCount'),
			$db->quoteName('a.award_count')             . ' AS ' . $db->quoteName('awardCount'),
			$db->quoteName('a.disciplinary_action_count')   . ' AS ' . $db->quoteName('disciplinaryCount'),
			$db->quoteName('a.total_credits')           . ' AS ' . $db->quoteName('totalCredits'),
			$db->quoteName('a.academic_score')          . ' AS ' . $db->quoteName('academicScore'),
			$db->quoteName('a.academic_rating')         . ' AS ' . $db->quoteName('academicRating'),
			$db->quoteName('a.conduct_score')           . ' AS ' . $db->quoteName('conductScore'),
			$db->quoteName('a.conduct_rating')          . ' AS ' . $db->quoteName('conductRating'),
			$db->quoteName('a.note')                    . ' AS ' . $db->quoteName('note'),
			$db->quoteName('a.description')             . ' AS ' . $db->quoteName('description'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_conducts AS a')
			->leftJoin('#__eqa_learners AS b','b.id = a.learner_id')
			->leftJoin('#__eqa_groups AS c','c.id = b.group_id')
			->leftJoin('#__eqa_courses AS d', 'd.id = c.course_id')
			->leftJoin('#__eqa_academicyears AS e', 'e.id = a.academicyear_id');

		//Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering','code'));
		$orderingDir = $query->db->escape($this->getState('list.direction','asc'));
		$query->order($db->quoteName($orderingCol).' '.$orderingDir);
		if($orderingCol==='firstname')
			$query->order('lastname ' . $orderingDir);

		//Filtering
		$search = $this->getState('filter.search');
		if(!empty($search)){
			$like = $db->quote('%'.trim($search).'%');
			$query->where('(CONCAT(`b`.`lastname`, " ", `b`.`firstname`) LIKE ' . $like .' OR `b`.`code` LIKE ' . $like . ')');
		}

		$academicyear_id = $this->getState('filter.academicyear_id');
		if(is_numeric($academicyear_id))
			$query->where('`a`.`academicyear_id`='.(int)$academicyear_id);

		$term = $this->getState('filter.term');
		if(is_numeric($term))
			$query->where('`a`.`term`='.(int)$term);

		$course_id = $this->getState('filter.course_id');
		if(is_numeric($course_id))
			$query->where('`d`.`id`='.(int)$course_id);

		$group_id = $this->getState('filter.group_id');
		if(is_numeric($group_id))
			$query->where('`c`.`id`='.(int)$group_id);

		return $query;
	}
    public function getStoreId($id = '')
    {
	    $id .= ':' . $this->getState('filter.search');
	    $id .= ':' . $this->getState('filter.academicyear_id');
	    $id .= ':' . $this->getState('filter.term');
	    $id .= ':' . $this->getState('filter.course_id');
	    $id .= ':' . $this->getState('filter.group_id');
        return parent::getStoreId($id);
    }

	public function getListByTerm(int $academicyearId, int $term):array
	{
		$db = $this->getDatabase();
		$query = $this->getListQuery();
		$query->clear('where')
			->where('`a`.`academicyear_id`='.$academicyearId)
			->where('`a`.`term`='.$term);
		$db->setQuery($query);
		return $db->loadObjectList();
	}

	public function caclculateAcacdemicYearResults(int $academicyearId, ?int $courseId):void
	{
		$db = DatabaseHelper::getDatabaseDriver();
		/*
		 * STEPS TO CALCULATE ACADEMIC YEAR RESULTS:
		 *
		 * 1. Get all the records in the table #__eqa_conducts that belong to
		 *    the given academicyear  and course (if specified), except those with term = 0.
		 * 2. Group the records by learner id.
		 * 3. For each group of records:
		 *  - Sum up the values for excused_absence_count,
		 *    unexcused_absence_count, resit_count, retake_count, award_count and disciplinary_action_count.
		 *  - Calculate academic score as the weighted average of the academic_score,
		 *    where weights are total_credit
		 *  - Calculate conduct score as the average of the conduct_score
		 *  - Calculate academic rating based on academic score using RatingHelper::rateAcademicScore().
		 *  - Calculate conduct rating based on conduct score using RatingHelper::rateConductScore().
		 * 4. Create or update the record in the table #__eqa_conducts for each learner for term =0
		 *    with the calculated results.
		 */

		//Step 1. Get data
		$columns = [
			'c.id',
			'c.learner_id',
			'c.excused_absence_count',
			'c.unexcused_absence_count',
			'c.resit_count',
			'c.retake_count',
			'c.award_count',
			'c.dissciplinary_action_count',
			'c.total_credits',
			'c.academic_score',
			'c.conduct_score',
			'c.note'
		];
		$query = $db->getQuery(true)
			->select('c.*')
			->from('#__eqa_conducts AS c')
			->where('c.academicyear_id='.$academicyearId)
			->where('c.term <> '. DatetimeHelper::TERM_NONE);
		if($courseId)
		{
			$query->leftJoin('#__eqa_learners AS l','l.id=c.learner_id')
				->leftJoin('#__eqa_groups AS g','g.id=l.group_id')
				->where('g.course_id='.$courseId);
		}
		$db->setQuery($query);
		$conducts = $db->loadAssocList();
		if(!$conducts)
			throw new Exception('Không có thông tin để tính toán');

		//Step 2, 3. Group data
		$learners = [];
		foreach ($conducts as $conduct) {
			$id = $conduct['learner_id'];
			unset($conduct['learner_id']);

			//Initialize if not exists
			if (!isset($learners[$id])) {
				$learners[$id] = [
					'excused_absence_count'=>0,
					'unexcused_absence_count'=>0,
					'resit_count'=>0,
					'retake_count'=>0,
					'award_count'=>0,
					'disciplinary_action_count'=>0,
					'total_credits'=>0,
					'academic_score'=>0,
					'term_count'=>0,
					'conduct_score'=>0,
				];
			}

			//Update
			$learners[$id]['excused_absence_count'] += $conduct['excused_absence_count'];
			$learners[$id]['unexcused_absence_count'] += $conduct['unexcused_absence_count'];
			$learners[$id]['resit_count'] += $conduct['resit_count'];
			$learners[$id]['retake_count'] += $conduct['retake_count'];
			$learners[$id]['award_count'] += $conduct['award_count'];
			$learners[$id]['disciplinary_action_count'] += $conduct['disciplinary_action_count'];

			//Update credits and academic scores
			$currentCredits = $learners[$id]['total_credits'];
			$currentAcademicScore = $learners[$id]['academic_score'];
			$termCredits = $conduct['total_credits'];
			$termAcademicScore = $conduct['academic_score'];
			$totalCredits = $currentCredits + $termCredits;
			if($totalCredits==0)
			{
				$msg = 'Số tín chỉ bằng 0, không thể tính toán: ' . print_r($conduct,true);
				throw new  Exception($msg);
			}
			$learners[$id]['total_credits'] = $totalCredits;
			$learners[$id]['academic_score'] = ($currentCredits*$currentAcademicScore + $termCredits*$termAcademicScore)/$totalCredits;

			//Update conduct scores
			$currentTermCount = $learners[$id]['term_count'];
			$learners[$id]['term_count']++;
			$learners[$id]['conduct_score'] = ($currentTermCount*$learners[$id]['conduct_score'] +  $conduct['conduct_score'])/($currentTermCount+1);

			//Update note
			$learners[$id]['note'] = $conduct['note'];
		}

		//Step 4. Update database
		$time = DatetimeHelper::getCurrentHanoiDatetime();
		$username = Factory::getApplication()->getIdentity()->username;
		$quotedTime = $db->quote($time);
		$quotedUsername = $db->quote($username);
		$columns = [
			'academicyear_id',
			'term',
			'learner_id',
			'excused_absence_count',
			'unexcused_absence_count',
			'resit_count',
			'retake_count',
			'award_count',
			'disciplinary_action_count',
			'total_credits',
			'academic_score',
			'academic_rating',
			'conduct_score',
			'conduct_rating',
			'note',
			'created_at',
			'created_by'
		];
		$columnPart = implode(', ', $db->quoteName($columns));
		$tuples = [];
		foreach ($learners as $id=>$properties){
			$properties['academic_score'] = round($properties['academic_score'],2);
			$properties['conduct_score'] = round($properties['conduct_score']);
			$values = [
				$academicyearId,
				DatetimeHelper::TERM_NONE,
				$id,
				$properties['excused_absence_count'],
				$properties['unexcused_absence_count'],
				$properties['resit_count'],
				$properties['retake_count'],
				$properties['award_count'],
				$properties['disciplinary_action_count'],
				$properties['total_credits'],
				$properties['academic_score'],
				$db->quote(RatingHelper::rateAcademicScore($properties['academic_score'])),
				$properties['conduct_score'],
				$db->quote(RatingHelper::rateConductScore($properties['conduct_score'])),
				is_null($properties['note'])?'NULL':$db->quote($properties['note']),
				$quotedTime,
				$quotedUsername
			];
			$tuples[] = '(' . implode(', ', $values) . ')';
		}
		$insertPart = implode(',', $tuples);
		$updateFields = [];
		foreach ($columns as $col) {
			if(in_array($col,['academicyear_id', 'term', 'learner_id', 'created_at', 'created_by']))
				continue;
			$updateFields[] = $db->quoteName($col) . '=VALUES('.$db->quoteName($col).')';
		}
		$updateFields[] = $db->quoteName('updated_at').'='.$quotedTime;
		$updateFields[] = $db->quoteName('updated_by').'='.$quotedUsername;
		$updatePart = implode(', ', $updateFields);

		$query = "INSERT INTO #__eqa_conducts ($columnPart) 
			VALUES {$insertPart}
			ON DUPLICATE KEY UPDATE {$updatePart}";
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception($this->getError());
	}
}