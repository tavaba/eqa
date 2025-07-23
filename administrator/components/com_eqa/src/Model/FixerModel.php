<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;

defined('_JEXEC') or die();

class FixerModel extends EqaAdminModel {

	public function recalculateExamResult(int $examseasonId)
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//1.Get examinees
		$columns = [
			'a.exam_id               AS examId',
			'd.subject_id            AS subjectId',
			'a.class_id              AS classId',
			'a.learner_id            AS learnerId',
			'g.admissionyear         AS admissionYear',
			'b.allowed               AS isAllowed',
			'a.attempt               AS attempt',
			'a.debtor                AS isDebtor',
			'`c`.`type`              AS stimulType',
			'`c`.`value`             AS stimulValue',
			'b.pam                   AS pam',
			'a.mark_orig             AS origMark',
			'a.anomaly               AS anomaly'
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_stimulations AS c', 'c.id=a.stimulation_id')
			->leftJoin('#__eqa_exams AS d', 'd.id=a.exam_id')
			->leftJoin('#__eqa_learners AS e', 'e.id=a.learner_id')
			->leftJoin('#__eqa_groups AS f', 'f.id=e.group_id')
			->leftJoin('#__eqa_courses AS g', 'g.id=f.course_id')
			->where('d.examseason_id=' . $examseasonId);
		$db->setQuery($query);
		$examinees = $db->loadObjectList();
		if(empty($examinees))
			throw new Exception('Không tìm thấy thông tin thí sinh');

		//Calculate original mark and final mark
		foreach ($examinees as $examinee)
		{
			if($examinee->isDebtor){
				if($examinee->isAllowed)
				{
					$examinee->origMark = 0;
					$examinee->finalMark = 0;
					$examinee->ntaken = $examinee->attempt;
				}
				else
				{
					$examinee->origMark=null;
					$examinee->finalMark=null;
					$examinee->ntaken = 0;
				}
				continue;
			}

			if($examinee->stimulType == StimulationHelper::TYPE_TRANS) //&& !isDebtor
			{
				$examinee->origMark = $examinee->stimulValue;
				$examinee->finalMark = $examinee->stimulValue;
				$examinee->ntaken = $examinee->attempt-1;
				continue;
			}

			if(!$examinee->isAllowed){ // && $examinee->stimulType != StimulationHelper::TYPE_TRANS
				$examinee->origMark = null;
				$examinee->finalMark = null;
				$examinee->ntaken = 0;
				continue;
			}

			if($examinee->stimulType == StimulationHelper::TYPE_EXEMPT) //&& !isDebtor && isAllowed
			{
				$examinee->origMark = $examinee->stimulValue;
				$examinee->finalMark = $examinee->stimulValue;
				$examinee->ntaken = $examinee->attempt;
				continue;
			}

			//Trường hợp còn lại, tính $finalMark theo $origMark và $stimulValue
			$addValue = $examinee->stimulType == StimulationHelper::TYPE_ADD ? $examinee->stimulValue : 0;
			$examinee->finalMark = ExamHelper::calculateFinalMark(
				$examinee->origMark,
				$examinee->anomaly,
				$examinee->attempt,
				$addValue,
				$examinee->admissionYear
			);
			if($examinee->anomaly== ExamHelper::EXAM_ANOMALY_REDO || ExamHelper::EXAM_ANOMALY_DELAY)
				$examinee->ntaken = $examinee->attempt - 1;
			else
				$examinee->ntaken = $examinee->attempt;
		}

		//2. Tính toán kết quả học phần
		foreach ($examinees as $examinee)
		{
			//Không được thi thì chỉ kết luận là không được thi
			if($examinee->finalMark===null)
			{
				$examinee->moduleMark = null;
				$examinee->moduleGrade = null;
				$examinee->conclusion = ExamHelper::CONCLUSION_NOT_ALLOWED;
				continue;
			}

			//Trường hợp còn lại thì tính toán và kết luận
			if($examinee->stimulType == StimulationHelper::TYPE_TRANS)
				$examinee->moduleMark = $examinee->stimulValue;
			else
				$examinee->moduleMark = ExamHelper::calculateModuleMark(
					0,
					$examinee->pam,
					$examinee->finalMark,
					$examinee->attempt,
					$examinee->admissionYear);
			$examinee->conclusion = ExamHelper::conclude($examinee->moduleMark, $examinee->finalMark, $examinee->anomaly, $examinee->attempt);
			$examinee->moduleGrade = ExamHelper::calculateModuleGrade($examinee->moduleMark, $examinee->conclusion);
		}

		//3. Update records in table '#__eqa_exam_learner'
		foreach ($examinees as $examinee)
		{
			$setClause = [];
			if($examinee->origMark !== null)
				$setClause[] = 'mark_orig=' . $examinee->origMark;
			else
				$setClause[] = 'mark_orig=NULL';
			if($examinee->finalMark !== null)
				$setClause[] = 'mark_final=' . $examinee->finalMark;
			else
				$setClause[] = 'mark_final=NULL';
			if($examinee->moduleMark !== null)
				$setClause[] = 'module_mark=' . $examinee->moduleMark;
			else
				$setClause[] = 'module_mark=NULL';
			if($examinee->moduleGrade !== null)
				$setClause[] = 'module_grade=' . $db->quote($examinee->moduleGrade);
			else
				$setClause[] = 'module_grade=NULL';
			$setClause[] = 'conclusion=' . $examinee->conclusion;

			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set($setClause)
				->where('exam_id=' . $examinee->examId)
				->where('learner_id=' . $examinee->learnerId);
			$db->setQuery($query);
			if (!$db->execute()) {
				throw new Exception('Lỗi cập nhật điểm');
			}

			//Update the record in table '#__eqa_class_learner'
			$setClause = ['ntaken=' . $examinee->ntaken];
			if(in_array($examinee->conclusion,[
				ExamHelper::CONCLUSION_NOT_ALLOWED,
				ExamHelper::CONCLUSION_FAILED_EXPIRED,
				ExamHelper::CONCLUSION_PASSED])
			)
				$setClause[] = 'expired=1';
			else
				$setClause[] = 'expired=0';
			$query = $db->getQuery(true)
				->update('#__eqa_class_learner')
				->set($setClause)
				->where('class_id=' . $examinee->classId)
				->where('learner_id=' . $examinee->learnerId);
			$db->setQuery($query);
			if (!$db->execute()) {
				throw new Exception('Lỗi cập nhật vào lớp học phần');
			}
		}

		$app->enqueueMessage(Text::_('COM_EQA_FIXER_RESULT_CALCULATED'), 'success');

	}
	public function deleteExam(int $examId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		/**
		 * 1. Remove all corresponding records from table #__eqa_regradings
		 * 2. Remove all corresponding records from table #__eqa_gradecorrections
		 * 3. Remove all corresponding records from table #__eqa_exam_learner
		 * 4. Remove the record from table #__eqa_exams
		 */

		//1. Remove all corresponding records from table #__eqa_regradings
		$db->setQuery("DELETE FROM #__eqa_regradings WHERE exam_id=$examId");
		if (!$db->execute())
			throw new Exception('Lỗi xóa thông tin phúc khảo');

		//2. Remove all corresponding records from table #__eqa_gradecorrections
		$db->setQuery("DELETE FROM #__eqa_gradecorrections WHERE exam_id=$examId");
		if (!$db->execute())
			throw new Exception('Lỗi xóa yêu cầu đính chính');

		//3. Remove all corresponding records from table #__eqa_exam_learner
		$db->setQuery("DELETE FROM #__eqa_exam_learner WHERE exam_id=$examId");
		if (!$db->execute())
			throw new Exception('Lỗi xóa thí sinh');

		//4. Remove the record from table #__eqa_exams
		$db->setQuery("DELETE FROM #__eqa_exams WHERE id=$examId");
		if (!$db->execute())
			throw new Exception('Lỗi xóa môn thi');
	}
}
