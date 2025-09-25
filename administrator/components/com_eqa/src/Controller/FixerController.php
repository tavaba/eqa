<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;
use stdClass;

defined('_JEXEC') or die();

class FixerController extends  EqaFormController
{
	public function debtors()
	{
		if(!$this->app->getIdentity()->authorise('core.admin'))
			die('Invalid request');
		jexit();
		$db = DatabaseHelper::getDatabaseDriver();

		//Lấy thông tin về tất cả thí sinh nợ phí các môn thi
		$columns = [
			$db->quoteName('a.learner_id')       . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('a.class_id')       . ' AS ' . $db->quoteName('classId'),
			$db->quoteName('a.exam_id')       . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('c.code')       . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('c.lastname')    . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('c.firstname')   . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('b.name')        . ' AS ' . $db->quoteName('exam'),
			$db->quoteName('e.ntaken')      . ' AS ' . $db->quoteName('nTaken'),
			$db->quoteName('e.pam')      . ' AS ' . $db->quoteName('pam'),
			$db->quoteName('a.mark_orig')  . ' AS ' . $db->quoteName('markOrig'),
			$db->quoteName('a.mark_final')  . ' AS ' . $db->quoteName('markFinal'),
			$db->quoteName('a.module_mark')  . ' AS ' . $db->quoteName('moduleMark'),
			$db->quoteName('a.module_grade')  . ' AS ' . $db->quoteName('moduleGrade'),
			$db->quoteName('a.conclusion')  . ' AS ' . $db->quoteName('conclusion'),
			$db->quoteName('f.name')        . ' AS ' . $db->quoteName('examseason'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->leftJoin('#__eqa_classes AS d', 'd.id = a.class_id')
			->leftJoin('#__eqa_class_learner AS e', 'e.class_id=a.class_id AND e.learner_id=a.learner_id')
			->leftJoin('#__eqa_examseasons AS f', 'f.id=b.examseason_id')
			->where('a.attempt=1 AND a.debtor=1 AND e.allowed=1 AND e.expired=0')
			->order('b.examseason_id DESC');
		$db->setQuery($query);
		$examinees = $db->loadObjectList(); //Mỗi phần tử là một đối tượng
		if(empty($examinees))
		{
			echo 'No info';
			jexit();
		}

		echo '<table class="table table-bordered table-hover">';
		{
			echo '<tr><td>TT</td><td>Mã HVSV</td><td>Họ đệm</td><td>Tên</td><td>Môn thi</td><td>Đã thi</td><td>ĐQT</td><td>Điểm1</td><td>Điểm2</td><td>Điểm HP</td><td>Bằng chữ</td><td>Kết luận</td><td>Kỳ thi</td></tr>';
			$seq=0;
			foreach ($examinees as $examinee) {
				$seq++;
				echo "<tr>";
				echo "<td>{$seq}</td>";
				echo "<td>{$examinee->learnerCode}</td>";
				echo "<td>{$examinee->lastname}</td>";
				echo "<td>{$examinee->firstname}</td>";
				echo "<td>{$examinee->exam}</td>";
				echo "<td>{$examinee->nTaken}</td>";
				echo "<td>".ExamHelper::markToText($examinee->pam)."</td>";
				echo "<td>".ExamHelper::markToText($examinee->markOrig)."</td>";
				echo "<td>".ExamHelper::markToText($examinee->markFinal)."</td>";
				echo "<td>".ExamHelper::markToText($examinee->moduleMark)."</td>";
				echo "<td>{$examinee->moduleGrade}</td>";
				echo "<td>".($examinee->conclusion?ExamHelper::getConclusion($examinee->conclusion):"-")."</td>";
				echo "<td>{$examinee->examseason}</td>";
				echo "</tr>";
			}
		}
		echo '</table>';

		//Fix
		foreach ($examinees as $examinee)
		{
			$moduleMark = round(0.3 * $examinee->pam,1);
			//Update exam result
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set([
					'mark_orig=0',
					'mark_final=0',
					'module_mark=' . $moduleMark,
					'module_grade=' . $db->quote('F'),
					'conclusion='.ExamHelper::CONCLUSION_FAILED,
				])
				->where("learner_id={$examinee->learnerId}")
				->where("exam_id={$examinee->examId}");
			$db->setQuery($query)->execute();

			//Update class info
			$query = $db->getQuery(true)
				->update('#__eqa_class_learner')
				->set('ntaken=1')
				->where("learner_id={$examinee->learnerId}")
				->where("class_id={$examinee->classId}");
			$db->setQuery($query);
			$db->execute();
		}
	}

	public function updateBase4Mark()
	{
		if(!$this->app->getIdentity()->authorise('core.admin'))
			die('Invalid request');

		$db = DatabaseHelper::getDatabaseDriver();

		//Load all exam-learner records
		$query = $db->getQuery(true)
			->select('exam_id, learner_id, module_mark, module_base4_mark')
			->from('#__eqa_exam_learner')
			->where('module_mark IS NOT NULL');
		$db->setQuery($query);
		$records = $db->loadAssocList();

		//Update base4 mark for each record
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set('module_base4_mark=:base4Mark')
			->where('exam_id=:examId')
			->where('learner_id=:learnerId');
		foreach ($records as $record)
		{
			if($record['module_base4_mark'] != null)
				continue;

			$base4Mark = ExamHelper::calculateBase4Mark($record['module_mark']);
			$query->bind(':base4Mark',$base4Mark, ParameterType::STRING);
			$query->bind(':examId',$record['exam_id'], ParameterType::INTEGER);
			$query->bind(':learnerId',$record['learner_id'], ParameterType::INTEGER);
			$db->setQuery($query);
			$db->execute();
		}
	}

	public function recalc():void
	{
		if(!$this->app->getIdentity()->authorise('core.admin'))
			die('Invalid request');
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			'a.exam_id          AS examId',
			'a.learner_id       AS learnerId',
			'a.class_id         AS classId',
			'a.mark_final       AS finalMark',
			'b.pam              AS pam'
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b','b.class_id=a.class_id AND b.learner_id=a.learner_id');
		$db->setQuery($query);
		$examinees = $db->loadObjectList();
		if(empty($examinees)) { echo "Nothing to do"; jexit(); }

		foreach ($examinees as $examinee)
		{
			$moduleMark = ExamHelper::calculateModuleMark(0,$examinee->pam,$examinee->finalMark,1,2023);
			$moduleBase4Mark = ExamHelper::calculateBase4Mark($moduleMark);
			$conclusion = ExamHelper::conclude($moduleMark, $examinee->finalMark, ExamHelper::EXAM_ANOMALY_NONE, 1, 2023);
			$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark,$conclusion);

			//Update exam result
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set([
					'module_mark='.$moduleMark,
					'module_base4_mark='.$moduleBase4Mark,
					'module_grade='.$db->quote($moduleGrade),
					'conclusion='.$conclusion
				])
				->where('exam_id='.$examinee->examId)
				->where('learner_id='.$examinee->learnerId);
			$db->setQuery($query)->execute();

			//Update class info
			if($conclusion == ExamHelper::CONCLUSION_PASSED)
			{
				$query = $db->getQuery(true)
					->update('#__eqa_class_learner')
					->set('expired=1')
					->where('class_id='.$examinee->classId)
					->where('learner_id='.$examinee->learnerId);
				$db->setQuery($query)->execute();
			}
		}
		echo count($examinees).' examinees have been updated';
	}

}