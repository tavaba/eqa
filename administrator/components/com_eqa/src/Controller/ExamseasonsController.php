<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Model\ExamseasonsModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExamseasonsController extends AdminController
{
	public function complete()
	{
		// Check for request forgeries
		$this->checkToken();

		//Set redirect in any case
		$url = Route::_('index.php?option=com_eqa&view=examseasons', false);
		$this->setRedirect($url);

		//Check permission
		if (!$this->app->getIdentity()->authorise('core.edit.state', $this->option))
		{
			$this->app->enqueueMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'));

			return;
		}

		// Get items to remove from the request.
		$cid = (array) $this->input->get('cid', [], 'int');

		// Remove zero values resulting from input filter
		$cid = array_filter($cid);

		if (empty($cid))
		{
			$this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));

			return;
		}

		// Get the model.
		$model = $this->getModel();
		$model->setCompleteStatus($cid, true);
	}
	public function undoComplete()
	{
		// Check for request forgeries
		$this->checkToken();

		//Set redirect in any case
		$url = Route::_('index.php?option=com_eqa&view=examseasons', false);
		$this->setRedirect($url);

		//Check permission
		if (!$this->app->getIdentity()->authorise('core.edit.state', $this->option))
		{
			$this->app->enqueueMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'));

			return;
		}

		// Get items to remove from the request.
		$cid = (array) $this->input->get('cid', [], 'int');

		// Remove zero values resulting from input filter
		$cid = array_filter($cid);

		if (empty($cid))
		{
			$this->app->enqueueMessage(Text::_('COM_EQA_NO_ITEM_SELECTED'));

			return;
		}

		// Get the model.
		$model = $this->getModel();
		$model->setCompleteStatus($cid, false);
	}

	public function exportMarkStatistic()
	{
		//CSRF
		$this->checkToken();

		//Redirect
		$url = Route::_('index.php?option=com_eqa&view=examseasons', false);
		$this->setRedirect($url);

		//Permission
		if (!$this->app->getIdentity()->authorise('core.manage', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
			return;
		}

		//Get examseasons Ids
		$examseasonIds = (array)$this->input->get('cid',[],'int');
		if(empty($examseasonIds)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}

		//Get statistic
		$model = $this->getModel();
		$markStatistic = $model->getMarkStatistic($examseasonIds);


		//Write to an Excel file
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		$sheet->setTitle('Thống kê điểm');
		IOHelper::writeExamseasonMarkStatistic($sheet, $markStatistic);

		$dataSheet = $spreadsheet->createSheet();
		$chartSheet = $spreadsheet->createSheet();
		$dataSheet->setTitle('Phổ điểm');
		$chartSheet->setTitle('Biểu đồ');
		IOHelper::writeExamseasonMarkDistribution($dataSheet, $chartSheet, $markStatistic);

		//Send file
		$fileName = 'Thống kê điểm thi.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName, true);
		jexit();
	}

	public function exportStatistic()
	{
		//CSRF
		$this->checkToken();

		//Redirect
		$url = Route::_('index.php?option=com_eqa&view=examseasons', false);
		$this->setRedirect($url);

		//Permission
		if (!$this->app->getIdentity()->authorise('core.manage', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'), 'error');
			return;
		}

		//Get examseasons Ids
		$examseasonIds = (array)$this->input->get('cid',[],'int');
		if(empty($examseasonIds)){
			$this->setMessage(Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED'),'error');
			return;
		}

		//Get statistic
		$model = $this->getModel();
		$statistic = $model->getStatistic($examseasonIds);

		//Write to an Excel file
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		$sheet->setTitle('Thống kê kỳ thi');
		IOHelper::writeExamseasonStatistic($sheet, $statistic);

		//Send file
		$fileName = 'Thống kê kỳ thi.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		jexit();
	}
}