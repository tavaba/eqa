<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class CohortlearnersModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','firstname','lastname');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
	public function getListQuery()
	{
		//Get cohort id
		//This param must be set by the View before calling this method
		$cohortId = $this->getState('filter.cohort_id');

		$db = $this->getDatabase();
		$columns = $db->quoteName(
			array('a.learner_id','b.code', 'b.lastname', 'b.firstname'),
			array('id',           'code',   'lastname',    'firstname')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_cohort_learner AS a')
			->leftJoin('#__eqa_learners AS b','b.id = a.learner_id')
			->where('a.cohort_id = '.$cohortId);

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
		return $query;
	}
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.cohort_id');
        $id .= ':' . $this->getState('filter.search');
        return parent::getStoreId($id);
    }
}