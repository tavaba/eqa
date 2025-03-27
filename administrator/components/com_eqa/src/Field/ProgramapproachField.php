<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\ProgramHelper;

/**
 * Supports an HTML select list of exam season types
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class ProgramapproachField extends ListField
{
    protected $type = 'programapproach';

    /**
     * Method to get a list of options for a list input.
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
        $options = parent::getOptions();
        foreach (ProgramHelper::approachs() as $key=>$approach)
        {
            $options[] = HTMLHelper::_('select.option', $key, $approach);
        }
        return $options;
    }

}
