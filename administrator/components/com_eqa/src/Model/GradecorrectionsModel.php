<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Interface\Regradingrequest;
use stdClass;

defined('_JEXEC') or die();

class GradecorrectionsModel extends EqaListModel
{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields']=array('a.id', 'examseason', 'examName');
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'a.id', $direction = 'DESC')
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
			$db->quoteName('a.id')           . ' AS ' . $db->quoteName('id'),
			$db->quoteName('a.exam_id')      . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('d.name')         . ' AS ' . $db->quoteName('examName'),
			$db->quoteName('a.learner_id')   . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('a.constituent')  . ' AS ' . $db->quoteName('constituentCode'),
			$db->quoteName('b.code')         . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('b.lastname')     . ' AS ' . $db->quoteName('learnerLastname'),
			$db->quoteName('b.firstname')    . ' AS ' . $db->quoteName('learnerFirstname'),
			$db->quoteName('a.status')       . ' AS ' . $db->quoteName('statusCode'),
			$db->quoteName('f.pam1')         . ' AS ' . $db->quotename('pam1'),
			$db->quoteName('f.pam2')         . ' AS ' . $db->quotename('pam2'),
			$db->quoteName('c.mark_orig')    . ' AS ' . $db->quotename('finalExamMark'),
			$db->quoteName('a.reason')       . ' AS ' . $db->quotename('reason'),
			$db->quoteName('a.changed')      . ' AS ' . $db->quotename('changed'),
			$db->quoteName('a.description')  . ' AS ' . $db->quotename('description'),
			$db->quoteName('a.handled_by')   . ' AS ' . $db->quotename('handledBy'),
			$db->quoteName('a.handled_at')   . ' AS ' . $db->quotename('handledAt'),
			$db->quoteName('a.updated_by')   . ' AS ' . $db->quotename('updatedBy'),
			$db->quoteName('a.updated_at')   . ' AS ' . $db->quotename('updatedAt'),
			$db->quoteName('g.lastname')     . ' AS ' . $db->quotename('reviewerLastname'),
			$db->quoteName('g.firstname')    . ' AS ' . $db->quotename('reviewerFirstname'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id = a.learner_id')
			->leftJoin('#__eqa_exam_learner AS c', 'c.exam_id=a.exam_id AND c.learner_id=a.learner_id')
			->leftJoin('#__eqa_exams AS d', 'd.id=a.exam_id')
			->leftJoin('#__eqa_classes AS e', 'e.id=c.class_id')
			->leftJoin('#__eqa_class_learner AS f', 'f.class_id=e.id AND f.learner_id=b.id')
			->leftJoin('#__eqa_employees AS g', 'g.id=a.reviewer_id');
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
			$query->where('a.status='.ExamHelper::EXAM_PPAA_STATUS_ACCEPTED);

		//3. Execute
		$db->setQuery($query);
		return $db->loadObjectList();
	}

}
