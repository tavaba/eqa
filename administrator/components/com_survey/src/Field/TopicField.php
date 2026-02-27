<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;

class TopicField extends ListField
{
    protected $type = 'topic';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = DatabaseHelper::getDatabaseDriver();
        $db->setQuery('SELECT id, title FROM #__survey_topics');
        $topics = $db->loadObjectList();
        foreach ($topics as $topic)
        {
            $options[] = HTMLHelper::_('select.option', $topic->id, $topic->title);
        }
        return $options;
    }

}