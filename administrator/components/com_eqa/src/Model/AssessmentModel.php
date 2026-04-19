<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\AdminModel;
class AssessmentModel extends AdminModel
{
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
