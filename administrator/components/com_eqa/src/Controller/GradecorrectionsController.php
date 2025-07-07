<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Language\Text;
use JRoute;
use JSession;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class GradecorrectionsController extends EqaAdminController {
	public function download()
	{
		/**
		 * 1. Check permission
		 * 2. Get examseason id (of the default examseason)
		 * 3. Get grade correction requests of that examseason
		 * 4. Write to spreadsheet and return
		 */
		try
		{
			//1. Check permission
			if(!$this->app->getIdentity()->authorise('core.manage'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//3. Get grade correction requests of that examseason
			$model = $this->getModel('gradecorrections');
			$requests = $model->getAllItems();
			if(empty($requests))
				throw new Exception('Không có yêu cầu đính chính nào');

			//4. Write to spreadsheet and return
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			IOHelper::writeGradeCorrectionRequests($spreadsheet, $requests);

			//5. Download file
			$filename = "Yêu cầu đính chính điểm.xlsx";
			IOHelper::sendHttpXlsx($spreadsheet, $filename);
			$this->app->close();
		}
		catch (Exception $e)
		{
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
			$this->setMessage($e->getMessage(), 'error');
		}
	}
}
