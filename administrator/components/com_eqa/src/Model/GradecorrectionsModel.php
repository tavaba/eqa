<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

defined('_JEXEC') or die();

class GradecorrectionsModel extends ListModel
{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields']=array('a.id', 'examseason', 'examName');
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'a.id', $direction = 'DESC'): void
	{
		parent::populateState($ordering, $direction);
	}
	public function canViewList(): bool
	{
		//1. Check if the user has manage permission on this component
		$acceptedPermissions = ['core.manage', 'eqa.supervise'];
		if(GeneralHelper::checkPermissions($acceptedPermissions))
			return true;

		//2. Or if he/she is the selected learner that views his/her own information
		//a. There must be a learner ID
		$selectedLearnerId = $this->getState('filter.learner_id');
		if(empty($selectedLearnerId))
			return false;

		//b. And the corresponding learner code must exist...
		$db = DatabaseHelper::getDatabaseDriver();
		$db->setQuery('SELECT `code` FROM #__eqa_learners WHERE id='.$selectedLearnerId);
		$learnerCode = $db->loadResult();
		if(empty($learnerCode))
			return false;

		//c. ... and match with signed-in user's learner code
		$signedInLearnerCode = GeneralHelper::getSignedInLearnerCode();
		if (empty($signedInLearnerCode) || ($learnerCode != $signedInLearnerCode))
			return false;
		return true;
	}
	protected function initListQuery(): DatabaseQuery
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.id',                  'id'),
			$db->quoteName('a.exam_id',             'examId'),
			$db->quoteName('d.name',                'examName'),
			$db->quoteName('a.learner_id',          'learnerId'),
			$db->quoteName('a.constituent',         'constituentCode'),
			$db->quoteName('b.code',                'learnerCode'),
			$db->quoteName('b.lastname',            'learnerLastname'),
			$db->quoteName('b.firstname',           'learnerFirstname'),
			$db->quoteName('a.status',              'statusCode'),
			$db->quoteName('f.pam1',                'pam1'),
			$db->quoteName('f.pam2',                'pam2'),
			$db->quoteName('c.mark_orig',           'finalExamMark'),
			$db->quoteName('a.reason',              'reason'),
			$db->quoteName('a.changed',             'changed'),
			$db->quoteName('a.description',         'description'),
			$db->quoteName('u_handled.name',        'handlerName'),
			$db->quoteName('a.handled_by_username', 'handlerUsername'),
			$db->quoteName('a.handled_at',          'handledAt'),
			$db->quoteName('u_modified.name',       'modifierName'),
			$db->quoteName('a.modified_at',         'modifiedAt'),
			$db->quoteName('g.lastname',            'reviewerLastname'),
			$db->quoteName('g.firstname',           'reviewerFirstname'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id = a.learner_id')
			->leftJoin('#__eqa_exam_learner AS c', 'c.exam_id=a.exam_id AND c.learner_id=a.learner_id')
			->leftJoin('#__eqa_exams AS d', 'd.id=a.exam_id')
			->leftJoin('#__eqa_classes AS e', 'e.id=c.class_id')
			->leftJoin('#__eqa_class_learner AS f', 'f.class_id=e.id AND f.learner_id=b.id')
			->leftJoin('#__eqa_employees AS g', 'g.id=a.reviewer_id')
			->leftJoin('#__users AS u_handled','u_handled.id=a.handled_by')
			->leftJoin('#__users AS u_modified','u_modified.id=a.modified_by');
		return $query;
	}
	public function getListQuery()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $this->initListQuery();

		//Filter by learner id (that should be set by a View)
		$learnerId = $this->getState('filter.learner_id');
		if(is_numeric($learnerId))
			$query->where('a.learner_id='.(int)$learnerId);


		//Filtering
		$examseasonId = $this->getState('filter.examseason_id');
		if(is_numeric($examseasonId))
		{
			if($examseasonId==0)
				$query->where('d.examseason_id = ' . DatabaseHelper::getDefaultExamseason()->id);
			else
				$query->where('d.examseason_id = '.(int)$examseasonId);
		}


		$status = $this->getState('filter.status');
		if(is_numeric($status))
			$query->where('a.status=' . (int)$status);

		//Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering','a.id'));
		$orderingDir = $query->db->escape($this->getState('list.direction','desc'));
		$query->order($db->quoteName($orderingCol).' '.$orderingDir);

		return $query;
	}

	public function getStoreId($id = '') {
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.examseason_id');
		$id .= ':' . $this->getState('filter.status');
		return parent::getStoreId($id);
	}

	public function getFilteredExamseasonId(): ?int
	{
		$filter = $this->getState('filter.examseason_id');
		if(is_numeric($filter))
			return $filter;
		return null;
	}
	public function getSelectedExamseasonId(): ?int
	{
		$filter = $this->getState('filter.examseason_id');
		if(is_numeric($filter))
			return $filter;
		return null;
	}
	public function getAllItems(bool $onlyAccepted=false) : array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Limit results to some specific examseasons
		$examseasonId = $this->getState('filter.examseason_id');
		if(!is_numeric($examseasonId))
			throw new Exception("Bạn cần chọn kỳ thi để tải danh sách");

		//2. Build the query
		$query = $this->initListQuery();
		if($examseasonId==0)
			$examseasonId = DatabaseHelper::getDefaultExamseason()->id;
		$query->where('d.examseason_id='.$examseasonId);
		if ($onlyAccepted)
			$query->where('a.status='.PpaaStatus::Accepted->value);

		//3. Execute
		$db->setQuery($query);
		return $db->loadObjectList();
	}

}
