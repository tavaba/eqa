<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;

defined('_JEXEC') or die();

class ClassController extends  EqaFormController
{
	public function importLearners(): void
	{
		//1. Check token
		$this->checkToken();

		//2. Check for creation permissions.
		if(!$this->app->getIdentity()->authorise('core.create', 'com_eqa'))
		{
			$this->setMessage('Bạn không có quyền nhập HVSV vào lớp học phần','error');
			$url = Route::_('index.php?option=com_eqa&view=classes', false);
			$this->setRedirect($url);
			return;
		}

		//3. Get data from request
		//3.1. The class id which must have been inserted to the form in the 'classlearners' view
		$classId = $this->input->getInt('class_id');
		if(empty($classId))
		{
			$this->setMessage('Không xác định được lớp học phần','error');
			$url = Route::_('index.php?option=com_eqa&view=classes', false);
			$this->setRedirect($url);
			return;
		}

		//3.2. The excel file
		$file = $this->input->files->get('excelfile');
		if(empty($file) || empty($file['tmp_name']))            //PHASE 1: Show upload form
		{
			$url = Route::_('index.php?option=com_eqa&view=class&layout=importlearners&class_id='.$classId,false);
			$this->setRedirect($url);
			return;
		}

		//PHASE 2: Process uploaded file
		//4. Load the spreadsheet and read LEARNER CODEs from it
		try
		{
			$spreadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);
			$sheet = $spreadsheet->getActiveSheet();
			$sheetData = $sheet->toArray('');
			$rowCount = count($sheetData);
			$learnerCodes = [];
			for($r=14; $r<$rowCount; $r++)          //Mã HVSV đầu tiên ở ô B15
			{
				$row = $sheetData[$r];
				$value = trim($row[1]);
				if(empty($value))
					break;
				$learnerCodes[$r+1] = $value;      //Cột B chứa mã HVSV. Chỉ số là số hiệu của dòng trong Excel
			}

			//5. Import to database
			$model = $this->getModel();
			[$countTotal, $countAdded, $countExisting] = $model->importLearners($classId, $learnerCodes);
			$msg = "Tổng số HVSV trong file: {$countTotal}. Đã có trong lớp: {$countExisting}. Đã thêm vào: {$countAdded}";
			$this->setMessage($msg, 'success');
		}
		catch(Exception $e)
		{
			$this->setMessage(Text::_($e->getMessage()), 'error');
		}
		$url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
		$this->setRedirect($url);
	}
	public function importPams(): void
	{
		//1. Check token
		$this->checkToken();

		//2. Check for creation permissions.
		if(!$this->app->getIdentity()->authorise('core.edit', 'com_eqa'))
		{
			$this->setMessage('Bạn không có quyền nhập điểm quá trình','error');
			$url = Route::_('index.php?option=com_eqa&view=classes', false);
			$this->setRedirect($url);
			return;
		}

		//3. Get data from request
		//3.1. The class id which must have been inserted to the form in the 'classlearners' view
		$classId = $this->input->getInt('class_id');
		if(empty($classId))
		{
			$this->setMessage('Không xác định được lớp học phần','error');
			$url = Route::_('index.php?option=com_eqa&view=classes', false);
			$this->setRedirect($url);
			return;
		}

		//3.2. The excel file
		$file = $this->input->files->get('excelfile');
		if(empty($file) || empty($file['tmp_name']))            //PHASE 1: Show upload form
		{
			$url = Route::_('index.php?option=com_eqa&view=class&layout=importpams&class_id='.$classId,false);
			$this->setRedirect($url);
			return;
		}

		//PHASE 2: Process uploaded file
		//4. Load the spreadsheet and read PAMs from it
		try
		{
			$spreadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);
			$sheet = $spreadsheet->getActiveSheet();
			$sheetData = $sheet->toArray('');
			$rowCount = count($sheetData);
			$data = [];
			for($r=14; $r<$rowCount; $r++)          //Mã HVSV đầu tiên ở ô B15
			{
				$row = $sheetData[$r];
				$learnerCode = trim($row[1]);       //Cột B
				if(empty($learnerCode))             //Kết thúc danh sách ĐQT
					break;

				//Đọc "Ghi chú" ở cột M (12)
				$description = trim($row[12]);

				//Đọc pam1, pam2, $pam lần lượt ở các cột I, J, K (8, 9, 10)
				$pam1 = trim($row[8]);
				$pam1 = ExamHelper::toPam($pam1, $description);
				$pam2 = trim($row[9]);
				$pam2 = ExamHelper::toPam($pam2, $description);
				$pam = trim($row[10]);
				$pam = ExamHelper::toPam($pam, $description);
				if($pam1 === false || $pam2 === false || $pam === false)
				{
					$msg = Text::sprintf('Dòng %d: ĐQP không hợp lệ', $r+1);
					throw new Exception($msg);
				}

				//Save to the array
				$data[] = [
					'row_index'=>$r+1,
					'learner_code'=>$learnerCode,
					'description'=>$description,
					'pam1'=>$pam1,
					'pam2'=>$pam2,
					'pam'=>$pam,
					'allowed' => ExamHelper::isAllowedToFinalExam($pam1, $pam2, $pam)
				];
			}

			//5. Import to database
			$model = $this->getModel();
			[$classSize, $countUpdated, $npam] = $model->importPams($classId, $data, true);
			$msg = "Sĩ số lớp học phần: {$classSize}. Số lượng được nhập ĐQT: {$countUpdated}. Tổng số HVSV đã có ĐQT: {$npam}/{$classSize}";
			$type = $npam==$classSize ? 'success' : 'info';
			$this->setMessage($msg, $type);
		}
		catch(Exception $e)
		{
			$this->setMessage(Text::_($e->getMessage()), 'error');
		}
		$url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
		$this->setRedirect($url);
	}

    /**
     * Thêm sinh viên vào một lớp học phần.
     * Gồm 2 pha.
     * - Pha 1 ('showform') sẽ redirect đến layout 'addlearners' để hiển thị form cho người dùng nhập dữ liệu
     * - Pha 2 ('getdata') sẽ nhận và lưu dữ liệu
     * Ghi chú: id của lớp học ('id') và pha ('phase') được truyền qua trường ẩn của form
     * ở các layout 'learners' và 'addlearners'
     * @return bool
     * @since 1.0.2
     */
    public function addLearners()
    {
        $classId = $this->app->input->getInt('class_id');

        // Access check
        if (!$this->app->getIdentity()->authorise('core.create',$this->option)) {
            // Set the internal error and also the redirect error.
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_CREATE'), 'error');
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=classlearners&class_id='.$classId,
                    false
                )
            );
            return false;
        }

        //Xác định pha của nhiệm vụ
        $phase = $this->app->input->getAlnum('phase','');
        if($phase !== 'getdata')
        {
            // Redirect to the 'add learners' screen.
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=class&layout=addlearners&class_id='.$classId,
                    false
                )
            );
        }
        else
        {
            //Pha này thì cần check token
            $this->checkToken();

            //Gọi model để nhập data
            $model = $this->getModel();
            $inputLearners = $this->app->input->getString('learners');
            $normalizedLearners = preg_replace('/[\s,;]+/', ' ', $inputLearners);
            $normalizedLearners = trim($normalizedLearners);
            $learnerCodes = explode(' ', $normalizedLearners);
            $model->addLearners($classId, $learnerCodes);
			DatabaseHelper::updateClassNPam($classId);


            //Add xong thì redirect về trang xem danh sách lớp học phần
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_eqa&view=classlearners&class_id='.$classId,
                    false
                )
            );

        }

        return true;
    }
    public function allow():void
    {
        // Check for request forgeries
        $this->checkToken();

        $classId = $this->app->input->getInt('class_id');
        if(empty($classId)){
            $url = Route::_('index.php?option=com_eqa&view=classes',false);
            $this->setRedirect($url);
            return;
        }

        //Set redirect in any other case
        $url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
        $this->setRedirect($url);


        // Access check
        if (!$this->app->getIdentity()->authorise('core.edit.state',$this->option)) {
            $this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
            return;
        }

        // Get items to remove from the request.
        $learnerIds = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $learnerIds = array_filter($learnerIds);

        if (empty($learnerIds)) {
            $this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
            return;
        }

        // Get the model and do the job
        $model = $this->getModel();
        $model->setAllowed($classId, $learnerIds,true);
    }
    public function deny():void
    {
        // Check for request forgeries
        $this->checkToken();

        $classId = $this->app->input->getInt('class_id');
        if(empty($classId)){
            $url = Route::_('index.php?option=com_eqa&view=classes',false);
            $this->setRedirect($url);
            return;
        }

        //Set redirect in any other case
        $url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
        $this->setRedirect($url);


        // Access check
        if (!$this->app->getIdentity()->authorise('core.edit.state',$this->option)) {
            $this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
            return;
        }

        // Get items to remove from the request.
        $learnerIds = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $learnerIds = array_filter($learnerIds);

        if (empty($learnerIds)) {
            $this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
            return;
        }

        // Get the model and do the job
        $model = $this->getModel();
        $model->setAllowed($classId, $learnerIds,false);
    }
    public function remove():void
    {
        // Check for request forgeries
        $this->checkToken();

        $classId = $this->app->input->getInt('class_id');
        if(empty($classId)){
            $url = Route::_('index.php?option=com_eqa&view=classes',false);
            $this->setRedirect($url);
            return;
        }

        //Set redirect in any other case
        $url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$classId,false);
        $this->setRedirect($url);


        // Access check
        if (!$this->app->getIdentity()->authorise('core.delete',$this->option)) {
            $this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
            return;
        }

        // Get item ids from the request.
        $learnerIds = (array) $this->input->get('cid', [], 'int');

        // Remove zero values resulting from input filter
        $learnerIds = array_filter($learnerIds);

        if (empty($learnerIds)) {
            $this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));
            return;
        }

        // Get the model and do the job
        $model = $this->getModel();
        foreach ($learnerIds as $learnerId) {
            $model->removeLearner($classId, $learnerId);
        }
		DatabaseHelper::updateClassNPam($classId);
    }

	/**
	 * Lấy danh sách sinh viên trong một lớp học phần dưới dạng JSON.
	 * Mỗi phần tử có 2 thuộc tính: 'id', 'name'
	 * @since 1.2.0
	 */
	public function getJsonClassLearners()
	{
		$app = $this->app;

		//Check access
		if (!$app->getIdentity()->authorise('core.manage',$this->option)) {
			echo new JsonResponse([], 'Access denied', true);
			$app->close();
		}

		//Get the class id from the request
		$classId = $app->input->getInt('class_id', 0);
		if (!$classId) {
			echo new JsonResponse([], 'Invalid class ID', true);
			$app->close();
		}

		//Retrieve the list of students
		$model = $this->getModel();
		$students = $model->getClassLearners($classId);
		if (!$students || !count($students))
			echo new JsonResponse([], 'No student found', true);
		else
			echo new JsonResponse($students);
		$app->close();
	}

}