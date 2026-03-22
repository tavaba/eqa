<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Survey\Administrator\Base\ListModel;

class RespondentgroupsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'type','size','author');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $subquerySize = '(SELECT COUNT(*) FROM #__survey_respondentgroup_respondent AS map WHERE map.group_id=rg.id)';
        $columns = [
            $db->quoteName('rg.id'),
            $db->quoteName('rg.created_by'),     //Must be left as is for access control
            $db->quoteName('rg.asset_id'),       //Must be left as is for access control
            $db->quoteName('rg.name')          . ' AS ' . $db->quoteName('name'),
            $db->quoteName('rg.type')          . ' AS ' . $db->quoteName('type'),
            $db->quoteName('rg.description')   . ' AS ' . $db->quoteName('description'),
            $db->quoteName('u.name')           . ' AS ' . $db->quoteName('author'),
            $db->quoteName('rg.created')       . ' AS ' . $db->quoteName('creationTime'),
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_respondentgroups AS rg')
            ->leftJoin('#__users AS u', 'u.id=rg.created_by')
            ->select($columns)
            ->select($subquerySize. ' AS '. $db->quoteName('size'));

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(name LIKE '.$like.' OR author LIKE '.$like.')');
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type))
            $query->where($db->quoteName('type').'='.(int)$type);

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

    public function canCreate(?string $specificAction = 'com.create.rgroup'): bool
    {
        return parent::canCreate($specificAction);
    }
}