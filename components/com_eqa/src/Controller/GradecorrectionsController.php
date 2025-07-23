<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Factory;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class GradecorrectionsController extends EqaAdminController
{
	public function download()
	{
		try
		{
			$this->checkToken();

			if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
				throw new Exception("Bạn không có quyền truy cập vào mục này");

			//Create an instance of the backend model
			$mvcFactory = GeneralHelper::getMVCFactory();
			$model = $mvcFactory->createModel('gradecorrections', 'Administrator');
			$items = $model->getAllItems();
			if(empty($items))
				throw new Exception("Không tìm thấy danh sách đính chính");

			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);

			IOHelper::writeGradeCorrectionRequests($spreadsheet, $items);

			// Force download of the Excel file
			$fileName = 'Danh sách đính chính.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			exit();

		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$url = JRoute::_('index.php?option=com_eqa&view=gradecorrections', false);
			$this->setRedirect($url);
		}
	}
}

