<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;

/*
 * Lấy danh sách Units có trong một campaign.
 */
class CampaignUnitField extends ListField
{
    protected $type = 'CampaignUnit';
    protected function getOptions(): array
    {
	    $db = DatabaseHelper::getDatabaseDriver();

		/*
		 * Tìm 'campaign_id' trong INPUT
		 */
	    $campaignId = Factory::getApplication()->input->getInt('campaign_id');
		if($campaignId)
		{
			$columns = [
				'DISTINCT(u.id) AS id',
				$db->quoteName('u.code',      'code'),
				$db->quoteName('u.name',      'name'),
			];
			$query = $db->getQuery(true)
				->select($columns)
				->from('#__survey_campaign_respondent AS cr')
				->leftJoin('#__survey_respondents AS r','r.id=cr.respondent_id')
				->leftJoin('#__survey_units AS u','u.id=r.unit_id')
				->where('cr.campaign_id='.$campaignId)
				->order('u.code');
			$db->setQuery($query);
		}
		else
		{
			$db->setQuery('SELECT id, code, name FROM #__survey_units ORDER BY code');
		}

        $options = parent::getOptions();
        $units = $db->loadObjectList();
        foreach ($units as $unit)
        {
            $text = $unit->code . ' - ' . $unit->name;
            $options[] = HTMLHelper::_('select.option', $unit->id, $text);
        }
        return $options;
    }

}