<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\CampusAdminModel;
class AssessmentModel extends CampusAdminModel
{
	/**
	 * @param  int $recordId
	 * @return int
	 * @since  2.1.6
	 */
	protected function getCampusIdOfRecord(int $recordId): int
	{
		return $this->getStoredCampusId('#__eqa_assessments', $recordId);
	}

	public function getAssmentTitleForExamroom(int $examroomId):?string
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('a.title')
			->from('#__eqa_assessment_learner AS al')
			->leftJoin('#__eqa_assessments AS a','a.id = al.assessment_id')
			->where('al.examroom_id = '.$examroomId)
			->setLimit(1);
		$db->setQuery($query);
		return $db->loadResult();
	}
}
