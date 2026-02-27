<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;

class AuthmodeField extends ListField
{
    protected $type = 'authmode';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (SurveyHelper::getAuthModes() as $code=> $text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}