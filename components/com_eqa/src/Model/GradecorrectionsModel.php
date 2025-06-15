<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class GradecorrectionsModel extends EqaListModel{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields']=array('examseason', 'examName');
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'examName', $direction = 'ASC')
	{
		parent::populateState($ordering, $direction);
	}

	public function getListQuery()
	{
		//Xây dựng truy vấn
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
			$db->quoteName('a.examiner1_id') . ' AS ' . $db->quotename('examiner1Id'),
			$db->quoteName('a.examiner2_id') . ' AS ' . $db->quotename('examiner2Id'),
			$db->quoteName('a.result')       . ' AS ' . $db->quotename('resut'),
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


		//Trong trường hợp model được gọi bởi view=regradings&layout=learnerrequests
		//Thì View sẽ set giá trị cấu hình này để giới hạn việc truy vấn các bản ghi
		//của một thí sinh cụ thể.
		$learnerId = $this->getState('learner_id');
		if(is_int($learnerId))
			$query->where('a.learner_id=' . $learnerId);


		// Filter by search in title.
		$search = $this->getState('filter.search');
		if (!empty($search))
		{
			$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
			$query->where('(d.name LIKE ' . $search . ')');
		}

		$examseasonId = $this->getState('filter.examseason_id');
		if(is_numeric($examseasonId))
			$query->where('d.examseason_id='.$examseasonId);
		else if(is_null($examseasonId) || $examseasonId==="default")
			$query->where('d.examseason_id=' . DatabaseHelper::getDefaultExamseason()->id);

		// Add the list ordering clause.
		$orderCol  = $this->getState('list.ordering', 'examName');
		$orderDirn = $this->getState('list.direction', 'ASC');

		$query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

		return $query;
	}

	public function getItemsWithoutPagination()
	{
		$db = $this->getDatabase();
		$db->setQuery($this->getListQuery());
		return $db->loadObjectList();
	}

	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.examseason_id');
		return parent::getStoreId($id);
	}
}
