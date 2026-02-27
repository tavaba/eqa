<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\ListModel;

class CampaignsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'surveyCount', 'startTime','endTime','creator');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        // Subquery: đếm số survey trong campaign
        $subqueryCountSurveys = 'SELECT COUNT(*) FROM #__survey_surveys AS s WHERE s.campaign_id=c.id';

        /*
         * Not that INNER JOIN is used here. It starts from #__survey_surveys
         * and filters by campaign_id first, then joins with #__survey_survey_respondent.
         * If we use LEFT JOIN then it will process all rows in #__survey_survey_respondent
         * which is much lager then #__survey_surveys so the performance would be worse.
         */
        $subqueryCountRespondents = 'SELECT COUNT(sr.respondent_id) 
        FROM #__survey_surveys AS s
        INNER JOIN #__survey_survey_respondent AS sr ON sr.survey_id = s.id
        WHERE s.campaign_id = c.id';

        $subqueryCountResponded = 'SELECT COUNT(sr.respondent_id)
        FROM #__survey_surveys AS s
        INNER JOIN #__survey_survey_respondent AS sr ON sr.survey_id = s.id
        WHERE s.campaign_id = c.id AND sr.responded = 1';

        //Subquery để đếm số unit có người tham gia các survey của campaign
        $subqueryCountUnits = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('r.unit_id') . ')')
            ->from($db->quoteName('#__survey_survey_respondent', 'sr'))
            ->innerJoin('#__survey_surveys AS s','s.id=sr.survey_id')
            ->innerJoin('#__survey_respondents AS r','r.id=sr.respondent_id')
            ->where($db->quoteName('s.campaign_id') . ' = ' . $db->quoteName('c.id'))
            ->where($db->quoteName('r.unit_id') . ' IS NOT NULL');

        $columns = [
            $db->quoteName('c.id'),
            $db->quoteName('c.created_by'),     //Must be left as is for access control
            $db->quoteName('c.asset_id'),       //Must be left as is for access control
            $db->quoteName('c.title'),
            $db->quoteName('c.description'),
            $db->quoteName('c.form_id')         . ' AS ' . $db->quoteName('formId'),
            $db->quoteName('f.title')           . ' AS ' . $db->quoteName('formTitle'),
            $db->quoteName('f.description')     . ' AS ' . $db->quoteName('formDescription'),
            $db->quoteName('c.auth_mode')       . ' AS ' . $db->quoteName('authMode'),
            $db->quoteName('c.start_time')      . ' AS ' . $db->quoteName('startTime'),
            $db->quoteName('c.end_time')        . ' AS ' . $db->quoteName('endTime'),
            $db->quoteName('u.name')            . ' AS ' . $db->quoteName('creator'),
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_campaigns AS c')
            ->leftJoin('#__survey_forms AS f','f.id=c.form_id')
            ->leftJoin('#__users AS u', 'u.id=c.created_by')
            ->select($columns)
            ->select('('.$subqueryCountSurveys.')       AS ' . $db->quoteName('countSurveys'))
            ->select('('.$subqueryCountUnits.')         AS ' . $db->quoteName('countUnits'))
            ->select('('.$subqueryCountRespondents.')   AS ' . $db->quoteName('countRespondents'))
            ->select('('.$subqueryCountResponded.')     AS ' . $db->quoteName('countResponded'));

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(title LIKE ' . $like . ')');
        }

        $createdBy = $this->getState('filter.created_by');
        if(!empty($createdBy))
            $query->where('c.created_by='.$createdBy);

        $isOver = $this->getState('filter.is_over');
        if(is_numeric($isOver))
        {
            if($isOver)
                $query->where('c.end_time < NOW()');
            else
                $query->where('c.end_time > NOW()');
        }

        $state = $this->getState('filter.state','');
        if($state=='')
            $query->whereIn($db->quoteName('c.state'),
                [
                    StateHelper::STATE_PUBLISHED,
                    StateHelper::STATE_UNPUBLISHED
                ]);
        elseif (is_numeric($state))
            $query->where($db->quoteName('c.state') . ' = ' . (int)$state);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        return parent::getStoreId($id);
    }

    public function canCreate(?string $specificAction = 'com.create.survey'): bool
    {
        return parent::canCreate($specificAction);
    }
}