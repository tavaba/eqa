<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class ClasslearnersModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','firstname','lastname', 'pam', 'allowed', 'ntaken', 'expired');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
	public function getListQuery()
	{
		//Get class id
		//This param must be set by the View before calling this method
		$classId = $this->getState('filter.class_id');

		$db = $this->getDatabase();
		$columns = $db->quoteName(
			array('a.learner_id','b.code', 'b.lastname', 'b.firstname', 'c.code', 'a.pam1', 'a.pam2','a.pam','a.allowed', 'a.ntaken', 'a.expired', 'a.description'),
			array('id',           'code',   'lastname',    'firstname',  'group',   'pam1',   'pam2', 'pam',   'allowed',   'ntaken',   'expired', 'description')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b','a.learner_id = b.id')
			->leftJoin('#__eqa_groups AS c', 'b.group_id = c.id')
			->where('a.class_id = '.$classId);

		//Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering','code'));
		$orderingDir = $query->db->escape($this->getState('list.direction','asc'));
		$query->order($db->quoteName($orderingCol).' '.$orderingDir);
		$query->order('firstname ASC, lastname ASC');

		//Filtering
		$search = $this->getState('filter.search');
		if(!empty($search)){
			$like = $db->quote('%'.$search.'%');
			$query->where('(CONCAT(`b`.`lastname`, " ", `b`.`firstname`) LIKE ' . $like .' OR `b`.`code` LIKE ' . $like . ')');
		}

		$allowed = $this->getState('filter.allowed');
		if(is_numeric($allowed))
			$query->where('`allowed`='.(int)$allowed);
		return $query;
	}
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.class_id');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.allowed');
        return parent::getStoreId($id);
    }
}