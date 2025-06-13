<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;

class EmployeeField extends ListField
{
    protected $type = 'List';
	static protected $options;
	static protected function initOptions()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        //Lấy danh sách giảng viên.
        $query = $db->getQuery(true)
            ->select('id, lastname, firstname')
            ->from('#__eqa_employees')
            ->where('published=1');
        $db->setQuery($query);
        $employees = $db->loadObjectList();

        $options = [];
        foreach ($employees as $person){
            $fullname = $person->lastname . ' ' . $person->firstname;
            $options[] = HTMLHelper::_('select.option', $person->id, $fullname);
        }

		//return
        self::$options = $options;
    }
	protected  function getOptions()
	{
		if(empty(self::$options))
			self::initOptions();
		$options = parent::getOptions();
		$options = array_merge($options, self::$options);
		return $options;
	}
	static public function getElementHtml(string $name, int|null $selectedValue=null, string $prompt='', string $class='select2-basic'): string
	{
		//Init groups if needed
		if(empty(self::$options))
			self::initOptions();

		//Opening tag
		$html="<select name='$name' class='$class'>";

		//Prompt
		$html .= "<option value=''>$prompt</option>";

		//Groups of options
		foreach (self::$options as $option){
			$html .= "<option value='$option->value'";
			if($option->value === $selectedValue)
				$html .= " selected";
			$html .= ">$option->text</option>";
		}

		//Closing tag
		$html .= "</select>";

		return $html;
	}
}
