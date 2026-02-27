<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;

class UnitField extends ListField
{
    protected $type = 'unit';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = DatabaseHelper::getDatabaseDriver();
        $db->setQuery('SELECT id, code, name FROM #__survey_units ORDER BY code');
        $units = $db->loadObjectList();
        foreach ($units as $unit)
        {
            $text = $unit->code . ' - ' . $unit->name;
            $options[] = HTMLHelper::_('select.option', $unit->id, $text);
        }
        return $options;
    }

}