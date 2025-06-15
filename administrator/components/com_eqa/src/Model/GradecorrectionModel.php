<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Interface\Regradingrequest;
use stdClass;

defined('_JEXEC') or die();

class GradecorrectionModel extends EqaAdminModel
{
	public function getGradeCorrectionRequests(int $examseasonId, bool $onlyAccepted): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		$columns = [
			$db->quoteName('a.id')           . ' AS ' . $db->quoteName('id'),
			$db->quoteName('a.exam_id')      . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('d.name')         . ' AS ' . $db->quoteName('examName'),
			$db->quoteName('a.learner_id')   . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('a.constituent')  . ' AS ' . $db->quoteName('constituentCode'),
			$db->quoteName('b.code')         . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('b.lastname')     . ' AS ' . $db->quoteName('learnerLastname'),
			$db->quoteName('b.firstname')    . ' AS ' . $db->quoteName('learnerFirstname'),
			$db->quoteName('a.status')       . ' AS ' . $db->quoteName('statusCode'),
			$db->quoteName('f.pam1')         . ' AS ' . $db->quotename('pam1'),
			$db->quoteName('f.pam2')         . ' AS ' . $db->quotename('pam2'),
			$db->quoteName('c.mark_orig')    . ' AS ' . $db->quotename('finalExamMark'),
			$db->quoteName('a.reason')       . ' AS ' . $db->quotename('reason'),
			$db->quoteName('a.examiner1_id') . ' AS ' . $db->quotename('examiner1Id'),
			$db->quoteName('a.examiner2_id') . ' AS ' . $db->quotename('examiner2Id'),
			$db->quoteName('a.result')       . ' AS ' . $db->quotename('resut'),
			$db->quoteName('a.description')  . ' AS ' . $db->quotename('description')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id = a.learner_id')
			->leftJoin('#__eqa_exam_learner AS c', 'c.exam_id=a.exam_id AND c.learner_id=a.learner_id')
			->leftJoin('#__eqa_exams AS d', 'd.id=a.exam_id')
			->leftJoin('#__eqa_classes AS e', 'e.id=c.class_id')
			->leftJoin('#__eqa_class_learner AS f', 'f.class_id=e.id AND f.learner_id=b.id')
			->where('d.examseason_id='.$examseasonId);
		if(!$onlyAccepted)
			$query->where('a.status = '.ExamHelper::EXAM_PPAA_STATUS_ACCEPTED);
		$query->order('a.exam_id ASC');
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if(empty($items))
			return [];
		return $items;
	}
}
