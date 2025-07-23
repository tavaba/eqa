<?php
namespace Kma\Component\Eqa\Site\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RegradingsController extends EqaAdminController
{
	public function downloadRegradingFee(): void
	{
		/**
		 * Các bước thực hiện
		 * 1. Kiểm tra quyền
		 * 2. Lấy thông tin kỳ thi (mặc định)
		 * 3. Kiểm tra, đảm bảo rằng kỳ thi chưa kết thúc, thời hạn phúc khảo đã qua. Nếu vi phạm thì báo lỗi
		 *    và kết thúc
		 * 4. Xử lý xuất file excel
		 * 5. Force download of the Excel file
		 */
		try
		{
			//Bước 1. Check permission
			if(!$this->app->getIdentity()->authorise('eqa.supervise','com_eqa'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Bước 2. Lấy thông tin kỳ thi trong trạng thái hiện thời của model
			$mvcFactory = GeneralHelper::getMVCFactory();
			$model = $mvcFactory->createModel('Regradings', 'Administrator');
			$examseasonId = $model->getSelectedExamseasonId();
			if(empty($examseasonId))
				throw new Exception('Hãy chọn một kỳ thi ở bộ lọc để thực hiện chức năng này');

			//Bước 3. Kiểm tra thời hạn phúc khảo. Nếu chưa kết thúc thì báo lỗi
			$examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
			if($examseason->canSendPpaaRequest())
				throw new Exception('Chưa hết hạn gửi yêu cầu phúc khảo');

			//Bước 4. Lấy danh sách yêu cầu phúc khảo của kỳ thi
			$regradingRequests = $model->getRegradingRequests($examseason->id, true);
			if(empty($regradingRequests))
				throw new Exception('Không có yêu cầu phúc khảo');


			//Bước 5. Xuất ra tập tin excel
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			IOHelper::writeRegradingFee($spreadsheet, $regradingRequests);

			//Bước 6. Force download of the Excel file
			$fileName = "Thu phí PK. {$examseason->name}.xlsx";
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			$this->app->close();
		}
		catch (Exception $e) {
			$url = JRoute::_('index.php?option=com_eqa&view=regradings', false);
			$this->setRedirect($url);
			$this->setMessage($e->getMessage(),'error');
		}

	}

}

