<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;

class GenderField extends ListField
{
    protected $type = 'gender';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (RespondentHelper::getGenders() as $code=>$text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}