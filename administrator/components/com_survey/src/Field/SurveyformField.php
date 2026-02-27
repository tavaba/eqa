<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;

class SurveyformField extends ListField
{
    protected $type = 'surveyform';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = DatabaseHelper::getDatabaseDriver();
        $db->setQuery('SELECT id, title FROM #__survey_forms');
        $forms = $db->loadObjectList();
        foreach ($forms as $form)
        {
            $options[] = HTMLHelper::_('select.option', $form->id, $form->title);
        }
        return $options;
    }

}