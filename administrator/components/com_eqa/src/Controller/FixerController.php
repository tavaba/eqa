<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use stdClass;

defined('_JEXEC') or die();

class FixerController extends  EqaFormController
{
	public function getClassLearners_bak()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true);

		$classId = $this->input->getInt('class_id');
		if (empty($classId))
			throw new Exception('Không xác định được lớp học phần');

		// Build the query - adjust table names and fields according to your database structure
		$query->select($db->quoteName(['b.id', 'b.code', 'b.lastname', 'b.firstname']))
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->where($db->quoteName('a.class_id') . ' = ' . $classId)
			->order($db->quoteName('b.firstname') . ' ASC')
			->order($db->quoteName('b.lastname') . ' ASC');
		$db->setQuery($query);
		try {
			$items = $db->loadObjectList();
			if(empty($items))
				throw new Exception('Không có sinh viên nào trong lớp này');

			$learners = [];
			foreach ($items as $item) {
				$learners[] = [
					'id'=>$item->id,
					'name'=>$item->code . ' - ' . $item->lastname.' '.$item->firstname
				];
			}
		} catch (Exception $e) {
			$learners = null;
		}
		$json = new JsonResponse($learners);
		$this->app->setHeader('Content-Type', 'application/json');
		//$this->app->sendHeaders();
		echo $json;
		$this->app->close();
	}
	public function jsonGetClassLearners()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true);

		$classId = $this->input->getInt('class_id');
		if (empty($classId))
			throw new Exception('Không xác định được lớp học phần');

		// Build the query - adjust table names and fields according to your database structure
		$query->select($db->quoteName(['b.id', 'b.code', 'b.lastname', 'b.firstname']))
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->where($db->quoteName('a.class_id') . ' = ' . $classId)
			->order($db->quoteName('b.firstname') . ' ASC')
			->order($db->quoteName('b.lastname') . ' ASC');
		$db->setQuery($query);
		$this->app->setHeader('Content-Type', 'application/json');
		try {
			$learners = $db->loadObjectList();
			if(empty($learners))
				throw new Exception('Không có sinh viên nào trong lớp này');

			$data = [];
			foreach ($learners as $item) {
				$data[] = [
					'id'=>$item->id,
					'name'=>$item->code . ' - ' . $item->lastname.' '.$item->firstname
				];
			}
		} catch (Exception $e) {
			$data = null;
		}
		$json = new JsonResponse($data);
		echo $json;
		$this->app->close();
	}
	/**
	 * Sửa điểm thi KTHP. Thực hiện qua phương thức GET. Yêu cầu truyền vào
	 * 3 tham số: 'examId', 'learnerId', 'newMark', 'confirm'
	 ** @since 1.1.10
	 */
	public function changeFinalExamMark()
	{
		try
		{
			//1. Check permissions
			if(!$this->app->getIdentity()->authorise('core.admin',$this->option))
				throw new Exception('0x01000000');

			//2. Get data from request
			$examId = $this->input->getInt('examId');
			if (empty($examId))
				throw new Exception('0x02000000');
			$learnerId = $this->input->getInt('learnerId');
			if(empty($learnerId))
				throw new Exception('0x03000000');
			$newMark = $this->input->getFloat('newMark');
			if(!ExamHelper::isValidMark($newMark))
				throw new Exception('0x04000000');

			//3. Retrieve additional information about the examinee from the datatabase
			$db = DatabaseHelper::getDatabaseDriver();
			$columns = $db->quoteName(
				array('a.name', 'f.code',      'b.class_id', 'd.subject_id', 'e.pam', 'b.attempt','c.type',     'c.value',     'b.anomaly', 'b.conclusion'),
				array('exam',   'learnerCode', 'classId',    'subjectId',    'pam',   'attempt',  'stimulType', 'stimulValue', 'anomaly',   'conclusion')
			);
			$query = $db->getQuery(true)
				->select($columns)
				->from('#__eqa_exam_learner AS b')
				->leftJoin('#__eqa_stimulations AS c', 'c.id=b.stimulation_id')
				->leftJoin('#__eqa_classes AS d', 'd.id=b.class_id')
				->leftJoin('#__eqa_class_learner AS e', 'e.class_id=d.id AND e.learner_id=b.learner_id')
				->leftJoin('#__eqa_learners AS f', 'f.id=b.learner_id')
				->leftJoin('#__eqa_exams AS a', 'a.id=b.exam_id')
				->where('b.exam_id=' . $examId. ' AND b.learner_id =' . $learnerId);
			$db->setQuery($query);
			$examinee = $db->loadObject(); //Mảng kết hợp, key là learnerCode
			if(empty($examinee))
				throw new Exception('Examinee not found');
			$classId = $examinee->classId;
			$pam = $examinee->pam;
			$attempt = $examinee->attempt;
			$addValue = $examinee->stimulType==StimulationHelper::TYPE_ADD ? $examinee->stimulValue : 0;
			$anomaly = $examinee->anomaly;
			$oldConclusion = $examinee->conclusion;

			//4. Check if the user has confirmed that he/she wants to change the mark
			if (!$this->input->get('confirm'))
			{
				$msg = Text::sprintf('Confirmation required. Exam: %s. Learner: %s',
					$examinee->exam,
					$examinee->learnerCode);
				$this->setMessage($msg, 'info');
				$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
				return;
			}

			//4. Recalculate final exam mark, module mark, module grade and conclusion
			$finalMark = ExamHelper::calculateFinalMark($newMark, $anomaly, $attempt, $addValue, 0);
			$moduleMark = ExamHelper::calculateModuleMark($learnerId, $pam, $finalMark, $attempt, 0);
			$conclusion = ExamHelper::conclude($moduleMark, $finalMark, $anomaly, $attempt);
			$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);

			//5. Update the table #__eqa_exam_learner
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set($db->quoteName('ppaa') . '=' . ExamHelper::EXAM_PPAA_REVIEW)
				->set($db->quoteName('mark_ppaa') . '=' . $newMark)
				->set($db->quoteName('mark_final') . '=' . $finalMark)
				->set($db->quoteName('module_mark') . '=' . $moduleMark)
				->set($db->quoteName('module_grade') . '=' . $db->quote($moduleGrade))
				->set($db->quoteName('conclusion') . '=' . $conclusion)
				->where('exam_id=' . $examId)
				->where('learner_id=' . $learnerId);
			$db->setQuery($query);
			if(!$db->execute())
				throw new Exception('Update mark error');

			//6. Nếu sau sửa điểm mà 'conclusion' có thay đổi thì cần cập nhận thông tin
			//   về quyền thi tiếp vào bảng #__eqa_class_learner
			if($oldConclusion == $conclusion)
				return;
			if($conclusion == ExamHelper::CONCLUSION_PASSED || $conclusion == ExamHelper::CONCLUSION_FAILED_EXPIRED)
				$expired=1;
			else
				$expired=0;
			$query = $db->getQuery(true)
				->update('#__eqa_class_learner')
				->set($db->quoteName('expired') . '=' . $expired)
				->where('class_id=' . $classId)
				->where('learner_id=' . $learnerId);
			$db->setQuery($query);
			if(!$db->execute())
				throw new Exception('Lỗi khi cập nhật thông tin vào lớp học phần');

			//7. Redirect back to the page where this method was called.
			$this->setMessage('Updated for learner '. $examinee->learnerCode);
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
			return;
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
			return;
		}
	}

	/**
	 * Xóa nợ của một sinh viên ở một môn thi. Thực hiện qua phương thức GET. Yêu cầu truyền vào
	 * 3 tham số: 'examId', 'learnerId', 'confirm'.
	 *
	 * @since 1.1.10
	 */
	public function unsetDebt()
	{
		try
		{
			//1. Check permissions
			if(!$this->app->getIdentity()->authorise('core.admin',$this->option))
				throw new Exception('0x01000000');

			//2. Get data from request
			$examId = $this->input->getInt('examId');
			if (empty($examId))
				throw new Exception('0x02000000');
			$learnerId = $this->input->getInt('learnerId');
			if(empty($learnerId))
				throw new Exception('0x03000000');

			//3. Retrieve additional information about the examinee from the datatabase
			$db = DatabaseHelper::getDatabaseDriver();
			$columns = $db->quoteName(
				array('c.name', 'b.code'),
				array('exam',   'learnerCode')
			);
			$query = $db->getQuery(true)
				->select($columns)
				->from('#__eqa_exam_learner AS a')
				->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
				->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
				->where('a.exam_id=' . $examId. ' AND a.learner_id =' . $learnerId);
			$db->setQuery($query);
			$examinee = $db->loadObject(); //Mảng kết hợp, key là learnerCode
			if(empty($examinee))
				throw new Exception('Examinee not found');

			//4. Check if the user has confirmed that he/she wants to change the mark
			if (!$this->input->get('confirm'))
			{
				$msg = Text::sprintf('Confirmation required. Exam: %s. Learner: %s',
					$examinee->exam,
					$examinee->learnerCode);
				$this->setMessage($msg, 'info');
				$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
				return;
			}

			//5. Update 'debtor' field in the table #__eqa_exam_learner
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set($db->quoteName('debtor') . '=0')
				->where('exam_id=' . $examId)
				->where('learner_id=' . $learnerId);
			$db->setQuery($query);
			if(!$db->execute())
				throw new Exception('Update debtor error');

			//6. Redirect back to the page where this method was called.
			$this->setMessage('Đã xóa nợ cho: '. $examinee->learnerCode);
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
			return;
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
			return;
		}
	}

	public function recalculateExamResult()
	{
		try
		{
			//1. Check permissions
			if(!$this->app->getIdentity()->authorise('core.admin',$this->option))
				throw new Exception('0x01000000');

			//2. Get data from request
			$examseasonId = $this->input->getInt('examseasonId');
			if (empty($examseasonId))
				throw new Exception('0x02000000');

			//3. Fix
			$model = $this->getModel('fixer');
			$model->recalculateExamResult($examseasonId);
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
		}
	}

	public function deleteExam()
	{
		try
		{
			//1. Check permissions
			if(!$this->app->getIdentity()->authorise('core.admin',$this->option))
				throw new Exception('0x01000000');

			//2. Get data from request
			$examId = $this->input->getInt('examId');
			if (empty($examId))
				throw new Exception('0x02000000');

			//3. Fix
			$model = $this->getModel('fixer');
			$model->deleteExam($examId);

			//4. Redirect with a success message
			$this->setMessage('Đã xóa');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
		}
	}

	public function fixPam()
	{
		$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
		try
		{
			//1. Check permissions
			if(!$this->app->getIdentity()->authorise('core.admin',$this->option))
				throw new Exception('0x01000000');

			//2. Init data
			$items = [];
			$items[] =[
				'learnerCode' => 'DT050131',
				'classCode' => 'DT1DVDA3-2-24(D5PCL-01)',
				'pam1'=>9,
				'pam2'=>9,
				'pam'=>9,
				'allowed'=>1,
				'expired'=>0
			];
			$items[] =[
				'learnerCode' => 'AT200250',
				'classCode' => 'LTCBNN2-2-24(A20C8D7-02)',
				'pam1'=>8,
				'pam2'=>8.5,
				'pam'=>0.7*8 + 0.3*8.5,
				'allowed'=>1,
				'expired'=>0
			];
			$items[] =[
				'learnerCode' => 'DT080309',
				'classCode' => 'CBTT2.1-2-24(A21C9D8-10)',
				'pam1'=>5.5,
				'pam2'=>10,
				'pam'=>0.7*5.5 + 0.3*10,
				'allowed'=>1,
				'expired'=>0
			];

			//3. Process
			$db = DatabaseHelper::getDatabaseDriver();
			$columns = $db->quoteName(
				array('a.learner_id', 'a.class_id'),
				array('learnerId', 'classId')
			);
			$query = $db->getQuery(true)
				->select($columns)
				->from('#__eqa_class_learner AS a')
				->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
				->leftJoin('#__eqa_classes AS c', 'c.id=a.class_id')
				->where('b.code=:learnerCode')
				->where('c.code=:classCode');
			foreach ($items as $item) {
				$query->bind(':learnerCode', $item['learnerCode']);
				$query->bind(':classCode', $item['classCode']);
				$db->setQuery($query);
				$learner = $db->loadObject();
				if(!isset($learner))
					throw new Exception("Không tìm thấy mã sinh viên {$item['learnerCode']} trong lớp {$item['classCode']}");

				$updateQuery = $db->getQuery(true)
					->update('#__eqa_class_learner')
					->set($db->quoteName('pam1') . '=' . $item['pam1'])
					->set($db->quoteName('pam2') . '=' . $item['pam2'])
					->set($db->quoteName('pam') . '=' . $item['pam'])
					->set($db->quoteName('allowed') . '=' . $item['allowed'])
					->set($db->quoteName('expired') . '=' . $item['expired'])
					->where('learner_id=' . $learner->learnerId)
					->where('class_id=' . $learner->classId);
				$db->setQuery($updateQuery);
				if(!$db->execute())
					throw new Exception("Lỗi khi cập nhật ĐQT cho sinh viên {$item['learnerCode']} trong lớp {$item['classCode']}");
			}

			//4. Redirect with a success message
			$this->setMessage('Đã xử lý xong');
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
		}
	}
	public function undoApplyingStimulation()
	{

	}
}