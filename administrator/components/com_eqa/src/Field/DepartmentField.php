<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\UnitHelper;

/**
 * Supports an HTML select list of education degrees
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class DepartmentField extends ListField
{
    protected $type = 'department';

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
            ->select('id, code, name')
            ->from('#__eqa_units')
            ->where('published=1 AND type='.UnitHelper::UNIT_TYPE_EDUCATION)
            ->order('code');
        $db->setQuery($query);
        $units = $db->loadObjectList();
        $options = parent::getOptions();
        foreach($units as $unit)
        {
            $options[] = HTMLHelper::_('select.option', $unit->id, $unit->code . ' - '. $unit->name );
        }
        return $options;
    }

}
