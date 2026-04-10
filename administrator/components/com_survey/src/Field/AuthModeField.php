<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Enum\AuthorizationMode;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;

class AuthModeField extends ListField
{
    protected $type = 'authmode';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (AuthorizationMode::getOptions() as $code=> $text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}