<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

defined('_JEXEC') or die();

class RegradingModel extends EqaAdminModel
{
	public function getPaperRegradings(int $examseason_id=null): array|null
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		if(!$examseason_id)
			$examseason_id = DatabaseHelper::getDefaultExamseasonId();
		if(!$examseason_id)
		{
			$app->enqueueMessage('Không xác định được kỳ thi', 'error');
			return null;
		}

		$subQueryExaminer1 = $db->getQuery(true)
			->from('#__eqa_employees AS z')
			->select('concat_ws(" ", z.lastname, z.firstname)')
			->where('z.id=' . 'f.examiner1_id');
		$subQueryExaminer2 = $db->getQuery(true)
			->from('#__eqa_employees AS z')
			->select('concat_ws(" ", z.lastname, z.firstname)')
			->where('z.id=' . 'f.examiner2_id');
		$columns = $db->quoteName(
			array('b.name', 'e.mask', 'd.mark_orig'),
			array('exam',   'mask',   'originalMark')
		);
		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id = a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->leftJoin('#__eqa_exam_learner AS d', 'd.exam_id=a.exam_id AND d.learner_id=a.learner_id')
			->leftJoin('#__eqa_papers AS e', 'e.exam_id=a.exam_id AND e.learner_id=a.learner_id')
			->leftJoin('#__eqa_packages AS f', 'f.id=e.package_id')
			->select('(' . $subQueryExaminer1 . ') AS examiner1')
			->select('(' . $subQueryExaminer2 . ') AS examiner2')
			->where([
				'c.id=' . $examseason_id,
				'b.testtype=' . ExamHelper::TEST_TYPE_PAPER,
				'a.status=' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED
			]);
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	public function getHybridRegradings(int $examseason_id=null): array|null
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		if(!$examseason_id)
			$examseason_id = DatabaseHelper::getDefaultExamseasonId();
		if(!$examseason_id)
		{
			$app->enqueueMessage('Không xác định được kỳ thi', 'error');
			return null;
		}

		$columns = $db->quoteName(
			array('b.name', 'e.code', 'e.lastname', 'e.firstname',  'd.mark_orig'),
			array('exam',   'code',   'lastname',   'firstname',    'originalMark')
		);
		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id = a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->leftJoin('#__eqa_exam_learner AS d', 'd.exam_id=a.exam_id AND d.learner_id=a.learner_id')
			->leftJoin('#__eqa_learners AS e', 'e.id=a.learner_id')
			->where([
				'c.id=' . $examseason_id,
				'b.testtype=' . ExamHelper::TEST_TYPE_MACHINE_HYBRID,
				'a.status=' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED
			]);
		$db->setQuery($query);
		return $db->loadObjectList();
	}
}
