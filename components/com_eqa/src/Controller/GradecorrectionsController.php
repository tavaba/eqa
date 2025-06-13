<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Factory;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class GradecorrectionsController extends EqaAdminController
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
		$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections' . $this->getRedirectToListAppend(), false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền thực hiện thao tác này','error');
			return;
		}

		$gradeCorrectionRequestIds = $this->app->input->get('cid',null,'array');
		if(empty($gradeCorrectionRequestIds))
		{
			$this->setMessage('Không có yêu cầu nào được chọn', 'error');
			return;
		}

		$model = $this->getModel();
		$model->acceptRequests($gradeCorrectionRequestIds);
	}
	public function showRejectForm()
	{
		$this->checkToken();

		if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền thực hiện thao tác này','error');
			$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections' . $this->getRedirectToListAppend(), false);
			$this->setRedirect($url);
			return;
		}

		$gradeCorrectionIds = $this->app->input->get('cid',null,'array');
		if(empty($gradeCorrectionIds))
		{
			$this->setMessage('Không có yêu cầu nào được chọn', 'error');
			$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections' . $this->getRedirectToListAppend(), false);
			$this->setRedirect($url);
			return;
		}

		//Pass item id to the view
		$session = $this->app->getSession();
		$session->set('gradecorrection_id', $gradeCorrectionIds[0]);
		$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections&layout=reject', false);
		$this->setRedirect($url);
	}
	public function reject()
	{
		$this->checkToken();

		//Redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections' . $this->getRedirectToListAppend(), false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền thực hiện thao tác này','error');
			return;
		}

		$requestId = $this->app->input->getInt('id');
		$description = $this->app->input->getString('description');
		if(!$requestId || !$description)
		{
			$this->setMessage('Dữ liệu không hợp lệ', 'error');
			return;
		}

		$model = $this->getModel();
		$model->rejectRequest($requestId, $description);
	}
	public function download()
	{
		$this->checkToken();

		//Redirect in any case
		$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections' . $this->getRedirectToListAppend(), false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền thực hiện thao tác này','error');
			return;
		}

		$model = $this->getModel('gradecorrections');
		$items = $model->getItemsWithoutPagination();
		$spreadsheet = new Spreadsheet();
		$spreadsheet->removeSheetByIndex(0);

		IOHelper::writeGradeCorrectionRequests($spreadsheet, $items);

		// Force download of the Excel file
		$fileName = 'Danh sách đính chính.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
	public function cancel()
	{
		$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections' . $this->getRedirectToListAppend(), false);
		$this->setRedirect($url);
	}
}

