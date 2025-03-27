<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Supports an HTML select list of education degrees
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class SpecialityField extends ListField
{
    protected $type = 'speciality';

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
            ->from('#__eqa_specialities')
            ->where('published = 1')
            ->order('code');
        $db->setQuery($query);
        $items = $db->loadObjectList();
        $options = parent::getOptions();
        foreach ($items as $item)
        {
            $options[] = HTMLHelper::_('select.option', $item->id, $item->code.' - '.$item->name);
        }
        return $options;
    }

}
