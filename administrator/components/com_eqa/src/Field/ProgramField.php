<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;

/**
 * Supports an HTML select list of education degrees
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class ProgramField extends ListField
{
    protected $type = 'program';

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
        $query = $db->getQuery(true)
            ->select('id, degree, name')
            ->from('#__eqa_programs')
            ->where('published = 1')
            ->order('degree');
        $db->setQuery($query);
        $res = $db->loadAssocList('id','name');
        $options = parent::getOptions();
        foreach ($res as $id=>$name)
        {
            $options[] = HTMLHelper::_('select.option', $id, $name);
        }
        return $options;
    }

}
