<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class CohortsModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id','code','name','size');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
	    $subQuerySize = $db->getQuery(true)
		    ->select('COUNT(1)')
		    ->from('#__eqa_cohort_learner AS z')
		    ->where('z.cohort_id = a.id');
        $columns = $db->quoteName(
            array('a.id', 'a.code', 'a.name', 'a.published'),
            array('id',   'code',   'name',   'published')
        );
        $query =  $db->getQuery(true)
	        ->from('#__eqa_cohorts AS a')
            ->select($columns)
	        ->select('(' . $subQuerySize . ') AS ' . $db->quoteName('size'));

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('(a.code LIKE ' . $like . 'OR a.name LIKE ' . $like . ')');
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getStoreId($id = '')
    {
        return parent::getStoreId($id);
    }
}