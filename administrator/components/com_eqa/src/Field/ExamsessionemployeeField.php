<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * Danh sách chọn cán bộ tham gia một ca thi, nhóm theo đơn vị.
 *
 * Field này kế thừa GroupedlistField nên không dùng được
 * {@see \Kma\Library\Kma\Field\StateAwareListField}. Cơ chế "giữ lại giá trị
 * đang lưu" được cài đặt riêng tại getGroups() — xem appendOrphanGroup().
 *
 * @since 1.0
 */
class ExamsessionemployeeField extends GroupedlistField
{
    protected $type = 'examsessionemployee';

    /**
     * Cache dùng chung trong một request. Chỉ chứa các đơn vị/cán bộ ĐANG DÙNG.
     *
     * @var array|null
     * @since 1.0
     */
	static protected $groups;

	static protected function initGroups()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        //Lấy danh sách đơn vị
        $query = $db->getQuery(true)
            ->select('id, name')
            ->from($db->quoteName('#__eqa_units'))
            ->where($db->quoteName('state') . ' = ' . StateHelper::STATE_PUBLISHED)
            ->order('name');
        $db->setQuery($query);
        $units = $db->loadAssocList('id','name');


        //Lấy danh sách giảng viên.
        $query = $db->getQuery(true)
            ->select('id, unit_id, lastname, firstname')
            ->from($db->quoteName('#__eqa_employees'))
            ->where($db->quoteName('state') . ' = ' . StateHelper::STATE_PUBLISHED);
        $db->setQuery($query);
        $employees = $db->loadObjectList();

        //Tạo một mảng [ID Đơn vị]->[Option Các giảng viên thuộc đơn vị]
        $temp = [];
        foreach ($units as $id=>$name)
            $temp[$id] = [];
        foreach ($employees as $person){
			if(!isset($temp[$person->unit_id]))
				continue;
            $fullname = $person->lastname . ' ' . $person->firstname;
            $temp[$person->unit_id][] = HTMLHelper::_('select.option', $person->id, $fullname);
        }

        //Tạo kết quả
        $groups = [];
        foreach ($units as $id=>$name){
            if(!empty($temp[$id]))
                $groups[$name] = $temp[$id];
        }

		//return
        self::$groups=$groups;
    }

	/**
	 * Bổ sung nhóm chứa giá trị đang được chọn nhưng không còn nằm trong danh
	 * sách (cán bộ đã nghỉ, đơn vị đã giải thể...).
	 *
	 * Không có bước này, form sửa sẽ hiển thị ô trống và thao tác Lưu sẽ ghi đè
	 * khóa ngoại về 0 — mất dữ liệu mà không có cảnh báo nào.
	 *
	 * Việc bù thực hiện trên BẢN SAO của cache tĩnh, vì giá trị đang chọn là
	 * thông tin riêng của từng instance field.
	 *
	 * @param   array  $groups  Danh sách nhóm gốc.
	 *
	 * @return  array
	 * @since   2.1.7
	 */
	protected function appendOrphanGroup(array $groups): array
	{
		$value = $this->value;

		if ($value === null || $value === '' || !is_numeric($value) || (int) $value <= 0) {
			return $groups;
		}

		$value = (int) $value;

		foreach ($groups as $options) {
			foreach ($options as $option) {
				if ((int) $option->value === $value) {
					return $groups;
				}
			}
		}

		$db    = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('id, lastname, firstname')
			->from($db->quoteName('#__eqa_employees'))
			->where($db->quoteName('id') . ' = ' . $value);
		$db->setQuery($query);
		$person = $db->loadObject();

		if (empty($person)) {
			return $groups;
		}

		$fullname = $person->lastname . ' ' . $person->firstname;
		$groups['(Không còn hiệu lực)'] = [
			HTMLHelper::_('select.option', $person->id, $fullname),
		];

		return $groups;
	}

	protected  function getOptions()
	{
		if(empty(self::$groups))
			self::initGroups();
		$groups = parent::getOptions();
		return $this->appendOrphanGroup(array_merge($groups, self::$groups));
	}

	static public function getElementHtml(string $name, int|null $selectedValue=null, string $prompt='', string $class='select2-basic'): string
	{
		//Init groups if needed
		if(empty(self::$groups))
			self::initGroups();

		//Opening tag
		$html="<select name='$name' class='$class'>";

		//Prompt
		$html .= "<option value=''>$prompt</option>";

		//Groups of options
		foreach (self::$groups as $groupName => $groupOptions){
			$html .= "<optgroup label='$groupName'>";
			foreach ($groupOptions as $option)
			{
				$html .= "<option value='$option->value'";
				if($option->value === $selectedValue)
					$html .= " selected";
				$html .= ">$option->text</option>";
			}
			$html .= "</optgroup>";
		}

		//Closing tag
		$html .= "</select>";

		return $html;
	}
}
