<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class SubjectField extends ListField
{
    protected $type = 'subject';

    protected function getOptions()
    {
        $db = $this->getDatabase();

        /*
         * Lấy danh sách môn học.
         * Nhãn của option dùng TÊN PHÂN BIỆT để quản trị viên không bị nhầm giữa
         * các môn học trùng tên chính thức nhưng khác mã, khác nội dung. (2.1.7)
         */
        $query = $db->getQuery(true)
            ->select('a.id, a.unit_id, a.code')
            ->select(DatabaseHelper::displayNameExpr('a') . ' AS ' . $db->quoteName('name'))
            ->from($db->quoteName('#__eqa_subjects', 'a'))
            ->where('a.published=1');
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
