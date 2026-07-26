<?php
namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/**
 * Model liệt kê các lớp học phần mà một giảng viên đã/đang giảng dạy
 * (#__eqa_classes.lecturer_id).
 *
 * Phân quyền: giảng viên chỉ xem được các lớp của chính mình; View set
 * state 'filter.employee_id' từ phiên đăng nhập. Admin/superuser không
 * có đặc quyền đối với chức năng này.
 *
 * @since 2.1.5
 */
class EmployeeClassesModel extends ListModel
{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = ['academicyear', 'term', 'subject_id'];
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'a.academicyear', $direction = 'DESC'): void
	{
		parent::populateState($ordering, $direction);
	}

	/**
	 * Chỉ cho phép giảng viên xem dữ liệu của chính mình.
	 *
	 * @return bool
	 * @since 2.1.5
	 */
	public function canViewList(): bool
	{
		$employeeId = (int) $this->getState('filter.employee_id');
		if (empty($employeeId))
			return false;

		$signedInEmployeeId = GeneralHelper::getSignedInEmployeeId();
		return !empty($signedInEmployeeId) && $signedInEmployeeId === $employeeId;
	}

	/**
	 * @return DatabaseQuery|null
	 * @since 2.1.5
	 */
	public function getListQuery()
	{
		$employeeId = (int) $this->getState('filter.employee_id');
		if (empty($employeeId))
			return null;

		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.id',           'id'),
			$db->quoteName('a.code',         'code'),
			$db->quoteName('b.name',         'subjectName'),
			$db->quoteName('a.academicyear', 'academicyear'),
			$db->quoteName('a.term',         'term'),
			$db->quoteName('a.size',         'size'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_classes', 'a'))
			->leftJoin(
				$db->quoteName('#__eqa_subjects', 'b'),
				$db->quoteName('b.id') . ' = ' . $db->quoteName('a.subject_id')
			)
			->where($db->quoteName('a.lecturer_id') . ' = ' . $employeeId);

		// Filtering: tìm kiếm theo mã lớp hoặc tên môn học
		$search = $this->getState('filter.search');
		if (!empty($search))
		{
			$like = $db->quote('%' . trim($search) . '%');
			$query->where('(' . $db->quoteName('a.code') . ' LIKE ' . $like
				. ' OR ' . $db->quoteName('b.name') . ' LIKE ' . $like . ')');
		}

		// Filtering: năm học, học kỳ, môn học
		$academicyear = $this->getState('filter.academicyear');
		if (is_numeric($academicyear))
			$query->where($db->quoteName('a.academicyear') . ' = ' . (int) $academicyear);

		$term = $this->getState('filter.term');
		if (is_numeric($term))
			$query->where($db->quoteName('a.term') . ' = ' . (int) $term);

		$subjectId = $this->getState('filter.subject_id');
		if (is_numeric($subjectId))
			$query->where($db->quoteName('a.subject_id') . ' = ' . (int) $subjectId);

		// Ordering
		$orderingCol = $db->escape($this->getState('list.ordering', 'a.academicyear'));
		$orderingDir = $db->escape($this->getState('list.direction', 'DESC'));
		$query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);
		$query->order($db->quoteName('a.term') . ' DESC');
		$query->order($db->quoteName('a.code') . ' ASC');

		return $query;
	}
}
