<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Exception;
use JComponentHelper;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once JPATH_ROOT.'/vendor/autoload.php';

defined('_JEXEC') or die();

class PaperexamController extends  EqaFormController {
	public function mask()
	{
		//Check token
		$this->checkToken();

		//Set redirect in any other case
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=paperexams',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('eqa.mask', $this->option)){
			$this->setMessage('Bạn không có quyền thực hiện chức năng này', 'error');
			return;
		}

		//Get form data
		$examId = $this->input->getInt('exam_id');
		$maskStart = $this->input->getInt('mask_start');
		$maskInterval = $this->input->getInt('mask_interval');
		$packageDefaultSize = $this->input->getInt('package_default_size');
		$packageMinSize = $this->input->getInt('package_min_size');
		if(empty($examId) || $maskStart<=0 || $maskInterval<=0 || $packageDefaultSize<=0 || $packageMinSize<=0 || $packageMinSize>$packageDefaultSize)
		{
			$this->setMessage('Dữ liệu không hợp lệ', 'error');
			return;
		}

		//Process
		$model = $this->getModel();
		$model->mask($examId, $maskStart, $maskInterval, $packageDefaultSize, $packageMinSize);
	}
	public function exportMaskMap()
	{
		//Checktoken
		$this->checkToken();

		//Set redirect
		$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));

		//Check permission
		if (!$this->app->getIdentity()->authorise('eqa.mask', $this->option))
		{
			$this->setMessage('Không có quyền thực hiện tác vụ', 'error');
			return;
		}

		//Determine the exam id
		$cid     = (array) $this->input->post->get('cid', [], 'int');
		if(empty($cid)){
			$this->setMessage('Không có môn thi được chọn','error');
			return;
		}
		$examId = $cid[0];

		//Load the map
		$model = $this->getModel();
		$map = $model->getMaskMap($examId, false);
		if(empty($map))
			return;

		//Create the spreadsheet
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		$examInfo = DatabaseHelper::getExamInfo($examId);
		IOHelper::writeMaskMap($sheet, $map, $examInfo);


		// Force download of the Excel file
		$examName = DatabaseHelper::getExamName($examId);
		$fileName = 'Sơ đồ phách. ' . $examName . '.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		$this->app->close();
	}
	public function editExaminers()
	{
		//Check token
		$this->checkToken();

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option)){
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa',false));
			return;
		}

		//Determine the exam id
		$cid     = (array) $this->input->post->get('cid', [], 'int');
		if(empty($cid)){
			$this->setMessage('Không có môn thi được chọn','error');
			return;
		}
		$examId = $cid[0];

		//Redirect to edit layout
		$url = JRoute::_('index.php?option=com_eqa&view=paperexam&layout=examiners&exam_id='.$examId,false);
		$this->setRedirect($url);
	}
	public function saveExaminers()
	{
		//Check token
		$this->checkToken();

		//Check permissions
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa',false));
			return;
		}

		//Redirect for the rest cases
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=paperexams',false));

		//Determin the exam id
		$examId = $this->app->input->getInt('exam_id');
		if(empty($examId)){
			$this->setMessage('Không có thông tin môn thi','error');
			return;
		}

		//Get data and process
		$data = $this->input->post->get('jform',[],'array');
		$model = $this->getModel();
		$ok = $model->saveExaminers($examId, $data);

		//Cập nhật trạng thái môn thi
		$model = $this->createModel('exam');
		$model->setExamStatus($examId,ExamHelper::EXAM_STATUS_EXAMINER_ASSIGNED);
	}
	public function exportMarkingSheet()
	{
		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=paperexams',false));

		//Determine the exam id
		$cid     = (array) $this->input->post->get('cid', [], 'int');
		if(empty($cid)){
			$this->setMessage('Không có môn thi được chọn','error');
			return;
		}
		$examId = $cid[0];

		//Get model and check if can export
		$model = $this->getModel();
		$canExport = $model->isExaminerAssigningDone($examId);
		if(!$canExport){
			$this->setMessage('Chưa hoàn thành phân công chấm thi!','error');
			return;
		}

		//Build the spreadsheet
		$spreadsheet = new Spreadsheet();
		$spreadsheet->removeSheetByIndex(0);
		$npackage = DatabaseHelper::getExamPackageCount($examId);
		for($packageNumber=1; $packageNumber<=$npackage; $packageNumber++)
		{
			$packageInfo = $model->getPackageInfo($examId, $packageNumber);
			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle('Túi số ' . $packageNumber);
			IOHelper::writeMarkingSheet($sheet, $packageInfo);
		}

		// Force download of the Excel file
		$examName = DatabaseHelper::getExamName($examId);
		$fileName = 'Phiếu chấm. ' . $examName . '.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
}