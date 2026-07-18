<?php
namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/**
 * Model liệt kê HVSV của một lớp học phần kèm kết quả học tập:
 * điểm quá trình (từ #__eqa_class_learner) và kết quả LẦN THI CUỐI
 * (bản ghi #__eqa_exam_learner có exam_id lớn nhất cho cặp
 * (class_id, learner_id) với điều kiện conclusion IS NOT NULL).
 *
 * Phân quyền: chỉ giảng viên phụ trách lớp (classes.lecturer_id) được xem.
 * Admin/superuser không có đặc quyền đối với chức năng này.
 *
 * @since 2.1.5
 */
class EmployeeClassLearnersModel extends ListModel
{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = ['search'];
		parent::__construct($config, $factory);
	}

	/**
	 * Chỉ giảng viên phụ trách lớp học phần mới được xem danh sách này.
	 *
	 * @return bool
	 * @since 2.1.5
	 */
	public function canViewList(): bool
	{
		$classId = (int) $this->getState('filter.class_id');
		if (empty($classId))
			return false;

		$signedInEmployeeId = GeneralHelper::getSignedInEmployeeId();
		if (empty($signedInEmployeeId))
			return false;

		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select($db->quoteName('lecturer_id'))
			->from($db->quoteName('#__eqa_classes'))
			->where($db->quoteName('id') . ' = ' . $classId);
		$db->setQuery($query);
		$lecturerId = (int) $db->loadResult();

		return !empty($lecturerId) && $lecturerId === $signedInEmployeeId;
	}

	/**
	 * @return DatabaseQuery|null
	 * @since 2.1.5
	 */
	public function getListQuery()
	{
		$classId = (int) $this->getState('filter.class_id');
		if (empty($classId))
			return null;

		$db = DatabaseHelper::getDatabaseDriver();

		// Derived table: lần thi cuối cùng (exam_id lớn nhất, đã có kết luận)
		// của từng cặp (class_id, learner_id)
		$lastAttemptQuery = $db->getQuery(true)
			->select([
				$db->quoteName('class_id'),
				$db->quoteName('learner_id'),
				'MAX(' . $db->quoteName('exam_id') . ') AS ' . $db->quoteName('exam_id'),
			])
			->from($db->quoteName('#__eqa_exam_learner'))
			->where($db->quoteName('conclusion') . ' IS NOT NULL')
			->where($db->quoteName('class_id') . ' = ' . $classId)
			->group([$db->quoteName('class_id'), $db->quoteName('learner_id')]);

		$columns = [
			$db->quoteName('b.id',                 'learnerId'),
			$db->quoteName('b.code',               'code'),
			$db->quoteName('b.lastname',           'lastname'),
			$db->quoteName('b.firstname',          'firstname'),
			$db->quoteName('c.code',               'group'),
			$db->quoteName('a.pam1',               'pam1'),
			$db->quoteName('a.pam2',               'pam2'),
			$db->quoteName('a.pam',                'pam'),
			$db->quoteName('a.allowed',            'allowed'),
			$db->quoteName('el.attempt',           'attempt'),
			$db->quoteName('el.mark_final',        'finalMark'),
			$db->quoteName('el.module_mark',       'moduleMark'),
			$db->quoteName('el.module_base4_mark', 'moduleBase4Mark'),
			$db->quoteName('el.module_grade',      'moduleGrade'),
			$db->quoteName('el.conclusion',        'conclusion'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_class_learner', 'a'))
			->innerJoin(
				$db->quoteName('#__eqa_learners', 'b'),
				$db->quoteName('b.id') . ' = ' . $db->quoteName('a.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_groups', 'c'),
				$db->quoteName('c.id') . ' = ' . $db->quoteName('b.group_id')
			)
			->leftJoin(
				'(' . $lastAttemptQuery . ') AS ' . $db->quoteName('last'),
				$db->quoteName('last.class_id') . ' = ' . $db->quoteName('a.class_id')
				. ' AND ' . $db->quoteName('last.learner_id') . ' = ' . $db->quoteName('a.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_exam_learner', 'el'),
				$db->quoteName('el.class_id') . ' = ' . $db->quoteName('last.class_id')
				. ' AND ' . $db->quoteName('el.learner_id') . ' = ' . $db->quoteName('last.learner_id')
				. ' AND ' . $db->quoteName('el.exam_id') . ' = ' . $db->quoteName('last.exam_id')
			)
			->where($db->quoteName('a.class_id') . ' = ' . $classId);

		// Filtering: tìm kiếm theo mã hoặc tên HVSV
		$search = $this->getState('filter.search');
		if (!empty($search))
		{
			$like = $db->quote('%' . trim($search) . '%');
			$query->where('(' . $db->quoteName('b.code') . ' LIKE ' . $like
				. ' OR ' . $db->quoteName('b.firstname') . ' LIKE ' . $like
				. ' OR ' . $db->quoteName('b.lastname') . ' LIKE ' . $like . ')');
		}

		// Ordering theo cấu hình chung (giống ClassModel::exportPams)
		if (ConfigHelper::getPersonSortOrder() === 'code')
		{
			$query->order($db->quoteName('b.code') . ' ASC');
		}
		else
		{
			$query->order($db->quoteName('b.firstname') . ' ASC');
			$query->order($db->quoteName('b.lastname') . ' ASC');
		}

		return $query;
	}

	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.class_id');
		$id .= ':' . $this->getState('filter.search');
		return parent::getStoreId($id);
	}
}
