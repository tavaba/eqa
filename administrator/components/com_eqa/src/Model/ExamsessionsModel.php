<?php
namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;

class ExamsessionsModel extends ListModel
{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = ['nexaminee', 'nexamroom'];
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'start', $direction = 'desc'): void
	{
		parent::populateState($ordering, $direction);
	}

	public function getListQuery()
	{
		$db = $this->getDatabase();

		// Subquery: đếm số phòng thi của ca thi
		$subNExamroom = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__eqa_examrooms', 'er'))
			->where($db->quoteName('er.examsession_id') . ' = ' . $db->quoteName('a.id'));

		// Subquery con: lấy danh sách examroom_id thuộc ca thi
		$subExamroomIds = $db->getQuery(true)
			->select($db->quoteName('er2.id'))
			->from($db->quoteName('#__eqa_examrooms', 'er2'))
			->where($db->quoteName('er2.examsession_id') . ' = ' . $db->quoteName('a.id'));

		// Subquery: đếm thí sinh từ cả 2 bảng (KTHP + sát hạch) qua UNION ALL
		$subNExaminee = '(SELECT COUNT(*) FROM ('
			. 'SELECT ' . $db->quoteName('el.id')
			. ' FROM '  . $db->quoteName('#__eqa_exam_learner', 'el')
			. ' WHERE ' . $db->quoteName('el.examroom_id') . ' IN (' . $subExamroomIds . ')'
			. ' UNION ALL'
			. ' SELECT ' . $db->quoteName('al.id')
			. ' FROM '   . $db->quoteName('#__eqa_assessment_learner', 'al')
			. ' WHERE '  . $db->quoteName('al.examroom_id') . ' IN (' . $subExamroomIds . ')'
			. ') AS ' . $db->quoteName('combined_examinees') . ')';

		$columns = [
			$db->quoteName('a.id',           'id'),
			$db->quoteName('a.name',         'name'),
			$db->quoteName('b.name',         'examseason'),
			$db->quoteName('a.start',        'start'),
			$db->quoteName('a.flexible',     'flexible'),
			$db->quoteName('a.monitor_ids',  'monitor_ids'),
			$db->quoteName('a.examiner_ids', 'examiner_ids'),
			$db->quoteName('a.description',  'description'),
			'(' . $subNExamroom . ') AS ' . $db->quoteName('nexamroom'),
			$subNExaminee . ' AS '        . $db->quoteName('nexaminee'),
		];

		$query = $db->getQuery(true)
			->from($db->quoteName('#__eqa_examsessions', 'a'))
			->leftJoin(
				$db->quoteName('#__eqa_examseasons', 'b'),
				$db->quoteName('a.examseason_id') . ' = ' . $db->quoteName('b.id')
			)
			->select($columns);

		// Filtering: tìm kiếm theo tên
		$search = $this->getState('filter.search');
		if (!empty($search)) {
			$like = $db->quote('%' . trim($search) . '%');
			$query->where($db->quoteName('a.name') . ' LIKE ' . $like);
		}

		// Filtering: theo kỳ thi
		$examseasonId = $this->getState('filter.examseason_id');
		if (is_numeric($examseasonId)) {
			$query->where($db->quoteName('a.examseason_id') . ' = ' . (int) $examseasonId);
		}

		// Filtering: theo loại ca thi linh hoạt
		$flexible = $this->getState('filter.flexible');
		if (is_numeric($flexible)) {
			$query->where($db->quoteName('a.flexible') . ' = ' . (int) $flexible);
		}

		// Ordering
		$orderingCol = $db->escape($this->getState('list.ordering', 'start'));
		$orderingDir = $db->escape($this->getState('list.direction', 'desc'));
		$query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

		return $query;
	}
	public function getItems()
	{
		$items = parent::getItems();

		if (!empty($items)) {
			foreach ($items as $item) {
				// Chuyển monitor_ids từ chuỗi CSV thành array
				$item->monitor_ids = !empty($item->monitor_ids)
					? explode(',', $item->monitor_ids)
					: [];

				// Chuyển examiner_ids từ chuỗi CSV thành array
				$item->examiner_ids = !empty($item->examiner_ids)
					? explode(',', $item->examiner_ids)
					: [];
			}
		}

		return $items;
	}
}