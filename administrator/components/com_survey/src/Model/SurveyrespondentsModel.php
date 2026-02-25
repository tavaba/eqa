<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\ListModel;

class SurveyrespondentsModel extends ListModel
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
        $surveyId = (int)$this->getState('filter.survey_id');
        if(empty($surveyId))
            throw new Exception('No survey id provided');
        $columns = [
            $db->quoteName('a.id')                  . ' AS ' . $db->quoteName('id'),
            $db->quoteName('a.responded')           . ' AS ' . $db->quoteName('responded'),
            $db->quoteName('b.type')                . ' AS ' . $db->quoteName('type'),
            $db->quoteName('b.code')                . ' AS ' . $db->quoteName('code'),
            $db->quoteName('b.is_person')           . ' AS ' . $db->quoteName('isPerson'),
            $db->quoteName('b.lastname')            . ' AS ' . $db->quoteName('lastname'),
            $db->quoteName('b.firstname')           . ' AS ' . $db->quoteName('firstname'),
            $db->quoteName('b.name')                . ' AS ' . $db->quoteName('name'),
            $db->quoteName('b.gender')              . ' AS ' . $db->quoteName('gender'),
            $db->quoteName('b.phone')               . ' AS ' . $db->quoteName('phone'),
            $db->quoteName('b.email')               . ' AS ' . $db->quoteName('email'),
            $db->quoteName('c.value')               . ' AS ' . $db->quoteName('token'),
        ];
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__survey_survey_respondent', 'a'))
            ->leftJoin($db->quoteName('#__survey_respondents','b'),'b.id=a.respondent_id')
            ->leftJoin($db->quoteName('#__survey_tokens','c'),'c.survey_id=a.survey_id AND c.respondent_id=a.respondent_id')
            ->select($columns)
            ->where('a.survey_id=' . $surveyId);


        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(code LIKE '.$like.' OR concat_ws(" ", lastname, firstname, name) LIKE '.$like.')');
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type))
            $query->where($db->quoteName('type').'='.(int)$type);
        $isPerson = $this->getState('filter.is_person');
        if(is_numeric($isPerson))
            $query->where('is_person=' . (int)$isPerson);
        $unitId = $this->getState('filter.unit_id');
        if(is_numeric($unitId))
            $query->where($db->quoteName('unit_id').'='.(int)$unitId);
        $respondentgroupId = $this->getState('filter.respondentgroup_id');
        if(is_numeric($respondentgroupId))
            $query->innerJoin('#__survey_respondentgroup_respondent AS rgr','rgr.respondent_id=a.id')
                ->where($db->quoteName('rgr.group_id') . ' = ' . $respondentgroupId);
        $gender = $this->getState('filter.gender');
        if(is_numeric($gender))
            $query->where($db->quoteName('gender').'='.(int)$gender);
        $responded = $this->getState('filter.responded');
        if(is_numeric($responded))
            $query->where($db->quoteName('responded').'='.(int)$responded);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'firstname'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);
        if($orderingCol=='firstname')
            $query->order('lastname ASC');

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