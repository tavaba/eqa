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
class AcademicyearField extends ListField
{
    protected $type = 'academicyear';

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
            ->select('id, code')
            ->from('#__eqa_academicyears')
            ->where('published = 1')
            ->order('code DESC');
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
