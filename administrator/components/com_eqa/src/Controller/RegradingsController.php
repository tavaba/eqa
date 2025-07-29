<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Interface\PpaaEntryInfo;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RegradingsController extends EqaAdminController {
	public function accept()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
				throw new Exception('Bạn không có quyền thực hiện chức năng này');

			//3. Get form data
			$cid = $this->input->post->get('cid',[],'array');
			$cid = array_filter($cid, 'intval');
			if(empty($cid))
				throw new Exception('Không có phần tử nào được chọn');

			//4. Call model and apply change
			$currentUsername = $this->app->getIdentity()->username;
			$currentTime = date('Y-m-d H:i:s');
			$model = $this->getModel('regrading');
			foreach ($cid  as $itemId)
				$model->accept($itemId, $currentUsername, $currentTime);

			//5. Redirect
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradings', false));
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradings', false));
		}
	}
	public function reject()
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2. Check permission
			if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
				throw new Exception('Bạn không có quyền thực hiện chức năng này');

			//3. Get form data
			$cid = $this->input->post->get('cid',[],'array');
			$cid = array_filter($cid, 'intval');
			if(empty($cid))
				throw new Exception('Không có phần tử nào được chọn');

			//4. Call model and apply change
			$currentUsername = $this->app->getIdentity()->username;
			$currentTime = date('Y-m-d H:i:s');
			$model = $this->getModel('regrading');
			foreach ($cid  as $itemId)
				$model->reject($itemId, $currentUsername, $currentTime);

			//5. Redirect
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradings', false));
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradings', false));
		}
	}

	/**
	 * Phân công chấm phúc khảo. Đối với chấm phúc khảo sẽ không thực hiện dồn túi mà sẽ phân công
	 * theo môn vì thường thì mỗi môn chỉ có tối đa vài chục bài.
	 *
	 * @since version 1.1.10
	 */
	public function assignRegradingExaminers()
	{

		try
		{
			//Bước 1. Lấy thông tin về kỳ thi hiện thời trong model
			$model = $this->getModel('regradings');
			$examseasonId = $model->getFilteredExamseasonId();

			if(empty($examseasonId))
				throw new Exception('Hãy chọn một kỳ thi ở bộ lọc để thực hiện chức năng này');

			//Bước 2. Kiểm tra, đảm bảo rằng kỳ thi chưa kết thúc, thời hạn phúc khảo đã qua. Nếu vi phạm thì báo lỗi
			$examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
			if($examseason->completed)
				throw new Exception('Kỳ thi đã kết thúc');
			if($examseason->canSendPpaaRequest())
				throw new Exception('Vẫn chưa hết hạn gửi yêu cầu phúc khảo');

			//Bước 3. Chuyển hướng sang form
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingemployees&examseason_id='.$examseason->id, false));
			return;
		}
		catch (Exception $e) {
			$this->setMessage($e->getMessage(),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradings', false));
			return;
		}

	}

	public function saveRegradingExaminers(bool $continueAssigning=false)
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
			$model = $this->getModel('regradings');
			$model->saveExaminers($examseasonId,$data);

			//Bước 5. Redirect đến trang tiếp theo nếu có
			$this->setMessage('Dữ liệu đã được lưu thành công','success');
			if($continueAssigning) {
				$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingemployees&examseason_id='.$examseasonId.'&layout=default', false));
			}
			else{
				$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradings', false));
			}
		}
		catch (Exception $e) {
			$this->setMessage($e->getMessage(),'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradings', false));
			return;
		}

	}

	public function applyRegradingExaminers()
	{
		$this->saveRegradingExaminers(true);
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

			//Bước 2. Lấy thông tin kỳ thi trong trạng thái hiện thời của model
			$model = $this->getModel('regradings');
			$examseasonId = $model->getFilteredExamseasonId();
			if(empty($examseasonId))
				throw new Exception('Hãy chọn một kỳ thi ở bộ lọc để thực hiện chức năng này');

			//Bước 3. Kiểm tra thời hạn phúc khảo. Nếu chưa kết thúc thì báo lỗi
			$examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
			if($examseason->canSendPpaaRequest())
				throw new Exception('Chưa hết hạn gửi yêu cầu phúc khảo');

			//Bước 4. Lấy danh sách yêu cầu phúc khảo của kỳ thi
			$model = $this->getModel('regradings');
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

	/**
	 * Tải về thông tin các bài thi viết cần phúc khảo để phục vụ việc rút bài thi
	 * Áp dụng cho kỳ thi mặc định (default=1)
	 * @since 1.1.9
	 */
	public function downloadPaperRegradings(): void
	{
		try
		{
			//Check permission
			if(!$this->app->getIdentity()->authorise('eqa.mask','com_eqa'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Bước 2. Lấy thông tin kỳ thi trong trạng thái hiện thời của model
			$model = $this->getModel('regradings');
			$examseasonId = $model->getFilteredExamseasonId();
			if(empty($examseasonId))
				throw new Exception('Hãy chọn một kỳ thi ở bộ lọc để thực hiện chức năng này');

			//Lấy danh sách bài thi viết cần phúc khảo của kỳ thi
			$examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
			$paperExams = $model->getPaperRegradings($examseasonId);
			if(empty($paperExams))
				throw new Exception('Không có yêu cầu phúc khảo. Hãy đảm bảo rằng các yêu cầu phúc khảo đã được chấp nhận');

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
			$url = JRoute::_('index.php?option=com_eqa&view=regradings', false);
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

			//Bước 2. Lấy thông tin kỳ thi trong trạng thái hiện thời của model
			$model = $this->getModel('regradings');
			$examseasonId = $model->getFilteredExamseasonId();
			if(empty($examseasonId))
				throw new Exception('Hãy chọn một kỳ thi ở bộ lọc để thực hiện chức năng này');

			//Lấy danh sách bài thi hỗn hợp cần phúc khảo của kỳ thi
			$examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
			$itestExams = $model->getHybridRegradings($examseason->id, true);
			if(empty($itestExams))
				throw new Exception('Không có yêu cầu phúc khảo. Hãy đảm bảo rằng các yêu cầu phúc khảo đã được chấp nhận');


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
			$url = JRoute::_('index.php?option=com_eqa&view=regradings', false);
			$this->setRedirect($url);
			$this->setMessage($e->getMessage(),'error');
		}

	}
	public function downloadPaperRegradingSheets(): void
	{
		try
		{
			//Check permission
			if(!$this->app->getIdentity()->authorise('core.manage','com_eqa'))
				throw new Exception('Bạn không có quyền truy cập chức năng này');

			//Bước 2. Lấy thông tin kỳ thi trong trạng thái hiện thời của model
			$model = $this->getModel('regradings');
			$examseasonId = $model->getFilteredExamseasonId();
			if(empty($examseasonId))
				throw new Exception('Hãy chọn một kỳ thi ở bộ lọc để thực hiện chức năng này');

			//Kiểm tra xem đã phân công xong cán bộ chấm phúc khảo chưa
			$examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
			if(!$model->examinersAssigned($examseason->id))
				throw new Exception('Chưa hoàn tất phân công cán bộ chấm phúc khảo');

			//Lấy danh sách bài thi viết cần phúc khảo của kỳ thi
			$paperExams = $model->getPaperRegradings($examseason->id);
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
			$url = JRoute::_('index.php?option=com_eqa&view=regradings',false);
			$this->setRedirect($url);
			$this->setMessage($e->getMessage(), 'error');
		}
	}

	public function uploadPaperRegradingResult():void
	{
		/**
		 * Cách thức thực hiện
		 * 1. Check form token
		 * 2. Check permissions
		 * 3. Xác định danh sách các file excel được gửi đến qua upload form
		 * 4. Load model
		 * 5. Với mỗi file, nạp spreadsheet, với mỗi worksheet thực hiện các bước sau:
		 *    Bước 1. Đọc toàn bộ dữ liệu của sheet vào một mảng
		 *    Bước 2. Xác định mã môn thi
		 *    Bước 3. Xác định dòng tiêu đề của bảng điểm
		 *    Bước 4. Đọc điểm theo số phách vào mảng
		 *    Bước 5. Gọi phương thức saveResultPaper() của model để ghi kết quả phúc khảo
		 * 6. Thông báo kết quả. Redirect về giao diện dashboard
		 */

		//1. Check token
		$this->checkToken();

		try
		{
			//2. Check permission
			if (!$this->app->getIdentity()->authorise('core.manage', 'com_eqa')) {
				throw new Exception('Bạn không có quyền truy cập trang này');
			}

			//3. Get uploaded files
			$files = $this->input->files->get('excelfiles', null, 'array');
			if(empty($files[0]['tmp_name']))
				throw new Exception('Không tìm thấy file');

			//4. Load model
			$model = $this->getModel('regradings');

			//5. Process each file
			foreach ($files as $file)
			{
				//5.1. Load spreadsheet
				$fileName = $file['name'];
				$filePath = $file['tmp_name'];
				$spreadsheet = IOHelper::loadSpreadsheet($filePath);

				//5.2. Process each worksheet
				foreach ($spreadsheet->getAllSheets() as $worksheet) {
					//Step 1. Read all data from sheet into a multi-dimensional array
					$sheetTitle = $worksheet->getTitle();
					$sheetData = $worksheet->toArray(null,false,false,false,true);
					$lastRowIndex = count($sheetData) - 1; //Last row index

					//Step 2. Find the exam name and id
					$pattern = '/^Môn thi:\s*(.+?)\s*\(Mã môn thi:\s*(\d+)\)$/u';
					$r=0;
					$examName=null;
					$examId = null;
					for(; $r<=$lastRowIndex; $r++)
					{
						if(!isset($sheetData[$r][0]))
							continue;
						if(preg_match($pattern, $sheetData[$r][0], $matches))
						{
							$examName = $matches[1];
							$examId = (int)$matches[2];
							break;
						}
					}
					if(!$examName || !$examId)
					{
						$msg = Text::sprintf('Không tìm thấy tên hoặc mã môn thi trong sheet <b>%s</b> của file <b>%s</b>',
							htmlspecialchars($sheetTitle), htmlspecialchars($fileName));
						throw new Exception($msg);
					}

					//Step 3. Find the heading row of the mark table
					$headingRowIndex = null;
					for ($r++; $r<=$lastRowIndex; $r++)
					{
						if($sheetData[$r][0]=='Phách' && $sheetData[$r][1]=='Điểm')
						{
							$headingRowIndex = $r;
							break;
						}
					}
					if(is_null($headingRowIndex))
					{
						$msg = Text::sprintf('Không tìm thấy dòng tiêu đề của bảng điểm trong sheet <b>%s</b> của file <b>%s</b>',
							htmlspecialchars($sheetTitle), htmlspecialchars($fileName));
						throw new Exception($msg);
					}

					//Step 4. Read marks by row number
					$regradingData = [];
					$uncomplete = false;
					for ($r=$headingRowIndex+2;$r<=$lastRowIndex;$r++)
					{
						//Check the end of the table
						$examineeMask = $sheetData[$r][0];    //This must be an integer
						if(!(is_numeric($examineeMask) && (int)$examineeMask == $examineeMask))
							break;

						//Read regrading info
						$regradingEntry = new PpaaEntryInfo();
						$regradingEntry->mask = $examineeMask;
						$regradingEntry->oldMark = $sheetData[$r][1]; //Old mark
						$regradingEntry->newMark = $sheetData[$r][2]; //New mark
						$regradingEntry->changeDescription = $sheetData[$r][4];

						//Both old and new marks must present to continue
						if(!isset($sheetData[$r][1]) || !isset($sheetData[$r][2]))
						{
							$uncomplete = true;
							break;
						}

						//Check if marks are valid
						if(!ExamHelper::isValidMark($regradingEntry->oldMark) || !ExamHelper::isValidMark($regradingEntry->newMark))
						{
							$msg = Text::sprintf('Điểm không hợp lệ ở dòng %d của sheet <b>%s</b> thuộc file <b>%s</s>',
								$r,
								htmlspecialchars($sheetTitle),
								htmlspecialchars($fileName)
							);
							throw new Exception($msg);
						}

						//Add entry to list
						$regradingData[] = $regradingEntry;
					}

					if($uncomplete)
					{
						$msg = Text::sprintf("Sheet <b>%s</b> file <b>%s</b> chưa hoàn thiện, bị bỏ qua",
							htmlspecialchars($sheetTitle), htmlspecialchars($fileName)
						);
						$this->app->enqueueMessage($msg, 'warning');
						continue;
					}

					//Step 5. Save result
					$model->savePaperRegradingResult($examId, $regradingData);
					$msg = Text::sprintf("Sheet <b>%s</b> file <b>%s</b>: Cập nhật thành công %d bản ghi",
						htmlspecialchars($sheetTitle),
						htmlspecialchars($fileName),
						count($regradingData));
					$this->app->enqueueMessage($msg,'success');
				}
			}
		}
		catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingresult&layout=uploadpaper', false));
			return;
		}

		//6. Redirect back to the page
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingresult&layout=uploadpaper', false));
	}
	public function uploadHybridRegradingResult():void
	{
		try
		{
			//1. Check token
			$this->checkToken();

			//2.Check permission
			if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
				throw new Exception('Bạn không có quyền truy cập trang này');

			//3. Retreive data from post request
			$examId = $this->input->post->getInt('exam_id');
			$multiple = $this->input->post->getInt('multiple');
			$file = $this->input->files->get('file');
			if(empty($examId) || empty($file['tmp_name']))
				throw new Exception('Dữ liệu form không hợp lệ');

			//4. Load the worksheet that contains the results
			$spreadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);
			if($multiple)
				$sheetName = 'Tất cả';
			else
				$sheetName = 'Bảng điểm';
			$sheet = $spreadsheet->getSheetByName($sheetName);
			if(empty($sheet))
			{
				$msg = Text::sprintf("File không hợp lệ. Không tìm thấy sheet <b>%s</b>", $sheetName);
				throw new Exception($msg);
			}

			//5. Extract exam results from the worksheet
			//   This returns an associative array with learner code as key and mark as value
			$rows = $sheet->toArray();
			$highestDataRow = count($rows)-1;
			$examResults = [];
			for($r=1; $r<=$highestDataRow; $r++)
			{
				$row = $rows[$r];
				$learnerCode = trim((string)$row[2]);  // Cột C (index 2)
				$mark = $row[8];                       // Cột I (index 8)

				// Bỏ qua dòng trống
				if (empty($learnerCode) && empty($mark)) {
					continue;
				}
				if(!ExamHelper::isValidMark($mark))
				{
					$msg = Text::sprintf("Điểm không hợp lệ: sheet <b>%s</b>, dòng <b>%d</b>", $sheetName, $r+1);
					throw new Exception($msg);
				}

				$examResults[$learnerCode] = floatval($mark);
			}
			if(count($examResults)==0)
				throw new Exception('Không tìm thấy dữ liệu');

			//5. Save results to database
			$model = $this->getModel('regradings');
			$count = $model->saveHybridRegradingResult($examId, $examResults);

			//6. Show message
			$examName = DatabaseHelper::getExamName($examId);
			$msg = Text::sprintf('Ghi điểm phúc khảo thành công cho %d thí sinh của môn thi <b>%s</b>', $count, htmlspecialchars($examName));
			$this->app->enqueueMessage($msg,'success');
		}
		catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingresult&layout=uploadpaper', false));
			return;
		}

		//7. Redirect back to the page
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=regradingresult&layout=uploaditest', false));
	}
}
