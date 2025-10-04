<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Model\ExamModel;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

class ExamController extends  EqaFormController
{

	/**
	 * This method is overided because we need to modify the target of redirect.
	 * An exam is always added to an exam season. So we need to redirect to the
	 * 'ExamSeasonExams' view with the exam season id stored in the form data.
	 *
	 * @param string $key The name of the primary key of the URL variable.
	 * @param string $urlVar The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
	 *
	 * @return boolean True on success.
	 * @since 1.1.2
	 */
	public function save($key = null, $urlVar = null): bool
	{
		//1. Make a copy of form data
		$data = $this->input->post->get('jform', [], 'array');

		//2. Call the parent class's save() method
		$result = parent::save($key, $urlVar);

		//3. Determine the redirect target URL
		if(!empty($data['examseason_id']))
			$redirect = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.(int)$data['examseason_id'],false);
		else
			$redirect = Route::_('index.php?option=com_eqa&view=exams',false);
		$this->setRedirect($redirect);

		//4. Return result
		return $result;
	}

	protected function checkCanAddExaminees(int $examId, bool $throw=true): bool
	{
		//TODO: Improve this method so that the component can be deploy in multitenant mode
		//1. Check if the user has edit permission
		$user = $this->app->getIdentity();
		if(!$user->authorise('core.edit', $this->option))
		{
			if($throw)
				throw new Exception(Text::_('COM_EQA_MSG_UNAUTHORISED'));
			return false;
		}

		//2. Check if the exam has been completed
		/**
		 * @var ExamModel $model
		 */
		$model = $this->getModel();
		$exam = $model->getItem($examId);
		if(empty($exam))
		{
			if($throw)
				throw new Exception('Không xác định được môn thi');
			return false;
		}
		if($exam->status >= ExamHelper::EXAM_STATUS_EXAM_CONDUCTED){
			if($throw)
				throw new Exception('Môn thi đã được tiến hành, không thể thêm thí sinh');
			return false;
		}

		//If all the above checks are passed then we have permission to add examinees
		return true;
	}
	protected function checkCanRemoveExaminees(int $examId, bool $throw=true): bool
	{
		//TODO: Improve this method so that the component can be deploy in multitenant mode
		//1. Check if the user has edit permission
		$user = $this->app->getIdentity();
		if(!$user->authorise('core.edit', $this->option))
		{
			if($throw)
				throw new Exception(Text::_('COM_EQA_MSG_UNAUTHORISED'));
			return false;
		}

		//2. Check if the exam has been completed
		/**
		 * @var ExamModel $model
		 */
		$model = $this->getModel();
		$exam = $model->getItem($examId);
		if(empty($exam))
		{
			if($throw)
				throw new Exception('Không xác định được môn thi');
			return false;
		}
		if($exam->status >= ExamHelper::EXAM_STATUS_EXAM_CONDUCTED){
			if($throw)
				throw new Exception('Môn thi đã được tiến hành, không thể xóa thí sinh');
			return false;
		}

		//If all the above checks are passed then we have permission to add examinees
		return true;
	}
    public function removeExaminees()
    {
		try{
			//Get exam id
			$examId = $this->input->getInt('exam_id');
			if(empty($examId))
				throw new Exception('Không xác định được môn thi');

			// Check for request forgeries
			$this->checkToken();

			//Check permissions
			$this->checkCanRemoveExaminees($examId,true);

			// Get items to remove from the request.
			$learnerIds = (array) $this->input->get('cid', [], 'int');
			$learnerIds = array_filter($learnerIds,'intval');
			$learnerIds = array_unique($learnerIds);
			if (empty($learnerIds))
				throw new Exception('Chưa chọn thí sinh nào');

			/**
			 * Remove examinees from the exam
			 * @var ExamModel $model
			 */
			$model = $this->getModel();
			$model->removeExaminees($examId, $learnerIds);

			//Set redirect to the examinees list page
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false));
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			if(empty($examId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false);
			$this->setRedirect($url);
		}

    }
    public function addExaminees()
    {
	    /**
	     * Thao tác này sẽ thực hiện một trong hai pha:
	     * Pha 1: Hiển thị giao diện nhập dữ liệu
	     * Pha 2: Thực hiện việc thêm thí sinh vào danh sách thi
	     * Dấu hiệu nhận biết pha 2 là giá trị của $attempt khác rỗng
	     */

	    try
	    {
			//Check token
		    $this->checkToken();

			//Determine the exam id
		    $examId = $this->app->input->getInt('exam_id');
			if(empty($examId))
				throw new Exception('Không xác định được môn thi');

		    //Check permission
		    $this->checkCanAddExaminees($examId,true);

			//Get some request data
		    $attempt = $this->input->getInt('attempt');
			if(empty($attempt))  //Phase 1
			{
				$this->setRedirect(JRoute::_(
					'index.php?option=com_eqa&view=exam&layout=addexaminees&exam_id='.$examId,
					false));
				return;
			}

			//Check token for phase 2
		    $this->checkToken();

			//Get further request data
			$ignoreError = $this->input->getInt('ignore_error');
			$addExpired = $this->input->getInt('add_expired');
		    $classCode = trim($this->input->getString('classcode'));
		    $inputExamineeCodes = $this->input->getString('learnercodelist');
		    $normalizedExamineeCodes = preg_replace('/[\s,;]+/', ' ', $inputExamineeCodes);
		    $normalizedExamineeCodes = trim($normalizedExamineeCodes);
		    $examineeCodes = explode(' ', $normalizedExamineeCodes);
			if(empty($classCode) || empty($examineeCodes))
				throw new Exception('Dữ liệu không hợp lệ');

		    /**
		     * Call model to add examinees
		     * @var ExamModel $model
		     */
		    $model = $this->getModel();
		    $countAdded = $model->addExamineesFromClass($examId, $classCode, $examineeCodes, $attempt, $ignoreError, $addExpired);

		    //Redirect to examinees list page
		    $msg = Text::sprintf('Có %d/%d thí sinh được thêm vào môn thi', $countAdded, count($examineeCodes));
		    $this->setMessage($msg, 'info');
		    $this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false));
	    }
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			if(empty($examId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false);
			$this->setRedirect($url);
		}
    }
	public function addFailedExaminees()
	{
		try
		{
			//Check token
			$this->checkToken();

			//Get the id of the exam to add examinees
			$examId = $this->app->input->getInt('exam_id');
			if(empty($examId))
				throw new Exception('Không xác định được môn thi');

			// Access check
			$this->checkCanAddExaminees($examId,true);

			/**
			 * Add failed examinees into the exam
			 * @var ExamModel $model
			 */
			$model = $this->getModel();
			$model->addFailedExaminees($examId);

			//Set redirect to the examinees list page
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false));
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			if(empty($examId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false);
			$this->setRedirect($url);
		}
	}

	//Hoãn thi
	public function delay()
	{
		//Check token
		$this->checkToken();

		//Determine exam id (if there is)
		$examId = $this->input->getInt('exam_id');
		$url = 'index.php?option=com_eqa&view=examexaminees';
		if(is_numeric($examId))
			$url .= '&exam_id=' . $examId;
		$this->setRedirect(JRoute::_($url, false));

		//Check permissions
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$cid = (array)$this->input->get('cid',[],'int');
		if(empty($cid) || empty($examId))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}

		//Process
		$model = $this->getModel();
		$model->delayExaminees($examId, $cid);
	}
	public function undoDelay()
	{
		//Check token
		$this->checkToken();

		//Determine exam id (if there is)
		$examId = $this->input->getInt('exam_id');
		$url = 'index.php?option=com_eqa&view=examexaminees';
		if(is_numeric($examId))
			$url .= '&exam_id=' . $examId;
		$this->setRedirect(JRoute::_($url, false));

		//Check permissions
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$cid = (array)$this->input->get('cid',[],'int');
		if(empty($cid) || empty($examId))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}

		//Process
		$model = $this->getModel();
		$model->undoDelayExaminees($examId, $cid);

	}
	public function saveQuestion()
	{
		//Check token
		$this->checkToken();

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
		{
			$msg = Text::_('COM_EQA_MSG_UNAUTHORISED');
			$this->setMessage($msg, 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
			return;
		}

		//Get data
		$input = $this->input->post;
		$examId = $input->getInt('exam_id');
		$questionAuthorId = $input->getInt('questionauthor_id');
		$questionSenderId = $input->getInt('questionsender_id');
		$questionQuantity = $input->getInt('nquestion');
		$questionDate = $input->getString('questiondate');
		if(empty($examId) || empty($questionAuthorId) || empty($questionSenderId) || empty($questionQuantity) || !DatetimeHelper::isValidDate($questionDate))
		{
			$msg = Text::_('COM_EQA_MSG_INVALID_DATA');
			$this->setMessage($msg, 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=exam&layout=question', false));
			return;
		}

		//Cập nhật thông tin về đề thi, đồng thời cập nhật trạng thái môn thi
		$model = $this->getModel();
		$model->updateExamQuestion($examId, $questionAuthorId, $questionSenderId, $questionQuantity, $questionDate);

		//Redirect
		$this->setRedirect(JRoute::_('index.php?option=com_eqa'));
	}
	public function distribute()
	{
		//Get the id of the exam to add examinees
		$examId = $this->app->input->getInt('exam_id');

		// Access check
		if (!$this->app->getIdentity()->authorise('core.create', $this->option)) {
			// Set the internal error and also the redirect error.
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');
			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,
					false
				)
			);
			return false;
		}

		//Xác định pha của nhiệm vụ
		$phase = $this->app->input->getAlnum('phase','');
		if($phase !== 'getdata')
		{
			// Redirect to the 'distribute' screen.
			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=exam&layout=distribute&exam_id='.$examId,
					false
				)
			);
		}
		else
		{
			//Pha này thì cần check token
			$this->checkToken();

			//1. Chuẩn bị dữ liệu

			//2. Gọi model để thêm thí sinh
			$model = $this->getModel();
			$data = $this->input->get('jform',null,'array');
			$model->distribute($examId, $data);

			//Add xong thì redirect về trang xem danh sách lớp học phần
			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=examrooms',
					false
				)
			);
		}

		return true;

	}
	public function distribute2()
	{
		//Check token
		$this->checkToken();

		//Get the id of the exam to add examinees
		$examId = $this->app->input->getInt('exam_id');

		// Access check
		if (!$this->app->getIdentity()->authorise('core.create', $this->option)) {
			// Set the internal error and also the redirect error.
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');
			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,
					false
				)
			);
			return false;
		}

		//1. Chuẩn bị dữ liệu
		$data = $this->input->get('jform',null,'array');

		//2. Gọi model để thêm thí sinh
		$model = $this->getModel();
		$model->distribute2($examId, $data);

		//Add xong thì redirect về trang xem danh sách lớp học phần
		$this->setRedirect(
			Route::_(
				'index.php?option=com_eqa&view=examrooms',
				false
			)
		);

		return true;

	}
	public function export()
	{
		$app = $this->app;
		$this->checkToken();
		if(!$app->getIdentity()->authorise('core.manage', $this->option))
		{
			echo Text::_('COM_EQA_MSG_UNAUTHORISED');
			exit();
		}

		//Prepare data
		$examId = $this->input->getInt('exam_id');
		if(empty($examId))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_ERORR_OCCURRED'));
			return;
		}
		$exam = DatabaseHelper::getExamInfo($examId);
		$examinees = DatabaseHelper::getExamExaminees($examId, false);

		// Prepare the spreadsheet
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		$sheetName = preg_replace('/[\\/?*:\[\]]/', '', $exam->name);
		$sheetName = mb_substr($sheetName, 0, 20);
		$sheetName .= ' (' . $exam->id . ')';
		$sheet->setTitle($sheetName);
		IOHelper::writeExamExaminees($sheet, $exam, $examinees);

		//Send file to user
		$fileName = "Danh sách thí sinh. " . $exam->name . '.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
	public function exportitest()
	{
		$app = $this->app;
		$this->checkToken();
		if(!$app->getIdentity()->authorise('core.manage', $this->option))
		{
			echo Text::_('COM_EQA_MSG_UNAUTHORISED');
			exit();
		}

		//Prepare data
		$examId = $this->input->getInt('exam_id');
		if(empty($examId))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_ERORR_OCCURRED'), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId, false));
			return;
		}
		$exam = DatabaseHelper::getExamInfo($examId);

		//Nếu không phải thi trắc nghiệm thì bỏ
		if($exam->testtype != ExamHelper::TEST_TYPE_MACHINE_OBJECTIVE && $exam->testtype!=ExamHelper::TEST_TYPE_MACHINE_HYBRID && $exam->testtype!=ExamHelper::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE)
		{
			$this->setMessage(Text::_('COM_EQA_MSG_NOT_MACHINE_TEST'), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId, false));
			return;
		}


		//Get info about all the examinees of the exam, ordering by exam time
		$db = DatabaseHelper::getDatabaseDriver();
		$columms = $db->quoteName(
			array('d.start', 'b.name', 'c.code',       'a.code'),
			array('start',   'room',   'learner_code', 'code')
		);
		$query = $db->getQuery(true)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_examrooms AS b', 'a.examroom_id=b.id')
			->leftJoin('#__eqa_learners AS c', 'a.learner_id=c.id')
			->leftJoin('#__eqa_examsessions AS d', 'b.examsession_id=d.id')
			->select($columms)
			->where('a.examroom_id>0 AND a.exam_id='.$examId)
			->order(array(
				$db->quoteName('start') . ' ASC',
				'code ASC'
			));
		$db->setQuery($query);
		$items = $db->loadObjectList();

		// Prepare the spreadsheet
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);

		//Write the header row
		$sheet->setCellValue('A1', 'Đợt thi');
		$sheet->setCellValue('B1', 'Ngày thi');
		$sheet->setCellValue('C1', 'Ca/Tiết');
		$sheet->setCellValue('D1', 'Phòng thi');
		$sheet->setCellValue('E1', 'Mã TS/TĐN');
		$sheet->setCellValue('F1', 'SBD');
		$sheet->setCellValue('G1', 'Ghi chú 1');
		$sheet->setCellValue('H1', 'Ghi chú 2');
		$sheet->getStyle('A1:H1')->getFont()->setBold(true);

		/*
		 * Ghi thông tin thí sinh. Ca thi sớm nhất được đánh số là 1.
		 * Khi có sự thay đổi 'start' thì tăng ca thêm 1
		 * Riêng "Đợt thi" thì luôn đặt là 1
		 */
		$lastStart='';
		$row=2;
		$session = 0;
		foreach ($items as $item)
		{
			//Xác định ca thi
			if($item->start !== $lastStart)
			{
				$session++;
				$lastStart = $item->start;
			}

			//Ghi các cột
			$sheet->setCellValue('A'.$row, 1);
			$sheet->setCellValue('B'.$row, DatetimeHelper::getFullDate($item->start));
			$sheet->setCellValue('C'.$row, $session);
			$sheet->setCellValue('D'.$row, $item->room);
			$sheet->setCellValue('E'.$row, $item->learner_code);
			$sheet->setCellValue('F'.$row, $item->code);

			//Next
			$row++;
		}


		// Export the spreadsheet to a temporary file
		$tempFile = tempnam(sys_get_temp_dir(), $exam->name) . '.xlsx';
		$writer = new Xlsx($spreadsheet);
		$writer->save($tempFile);

		// Force download of the Excel file
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="Ca iTest - ' . $exam->name . '.xlsx"');
		header('Cache-Control: max-age=0');
		readfile($tempFile);

		// Clean up temporary file
		unlink($tempFile);
		exit();
	}

	/**
	 * Cập nhật thông tin về các trường hợp HVSV được khuyến khích
	 *
	 * @since version 1.0.0
	 */
	public function stimulate(): void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Get exam id
			$examId = $this->input->getInt('exam_id');
			if(empty($examId))
				throw new Exception('Không xác định được môn thi');

			//Check permissions
			if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
				throw new Exception(Text::_('COM_EQA_MSG_UNAUTHORISED'));

			/**
			 * Update stimulation information
			 * @var ExamModel $model
			 */
			$model = $this->getModel();
			$msg = $model->updateStimulations($examId);

			//Set redirect in case of success
			$this->setMessage($msg);
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false));
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			if(empty($examId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false);
			$this->setRedirect($url);
			return;
		}
	}
	public function updateDebt(): void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Get exam id
			$examId = $this->input->getInt('exam_id');
			if(empty($examId))
				throw new Exception('Không xác định được môn thi');

			//Check permissions
			if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
				throw new Exception(Text::_('COM_EQA_MSG_UNAUTHORISED'));

			/**
			 * Update debt information
			 * @var ExamModel $model
			 */
			$model = $this->getModel();
			$messages = $model->updateDebt($examId);

			//Set redirect in any case
			$this->setMessage(implode('. ', $messages));
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false));
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			if(empty($examId))
				$url = Route::_('index.php?option=com_eqa',false);
			else
				$url = Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$examId,false);
			$this->setRedirect($url);
		}
	}
	public function importitest(): void
	{
		$examMarkPrecision = ConfigHelper::getExamMarkPrecision();

		//Check token
		$this->checkToken();

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
		{
			$msg = Text::_('COM_EQA_MSG_UNAUTHORISED');
			$this->setMessage($msg, 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa',false));
			return;
		}

		//Set redirect in any other case
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=exam&layout=uploaditest',false));

		//Get data
		$examId = $this->input->post->getInt('exam_id');
		$multiple = $this->input->post->getInt('multiple');
		$file = $this->input->files->get('file');
		if(empty($examId) || empty($file['tmp_name']))
		{
			$this->setMessage("Dữ liệu form không hợp lệ", 'error');
			return;
		}

		//Đọc file
		$spreadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);

		//Tìm sheet để đọc
		if($multiple)
			$sheetName = 'Tất cả';
		else
			$sheetName = 'Bảng điểm';
		$sheet = $spreadsheet->getSheetByName($sheetName);
		if(empty($sheet))
		{
			$msg = Text::sprintf("File không hợp lệ. Không tìm thấy sheet <b>%s</b>", $sheetName);
			$this->setMessage($msg, 'error');
			return;
		}

		//Đọc dữ liệu từ file excel.
		//Nạp dữ liệu vào mảng nên index các cột, dòng được tính 0-based
		$data = $sheet->toArray();
		$highestDataRow = count($data)-1;
		$examinees = [];
		$colSequence = 0;      //STT
		$colCode = 1;           //Số báo danh
		$colLearnerCode = 2;    //Mã HVSV
		$colMark=8;
		$colwDescription=10;
		for($row=1; $row<=$highestDataRow; $row++)
		{
			if(empty($data[$row][$colSequence]))
				break;
			$dataRow = $data[$row];
			$examinee = new \stdClass();
			if(empty($data[$row][$colCode]) || empty($data[$row][$colLearnerCode]))
			{
				$msg = Text::sprintf("Dữ liệu không hợp lệ: sheet <b>%s</b>, dòng <b>%d</b>", $sheetName, $row+1);
				$this->setMessage($msg, 'error');
				return;
			}
			$examinee->code = (int)$dataRow[$colCode];
			$examinee->learnerCode = $dataRow[$colLearnerCode];
			$mark = $dataRow[$colMark];
			if(!is_numeric($mark))
				$examinee->mark = 0;
			elseif($mark<0 || $mark>10)
			{
				$msg = Text::sprintf("Điểm không hợp lệ: sheet <b>%s</b>, dòng <b>%d</b>", $sheetName, $row+1);
				$this->setMessage($msg, 'error');
				return;
			}
			else
				$examinee->mark = GeneralHelper::toFloat($mark, $examMarkPrecision);
			$examinee->description = $dataRow[$colwDescription];
			$examinees[] = $examinee;
		}

		//Nhập dữ liệu
		$model = $this->getModel();
		if(!$model->importitest($examId, $examinees))
		{
			return;     //Model should enqueue all the error messages (if there are)
		}

		//Cập nhật trạng thái môn thi
		$exam = DatabaseHelper::getExamInfo($examId);
		if($exam->countConcluded>0)
		{
			if($exam->countConcluded == $exam->countToTake + $exam->countExempted)
				$model->setExamStatus($examId, ExamHelper::EXAM_STATUS_MARK_FULL);
			else
				$model->setExamStatus($examId,ExamHelper::EXAM_STATUS_MARK_PARTIAL);
		}

		//Thông báo kết quả
		$msg = Text::sprintf("Môn thi <b>%s</b>: %d/%d đã có kết quả",
			$exam->name,
			$exam->countConcluded,
			$exam->countToTake + $exam->countExempted
		);
		$this->app->enqueueMessage($msg);
	}
}