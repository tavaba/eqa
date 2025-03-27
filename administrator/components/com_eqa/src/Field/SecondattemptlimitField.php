<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

/**
 * Supports an HTML select list of education degrees
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class SecondattemptlimitField extends ListField
{
    protected $type = 'secondattemptlimit';

	protected function getOptions()
    {
        $options = parent::getOptions();
        foreach (ExamHelper::getSecondAttemptLimits() as $code=>$text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}
