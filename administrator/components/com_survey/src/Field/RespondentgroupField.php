<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;

class RespondentgroupField extends ListField
{
    protected $type = 'respondentgroup';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = DatabaseHelper::getDatabaseDriver();
        $db->setQuery('SELECT id, name FROM #__survey_respondentgroups ORDER BY id DESC');
        $groups = $db->loadObjectList();
        foreach ($groups as $group)
        {
            $options[] = HTMLHelper::_('select.option', $group->id, $group->name);
        }
        return $options;
    }

}