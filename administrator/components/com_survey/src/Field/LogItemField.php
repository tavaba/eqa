<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Enum\EntityType;

class LogItemField extends ListField
{
    protected $type = 'logitem';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        foreach (EntityType::getAll() as $code=> $text)
        {
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}