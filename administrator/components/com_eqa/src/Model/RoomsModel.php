<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class RoomsModel extends EqaListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','type','capacity','published','ordering','building');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'building', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id', 'a.building_id', 'a.code', 'a.type', 'a.capacity', 'a.published', 'a.ordering', 'b.code'),
            array('id',    'building_id',   'code',   'type',   'capacity',   'published',   'ordering', 'building')
        );
        $query->from('#__eqa_rooms AS a')
            ->leftJoin('#__eqa_buildings AS b','a.building_id = b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('a.code LIKE '.$like);
        }

        $building_id = $this->getState('filter.building_id');
        if(!empty($building_id)){
            $query->where('a.building_id = '.(int)$building_id);
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type)){
            $query->where('a.type = '.(int)$type);
        }

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);
        $query->order('code asc');

        return $query;
    }

    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.building_id');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }
}
