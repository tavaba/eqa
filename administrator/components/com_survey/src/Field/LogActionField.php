<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\LogHelper;

class LogActionField extends ListField
{
    protected $type = 'logaction';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (LogHelper::getActionTypes() as $code=>$text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}