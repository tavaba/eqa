<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;

class PerformanceModeField extends ListField
{
    protected $type = 'performanceoption';
    protected function getOptions(): array
    {
        $options = [];
        $options[] = HTMLHelper::_('select.option', null, '- Hiệu năng -');
        foreach (SurveyHelper::getPerformanceModes() as $code=> $text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}