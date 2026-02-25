<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\LogHelper;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;

class TermField extends ListField
{
    const MAX_TERMS = 3;
    protected $type = 'term';
    protected function getOptions(): array
    {
        $options = [];
        $options[] = HTMLHelper::_('select.option', '', '- Học kỳ -');
        for ($term=1; $term<=self::MAX_TERMS; $term++)
        {
            $options[] = HTMLHelper::_('select.option', $term, $term);
        }
        return $options;
    }

}