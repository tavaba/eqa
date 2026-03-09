<?php

namespace Kma\Component\Eqa\Administrator\Rule;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Form\Form;
use Joomla\Registry\Registry;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use SimpleXMLElement;

/**
 * Kiểm tra tính hợp lệ của mã lớp học phần.
 *
 * Cấu trúc mã lớp học phần:
 *   [Mã môn học]-[Học kỳ]-[2 chữ số cuối năm học]([Nhóm khóa học]-[STT 2 chữ số])
 *   Ví dụ: CLC1ATATAT2-1-25(A18-KN01), ATCTKM3-1-25(A19C7-01)
 *
 * @since  2.0.4
 */
class ClasscodeRule extends FormRule
{
	/**
	 * @param   SimpleXMLElement  $element
	 * @param   mixed             $value
	 * @param   string|null       $group
	 * @param   Registry|null     $input
	 * @param   Form|null         $form
	 *
	 * @return  bool
	 * @since   2.0.4
	 */
	public function test(
		SimpleXMLElement $element,
		                 $value,
		                 $group = null,
		?Registry $input = null,
		?Form $form = null
	): bool {
		if (!isset($input) || !($input instanceof Registry)) {
			return false;
		}

		// 1. Lấy các giá trị từ form
		$groupCode    = $input->get('coursegroup');
		$academicyear = (int) $input->get('academicyear');  // INT, ví dụ: 2025
		$term         = (int) $input->get('term');
		$subjectId    = (int) $input->get('subject_id');

		if (empty($academicyear) || empty($term) || empty($subjectId)) {
			return false;
		}

		// 2. Lấy 2 chữ số cuối của năm học từ giá trị INT trực tiếp
		//    Ví dụ: 2025 % 100 = 25
		$firstYear = $academicyear % 100;

		// 3. Lấy mã môn học
		$db    = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select($db->quoteName('code'))
			->from($db->quoteName('#__eqa_subjects'))
			->where('id = ' . $subjectId);
		$db->setQuery($query);
		$subjectCode = $db->loadResult();

		if (empty($subjectCode)) {
			return false;
		}

		// 4. Xây dựng pattern và kiểm tra
		$pattern = '/^'
			. preg_quote($subjectCode, '/')
			. '-' . $term
			. '-' . $firstYear
			. '\(' . preg_quote($groupCode, '/')
			. '[A-Z0-9\-]+\d\d\)$/';

		return (bool) preg_match($pattern, $value);
	}
}