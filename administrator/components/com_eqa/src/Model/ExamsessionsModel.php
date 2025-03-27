<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class ExamsessionsModel extends EqaListModel{
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('nexaminee','nexamroom');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'start', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id',  'b.name',  'a.name', 'a.start', 'a.flexible', 'a.monitor_ids','a.examiner_ids', 'a.description'),
            array('id',  'examseason', 'name',    'start',  'flexible',   'monitor_ids',  'examiner_ids',   'description')
        );
        $query =  $db->getQuery(true)
            ->from('#__eqa_examsessions AS a')
            ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search))
        {
            $like = $db->quote('%'.$search.'%');
            $query->where('a.name LIKE '.$like);
        }

        $examseason_id = $this->getState('filter.examseason_id');
        if(is_numeric($examseason_id))
            $query->where('a.examseason_id='.(int)$examseason_id);

        $flexible = $this->getState('filter.flexible');
        if(is_numeric($flexible))
            $query->where('a.flexible='.(int)$flexible);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','start'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getItems()
    {
        $items = parent::getItems();
        if(!empty($items)){
            foreach ($items as $item){
                // Check if 'monitor_ids' exists and is not empty
                if (!empty($item->monitor_ids)) {
                    // Convert the comma-separated string back into an array
                    $item->monitor_ids = explode(',', $item->monitor_ids);
                }

                // Check if 'examiner_ids' exists and is not empty
                if (!empty($item->examiner_ids)) {
                    // Convert the comma-separated string back into an array
                    $item->examiner_ids = explode(',', $item->examiner_ids);
                }
            }
        }
        return $items;
    }
}