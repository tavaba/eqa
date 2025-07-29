<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use JFactory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use function Symfony\Component\String\b;

defined('_JEXEC') or die();

class GradecorrectionModel extends EqaAdminModel {

	public function getRequest(int $itemId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'b.code',      'b.lastname',      'b.firstname',      'd.name',         'c.name',   'a.constituent', 'a.reason', 'a.status'),
			array('id',   'learnerCode', 'learnerLastname', 'learnerFirstname', 'examseasonName', 'examName', 'constituent',   'reason',   'status')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id')
			->where('a.id = ' . $itemId);
		$db->setQuery($query);
		return $db->loadObject();
	}

	/**
	 * Ghi nhận yêu cầu đính chính được chấp nhận
	 * @param   int     $itemId
	 * @param   string  $currentUsername
	 * @param   string  $currentTime
	 *
	 *
	 * @throws Exception
	 * @since 1.1.10
	 */
	public function accept(int $itemId, string $currentUsername, string $currentTime)
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get information about the grade correction request
		$columns = [
			$db->quoteName('b.code')                 . ' AS ' . $db->quoteName('learnerCode'),
			'CONCAT_WS(" ", b.lastname, b.firstname)'      . ' AS ' . $db->quoteName('fullname'),
			$db->quoteName('d.completed')            . ' AS ' . $db->quoteName('examseasonCompleted')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id')
			->where('a.id = ' . $itemId);
		$db->setQuery($query);
		$item = $db->loadObject();

		//2. Check if can accept
		if(!isset($item))
			throw new Exception("Không tìm thấy yêu cầu đính chính");
		if($item->examseasonCompleted)
			throw new Exception("Kỳ thi đã kết thúc nên không thể chấp nhận yêu cầu đính chính");

		//3. Update database
		$query = $db->getQuery(true)
			->update('#__eqa_gradecorrections')
			->set([
				'status = ' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED,
				'handled_by = ' . $db->quote($currentUsername),
				'handled_at = ' . $db->quote($currentTime)
			])
			->where('id = ' . $itemId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception("Xảy ra lỗi khi chấp nhận yêu cầu đính chính");

		//4. Return success message
		$app = JFactory::getApplication();
		$msg = Text::sprintf('Yêu cầu đính chính của <b>%s (%s)</b> đã được chấp nhận',
			$item->fullname, $item->learnerCode
		);
		$app->enqueueMessage($msg, "success");
	}
	public function getRejectForm($itemId)
	{
		//1. Load form
		$form = FormHelper::getBackendForm('com_eqa.gradecorrection.reject', 'gradecorrectionreject.xml', []);

		//2. Load form data
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('b.code', 'b.lastname', 'b.firstname','c.name','a.reason'),
			array('learnerCode', 'learnerLastname', 'learnerFirstname','examName','reason')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->where('a.id = ' . (int)$itemId);
		$db->setQuery($query);
		$item = $db->loadObject();
		$data=[
			'id' => $itemId,
			'learner'=> $item->learnerCode . ' - ' . implode(' ', [$item->learnerLastname,$item->learnerFirstname]),
			'exam'=>$item->examName,
			'reason'=>$item->reason,
			'description'=>''
		];

		//3. Bind data into form
		$form->bind($data);
		return $form;
	}
	public function reject(int $itemId, string $description, string $currentUsername, string $currentTime)
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get information about the grade correction request
		$columns = [
			$db->quoteName('b.code')                 . ' AS ' . $db->quoteName('learnerCode'),
			'CONCAT_WS(" ", b.lastname, b.firstname)'      . ' AS ' . $db->quoteName('fullname'),
			$db->quoteName('d.completed')            . ' AS ' . $db->quoteName('examseasonCompleted')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id')
			->where('a.id = ' . $itemId);
		$db->setQuery($query);
		$item = $db->loadObject();

		//2. Check if can reject
		if(!isset($item))
			throw new Exception("Không tìm thấy yêu cầu đính chính");
		if($item->examseasonCompleted)
			throw new Exception("Kỳ thi đã kết thúc nên không thể từ chối yêu cầu đính chính");

		//3. Update database
		$query = $db->getQuery(true)
			->update('#__eqa_gradecorrections')
			->set([
				'status = ' . ExamHelper::EXAM_PPAA_STATUS_REJECTED,
				'description = ' . $db->quote($description),
				'handled_by = ' . $db->quote($currentUsername),
				'handled_at = ' . $db->quote($currentTime)
			])
			->where('id = ' . $itemId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception("Xảy ra lỗi khi từ chối yêu cầu đính chính");

		//4. Return success message
		$app = JFactory::getApplication();
		$msg = Text::sprintf('Yêu cầu đính chính của <b>%s (%s)</b> đã bị từ chối',
			$item->fullname, $item->learnerCode
		);
		$app->enqueueMessage($msg, "success");
	}

	public function getCorrectionForm($itemId)
	{
		//1. Load form
		$form = FormHelper::getBackendForm('com_eqa.gradecorrection.correct', 'gradecorrection.xml');

		//2. Load form data
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('b.code',      'b.lastname',      'b.firstname',      'f.name',     'c.name',   'a.constituent', 'a.reason', 'e.pam1', 'e.pam2', 'd.mark_orig'),
			array('learnerCode', 'learnerLastname', 'learnerFirstname', 'examseason', 'exam', 'constituent',   'reason',   'pam1',   'pam2',   'origMark')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_exam_learner AS d', 'd.exam_id=a.exam_id AND d.learner_id=a.learner_id')
			->leftJoin('#__eqa_class_learner AS e', 'e.class_id=d.class_id AND e.learner_id=a.learner_id')
			->leftJoin('#__eqa_examseasons AS f', 'f.id=c.examseason_id')
			->where('a.id = ' . (int)$itemId);
		$db->setQuery($query);
		$item = $db->loadObject();
		if(empty($item))
			throw new Exception("Có lỗi khi tìm dữ liệu về yêu cầu đính chính");

		//3. Bind data into form (depent on constituent)
		$data=[
			'id' => $itemId,
			'constituent' => $item->constituent,
			'learner'=> $item->learnerCode . ' - ' . implode(' ', [$item->learnerLastname,$item->learnerFirstname]),
			'examseason' => $item->examseason,
			'exam'=>$item->exam,
			'reason'=>$item->reason
		];
		switch ($item->constituent) {
			case ExamHelper::MARK_CONSTITUENT_PAM1:
				$data['pam1'] = $item->pam1;
				$form->removeField('pam2');
				$form->removeField('final_exam');
				break;
			case ExamHelper::MARK_CONSTITUENT_PAM2:
				$data['pam2'] = $item->pam2;
				$form->removeField('pam1');
				$form->removeField('final_exam');
				break;
			case ExamHelper::MARK_CONSTITUENT_PAM:
				$data['pam1'] = $item->pam1;
				$data['pam2'] = $item->pam2;
				$form->removeField('final_exam');
				break;
			case ExamHelper::MARK_CONSTITUENT_FINAL_EXAM:
				$data['final_exam'] = $item->origMark;
				$form->removeField('pam1');
				$form->removeField('pam2');
				break;
			case ExamHelper::MARK_CONSTITUENT_ALL:
				$data['pam1'] = $item->pam1;
				$data['pam2'] = $item->pam2;
				$data['final_exam'] = $item->origMark;
				break;
			default:
				throw new Exception("Dữ liệu không hợp lệ");
		}
		$form->bind($data);

		//4. Return form
		return $form;
	}

	public function correct(array $formData, string $currentUsername, string $currentTime)
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Parse form data
		$itemId = $formData['id'] ?? null;
		$constituent = $formData['constituent'] ?? null;
		$newPam1 = $formData['pam1'] ?? null;
		$newPam2 = $formData['pam2'] ?? null;
		$newFinalExam = $formData['final_exam'] ?? null;
		$changed = $formData['changed'] ?? null;
		$description = $formData['description'] ?? null;
		$reviewerId = $formData['reviewer_id'] ?? null;
		$isCompleted = $formData['completed'] ?? null;

		//2. Some values must be present
		if(is_null($itemId)) throw new Exception("Thiếu mã số yêu cầu đính chính");
		if(is_null($constituent)) throw new Exception("Thiếu loại điểm cần đính chính");
		if(is_null($changed)) throw new Exception("Thiếu trạng thái thay đổi");
		if(is_null($description)) throw new Exception("Thiếu mô tả việc xử lý yêu cầu đính chính");
		if(is_null($reviewerId)) throw new Exception("Thiếu thông tin về người đã xử lý yêu cầu đính chính");
		if(is_null($isCompleted)) throw new Exception("Thiếu thông tin về trạng thái xử lý");
		if(
			($constituent==ExamHelper::MARK_CONSTITUENT_PAM1 && (!is_numeric($newPam1) || $newPam2!=null || $newFinalExam!=null))
			|| ($constituent==ExamHelper::MARK_CONSTITUENT_PAM2 && (!is_numeric($newPam2) || $newPam1!=null || $newFinalExam!=null))
			|| ($constituent==ExamHelper::MARK_CONSTITUENT_FINAL_EXAM && (!is_numeric($newFinalExam) || $newPam1!=null || $newPam2!=null))
			|| ($constituent==ExamHelper::MARK_CONSTITUENT_PAM && (!is_numeric($newPam1) || !is_numeric($newPam2) || $newFinalExam!=null))
			|| ($constituent==ExamHelper::MARK_CONSTITUENT_ALL && (!is_numeric($newPam1) || !is_numeric($newPam2) || !is_numeric($newFinalExam)))
		)
			throw new Exception("Các thành phần điểm không phù hợp với yêu cầu đính chính");

		//3. New marks must be valid
		if($newPam1!==null && !ExamHelper::isValidMark($newPam1)) throw new Exception("Giá trị điểm quá trình TP1 không hợp lệ");
		if($newPam2!==null && !ExamHelper::isValidMark($newPam2)) throw new Exception("Giá trị điểm quá trình TP2 không hợp lệ");
		if($newFinalExam!==null && !ExamHelper::isValidMark($newFinalExam)) throw new Exception("Giá trị điểm cuối kỳ không hợp lệ");

		//4. Save the reviewer info and the description to the table #__eqa_gradecorrections
		$updatedValues =[
			'reviewer_id = ' . (int)$reviewerId,
			'changed = ' . (int)$changed,
			'description = ' . $db->quote($description),
			'status = ' . ($isCompleted ? ExamHelper::EXAM_PPAA_STATUS_DONE : ExamHelper::EXAM_PPAA_STATUS_REQUIRE_INFO),
			'updated_by = ' . $db->quote($currentUsername),
			'updated_at = ' . $db->quote($currentTime)
		];
		$query = $db->getQuery(true)
			->update('#__eqa_gradecorrections')
			->set($updatedValues)
			->where('id =' . (int)$itemId);
		$db->setQuery($query);
		if(!$db->execute()) throw new Exception("Xảy ra lỗi khi lưu lại thông tin về việc xử lý yêu cầu đính chính");

		//5. If no changes are made then return immediately
		if(!$changed)
			return;

		//6. Retrieve information about the correction request
		$columns = $db->quoteName(
			array('a.exam_id', 'a.learner_id', 'b.class_id', 'd.subject_id', 'e.pam1', 'e.pam2', 'e.allowed', 'e.ntaken', 'e.expired', 'b.attempt','c.type',     'c.value',     'b.anomaly', 'b.conclusion'),
			array('examId',    'learnerId',    'classId',    'subjectId',    'pam1',   'pam2',   'allowed',   'ntaken',   'expired',   'attempt',  'stimulType', 'stimulValue', 'anomaly',   'conclusion')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_exam_learner AS b', 'b.exam_id=a.exam_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_stimulations AS c', 'c.id=b.stimulation_id')
			->leftJoin('#__eqa_classes AS d', 'd.id=b.class_id')
			->leftJoin('#__eqa_class_learner AS e', 'e.class_id=d.id AND e.learner_id=a.learner_id')
			->where('a.id=' . (int)$itemId);
		$db->setQuery($query);
		$examinee = $db->loadObject();
		if(empty($examinee))
			throw new Exception('Không tìm thấy thông tin của thí sinh');

		//7. Update database
		//7.1. Calculate pam (maybe new, maybe old)
		$newPam1 = is_null($newPam1) ? $examinee->pam1 : $newPam1;
		$newPam2 = is_null($newPam2) ? $examinee->pam2 : $newPam2;
		$newPam = ExamHelper::calculatePam($examinee->subjectId, $newPam1, $newPam2);

		//7.2. Update the table #__eqa_class_learner if $pam1 or $pam2 has been changed
		if($constituent == !ExamHelper::MARK_CONSTITUENT_FINAL_EXAM)
		{
			$updatedValues = [
				'pam1 = ' . (float)$newPam1,
				'pam2 = ' . (float)$newPam2,
				'pam = ' . $newPam
			];
			if(ExamHelper::isAllowedToFinalExam($newPam1, $newPam2, $newPam))
			{
				$updatedValues[] = 'allowed = 1';
				if($examinee->ntaken==0)                //Chỉ cập nhật trường expired nếu chưa có lần thi nào
					$updatedValues[] = 'expired = 0';
			}
			else
			{
				$updatedValues[] = 'allowed = 0';
				$updatedValues[] = 'expired = 1';
			}

			$query = $db->getQuery(true)
				->update('#__eqa_class_learner')
				->set($updatedValues)
				->where('class_id='.(int)$examinee->classId.' AND learner_id='.(int)$examinee->learnerId);
			$db->setQuery($query);
			if(!$db->execute())
				throw new Exception("Xảy ra lỗi khi cập nhật điểm quá trình");
		}

		//7.3. Always update the table #__eqa_exam_learner because some marks have been changed
		//7.3.1. Tính toán lại điểm thi, điểm học phần và kết luận
		$admissionYear = $examinee->attempt>1 ? DatabaseHelper::getLearnerAdmissionYear($examinee->learnerId) : 0;
		$addValue = $examinee->stimulType==StimulationHelper::TYPE_ADD ? $examinee->stimulValue : 0;
		$finalMark = ExamHelper::calculateFinalMark($newFinalExam, $examinee->anomaly, $examinee->attempt, $addValue, $admissionYear);
		$moduleMark = ExamHelper::calculateModuleMark($examinee->learnerId, $newPam, $finalMark, $examinee->attempt, $admissionYear);
		$conclusion = ExamHelper::conclude($moduleMark, $finalMark, $examinee->anomaly, $examinee->attempt);
		$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);

		//7.3.2. Cập nhật điểm phúc khảo vào bảng #__eqa_exam_learner
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set($db->quoteName('ppaa') . '=' . ExamHelper::EXAM_PPAA_REVIEW)
			->set($db->quoteName('mark_ppaa') . '=' . $newFinalExam)
			->set($db->quoteName('mark_final') . '=' . $finalMark)
			->set($db->quoteName('module_mark') . '=' . $moduleMark)
			->set($db->quoteName('module_grade') . '=' . $db->quote($moduleGrade))
			->set($db->quoteName('conclusion') . '=' . $conclusion)
			->where('exam_id=' . $examinee->examId)
			->where('learner_id=' . $examinee->learnerId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception('Lỗi khi cập nhật điểm môn thi');

		//7.3.3 Nếu sau phúc khảo 'conclusion' có thay đổi thì cần cập nhận thông tin
		//      về quyền thi tiếp vào bảng #__eqa_class_learner
		if($examinee->conclusion == $conclusion)
			return;

		if($conclusion == ExamHelper::CONCLUSION_PASSED || $conclusion == ExamHelper::CONCLUSION_FAILED_EXPIRED)
			$expired=1;
		else
			$expired=0;
		$query = $db->getQuery(true)
			->update('#__eqa_class_learner')
			->set($db->quoteName('expired') . '=' . $expired)
			->where('class_id=' . $examinee->classId)
			->where('learner_id=' . $examinee->learnerId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception('Lỗi khi cập nhật thông tin vào lớp học phần');

	}
}
