<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Field
 *
 * @copyright   (C) 2025 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Service\ConfigService;
use Kma\Library\Kma\Helper\DatetimeHelper;

/**
 * Field hiển thị danh sách năm học để chọn.
 *
 * Danh sách được tạo động dựa trên năm hiện tại và hai tham số cấu hình
 * của component (academicyear_upper_offset, academicyear_lower_offset).
 * Giá trị lưu vào DB là năm đầu tiên của năm học (INT), ví dụ: 2025.
 *
 * @since  2.0.4
 */
class AcademicyearField extends ListField
{
	/**
	 * Kiểu field.
	 *
	 * @var    string
	 * @since  1.0
	 */
	protected $type = 'academicyear';

	/**
	 * Tạo danh sách các năm học để chọn.
	 *
	 * Khoảng năm được xác định tương đối so với năm hiện tại:
	 *   - Năm cao nhất = năm hiện tại + upper_offset
	 *   - Năm thấp nhất = năm hiện tại - lower_offset
	 *
	 * @return  array  Mảng các option cho thẻ select.
	 * @since   2.0.4
	 */
	protected function getOptions(): array
	{
		$config      = new ConfigService();
		$currentYear = (int) date('Y');
		$upperYear   = $currentYear + $config->getAcademicYearUpperOffset();
		$lowerYear   = $currentYear - $config->getAcademicYearLowerOffset();

		$options = parent::getOptions();

		for ($year = $upperYear; $year >= $lowerYear; $year--) {
			$label     = DatetimeHelper::decodeAcademicYear($year); // "2025-2026"
			$options[] = HTMLHelper::_('select.option', $year, $label);
		}

		return $options;
	}
}