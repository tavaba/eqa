<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\ExternalDataHelper;

class ExamseasonField extends ListField
{
    protected $type = 'examseason';
    protected function getOptions(): array
    {
        $options = [];
        $options[] = HTMLHelper::_('select.option', '', '- Kỳ thi -');
        $examseasons = ExternalDataHelper::fetchExamseasons();
        foreach ($examseasons as $id=>$name)
        {
            $options[] = HTMLHelper::_('select.option', $id, $name);
        }
        return $options;
    }

}