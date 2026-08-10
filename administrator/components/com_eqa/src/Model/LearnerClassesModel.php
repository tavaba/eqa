<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\LearnerAccessHelper;
use RuntimeException;
use Kma\Component\Eqa\Administrator\Helper\TermHelper;

class LearnerClassesModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('academicyear', 'term', 'name');
        parent::__construct($config, $factory);
    }
	/**
	 * Kiểm soát quyền xem danh sách lớp học phần của một người học.
	 *
	 * @return bool
	 * @since  2.1.6
	 */
	public function canViewList(): bool
	{
		$learnerId = (int) $this->getState('filter.learner_id');
		if ($learnerId <= 0)
			return false;

		try {
			LearnerAccessHelper::assertCanViewLearner($learnerId);
			return true;
		} catch (RuntimeException $e) {
			return false;
		}
	}

	public function getListQuery()
	{
		//Get the learner's ID
		//This param must be set by the View before calling this method
		$learnerId = $this->getState('filter.learner_id');
		if (empty($learnerId)) {
			return null;
		}

		// Chốt chặn quyền theo cơ sở đào tạo (2.1.6): phủ mọi lối vào model.
		LearnerAccessHelper::assertCanViewLearner((int) $learnerId);

		$db      = DatabaseHelper::getDatabaseDriver();
		$columns = [
			'a.class_id   AS id',
			'b.academicyear AS academicyear',
			'b.term         AS term',
			'b.name         AS name',
			'd.code         AS subjectCode',
			'd.credits      AS credits',
			'a.pam1         AS pam1',
			'a.pam2         AS pam2',
			'a.pam          AS pam',
			'a.allowed      AS allowed',
			'a.ntaken       AS ntaken',
			'a.expired      AS expired',
		];

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_classes AS b', 'b.id = a.class_id')
			->leftJoin('#__eqa_subjects AS d', 'd.id = b.subject_id')
			->where('a.learner_id = ' . (int) $learnerId);

		// Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering', 'academicyear'));
		$orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
		$query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

		// Filtering
		$search = $this->getState('filter.search');
		if (!empty($search)) {
			$like = $db->quote('%' . trim($search) . '%');
			$query->where('(b.name LIKE ' . $like . ' OR d.code LIKE ' . $like . ')');
		}

		$academicyear = $this->getState('filter.academicyear');
		if (is_numeric($academicyear)) {
			$query->where('b.academicyear = ' . (int) $academicyear);
		}

		$term = $this->getState('filter.term');
		if (is_numeric($term) && $term !== TermHelper::TERM_NONE) {
			$query->where('b.term = ' . (int) $term);
		}

		return $query;
	}
}