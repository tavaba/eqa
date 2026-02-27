<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\ListModel;

class CampaignRespondentsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'code', 'responded');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'firstname', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }
    protected function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $campaignId = $this->getState('filter.campaign_id');
        if(empty($campaignId))
            throw new Exception('No campaign id provided');

        $query = $db->getQuery(true)
            ->select([
                'r.code',
                'r.name',
                'r.lastname',
                'r.firstname',
                'r.is_person            AS isPerson',
                'u.code                 AS unitCode',
                'cr.survey_count        AS countSurveys',
                'cr.response_count      AS countResponded',
                '(cr.response_count*100/cr.survey_count) AS progress'
            ])
            ->from($db->quoteName('#__survey_campaign_respondent','cr'))
            ->leftJoin($db->quoteName('#__survey_respondents', 'r'),'r.id=cr.respondent_id')
            ->leftJoin('#__survey_units AS u', 'u.id = r.unit_id')
            ->where('cr.campaign_id='.(int)$campaignId);

        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(r.code LIKE '.$like.' OR concat_ws(" ", r.lastname, r.firstname, r.name) LIKE '.$like.')');
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type))
            $query->where($db->quoteName('r.type').'='.(int)$type);
        $isPerson = $this->getState('filter.is_person');
        if(is_numeric($isPerson))
            $query->where('r.is_person=' . (int)$isPerson);
        $unitId = $this->getState('filter.unit_id');
        if(is_numeric($unitId))
            $query->where($db->quoteName('r.unit_id').'='.(int)$unitId);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'firstname'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);
        if($orderingCol=='firstname')
            $query->order('lastname '. $orderingDir);
        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.survey_id');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.is_person');
        $id .= ':' . $this->getState('filter.unit_id');
        $id .= ':' . $this->getState('filter.respondentgroup_id');
        $id .= ':' . $this->getState('filter.gender');
        $id .= ':' . $this->getState('filter.responded');
        return parent::getStoreId($id);
    }

    /**
     * Check whether the current user has permission to add/remove respondent in a survey
     *
     * @return bool
     * @since 1.0.0
     */
    public function canAddOrRemove(): bool
    {
        /**
         * A user is allowed to add/remove respondents in a survey only if
         * he/she has permission to edit that survey
         * @var SurveyModel $model
         */
        $surveyId = (int)$this->getState('filter.survey_id');
        $mvcFactory = ComponentHelper::getMVCFactory();
        $model = $mvcFactory->createModel('Survey');
        return $model->canEdit($surveyId);
    }
}