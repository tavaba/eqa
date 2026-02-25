<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;

class CampaignField extends ListField
{
    protected $type = 'campaign';
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db = DatabaseHelper::getDatabaseDriver();
        $db->setQuery('SELECT id, title FROM #__survey_campaigns');
        $campaigns = $db->loadObjectList();
        foreach ($campaigns as $campaign)
        {
            $options[] = HTMLHelper::_('select.option', $campaign->id, $campaign->title);
        }
        return $options;
    }

}