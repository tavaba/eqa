<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class ClassesModel extends ListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id','coursegroup','code','name','size','npam', 'academicyear','term');
        parent::__construct($config, $factory);
    }
	protected function populateState($ordering = 'id', $direction = 'desc'): void
	{
		parent::populateState($ordering, $direction);
	}
	public function getListQuery()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'a.coursegroup', 'a.code', 'a.name', 'a.lecturer_id', 'a.academicyear', 'a.term', 'a.size', 'a.npam', 'a.description'),
			array('id',   'coursegroup',   'code',   'name',   'lecturer_id',   'academicyear',   'term',   'size',   'npam',   'description')
		);

		$query = $db->getQuery(true);
		$query->from('#__eqa_classes AS a')
			->leftJoin('#__eqa_subjects AS c', 'c.id = a.subject_id')
			->select($columns);

		// Filtering
		$search = $this->getState('filter.search');
		if (!empty($search)) {
			$like = $db->quote('%' . trim($search) . '%');
			$query->where('(a.name LIKE ' . $like . ' OR a.code LIKE ' . $like . ')');
		}

		$unitId = $this->getState('filter.unit_id');
		if (is_numeric($unitId)) {
			$query->where('c.unit_id = ' . (int) $unitId);
		}

		$subjectId = $this->getState('filter.subject_id');
		if (is_numeric($subjectId)) {
			$query->where('a.subject_id = ' . (int) $subjectId);
		}

		$pam = $this->getState('filter.pam');
		switch ($pam) {
			case 'none':
				$query->where($db->quoteName('a.npam') . ' = 0');
				break;
			case 'full':
				$query->where($db->quoteName('a.npam') . ' = ' . $db->quoteName('a.size'));
				break;
			case 'partial':
				$query->where([
					$db->quoteName('a.npam') . ' > 0',
					$db->quoteName('a.npam') . ' < ' . $db->quoteName('a.size'),
				]);
				break;
		}

		$academicyear = $this->getState('filter.academicyear');
		if (is_numeric($academicyear)) {
			$query->where('a.academicyear = ' . (int) $academicyear);
		}

		$term = $this->getState('filter.term');
		if (is_numeric($term)) {
			$query->where('a.term = ' . (int) $term);
		}

		$lecturerId = $this->getState('filter.lecturer_id');
		if (is_numeric($lecturerId)) {
			$query->where('a.lecturer_id = ' . (int) $lecturerId);
		}

		// Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
		$orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
		$query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

		return $query;
	}
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.subject_id');
        $id .= ':' . $this->getState('filter.testtype');
        $id .= ':' . $this->getState('filter.academicyear_id');
        $id .= ':' . $this->getState('filter.term');
	    $id .= ':' . $this->getState('filter.lecturer_id');
	    $id .= ':' . $this->getState('list.ordering');
	    $id .= ':' . $this->getState('list.direction');
        return parent::getStoreId($id);
    }
}