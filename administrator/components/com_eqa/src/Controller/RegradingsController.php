<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use JRoute;
use JSession;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RegradingsController extends EqaAdminController {

	/**
	 * Phân công chấm phúc khảo. Đối với chấm phúc khảo sẽ không thực hiện dồn túi mà sẽ phân công
	 * theo môn vì thường thì mỗi môn chỉ có tối đa vài chục bài.
	 *
	 * @since version 1.1.10
	 */
	public function assignExaminers()
	{
		/**
		 * Các bước thực hiện
		 * 1. Lấy thông tin về kỳ thi (mặc định)
		 * 2. Kiểm tra, đảm bảo rằng kỳ thi chưa kết thúc, thời hạn phúc khảo đã qua. Nếu vi phạm thì báo lỗi
		 *    và kết thúc
		 * 3. Redirect sang form
		 */

		//Bước 1. Lấy thông tin về kỳ thi (mặc định)
		$examseason = DatabaseHelper::getExamseasonInfo(); //Mặc định lấy kỳ thi đang diễn ra

		//Bước 2. Kiểm tra, đảm bảo rằng kỳ thi chưa kết thúc, thời hạn phúc khảo đã qua. Nếu vi phạm thì báo lỗi
		$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
		if(empty($examseason))
		{
			$this->setMessage('Không tìm thấy kỳ thi','error');
			return;
		}
		if($examseason->completed)
		{
			$this->setMessage('Kỳ thi đã kết thúc','error');
			return;
		}
		if($examseason->canSendPpaaRequest()) {
			$this->setMessage('Vẫn chưa hết hạn gửi yêu cầu phúc khảo','error');
			return;
		}

		//Bước 3. Chuyển hướng sang form
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingemployees&examseason_id='.$examseason->id, false));

	}

	public function saveExaminers(bool $continueAssigning=false)
	{
		try
		{
			//Bước 1. Kiểm tra token
			$this->checkToken();

			//Bước 2. Kiểm tra quyền
			if(!$this->app->getIdentity()->authorise('core.edit',$this->option)) {
				throw new Exception('Bạn không có quyền truy cập trang này');
			}

			//Bước 3. Lấy dữ liệu từ form
			$examseasonId = $this->input->getInt('examseason_id');
			$data = $this->input->post->get('jform',[],'array');
			if(empty($examseasonId) || empty($data))
			{
				throw new Exception('Dữ liệu không hợp lệ');
			}

			//Bước 4. Lưu vào database
			$model = $this->getModel('regrading');
			$model->saveExaminers($examseasonId,$data);

			//Bước 5. Redirect đến trang tiếp theo nếu có
			$this->setMessage('Dữ liệu đã được lưu thành công','success');
			if($continueAssigning) {
				$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingemployees&examseason_id='.$examseasonId.'&layout=default', false));
			}
			else{
				$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
			}
		}
		catch (Exception $e) {
			$this->setMessage($e->getMessage(),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa', false));
			return;
		}

	}

	public function applyExaminers()
	{
		$this->saveExaminers(true);
	}

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
			if(!$this->app->getIdentity()->authorise('core.manage','com_eqa'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Bước 2. Lấy thông tin kỳ thi
			$examseason = DatabaseHelper::getDefaultExamseason();
			if(!$examseason)
				throw new Exception('Không tìm thấy kỳ thi mặc định');

			//Bước 3. Kiểm tra thời hạn phúc khảo. Nếu chưa kết thúc thì báo lỗi
			if($examseason->canSendPpaaRequest())
				throw new Exception('Chưa hết hạn gửi yêu cầu phúc khảo');

			//Bước 4. Lấy danh sách bài thi viết cần phúc khảo của kỳ thi
			$model = $this->getModel('regrading');
			$regradingRequests = $model->getRegradingRequests($examseason->id, true);


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
			$url = JRoute::_('index.php?option=com_eqa', false);
			$this->setRedirect($url);
			$this->setMessage($e->getMessage(),'error');
		}

	}

	/**
	 * Tải về thông tin các bài thi viết cần phúc khảo để phục vụ việc rút bài thi
	 * Áp dụng cho kỳ thi mặc định (default=1)
	 * @since 1.1.9
	 */
	public function downloadPaperInfo(): void
	{
		try
		{
			//Check permission
			if(!$this->app->getIdentity()->authorise('eqa.mask','com_eqa'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Lấy thông tin kỳ thi
			$examseason = DatabaseHelper::getDefaultExamseason();
			if(!$examseason)
				throw new Exception('Không tìm thấy kỳ thi mặc định');

			//Lấy danh sách bài thi viết cần phúc khảo của kỳ thi
			$model = $this->getModel('regrading');
			$paperExams = $model->getPaperExams($examseason->id, false);

			//Lấy họ và tên của các giảng viên theo Id
			$examinerIds = [];
			foreach ($paperExams as $examId => $papers)
			{
				foreach ($papers as $paper)
				{
					array_push($examinerIds, $paper->oldExaminer1Id, $paper->oldExaminer2Id);
				}
			}
			$examinerIds = array_unique($examinerIds);
			$examiners = DatabaseHelper::getEmployeeInfos($examinerIds, false);

			//Xuất ra tập tin excel
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			foreach ($paperExams as $examId => $papers)
			{
				$examName = $papers[0]->examName;
				$sheet = $spreadsheet->createSheet();
				$sheetTitle = $examId . '_' . $examName;
				$sheetTitle = IOHelper::sanitizeSheetTitle($sheetTitle);
				$sheetTitle = trim(mb_substr($sheetTitle,0,20));
				$sheet->setTitle($sheetTitle);
				IOHelper::writePaperExamRegradingFullInfo($sheet, $examseason->name, $examId, $examName, $papers, $examiners);
			}

			// Force download of the Excel file
			$fileName = 'Thông tin để rút bài thi viết.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			$this->app->close();
		}
		catch (Exception $e) {
			$url = JRoute::_('index.php?option=com_eqa', false);
			$this->setRedirect($url);
			$this->setMessage($e->getMessage(),'error');
		}

	}
	public function downloadHybridRegradings(): void
	{
		try
		{
			//Check permission
			if(!$this->app->getIdentity()->authorise('core.manage','com_eqa'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Lấy thông tin kỳ thi
			$examseason = DatabaseHelper::getDefaultExamseason();
			if(!$examseason)
				throw new Exception('Không tìm thấy kỳ thi mặc định');

			//Lấy danh sách bài thi hỗn hợp cần phúc khảo của kỳ thi
			$model = $this->getModel('regrading');
			$itestExams = $model->getHybridRegradings($examseason->id, false);


			//Xuất ra tập tin excel
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			foreach ($itestExams as $examId => $works)
			{
				$examName = $works[0]->examName;
				$sheet = $spreadsheet->createSheet();
				$sheetTitle = $examId . '-' . $examName;
				$sheetTitle = IOHelper::sanitizeSheetTitle($sheetTitle);
				$sheet->setTitle($sheetTitle);
				IOHelper::writeHybridExamRegradings($sheet, $examseason->name, $examId, $examName, $works);
			}

			// Force download of the Excel file
			$fileName = 'Thông tin phúc khảo bài thi iTest.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			$this->app->close();
		}
		catch (Exception $e) {
			$url = JRoute::_('index.php?option=com_eqa', false);
			$this->setRedirect($url);
			$this->setMessage($e->getMessage(),'error');
		}

	}

	public function downloadMarkingSheets(): void
	{
		try
		{
			//Check permission
			if(!$this->app->getIdentity()->authorise('core.manage','com_eqa'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Lấy thông tin kỳ thi
			$examseason = DatabaseHelper::getDefaultExamseason();
			if(!$examseason)
				throw new Exception('Không tìm thấy kỳ thi mặc định');

			//Lấy model
			$model = $this->getModel('regrading');

			//Kiểm tra xem đã phân công xong cán bộ chấm phúc khảo chưa
			if(!$model->examinersAssigned($examseason->id))
				throw new Exception('Chưa hoàn tất phân công cán bộ chấm phúc khảo');

			//Lấy danh sách bài thi viết cần phúc khảo của kỳ thi
			$paperExams = $model->getPaperExams($examseason->id);
			if (empty($paperExams))
				throw new Exception('Không có bài thi viết nào để phúc khảo. Hãy kiểm tra, đảm bảo rằng yêu cầu phúc khảo của thí sinh đã được chấp nhận');

			//Lấy thông tin cán bộ chấm thi liên quan
			$employeeIds = [];
			foreach ($paperExams as $examId => $papers)
			{
				foreach ($papers as $paper)
				{
					array_push($employeeIds, $paper->examiner1Id, $paper->examiner2Id);
				}
			}
			$employeeIds = array_unique($employeeIds);
			$employees = DatabaseHelper::getEmployeeInfos($employeeIds);

			//Write to excel files
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			foreach ($paperExams as $examId => $papers)
			{
				$sheet = $spreadsheet->createSheet();
				$sheetTitle = $examId.'_'.$papers[0]->examName;
				$sheetTitle = IOHelper::sanitizeSheetTitle($sheetTitle, 20);
				$sheet->setTitle($sheetTitle);
				IOHelper::writeRegradingMarkingSheet($sheet, $examseason, $examId, $papers[0]->examName, $papers, $employees);
			}

			//Force download of the Excel file
			$fileName = 'Phiếu chấm phúc khảo.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			$this->app->close();
		}
		catch(Exception $e){
			$url = JRoute::_('index.php?option=com_eqa',false);
			$this->setRedirect($url);
			$this->setMessage($e->getMessage(), 'error');
		}
	}
}
