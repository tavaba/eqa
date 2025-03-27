<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExamsController extends EqaAdminController {
	public function export()
	{
		$app = $this->app;
		$this->checkToken();
		if(!$app->getIdentity()->authorise('core.manage',$this->option))
		{
			echo Text::_('COM_EQA_MSG_UNAUTHORISED');
			exit();
		}

		$examIds = (array) $this->input->get('cid', [], 'int');

		// Prepare the spreadsheet
		$spreadsheet = new Spreadsheet();
		$spreadsheet->removeSheetByIndex(0);

		foreach ($examIds as $index => $examId) {
			$exam = DatabaseHelper::getExamInfo($examId);
			$examinees = DatabaseHelper::getExamExaminees($examId, false);

			// Create a worksheet for each exam
			$sheet = $spreadsheet->createSheet($index);
			/*-----------------
			 * Dường như việc đặt tên unicode cho sheet khiến phát
			 * sinh lỗi mở file trong một số trường hợp!!!!
			$sheetName = $exam->name;
			if(strlen($sheetName)>15)
				$sheetName = substr($sheetName,0,15);
			$sheetName .= ' (' . $exam->id . ')';
			\------------*/
			$sheetName = $examId;
			$sheet->setTitle($sheetName);
			IOHelper::writeExamExaminees($sheet, $exam, $examinees);
		}

		// Force download of the Excel file
		$fileName = "Danh sách thí sinh môn thi.xlsx";
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
	public function recheckStatus()
	{
		//Check token
		$this->checkToken();

		//Set redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=exams',false);
		$this->setRedirect($url);

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get exam ids
		$examIds = $this->input->get('cid',[],'int');
		if(empty($examIds))
		{
			$this->setMessage('Không xác định được môn thi','error');
			return;
		}

		//update
		$model = $this->getModel();
		foreach ($examIds as $examId)
			$model->recheckStatus($examId);
	}
	public function exportResultForLearners()
	{
		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=exams',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.manage',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$examIds = $this->input->post->get('cid',[],'int');
		if(empty($examIds)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}

		//Process
		$spreadsheet = new Spreadsheet();
		$spreadsheet->removeSheetByIndex(0);
		$model = $this->getModel();
		foreach ($examIds as $examId)
		{
			$examInfo = DatabaseHelper::getExamInfo($examId);
			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle($examInfo->code);
			$examResult = $model->getExamResult($examInfo->id);
			IOHelper::writeExamResultForLearners($sheet, $examInfo, $examResult);
		}

		//Send result
		$fileName = 'Tổng hợp điểm môn thi.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}

	public function exportResultForEms()
	{
		/* EMS = Education Management System = Hệ thống quản lý đào tạo của Học viện */

		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=exams',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.manage',$this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			return;
		}

		//Get data
		$examIds = $this->input->post->get('cid',[],'int');
		if(empty($examIds)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}

		//Process
		$spreadsheet = new Spreadsheet();
		$spreadsheet->removeSheetByIndex(0);
		$model = $this->getModel();
		foreach ($examIds as $examId)
		{
			$examInfo = DatabaseHelper::getExamInfo($examId);
			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle($examInfo->code);
			$examResult = $model->getExamResult($examInfo->id);
			IOHelper::writeExamResultForEms($sheet, $examInfo, $examResult);
		}

		//Send result
		$fileName = 'Nhập QLĐT. ' . $examInfo->name . '.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
}
