<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Model\ListModel;

class LogsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'action', 'entityType');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }
     public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('a.id'),
            $db->quoteName('b.name')            . ' AS ' . $db->quoteName('user'),
            $db->quoteName('a.action'),
            $db->quoteName('a.entity_type')     . ' AS ' . $db->quoteName('entityType'),
            $db->quoteName('a.entity_id')       . ' AS ' . $db->quoteName('entityId'),
            $db->quoteName('a.result'),
            $db->quoteName('a.data'),
            $db->quoteName('a.log_date'),
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_logs AS a')
            ->leftJoin('#__users AS b', 'b.id=a.user_id')
            ->select($columns);

        //Filtering
        $userId = $this->getState('filter.user_id');
        if(is_numeric($userId))
            $query->where($db->quoteName('a.user_id').'='.(int)$userId);

        $action = $this->getState('filter.action');
        if(is_numeric($action))
            $query->where($db->quoteName('a.action').'='.(int)$action);

        $itemType = $this->getState('filter.entity_type');
        if(is_numeric($itemType))
            $query->where($db->quoteName('a.entity_type').'='.(int)$itemType);

        $result = $this->getState('filter.result');
        if(is_numeric($result))
            $query->where($db->quoteName('a.result').'='.(int)$result);

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(a.data LIKE '.$like.')');
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'DESC'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.user_id');
        $id .= ':' . $this->getState('filter.action');
        $id .= ':' . $this->getState('filter.entity_type');
        $id .= ':' . $this->getState('filter.result');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('list.ordering');
        $id .= ':' . $this->getState('list.direction');
        return parent::getStoreId($id);
    }
}