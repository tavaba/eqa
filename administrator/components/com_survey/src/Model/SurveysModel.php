<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\ListModel;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;

class SurveysModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'respondentCount','responseCount', 'startTime', 'endTime', 'createdBy');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    protected function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();
        //Đếm số lượng người tham gia khảo sát. Chỉ áp dụng đối với những cuộc khảo sát
        //có auth_mode là LISTED.
        $subqueryRespondentCount=$db->getQuery(true)
            ->from('#__survey_survey_respondent AS sr')
            ->select('COUNT(1)')
            ->where('(sr.survey_id=a.id AND a.auth_mode=' . SurveyHelper::AUTH_MODE_ASSIGNED.')');
        $subqueryResponseCount = $db->getQuery(true)
            ->from('#__survey_responses AS r')
            ->select('COUNT(1)')
            ->where('r.survey_id=a.id');
        $columns = [
            $db->quoteName('a.id'),
            $db->quoteName('a.created_by'),     //Must be left as is for access control
            $db->quoteName('a.asset_id'),       //Must be left as is for access control
            $db->quoteName('a.title'),
            $db->quoteName('a.description'),
            $db->quoteName('a.form_id')             . ' AS ' . $db->quoteName('formId'),
            $db->quoteName('f.title')               . ' AS ' . $db->quoteName('formTitle'),
            $db->quoteName('f.description')         . ' AS ' . $db->quoteName('formDescription'),
            $db->quoteName('a.start_time')          . ' AS ' . $db->quoteName('startTime'),
            $db->quoteName('a.end_time')            . ' AS ' . $db->quoteName('endTime'),
            $db->quoteName('a.auth_mode')           . ' AS ' . $db->quoteName('authMode'),
            $db->quoteName('a.allow_edit_response') . ' AS ' . $db->quoteName('allowEditResponse'),
            $db->quoteName('a.strictly_anonymous')  . ' AS ' . $db->quoteName('strictlyAnonymous'),
            $db->quoteName('u.name')                . ' AS ' . $db->quoteName('createdBy'),
            $db->quoteName('a.state'),
        ];
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__survey_surveys', 'a'))
            ->leftJoin($db->quoteName('#__users','u'),'u.id=a.created_by')
            ->leftJoin('#__survey_forms AS f','f.id=a.form_id')
            ->select($columns)
            ->select('(' . $subqueryRespondentCount . ') AS ' . $db->quoteName('respondentCount'))
            ->select('(' . $subqueryResponseCount . ') AS ' . $db->quoteName('responseCount'))
            ->where('a.campaign_id IS NULL'); //Show single surveys only


        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $query->where('a.title LIKE ' . $db->quote('%' . $db->escape($search, true) . '%'));
        }

        $createdBy = $this->getState('filter.created_by');
        if(!empty($createdBy))
            $query->where('a.created_by='.$createdBy);

        $isOver = $this->getState('filter.is_over');
        if(is_numeric($isOver))
        {
            if($isOver)
                $query->where('a.end_time < NOW()');
            else
                $query->where('a.end_time > NOW()');
        }

        $state = $this->getState('filter.state','');
        if($state=='')
            $query->whereIn($db->quoteName('a.state'),
                [
	                StateHelper::STATE_PUBLISHED,
	                StateHelper::STATE_UNPUBLISHED
                ]);
        elseif (is_numeric($state))
            $query->where($db->quoteName('a.state') . ' = ' . (int)$state);

        // Add sorting
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'asc');
        $query->order($db->escape($orderCol . ' ' . $orderDirn));

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