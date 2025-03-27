<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RegradingsController extends EqaAdminController {
	public function paperlist()
	{

		//Set redirect in any case
		$url = JRoute::_('index.php?option=com_eqa', false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('core.manage','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền truy cập trang này');
			return;
		}

		//Lấy danh sách bài thi viết cần phúc khảo của kỳ thi mặc định
		$model = $this->getModel('regrading');
		$regradings = $model->getPaperRegradings();

		//Xuất ra tập tin excel
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		IOHelper::writePaperRegradings($sheet, $regradings);

		// Force download of the Excel file
		$fileName = 'Danh sách phúc khảo bài thi viết.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();

	}
	public function hybridlist()
	{

		//Set redirect in any case
		$url = JRoute::_('index.php?option=com_eqa', false);
		$this->setRedirect($url);

		if(!$this->app->getIdentity()->authorise('core.manage','com_eqa'))
		{
			$this->setMessage('Bạn không có quyền truy cập trang này');
			return;
		}

		//Lấy danh sách bài thi viết cần phúc khảo của kỳ thi mặc định
		$model = $this->getModel('regrading');
		$regradings = $model->getHybridRegradings();

		//Xuất ra tập tin excel
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getSheet(0);
		IOHelper::writeHybridRegradings($sheet, $regradings);

		// Force download of the Excel file
		$fileName = 'Danh sách phúc khảo bài thi hỗn hợp.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();

	}
}
