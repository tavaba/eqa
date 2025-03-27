<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
class GroupField extends ListField
{
    protected $type = 'group';

    /**
     * Method to get a list of options for a list input.
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id','a.code','b.code','b.admissionyear'),
            array('id','code','course','admissionyear')
        );
        $query = $db->getQuery(true)
            ->from('#__eqa_groups AS a')
            ->leftJoin('#__eqa_courses AS b', 'a.course_id = b.id')
            ->select($columns)
            ->where('a.published = 1 AND b.published=1')
            ->order('code ASC');
        $db->setQuery($query);
        $res = $db->loadAssocList('id','code');
        $options = parent::getOptions();
        foreach ($res as $id=>$code)
        {
            $options[] = HTMLHelper::_('select.option', $id, $code);
        }
        return $options;
    }

}
