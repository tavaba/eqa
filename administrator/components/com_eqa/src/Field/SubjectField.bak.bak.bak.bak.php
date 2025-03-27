<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;
class SubjectField_bak extends GroupedlistField
{
    protected $type = 'subject';

    /**
     * Tạo một list box (inputbox) để lựa chọn môn học
     * Vì danh sách tương đối lớn nên các môn sẽ được chi thành các groups theo khoa phụ trách
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
        $db = $this->getDatabase();

        //Lấy danh sách đơn vị
        $query = $db->getQuery(true)
            ->select('id, name')
            ->from('#__eqa_units')
            ->where('published=1')
            ->order('name');
        $db->setQuery($query);
        $units = $db->loadAssocList('id','name');
        $units['other'] = Text::_('COM_EQA_GENERAL_UNDEFINED');


        //Lấy danh sách môn học
        $query = $db->getQuery(true)
            ->select('id, unit_id, code, name')
            ->from('#__eqa_subjects')
            ->where('published=1');
        $db->setQuery($query);
        $subjects = $db->loadObjectList();

        //Tạo một mảng [ID Đơn vị]->[Option Các giảng viên thuộc đơn vị]
        $temp = [];
        foreach ($units as $id=>$name)
            $temp[$id] = [];
        foreach ($subjects as $subject){
            $fullname = $subject->code . ' - ' . $subject->name;
            if(is_numeric($subject->unit_id))
                $temp[$subject->unit_id][] = HTMLHelper::_('select.option', $subject->id, $fullname);
            else
                $temp['other'] = HTMLHelper::_('select.option', $subject->id, $fullname);
        }

        //Tạo kết quả
        $groups = parent::getOptions();
        foreach ($units as $id=>$name){
            if(!empty($temp[$id]))
                $groups[$name] = $temp[$id];
        }
        return $groups;
    }

}
