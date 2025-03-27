<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RegradingsController extends EqaAdminController
{
	public function getRedirectToListAppend()
	{
		$layout = $this->input->getString('layout');
		if($layout)
			return '&layout=' . $layout;
		return '';
	}
	public function accept()
	{
		$this->checkToken();

		//Redirect in any case
		$url = 'index.php?option=com_eqa&view=regradings' . $this->getRedirectToListAppend();
		$url = JRoute::_($url, false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền thực hiện thao tác này','error');
			return;
		}

		$regradingIds = $this->app->input->get('cid',null,'array');
		if(empty($regradingIds))
		{
			$this->setMessage('Không có yêu cầu nào được chọn', 'error');
			return;
		}

		$model = $this->getModel();
		$model->handleRequests($regradingIds, true);
	}
	public function reject()
	{
		$this->checkToken();

		//Redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=regradings' . $this->getRedirectToListAppend(), false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền thực hiện thao tác này','error');
			return;
		}

		$regradingIds = $this->app->input->get('cid',null,'array');
		if(empty($regradingIds))
		{
			$this->setMessage('Không có yêu cầu nào được chọn', 'error');
			return;
		}

		$model = $this->getModel();
		$model->handleRequests($regradingIds, false);
	}
	public function download()
	{
		$this->checkToken();

		//Redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=regradings' . $this->getRedirectToListAppend(), false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền thực hiện thao tác này','error');
			return;
		}

		$model = $this->getModel('regradings');
		$items = $model->getItemsWithoutPagination();
		$spreadsheet = new Spreadsheet();
		$spreadsheet->removeSheetByIndex(0);

		IOHelper::writeRegradings($spreadsheet, $items);

		// Force download of the Excel file
		$fileName = 'Danh sách phúc khảo.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
}

