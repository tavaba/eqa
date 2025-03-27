<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class MmproductionsModel extends EqaListModel{
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'firstname','quantity');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'c.name', 'b.lastname', 'b.firstname', 'a.role', 'a.quantity'),
			array('id',   'exam',   'lastname',   'firstname',   'role',   'quantity')
		);
        $query =  $db->getQuery(true)
	        ->select($columns)
            ->from('#__eqa_mmproductions AS a')
	        ->leftJoin('#__eqa_employees AS b', 'b.id=a.examiner_id')
	        ->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id');

		//Filtering

		//Sorting
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
}