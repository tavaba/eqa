<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

/**
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class ExamattemptField extends ListField
{
    protected $type = 'examattempt';

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
        $options[] = HTMLHelper::_('select.option', 1, Text::_('COM_EQA_EXAM_ATTEMPT_1'));
        $options[] = HTMLHelper::_('select.option', 2, Text::_('COM_EQA_EXAM_ATTEMPT_2'));
        return $options;
    }

}
