<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class ConductsModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('courseId','groupId','firstname', 'academicScore', 'academicRating', 'conductScore', 'conductRating');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'firstname', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
	public function getListQuery()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.id')                      . ' AS ' . $db->quoteName('id'),
			$db->quoteName('e.code')                    . ' AS ' . $db->quoteName('academicyear'),
			$db->quoteName('a.term')                    . ' AS ' . $db->quoteName('termCode'),
			$db->quoteName('d.code')                    . ' AS ' . $db->quoteName('course'),
			$db->quoteName('c.code')                    . ' AS ' . $db->quoteName('group'),
			$db->quoteName('b.code')                    . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('b.firstname')               . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('b.lastname')                . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('a.excused_absence_count')   . ' AS ' . $db->quoteName('excusedAbsenceCount'),
			$db->quoteName('a.unexcused_absence_count') . ' AS ' . $db->quoteName('unexcusedAbsenceCount'),
			$db->quoteName('a.resit_count')             . ' AS ' . $db->quoteName('resitCount'),
			$db->quoteName('a.retake_count')            . ' AS ' . $db->quoteName('retakeCount'),
			$db->quoteName('a.award_count')             . ' AS ' . $db->quoteName('awardCount'),
			$db->quoteName('a.disciplinary_action_count')   . ' AS ' . $db->quoteName('disciplinaryCount'),
			$db->quoteName('a.academic_score')          . ' AS ' . $db->quoteName('academicScore'),
			$db->quoteName('a.academic_rating')         . ' AS ' . $db->quoteName('academicRating'),
			$db->quoteName('a.conduct_score')           . ' AS ' . $db->quoteName('conductScore'),
			$db->quoteName('a.conduct_rating')          . ' AS ' . $db->quoteName('conductRating'),
			$db->quoteName('a.note')                    . ' AS ' . $db->quoteName('note'),
			$db->quoteName('a.description')             . ' AS ' . $db->quoteName('description'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_conducts AS a')
			->leftJoin('#__eqa_learners AS b','b.id = a.learner_id')
			->leftJoin('#__eqa_groups AS c','c.id = b.group_id')
			->leftJoin('#__eqa_courses AS d', 'd.id = c.course_id')
			->leftJoin('#__eqa_academicyears AS e', 'e.id = a.academicyear_id');

		//Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering','code'));
		$orderingDir = $query->db->escape($this->getState('list.direction','asc'));
		$query->order($db->quoteName($orderingCol).' '.$orderingDir);
		$query->order('firstname ASC, lastname ASC');

		//Filtering
		$search = $this->getState('filter.search');
		if(!empty($search)){
			$like = $db->quote('%'.trim($search).'%');
			$query->where('(CONCAT(`b`.`lastname`, " ", `b`.`firstname`) LIKE ' . $like .' OR `b`.`code` LIKE ' . $like . ')');
		}

		$academicyear_id = $this->getState('filter.academicyear_id');
		if(is_numeric($academicyear_id))
			$query->where('`a`.`academicyear_id`='.(int)$academicyear_id);

		$term = $this->getState('filter.term');
		if(is_numeric($term))
			$query->where('`a`.`term`='.(int)$term);

		$course_id = $this->getState('filter.course_id');
		if(is_numeric($course_id))
			$query->where('`d`.`id`='.(int)$course_id);

		$group_id = $this->getState('filter.group_id');
		if(is_numeric($group_id))
			$query->where('`c`.`id`='.(int)$group_id);

		return $query;
	}
    public function getStoreId($id = '')
    {
	    $id .= ':' . $this->getState('filter.search');
	    $id .= ':' . $this->getState('filter.academicyear_id');
	    $id .= ':' . $this->getState('filter.term');
	    $id .= ':' . $this->getState('filter.course_id');
	    $id .= ':' . $this->getState('filter.group_id');
        return parent::getStoreId($id);
    }

	public function getListByTerm(int $academicyearId, int $term):array
	{
		$db = $this->getDatabase();
		$query = $this->getListQuery();
		$query->clear('where')
			->where('`a`.`academicyear_id`='.$academicyearId)
			->where('`a`.`term`='.$term);
		$db->setQuery($query);
		return $db->loadObjectList();
	}
}