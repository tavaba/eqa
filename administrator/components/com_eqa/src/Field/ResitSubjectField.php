<?php

namespace Kma\Component\Eqa\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

/**
 * Custom form field: danh sách môn học có môn thi tương ứng trong DANH SÁCH
 * THI LẦN 2 đang mở. Dùng cho bộ lọc của view ResitExaminees.
 *
 * Chỉ liệt kê những môn học mà `#__eqa_exams.subject_id` của ít nhất một
 * bản ghi trong `#__eqa_resit_learner` (thuộc danh sách đó) trỏ tới.
 *
 * @since 2.0.3
 */
class ResitSubjectField extends ListField
{
	/** @var string Tên type dùng trong XML form */
	protected $type = 'resitsubject';

	/**
	 * Xây dựng danh sách option cho dropdown môn học.
	 *
	 * Query logic:
	 *   1. Lấy tập hợp subject_id phân biệt từ các môn thi (exam)
	 *      đang có mặt trong bảng resitexaminees (qua last_exam_id).
	 *   2. JOIN với bảng subjects để lấy code và tên phân biệt.
	 *   3. Sắp xếp theo code môn học.
	 *
	 * @return array
	 * @since 2.0.4
	 * @updated 2.1.7  Nhãn option dùng tên phân biệt thay cho tên chính thức.
	 * @updated 2.1.8  Chỉ xét các bản ghi thuộc danh sách thi lần 2 đang mở.
	 */
	protected function getOptions(): array
	{
		//Bộ lọc chỉ có nghĩa trong phạm vi danh sách đang mở (2.1.8)
		$resitId = (int) Factory::getApplication()->getInput()->getInt('resit_id');

		if ($resitId <= 0) {
			return parent::getOptions();
		}

		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('su.id'),
				$db->quoteName('su.code'),
				DatabaseHelper::displayNameExpr('su') . ' AS ' . $db->quoteName('name'),
			])
			->from($db->quoteName('#__eqa_subjects', 'su'))
			->join(
				'INNER',
				$db->quoteName('#__eqa_exams', 'ex') .
				' ON ' . $db->quoteName('ex.subject_id') . ' = ' . $db->quoteName('su.id')
			)
			->join(
				'INNER',
				$db->quoteName('#__eqa_resit_learner', 'sa') .
				' ON ' . $db->quoteName('sa.last_exam_id') . ' = ' . $db->quoteName('ex.id') .
				' AND ' . $db->quoteName('sa.resit_id') . ' = ' . $resitId
			)
			->group($db->quoteName('su.id'))
			->order($db->quoteName('su.code') . ' ASC');

		$db->setQuery($query);
		$subjects = $db->loadObjectList();

		$options = parent::getOptions();

		foreach ($subjects as $subject) {
			$label     = $subject->code . ' - ' . $subject->name;
			$options[] = HTMLHelper::_('select.option', $subject->id, $label);
		}

		return $options;
	}
}