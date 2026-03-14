<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultType;
use Kma\Component\Eqa\Administrator\Enum\AssessmentType;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class AssessmentResultTypeField extends ListField
{
    protected $type = 'assessmentResultType';

    /**
     * Method to get a list of options for a list input.
     *
     * @return	array		An array of JHtml options.
     *
     * @since   2.0.5
     */
    protected function getOptions()
    {
        $options = [];
	    $options[] = HTMLHelper::_('select.option', null, '-- Dạng kết luận --');
        foreach (AssessmentResultType::getOptions() as $code=>$text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}
