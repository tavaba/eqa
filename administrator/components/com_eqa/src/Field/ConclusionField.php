<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

/**
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 */
class ConclusionField extends ListField
{
    protected $type = 'conclusion';

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
	    $options[] = HTMLHelper::_('select.option', 0, 'Chưa có');
        foreach (ExamHelper::getConclusions() as $code=>$text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}
