<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Component\Survey\Administrator\Base\ListModel;

class CampaignUnitsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'code');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'code', $direction = 'ASC'): void
    {
        parent::populateState($ordering, $direction);
    }
    protected function getListQuery()
    {
        //Determine the id of the campaign we are viewing
        //This id must be set by the View class
        $campaignId = $this->getState('filter.campaign_id');
        if(empty($campaignId) || !is_numeric($campaignId))
            throw new Exception('Cannot determine the campaign ID');

        $db = DatabaseHelper::getDatabaseDriver();

        /*
         * Get list of units that have at least one respondent of this campaign
         */
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('r.unit_id'))
            ->from($db->quoteName('#__survey_respondents', 'r'))
            ->innerJoin('#__survey_survey_respondent AS sr', 'sr.respondent_id = r.id')
            ->innerJoin('#__survey_surveys AS s', 's.id = sr.survey_id')
            ->where($db->quoteName('s.campaign_id') . ' = :campaign_id')
            ->where($db->quoteName('r.unit_id') . ' IS NOT NULL')
            ->bind(':campaign_id', $campaignId, \Joomla\Database\ParameterType::INTEGER);
        $db->setQuery($query);
        $unitIds = $db->loadColumn();
        $unitIdSet = '(' . implode(',', $unitIds) .')';

        // Subquery đếm số lượt respondent của unit tham gia các survey của campaign
        $subqueryCountRespondents = $db->getQuery(true)
            ->select('COUNT(' . $db->quoteName('sr.id') . ')')
            ->from($db->quoteName('#__survey_survey_respondent', 'sr'))
            ->innerJoin('#__survey_surveys AS s', 's.id = sr.survey_id')
            ->innerJoin('#__survey_respondents AS r', 'r.id = sr.respondent_id')
            ->where($db->quoteName('r.unit_id') . ' = ' . $db->quoteName('u.id'))
            ->where($db->quoteName('s.campaign_id') . ' = :campaign_id');

        // Subquery đếm số lượt respondent đã phản hồi
        $subqueryCountResponded = $db->getQuery(true)
            ->select('COUNT(' . $db->quoteName('sr.id') . ')')
            ->from($db->quoteName('#__survey_survey_respondent', 'sr'))
            ->innerJoin('#__survey_surveys AS s', 's.id = sr.survey_id')
            ->innerJoin('#__survey_respondents AS r', 'r.id = sr.respondent_id')
            ->where($db->quoteName('r.unit_id') . ' = ' . $db->quoteName('u.id'))
            ->where($db->quoteName('s.campaign_id') . ' = :campaign_id')
            ->where($db->quoteName('sr.responded') . ' = 1');

        // Subquery tạo bảng tạm với countRespondents và countResponded
        $subqueryBase = $db->getQuery(true)
            ->select([
                $db->quoteName('u.id'),
                $db->quoteName('u.code'),
                $db->quoteName('u.name'),
                '(' . $subqueryCountRespondents . ') AS countRespondents',
                '(' . $subqueryCountResponded . ') AS countResponded'
            ])
            ->from($db->quoteName('#__survey_units', 'u'))
            ->where('u.id IN ' . $unitIdSet);

        // Query chính: SELECT từ derived table và tính progress
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('base.id'),
                $db->quoteName('base.code'),
                $db->quoteName('base.name'),
                $db->quoteName('base.countRespondents'),
                $db->quoteName('base.countResponded')
            ])
            ->select('CASE 
            WHEN ' . $db->quoteName('base.countRespondents') . ' = 0 THEN 0 
            ELSE (' . $db->quoteName('base.countResponded') . ' * 100) / ' . $db->quoteName('base.countRespondents') . '
        END AS ' . $db->quoteName('progress'))
            ->from('(' . $subqueryBase . ') AS base')
            ->bind(':campaign_id', $campaignId, \Joomla\Database\ParameterType::INTEGER);

        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like=$db->quote('%' . $db->escape($search, true). '%');
            $query->where($db->quoteName('base.code') . ' LIKE ' . $like . ' OR ' . $db->quoteName('base.name') . ' LIKE ' . $like);
        }


        // Add sorting
        $orderCol  = $this->state->get('list.ordering', 'code');
        $orderDirn = $this->state->get('list.direction', 'asc');
        $query->order($db->escape($orderCol . ' ' . $orderDirn));

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        return parent::getStoreId($id);
    }

}