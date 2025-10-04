<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExamsController extends EqaAdminController {

	/**
	 * We override the parent method because we want to redirect to
	 * the view 'ExamseasonExams' instead of 'Exams'
	 * @since 1.1.2
	 */
	public function delete(): void
	{
		try
		{
			parent::delete();
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
		}

		//If an examseason id was specified then redirect to that page
		$examseasonId = $this->input->getInt('examseason_id');
		if(!empty($examseasonId))
			$url = JRoute::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$examseasonId,false);
		else
			$url = JRoute::_('index.php?option=com_eqa&view=exams',false);
		$this->setRedirect($url);
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
		//Set redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=exams',false);
		$this->setRedirect($url);

		//TEMPORARY DISABLE THIS FUNCTIONALITY
		$this->setMessage('Tính năng này tạm thời bị vô hiệu hóa. 
		Sẽ cần phải điều chỉnh chương trình để có thể loại bỏ hoàn toàn
		chức năng này.','warning'); //TODO: Remove this line after testing
		return;

		//Check token
		$this->checkToken();

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
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
		if(!$this->app->getIdentity()->authorise('core.manage', $this->option))
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
		if(!$this->app->getIdentity()->authorise('core.manage', $this->option))
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
		if(count($examIds)==1){
			$fileName = 'Điểm nhập QLĐT. '.$examInfo->name.'.xlsx';
		}else{
			$fileName = 'Điểm thi nhập Quản lý đào tạo ('.count($examIds).' môn).xlsx';
		}
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
	public function exportResultForEms2()
	{
		/* EMS = Education Management System = Hệ thống quản lý đào tạo của Học viện */

		//Check token
		$this->checkToken();

		//Redirect in any case
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa&view=exams',false));

		//Check permission
		if(!$this->app->getIdentity()->authorise('core.manage', $this->option))
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
			IOHelper::writeExamResultForEms2($sheet, $examInfo, $examResult);
		}

		//Send result
		if(count($examIds)==1){
			$fileName = 'Điểm lần 2 nhập QLĐT. '.$examInfo->name.'.xlsx';
		}else{
			$fileName = 'Điểm lần 2 nhập Quản lý đào tạo ('.count($examIds).' môn).xlsx';
		}
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
}
