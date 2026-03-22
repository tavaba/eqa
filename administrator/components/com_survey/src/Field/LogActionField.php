<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;

class LogActionField extends ListField
{
    protected $type = 'logaction';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        return $options;
    }

}