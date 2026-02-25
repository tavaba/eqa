<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;

class RespondentGroupMemberUnitField extends ListField
{
    protected $type = 'RespondentGroupMemberUnit';

    /**
     * Returns the options for this list field. The options is a set of
     * units of members of a particular respondent group.
     *
     * @return  array  The field option objects.
     * @since 1.0.1
     */
    protected function getOptions(): array
    {
        /**
         * Get the current group ID from session. This value must be set by the view.
         *
         * @var CMSApplication $app
         */
        $app = Factory::getApplication();
        $groupId = $app->getUserState('com_survey.respondentgroupmember.group_id');

        $options = [];
        $options[] = HTMLHelper::_('select.option',null, '- Đơn vị -');

        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('DISTINCT(r.unit_id) AS id, u.code, u.name')
            ->from('#__survey_respondentgroup_respondent AS rr')
            ->leftJoin('#__survey_respondents AS r', 'r.id = rr.respondent_id')
            ->leftJoin('#__survey_units AS u', 'u.id = r.unit_id')
            ->where('rr.group_id = '.$groupId);
        $db->setQuery($query);
        $units = $db->loadObjectList();
        foreach ($units as $unit)
        {
            $text = $unit->code . ' - ' . $unit->name;
            $options[] = HTMLHelper::_('select.option', $unit->id, $text);
        }
        return $options;
    }

}