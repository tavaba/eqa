<?php
namespace Kma\Component\Eqa\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\CMS\Form\Form;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use SimpleXMLElement;
class ClasscodeRule extends FormRule
{
	public function test(SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null): bool
	{
		/**
		 * Cấu trúc của Mã lớp học phần như sau:
		 * Ngoài dấu ngoặc: Mã môn học, Gạch nối, Học kỳ, Gạch nối, Năm bắt đầu năm học.
		 * Trong dấu ngoặc: Nhóm khóa học, Gạch nối, Số thứ tự lớp học phần ghi đủ 02 chữ số.
		 * Lưu ý chỉ sử dụng chữ cái in hoa, chữ số, dấu gạch nối, dấu chấm và dấu ngoặc đơn; KHÔNG có dấu cách.
		 * Ví dụ: CLC1ATATAT2-1-24(A18-KN01), ATCTKM3-1-24(A19C7-01).
		 **/

		//This requires $input to exist and be an instance of Registry class
		if (!isset($input) || !($input instanceof Registry)) {
			return false;
		}
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get value of corresponding fields
		$groupCode = $input->get('coursegroup');
		$academicYearId = (int)$input->get('academicyear_id');
		$term = (int)$input->get('term');
		$subjectId = (int)$input->get('subject_id');

		//2. Get first year of the academic year
		$query = $db->getQuery(true)
			->select('code')
			->from('#__eqa_academicyears')
			->where("id={$academicYearId}");
		$db->setQuery($query);
		$academicYearCode = $db->loadResult();
		if(empty($academicYearCode))
			return false;
		$firstYear = substr($academicYearCode, 2,2);

		//3. Get the subject code
		$query = $db->getQuery(true)
			->select('code')
			->from('#__eqa_subjects')
			->where("id={$subjectId}");
		$db->setQuery($query);
		$subjectCode = $db->loadResult();
		if(empty($subjectCode))
			return false;

		//4.Build the pattern for matching the $value
		$pattern = '/^'
			. preg_quote($subjectCode,'/')
			.'-' . $term
			.'-' . $firstYear
			.'\(' . preg_quote($groupCode,'/')
			.'[A-Z0-9\-]+\d\d\)$/';
		if(preg_match($pattern,$value))
			return true;
		return false;
	}
}