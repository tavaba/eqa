<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Base\CampusListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

/**
 * List model của 'kỳ thi' (examseason) — gốc của cây tổ chức thi.
 *
 * Từ 2.1.6, kỳ thi được quản lý riêng theo từng cơ sở đào tạo (lọc cứng).
 *
 * @since 1.0.0
 */
class ExamseasonsModel extends CampusListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'academicyear','term','type','attermpt','nexam','default','completed','campus_id','campus_name');
        parent::__construct($config, $factory);
    }
    public function populateState($ordering = 'id', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

	/**
	 * @since 2.1.6
	 */
	protected function getCampusColumn(): string
	{
		return 'a.campus_id';
	}

	public function getListQuery()
	{
		$db = $this->getDatabase();

		$subQueryNumberOfExamsessions = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_examsessions AS z')
			->where('z.examseason_id = a.id');

		$subQueryNumberOfExams = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exams AS y')
			->where('y.examseason_id = a.id');

		$subQueryNumberOfEntries = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner AS x')
			->leftJoin('#__eqa_exams AS w', 'w.id = x.exam_id')
			->where('w.examseason_id = a.id');

		$columns = $db->quoteName(
			array('a.id', 'a.academicyear', 'a.term', 'a.type', 'a.name', 'a.attempt', 'a.default', 'a.start', 'a.finish', 'a.ppaa_req_enabled', 'a.ppaa_req_deadline', 'a.statistic', 'a.description', 'a.completed', 'cam.name'),
			array('id',   'academicyear',   'term',   'type',   'name',   'attempt',   'default',   'start',   'finish',   'ppaa_req_enabled',   'ppaa_req_deadline',   'statistic',   'description',   'completed',   'campus_name')
		);

		$query = parent::getListQuery();
		$query->from('#__eqa_examseasons AS a')
			->leftJoin('#__eqa_campuses AS cam', 'cam.id = a.campus_id')
			->select($columns)
			->select('(' . $subQueryNumberOfExamsessions . ') AS nexamsession')
			->select('(' . $subQueryNumberOfExams . ') AS nexam')
			->select('(' . $subQueryNumberOfEntries . ') AS nentry');

		// Lọc theo cơ sở đào tạo (2.1.6)
		$this->applyCampusScope($query);

		// Filtering
		$search = $this->getState('filter.search');
		if (!empty($search)) {
			$like = $db->quote('%' . trim($search) . '%');
			$query->where('a.name LIKE ' . $like);
		}

		$academicyear = $this->getState('filter.academicyear');
		if (is_numeric($academicyear)) {
			$query->where('a.academicyear = ' . (int) $academicyear);
		}

		$term = $this->getState('filter.term');
		if (is_numeric($term)) {
			$query->where('a.term = ' . (int) $term);
		}

		$type = $this->getState('filter.type');
		if (is_numeric($type)) {
			$query->where('a.type = ' . (int) $type);
		}

		$completed = $this->getState('filter.completed');
		if (is_numeric($completed)) {
			$query->where('a.completed = ' . (int) $completed);
		}

		$default = $this->getState('filter.default');
		if (is_numeric($default)) {
			$query->where('a.default = ' . (int) $default);
		}

		// Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
		$orderingDir = $query->db->escape($this->getState('list.direction', 'DESC'));
		$query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

		return $query;
	}
}
