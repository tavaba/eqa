<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Model\ClassModel;
use Kma\Component\Eqa\Administrator\Model\ExamModel;
use Kma\Component\Eqa\Administrator\Model\ExamseasonModel;
use Kma\Component\Eqa\Administrator\Model\ExamseasonsModel;
use Kma\Component\Eqa\Administrator\Model\SubjectModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpWord\PhpWord;

class ExamseasonController extends EqaFormController
{
	protected function checkCanAddExams(int $examseasonId, bool $examseasonMustBeEmpty=false, bool $throw=true): bool
	{
		//TODO: If it is required to deploy in multi-tenant environment,
		// we need to check if the user has access to this concrete examseason
		$user = $this->app->getIdentity();
		if(!$user->authorise('core.create', $this->option))
		{
			if($throw)
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			else
				return false;
		}

		/**
		 * 2. Check if the examseason has been completed
		 * @var ExamseasonModel $model
		 */
		$model = $this->getModel();
		$examseason = $model->getItem($examseasonId);
		if(empty($examseason))
		{
			if($throw)
				throw new Exception('Không tìm thấy kỳ thi');
			else
				return false;
		}
		if($examseason->completed)
		{
			if($throw)
				throw new Exception('Kỳ thi đã hoàn thành. Không thể thêm môn thi.');
			else
				return false;
		}

		/**
		 * 3. Check if the examseason is empty (no exams yet).
		 */
		if($examseasonMustBeEmpty && $model->getExamCount($examseasonId)>0)
		{
			if($throw)
			{
				$msg = 'Kỳ thi đã có môn thi. Để tránh trường hợp người dùng chọn nhầm kỳ thi khi thêm
				(RẤT NHIỀU) môn thi và phải mất rất nhiều thời gian để xóa, phần mềm yêu cầu thực hiện 
				chức năng này khi kỳ thi đang trống, tức là chưa có bất kỳ môn thi nào.';
				throw new Exception($msg);
			}
			else
				return false;
		}

		//If all the above conditions are met,
		return true;
	}
	public function addExams()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Retrieve exam season id
			$examseasonId = $this->app->input->getInt('examseason_id');
			if(empty($examseasonId))
				throw new Exception('Không xác định được kỳ thi');

			//3. Check permission
			$this->checkCanAddExams($examseasonId,true,true);

			//4. Load ids of subjects to create exams to be added
			$subjectIds = $this->input->post->get('cid',[],'array');
			$subjectIds = array_filter($subjectIds,'intval'); //Remove empty elements
			$subjectIds = array_unique($subjectIds); //Remove duplicate items

			/**
			 * PHASE 1: Redirect to form for selecting subjects
			 */
			if(empty($subjectIds))
			{
				$redirect = Route::_('index.php?option=com_eqa&view=examseason&layout=addexams&examseason_id='.$examseasonId,false);
				$this->setRedirect($redirect);
				return;
			}

			/**
			 * PHASE 2: Add exams
			 * @var ExamseasonModel $model
			*/
			$model = $this->getModel();
			$model->addExams($examseasonId, $subjectIds);

			//Redirect to list view
			$url = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$examseasonId,false);
			$this->setRedirect($url);
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			if(empty($examseasonId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$examseasonId,false);
			$this->setRedirect($url);
			return;
		}
	}
	public function addExamsForClasses():void
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Retrieve exam season id
			$examseasonId = $this->app->input->getInt('examseason_id');
			if(empty($examseasonId))
				throw new Exception('Không xác định được kỳ thi');

			//3. Check permission
			$this->checkCanAddExams($examseasonId,false,true);

			//4. Load ids of classes to create exams to be added
			$classIds = $this->input->post->get('cid',[],'array');
			$classIds = array_filter($classIds,'intval'); //Remove empty elements
			$classIds = array_unique($classIds); //Remove duplicate items

			/**
			 * PHASE 1: Redirect to form for selecting subjects
			 */
			if(empty($classIds))
			{
				$redirect = Route::_('index.php?option=com_eqa&view=examseason&layout=addExamsForClasses&examseason_id='.$examseasonId,false);
				$this->setRedirect($redirect);
				return;
			}

			/**
			 * PHASE 2: Add exams
			 * @var ExamseasonModel $examseasonModel
			 * @var ClassModel $classModel
			 * @var SubjectModel $subjectModel
			 * @var ExamModel $examModel
			 */
			$examseasonModel = $this->getModel();
			$classModel = $this->getModel('Class');
			$subjectModel = $this->getModel('Subject');
			$examModel = $this->getModel('Exam');
			foreach ($classIds as $classId) {
				//1. Load class information and subject information so we can determine some exam properties
				$class = $classModel->getItem($classId);
				if(empty($class))
					throw new Exception('Không tìm thấy lớp học phần với id='.$classId);
				$subject = $subjectModel->getItem($class->subject_id);
				if(empty($subject))
					throw new Exception('Không tìm thấy môn học với id='.$class->subject_id);

				//2. Prepare data for creating an exam
				$exam['name'] = $class->name;
				$exam['subject_id'] = $class->subject_id;
				$exam['examseason_id'] = $examseasonId;
				$exam['testtype'] = $subject->finaltesttype; //Copy test type from subject
				$exam['duration'] = $subject->finaltestduration; //Copy duration from subject
				$exam['kmonitor'] = $subject->kmonitor; //Copy duration from subject
				$exam['kassess'] = $subject->kassess; //Copy duration from subject
				$exam['usetestbank'] = empty($subject->testbankyear)?0:1;
				$exam['status'] = ExamHelper::EXAM_STATUS_UNKNOWN;
				$exam['nexaminee'] = 0;

				//3. Create the exam
				// and get its id
				// and clear the examModel state for new exam
				if (!$examModel->save($exam)) {
					$msg = sprintf('Tạo môn thi không thành công: <b>%s</b>', htmlspecialchars($exam['name']));
					$msg .= $examModel->getError();
					throw new Exception($msg);
				}
				$examId= $examModel->getState($examModel->getName().'.id');
				$examModel->setState($examModel->getName().'.id');

				//4. Load learners of the class
				$classLearners = $classModel->getLearners($classId);

				//5. Add examinees into the exam
				$countAdded = $examModel->addExaminees($examId, $classLearners);
				$countTotal = count($classLearners);

				//6. Prepare a message about how many examinees have been added
				$msg = sprintf('Lớp <b>%s</b>: đã tạo môn thi cho %d/%d thí sinh',
					htmlspecialchars($class->code),
					$countAdded,
					$countTotal
				);
				$type = $countAdded==$countTotal?'success':'info';
				$this->app->enqueueMessage($msg,$type);
			}

			//Redirect to list view
			$url = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$examseasonId,false);
			$this->setRedirect($url);
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			if(empty($examseasonId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$examseasonId,false);
			$this->setRedirect($url);
			return;
		}
	}
	public function addRetakeExams(): void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Xác định kỳ thi
			$examseasonId = $this->app->input->getInt('examseason_id');
			if(!$examseasonId)
				throw new Exception('Không xác định được kỳ thi.');

			//Access check
			$this->checkCanAddExams($examseasonId,true,true);

			/**
			 * Load the list of examinees/exams that will be used to generate retake exams
			 * @var ExamseasonsModel $examseasonsModel
			 */
			$examseasonsModel = $this->getModel('Examseasons');
			$unpassedExaminees = $examseasonsModel->getUnpassedExaminees();
			if(empty($unpassedExaminees))
				throw new Exception('Không có thí sinh nào cần thi lại.');

			//Group the list by the property 'subjectId'
			$groupedExaminees = [];
			foreach ($unpassedExaminees as $examinee) {
				$subjectId = $examinee->subjectId;
				if (!isset($groupedExaminees[$subjectId])) {
					$groupedExaminees[$subjectId] = [];
				}
				$groupedExaminees[$subjectId][] = $examinee;
			}

			/**
			 * Add retake (supplementary) exams to this examseason, one exam for each group
			 * @var ExamseasonModel $examseasonModel
			 * @var SubjectModel $subjectModel
			 * @var ExamModel $examModel
			 */
			$examseasonModel = $this->getModel();
			$subjectModel = $this->getModel('Subject');
			$examModel = $this->getModel('Exam');
			foreach ($groupedExaminees as $subjectId=>$examinees)
			{
				/**
				 * Check if exists an exam for the same subject.
				 * If yes, then skip adding another exam, just add examinees to the existing exam
				 */
				$exam = $examseasonModel->getExamForSubject($examseasonId, $subjectId);
				if (!empty($exam))
				{
					$examId = $exam->id;
					$examName = $exam->name;
					$examExists=true;
				}
				else
				{
					//Get the subject information. The subject name will be used as the name of the exam
					$subject = $subjectModel->getItem($subjectId);
					if (empty($subject))
						throw new Exception(sprintf('Không tìm thấy môn học với id=%d', $subjectId));
					$examName = $subject->name;
					$examExists = false;

					//Prepare data for creating an exam
					$examData                  = [];
					$examData['name']          = $examName;
					$examData['subject_id']    = $subjectId;
					$examData['examseason_id'] = $examseasonId;
					$examData['testtype']      = $subject->finaltesttype; //Copy test type from subject
					$examData['duration']      = $subject->finaltestduration; //Copy duration from subject
					$examData['kmonitor']      = $subject->kmonitor; //Copy duration from subject
					$examData['kassess']       = $subject->kassess; //Copy duration from subject
					$examData['usetestbank']   = empty($subject->testbankyear) ? 0 : 1;
					$examData['status']        = ExamHelper::EXAM_STATUS_UNKNOWN;
					$examData['nexaminee']     = 0;

					//Create the exam and get the id of the newly created exam.
					//And then clear the examModel state for new exam
					if (!$examModel->save($examData))
					{
						$msg = sprintf('Tạo môn thi không thành công: <b>%s</b>', htmlspecialchars($exam['name']));
						$msg .= $examModel->getError();
						throw new Exception($msg);
					}
					$examId = $examModel->getState($examModel->getName() . '.id');
					$examModel->setState($examModel->getName() . '.id');
				}

				//Add examinees into the exam
				$countAdded = $examModel->addExaminees($examId, $examinees);
				$countTotal = count($examinees);
				if($examExists)
					$msg = sprintf('Môn thi <b>%s</b> đã tồn tại, %d/%d đã được thêm vào',
						htmlspecialchars($examName),
						$countAdded,
						$countTotal
					);
				else
					$msg = sprintf('Đã tạo môn thi <b>%s</b>, %d/%d đã được thêm vào',
						htmlspecialchars($examName),
						$countAdded,
						$countTotal
					);
				$this->app->enqueueMessage($msg,'success');
			}
			//Redirect to the list of exams of the examseason
			$msg = sprintf('Tổng cộng có %d môn thi, %d lượt thí sinh được xử lý',
				count($groupedExaminees),
				count($unpassedExaminees));
			$this->app->enqueueMessage($msg);
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examseasonExams&examseason_id='.$examseasonId,false));
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			if(empty($examseasonId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$examseasonId,false);
			$this->setRedirect($url);
		}
	}
	protected function setPpaaReqStatus(bool $status){
		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=examseasons',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$cid = $this->input->post->get('cid',[],'int');
		if(empty($cid)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}
		$examseasonId = $cid[0];
		$model = $this->getModel();

		if($status)
			$model->enablePpaaReq($examseasonId);
		else
			$model->disablePpaaReq($examseasonId);
	}
	public function enablePpaaReq()
	{
		$this->setPpaaReqStatus(true);
	}
	public function disablePpaaReq()
	{
		$this->setPpaaReqStatus(false);
	}
	public function exportExaminees()
	{
		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=examseasons',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.manage',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$cid = $this->input->post->get('cid',[],'int');
		if(empty($cid)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}
		$examseasonId = $cid[0];
		$model = $this->getModel();
		$examinees = $model->getExaminees($examseasonId);

		//Initialize a spreadsheet
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		IOHelper::writeExamseasonExaminees($sheet, $examinees);

		//Send file
		IOHelper::sendHttpXlsx($spreadsheet,'Danh sách thí sinh kỳ thi.xlsx');
		exit();
	}

	/**
	 * Export danh sách các trường hợp thí sinh không đủ điều kiện thi
	 * @return void
	 *
	 * @throws \Exception
	 * @since version
	 */
	public function exportIneligibleEntries()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.manage',$this->option))
				throw new Exception(Text::_('COM_EQA_MSG_UNAUTHORISED'));

			//3. Get data from post
			$cid = $this->input->post->get('cid',[],'int');
			if(empty($cid))
				throw new Exception(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'));
			$examseasonId = $cid[0];

			//4. Get model and retrieve examinees
			$model = $this->getModel();
			$ineligibleEntries = $model->getIneligibleEntries($examseasonId);
			if(empty($ineligibleEntries))
				throw new Exception('Không có thí sinh nào bị cấm thi');

			//5. Write to excel
			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getSheet(0);
			$examseasonInfo = DatabaseHelper::getExamseasonInfo($examseasonId);
			IOHelper::writeExamseasonIneligibleEntries($sheet, $examseasonInfo, $ineligibleEntries);

			//6. Send file
			IOHelper::sendHttpXlsx($spreadsheet,'Danh sách cấm thi.xlsx');
			exit();
		}
		catch (Exception $exception)
		{
			$this->setMessage($exception->getMessage(),'error');
			$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=examseasons',false));
			return;
		}
	}
	public function exportSanctions()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.manage',$this->option))
				throw new Exception(Text::_('COM_EQA_MSG_UNAUTHORISED'));

			//3. Get data from post
			$cid = $this->input->post->get('cid',[],'int');
			if(empty($cid))
				throw new Exception(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'));
			$examseasonId = $cid[0];

			//4. Get model and retrieve sanctions
			$model = $this->getModel();
			$sanctions = $model->getSanctions($examseasonId);
			if(empty($sanctions))
				throw new Exception('Không có thí sinh nào bị kỷ luật');

			//5. Write to excel
			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getSheet(0);
			$examseasonInfo = DatabaseHelper::getExamseasonInfo($examseasonId);
			IOHelper::writeExamseasonSanctions($sheet, $examseasonInfo, $sanctions);

			//6. Send file
			IOHelper::sendHttpXlsx($spreadsheet,'Danh sách xử lý kỷ luật.xlsx');
			exit();
		}
		catch (Exception $exception)
		{
			$this->setMessage($exception->getMessage(),'error');
			$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=examseasons',false));
			return;
		}
	}

	public function exportLearnerMarks()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.manage',$this->option))
				throw new Exception('Bạn không có quyền thực hiện chức năng này');

			//3. Get form data
			$cid = $this->input->post->get('cid',[],'array');
			$cid = array_filter($cid,'intval');
			if(empty($cid))
				throw new Exception('Không có kỳ thi nào được chọn');
			$examseasonId= $cid[0];

			//4. Call model and get data
			$model = $this->getModel();
			$learnerMarks = $model->getLearnerMarks($examseasonId);
			if(empty($learnerMarks))
				throw new Exception('Không có dữ liệu để xuất');

			//5. Write to Word document
			$phpWord = new PhpWord();
			IOHelper::writeExamseasonLearnerMarks($phpWord, $examseasonId, $learnerMarks);
			//IOHelper::testPhpWord($phpWord);

			//6. Send file
			IOHelper::sendHttpDocx($phpWord,'Bảng điểm tổng hợp.docx');
			jexit();
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examseasons',false));
		}
	}
}
