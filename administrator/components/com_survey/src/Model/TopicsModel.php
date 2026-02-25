<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Model\ListModel;

class TopicsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id','title', 'createdBy');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'title', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('id'),
            $db->quoteName('title'),
            $db->quoteName('description'),
            $db->quoteName('created_by'),     //Must be left as is for access control
            $db->quoteName('modified_by'),
            $db->quoteName('modified'),
            $db->quoteName('published')     . ' AS ' . $db->quoteName('published'),
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_topics')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(title LIKE ' . $like . ')');
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'title'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        return parent::getStoreId($id);
    }

    public function canCreate(?string $specificAction = 'com.create.topic'): bool
    {
        return parent::canCreate($specificAction);
    }
}