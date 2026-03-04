<?php

namespace Kma\Component\Eqa\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Custom form field: danh sách môn học có môn thi tương ứng trong bảng
 * #__eqa_secondattempts. Dùng cho bộ lọc của view SecondAttempts.
 *
 * Chỉ liệt kê những môn học mà `#__eqa_exams.subject_id` của ít nhất một
 * bản ghi trong `#__eqa_secondattempts` trỏ tới.
 *
 * @since 2.0.3
 */
class SecondAttemptSubjectField extends ListField
{
	/** @var string Tên type dùng trong XML form */
	protected $type = 'secondattemptsubject';

	/**
	 * Xây dựng danh sách option cho dropdown môn học.
	 *
	 * Query logic:
	 *   1. Lấy tập hợp subject_id phân biệt từ các môn thi (exam)
	 *      đang có mặt trong bảng secondattempts (qua last_exam_id).
	 *   2. JOIN với bảng subjects để lấy code và name.
	 *   3. Sắp xếp theo code môn học.
	 *
	 * @return array
	 * @since 2.0.4
	 */
	protected function getOptions(): array
	{
		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('su.id'),
				$db->quoteName('su.code'),
				$db->quoteName('su.name'),
			])
			->from($db->quoteName('#__eqa_subjects', 'su'))
			->join(
				'INNER',
				$db->quoteName('#__eqa_exams', 'ex') .
				' ON ' . $db->quoteName('ex.subject_id') . ' = ' . $db->quoteName('su.id')
			)
			->join(
				'INNER',
				$db->quoteName('#__eqa_secondattempts', 'sa') .
				' ON ' . $db->quoteName('sa.last_exam_id') . ' = ' . $db->quoteName('ex.id')
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