<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;

class SpecialitiesModel extends ListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','published','ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true)
            ->from('#__eqa_specialities')
            ->select('*');

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
}