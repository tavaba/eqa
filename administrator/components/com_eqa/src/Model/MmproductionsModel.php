<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\CampusListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class MmproductionsModel extends CampusListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'firstname','quantity','campus_id');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * @since 2.1.6
     */
    protected function getCampusColumn(): string
    {
        return 'd.campus_id';
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
	        ->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
	        ->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id');

        // Lọc cứng theo cơ sở đào tạo, suy diễn qua môn thi → kỳ thi (2.1.6)
        $this->applyCampusScope($query);

		//Filtering

		//Sorting
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    /**
     * @since 2.1.6
     */
    public function getStoreId($id = '')
    {
        return parent::getStoreId($id);
    }
}