<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;
class SubjectField extends ListField
{
    protected $type = 'subject';

    protected function getOptions()
    {
        $db = $this->getDatabase();

        //Lấy danh sách môn học
        $query = $db->getQuery(true)
            ->select('id, unit_id, code, name')
            ->from('#__eqa_subjects')
            ->where('published=1');
        $db->setQuery($query);
        $subjects = $db->loadObjectList();

		$options = parent::getOptions();
        foreach ($subjects as $subject){
            $fullname = $subject->code . ' - ' . $subject->name;
	        $options[] = HTMLHelper::_('select.option', $subject->id, $fullname);
        }
        return $options;
    }

}
