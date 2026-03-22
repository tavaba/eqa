<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

class ResultField extends ListField
{
    protected $type = 'result';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
	    $options[] = HTMLHelper::_('select.option', 1, 'Thành công');
	    $options[] = HTMLHelper::_('select.option', 0, 'Thất bại');
        return $options;
    }

}