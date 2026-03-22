<?php
namespace Kma\Component\Survey\Site\Model;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Component\Survey\Administrator\Base\ListModel;

class SurveysModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'size', 'startTime', 'endTime', 'createdBy');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    protected function getListQuery()
    {
        //Retrieve the respondent id that was set to the application state
        //by the dispatcher
        $respondent = Factory::getApplication()->get('respondent');
        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('s.id'),
            $db->quoteName('s.title'),
            $db->quoteName('s.description'),
            $db->quoteName('s.form_id')             . ' AS ' . $db->quoteName('formId'),
            $db->quoteName('s.start_time')          . ' AS ' . $db->quoteName('startTime'),
            $db->quoteName('s.end_time')            . ' AS ' . $db->quoteName('endTime'),
            $db->quoteName('s.auth_mode')           . ' AS ' . $db->quoteName('authMode'),
            $db->quoteName('s.allow_edit_response') . ' AS ' . $db->quoteName('allowEditResponse'),
            $db->quoteName('s.strictly_anonymous')  . ' AS ' . $db->quoteName('strictlyAnonymous'),
            $db->quoteName('s.state'),
            $db->quoteName('sr.responded')          . ' AS ' . $db->quoteName('responded'),
        ];

        //Init the query object
        $leftJoinConditions = isset($respondent->id)
            ? 'sr.survey_id=s.id AND sr.respondent_id='.(int)$respondent->id
            : '1=0';
        $query = $db->getQuery(true)
            ->select($columns)
            ->from($db->quoteName('#__survey_surveys', 's'))
            ->leftJoin($db->quoteName('#__survey_survey_respondent', 'sr'), $leftJoinConditions);


        // Build auth conditions
        $now = Factory::getDate()->toSql();
        $authConditions = [];

        // AUTH_MODE_ANYONE - always accessible
        $authConditions[] = $db->quoteName('s.auth_mode') . ' = ' . SurveyHelper::AUTH_MODE_ANYONE;

        if ($respondent !== null) {
            // User is authenticated
            $authConditions[] = $db->quoteName('s.auth_mode') . ' = ' . SurveyHelper::AUTH_MODE_AUTHENTICATED;

            if (isset($respondent->id)) { // User has respondent_id
                $authConditions[] = $db->quoteName('s.auth_mode') . ' = ' . SurveyHelper::AUTH_MODE_RESPONDENT;
                $authConditions[] = '(' .
                    $db->quoteName('s.auth_mode') . ' = ' . SurveyHelper::AUTH_MODE_ASSIGNED .
                    ' AND ' . $db->quoteName('sr.survey_id') . ' IS NOT NULL' .
                    ')';
            }
        }

        //Combine the auth conditions
        $query->where('(' . implode(' OR ', $authConditions) . ')');

        //Add deadline and participating condition
        $deadlineCondition = $db->quoteName('s.end_time') . ' > ' . $db->quote($now);
        if ($respondent !== null && isset($respondent->id)) {
            $participatingCondition = $db->quoteName('sr.survey_id') . ' IS NOT NULL';
            $query->where('(' . $deadlineCondition . ' OR ' . $participatingCondition . ')');
        } else {
            $query->where($deadlineCondition);
        }

        // Filter by search in title
        $isOver = $this->getState('filter.is_over');
        if($isOver==-1)
            $query->where('s.start_time>NOW()');
        elseif($isOver==1)
            $query->where('s.end_time<NOW()');
        elseif(is_numeric($isOver) && $isOver==0)
            $query->where('s.start_time<NOW() AND s.end_time>NOW()');

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $query->where('s.title LIKE ' . $db->quote('%' . $db->escape($search, true) . '%'));
        }

        // Add sorting
        $orderCol  = $this->state->get('list.ordering', 's.id');
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