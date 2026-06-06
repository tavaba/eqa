<?php

namespace Kma\Library\Kma\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Enum\Gender;

/**
 * Form field hiển thị danh sách giới tính (Nam / Nữ).
 * Sử dụng enum Gender để lấy danh sách option, đảm bảo nhất quán
 * giữa tất cả các component dùng chung lib_kma.
 *
 * Sử dụng trong XML form:
 *   <fieldset addfieldprefix="Kma\Library\Kma\Field">
 *       <field name="gender" type="gender" label="Giới tính">
 *           <option value="">- Giới tính -</option>
 *       </field>
 *   </fieldset>
 *
 * @since 1.x
 */
class GenderField extends ListField
{
	/** @var string Loại field, khớp với giá trị type="gender" trong XML. */
	protected $type = 'gender';

	/**
	 * Trả về danh sách option cho dropdown giới tính.
	 * Option trống (nếu có) được lấy từ XML form thông qua parent::getOptions().
	 *
	 * @return  array
	 */
	protected function getOptions(): array
	{
		$options = parent::getOptions();
		if(count($options)==0)
			$options[] = HTMLHelper::_('select.option', null, '-- Giới tính --');

		foreach (Gender::cases() as $case) {
			$options[] = HTMLHelper::_('select.option', $case->value, $case->getLabel());
		}

		return $options;
	}
}