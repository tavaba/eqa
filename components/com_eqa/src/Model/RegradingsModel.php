<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class RegradingsModel extends EqaListModel{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields']=array('examseason', 'exam');
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'exam', $direction = 'ASC')
	{
		parent::populateState($ordering, $direction);
	}

	public function getListQuery()
	{
		//Xây dựng truy vấn
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = array(
			'a.id AS id',
			'c.name AS examseason',
			'b.name AS exam',
			'a.status AS status',
			'd.credits AS credits',
			'e.lastname AS lastname',
			'e.firstname AS firstname',
			'e.code AS code',
			'`f`.`code` AS `group`',
			'g.code AS course'
		);

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->leftJoin('#__eqa_subjects AS d', 'd.id=b.subject_id')
			->leftJoin('#__eqa_learners AS e', 'e.id=a.learner_id')
			->leftJoin('#__eqa_groups AS f', 'f.id=e.group_id')
			->leftJoin('#__eqa_courses AS g', 'g.id=f.course_id');

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
			$query->where('(b.name LIKE ' . $search . ')');
		}

		$examseasonId = $this->getState('filter.examseason_id');
		if(is_numeric($examseasonId))
			$query->where('c.id='.$examseasonId);
		else if(is_null($examseasonId) || $examseasonId==="default")
			$query->where('`c`.`default`=1');
		$courseId = $this->getState('filter.course_id');
		if(is_numeric($courseId))
			$query->where('g.id=' . (int)$courseId);

		// Add the list ordering clause.
		$orderCol  = $this->getState('list.ordering', 'exam');
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
