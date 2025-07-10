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
use Kma\Component\Eqa\Administrator\Interface\Regradingrequest;
use stdClass;

defined('_JEXEC') or die();

class GradecorrectionsModel extends EqaListModel
{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields']=array('a.id', 'examseason', 'examName');
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'a.id', $direction = 'DESC')
	{
		parent::populateState($ordering, $direction);
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
			$db->quoteName('a.handled_by')   . ' AS ' . $db->quotename('handlerId'),
			$db->quoteName('a.reviewer_id')  . ' AS ' . $db->quotename('reviewerId'),
			$db->quoteName('a.changed')      . ' AS ' . $db->quotename('changed'),
			$db->quoteName('a.description')  . ' AS ' . $db->quotename('description')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id = a.learner_id')
			->leftJoin('#__eqa_exam_learner AS c', 'c.exam_id=a.exam_id AND c.learner_id=a.learner_id')
			->leftJoin('#__eqa_exams AS d', 'd.id=a.exam_id')
			->leftJoin('#__eqa_classes AS e', 'e.id=c.class_id')
			->leftJoin('#__eqa_class_learner AS f', 'f.class_id=e.id AND f.learner_id=b.id');
		return $query;
	}
	public function getListQuery()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $this->initListQuery();

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
