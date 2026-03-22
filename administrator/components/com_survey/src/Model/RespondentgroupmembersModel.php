<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Survey\Administrator\Base\ListModel;

class RespondentgroupmembersModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id','code','lastname','firstname');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        /**
         * Get the group id from the State
         * This value must be set by View before calling this model.
         */
        $groupId = (int)$this->getState('filter.group_id');


        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('r.id')            . ' AS ' . $db->quoteName('id'),
            $db->quoteName('r.type')          . ' AS ' . $db->quoteName('type'),
            $db->quoteName('r.code')          . ' AS ' . $db->quoteName('code'),
            $db->quoteName('r.lastname')      . ' AS ' . $db->quoteName('lastname'),
            $db->quoteName('r.firstname')     . ' AS ' . $db->quoteName('firstname'),
            $db->quoteName('r.name')          . ' AS ' . $db->quoteName('name'),
            $db->quoteName('r.gender')        . ' AS ' . $db->quoteName('gender'),
            $db->quoteName('r.is_person')     . ' AS ' . $db->quoteName('isPerson'),
            $db->quoteName('r.email')         . ' AS ' . $db->quoteName('email'),
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_respondentgroup_respondent AS map')
            ->leftJoin('#__survey_respondents AS r', 'r.id=map.respondent_id')
            ->select($columns)
            ->where('map.group_id='.$groupId);

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(code LIKE '.$like.' OR concat_ws(" ", lastname, firstname) LIKE '.$like.')');
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type))
            $query->where($db->quoteName('type').'='.(int)$type);
        $isPerson = $this->getState('filter.is_person');
        if(is_numeric($isPerson))
            $query->where('r.is_person=' . (int)$isPerson);
        $unitId = $this->getState('filter.unit_id');
        if(is_numeric($unitId))
            $query->where($db->quoteName('r.unit_id').'='.(int)$unitId);
        $gender = $this->getState('filter.gender');
        if(is_numeric($gender))
            $query->where($db->quoteName('gender').'='.(int)$gender);


        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.type');
        return parent::getStoreId($id);
    }

    /**
     * Check whether the current user has permission to add/remove respondent in a group
     *
     * @return bool
     * @since 1.0.0
     */
    public function canAddOrRemove(): bool
    {
        /**
         * A user is allowed to add/remove respondents in a respondent group only if
         * he/she has permission to edit that group
         * @var RespondentgroupModel $model
         */
        $groupId = (int)$this->getState('filter.group_id');
        $model = ComponentHelper::createModel('Respondentgroup');
        return $model->canEdit($groupId);
    }
}