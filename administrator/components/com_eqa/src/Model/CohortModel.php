<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

defined('_JEXEC') or die();

class CohortModel extends EqaAdminModel {
	public function addLearners(int $cohortId, array $learnerIds): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		$query = $db->getQuery(true)
			->insert($db->quoteName('#__eqa_cohort_learner'))
			->columns([$db->quoteName('cohort_id'), $db->quoteName('learner_id')]);

		foreach ($learnerIds as $learnerId) {
			$query->values($cohortId . ', ' . (int) $learnerId);
		}

		// Replace INSERT with INSERT IGNORE
		$sql = str_replace('INSERT', 'INSERT IGNORE', (string) $query);

		$db->setQuery($sql);
		$db->execute();
	}

	public function removeLearners(int $cohortId, array $learnerIds): void
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$learnerIdSet = '(' . implode(',', $learnerIds) . ')';
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__eqa_cohort_learner'))
			->where(
				array(
					$db->quoteName('cohort_id') . '=' . $cohortId,
					$db->quoteName('learner_id') . ' IN ' . $learnerIdSet
				)
			);
		$db->setQuery($query);
		$db->execute();
	}
}
