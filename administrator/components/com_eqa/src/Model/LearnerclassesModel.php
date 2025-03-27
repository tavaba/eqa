<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class LearnerclassesModel extends ListModel {
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('academicyear', 'term', 'name');
        parent::__construct($config, $factory);
    }
    public function getListQuery()
    {
        //Get learner_id
	    $app = Factory::getApplication();
	    $learnerId = $app->input->getInt('learner_id');
		if(empty($learnerId))
			return null;

        $db = DatabaseHelper::getDatabaseDriver();
        $columns = $db->quoteName(
            array('b.id', 'c.code',       'b.term', 'b.name'),
            array('id',   'academicyear', 'term',   'name')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_class_learner AS a')
            ->leftJoin('#__eqa_classes AS b','b.id=a.class_id')
            ->leftJoin('#__eqa_academicyears AS c', 'c.id=b.academicyear_id')
            ->where('a.learner_id = ' . $learnerId);

	    //Ordering
	    $orderingCol = $query->db->escape($this->getState('list.ordering','academicyear'));
	    $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
	    $query->order($db->quoteName($orderingCol).' '.$orderingDir);

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
        $id .= ':' . $this->getState('filter.class_id');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.allowed');
        return parent::getStoreId($id);
    }
}