<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;

class EmployeeField extends GroupedlistField
{
    protected $type = 'employee';
	static protected $groups;
	static protected function initGroups()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        //Lấy danh sách đơn vị
        $query = $db->getQuery(true)
            ->select('id, name')
            ->from('#__eqa_units')
            ->where('published=1')
            ->order('name');
        $db->setQuery($query);
        $units = $db->loadAssocList('id','name');


        //Lấy danh sách giảng viên.
        $query = $db->getQuery(true)
            ->select('id, unit_id, lastname, firstname')
            ->from('#__eqa_employees')
            ->where('published=1');
        $db->setQuery($query);
        $employees = $db->loadObjectList();

        //Tạo một mảng [ID Đơn vị]->[Option Các giảng viên thuộc đơn vị]
        $temp = [];
        foreach ($units as $id=>$name)
            $temp[$id] = [];
        foreach ($employees as $person){
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
	protected  function getGroups()
	{
		if(empty(self::$groups))
			self::initGroups();
		$groups = parent::getGroups();
		return array_merge($groups, self::$groups);
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
