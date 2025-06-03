<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use stdClass;

class ExamroomsController extends EqaAdminController {
    public function export()
    {
        $app = $this->app;
        $this->checkToken();
        if(!$app->getIdentity()->authorise('core.manage',$this->option))
        {
            echo Text::_('COM_EQA_MSG_UNAUTHORISED');
            exit();
        }

        $examroomIds = (array) $this->input->get('cid', [], 'int');

        // Prepare the spreadsheet
	    $model = $this->getModel();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        foreach ($examroomIds as $index => $examroomId) {
	        $examroom = DatabaseHelper::getExamroomInfo($examroomId);

			//Kiểm tra xem đã phân công CBCT, CBChT chưa
	        if(!$model->canExport($examroomId))
	        {
				$msg = "Phòng thi <b>$examroom->name</b>: chưa phân công CBCT, CBCT-ChT";
				$this->setMessage($msg, 'error');
				$url = 'index.php?option=com_eqa&view=examsessionemployees&examsession_id='.$examroom->examsessionId;
				$this->setRedirect(JRoute::_($url, false));
				return;
	        }

            $examinees = $model->getExaminees($examroomId);

            // Create a new worksheet for each exam room and Set the worksheet title
            $sheet = $spreadsheet->createSheet($index);
			$sheetTitle = $examroom->name;
			if(strlen($sheetTitle)>20)
				$sheetTitle = substr($sheetTitle,0,20);
			$sheetTitle .= ' (' . $examroomId . ')';
            $sheet->setTitle($sheetTitle);

			//Write
	        IOHelper::writeExamroomExaminees($sheet, $examroom, $examinees);
        }

	    // Force download of the Excel file
	    $fileName = 'Danh sách thí sinh phòng thi.xlsx';
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
	    exit();
    }
	public function import(): void
	{
		$app = Factory::getApplication();
		$fileFormField = 'file_report';

		// Check for request forgeries.
		$this->checkToken();

		//Set redirect to list view in any case
		$this->setRedirect(JRoute::_('index.php?option=com_eqa&view=examrooms&layout=import', false));

		//Access Check
		if(!$app->getIdentity()->authorise('core.create',$this->option))
		{
			$this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
			return;
		}

		$importAnomaly = $app->input->getBool('import_anomaly');

		$files = $this->input->files;
		$file = $files->get($fileFormField);
		if(empty($file['tmp_name'])){
			$this->setMessage(Text::_('COM_EQA_MSG_ERROR_NO_FILE_UPLOADED'), 'error');
			return;
		}

		//Mở file (tạo $spreadsheet)
		$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
		try {
			if ($fileExtension === 'xls') {
				$reader = new Xls();
			}
			else {
				// Assume it's an Excel 2007 or later (.xlsx)
				$reader = IOFactory::createReader('Xlsx');
			}
			$spreadsheet = $reader->load($file['tmp_name']);
		}
		catch (Exception $e){
			$msg = '<b>' . htmlspecialchars($file['name']) . '</b>: ' . $e->getMessage();
			$app->enqueueMessage($msg,'error');
		}

		$pattern = '/^Ngày thi: [\s\S]+ Mã phòng thi: ([0-9]+)$/';
		$examIds = [];
		$sheetNumber = $spreadsheet->getSheetCount();
		for($sh=0; $sh<$sheetNumber; $sh++){
			$sheet = $spreadsheet->getSheet($sh);

			//1. Tìm dòng chứa thông tin phòng thi và xác định id của phòng thi
			//    "Ngày thi: 17/10/2024   Giờ thi: 07:30    Phòng thi: 403-TA1   Mã phòng thi: 65"
			$highestRow = $sheet->getHighestRow('A');
			$row=1;
			for(; $row <= $highestRow; $row++)
			{
				$cellValue = $sheet->getCell('A'.$row)->getValue();
				if(empty($cellValue))
					continue;
				$matched = preg_match($pattern, $cellValue, $matches);
				if($matched){
					$examroomId = (int)$matches[1];
					break;
				}
			}
			if($row >= $highestRow) //Not found
			{
				$msg = Text::sprintf('Sheet <b>%s</b>: không tìm thấy thông tin phòng thi', $sheet->getTitle());
				$app->enqueueMessage($msg, 'error');
				continue; //Nhảy sang sheet kế tiếp
			}

			//2. Lấy thông tin về các exam liên quan
			$examroomExamIds = DatabaseHelper::getExamroomExamIds($examroomId);
			if (empty($examroomExamIds)){
				$msg = Text::sprintf('Sheet <b>%s</b>: không xác định được môn thi', $sheet->getTitle());
				$app->enqueueMessage($msg, 'error');
				continue; //Nhảy sang sheet kế tiếp
			}
			$canEdit=true;
			foreach ($examroomExamIds as $examId)
			{
				if (DatabaseHelper::isCompletedExam($examId))
				{
					$canEdit = false;
					break;
				}
			}
			if(!$canEdit){
				$msg = Text::sprintf('Sheet <b>%s</b>: môn thi đã hoàn tất, không thể nhập dữ liệu', $sheet->getTitle());
				$app->enqueueMessage($msg, 'error');
				continue; //Nhảy sang sheet kế tiếp
			}
			$examIds = array_merge($examIds, $examroomExamIds);

			//2. Tìm dòng tiêu đề
			$row=1;
			$highestRow = $sheet->getHighestRow('A');
			for(; $row<=$highestRow; $row++){
				$cellValue = $sheet->getCell('A'.$row)->getValue();
				if($cellValue=='STT')
					break;
			}
			if($row >= $highestRow) //Not found
			{
				$msg = Text::sprintf('Sheet <b>%s</b>: không tìm thấy thông tin thí sinh', $sheet->getTitle());
				$app->enqueueMessage($msg, 'error');
				continue;
			}

			//3. Load examinees' data
			//Cột 'B' = $examineeCode: Số báo danh
			//Cột 'C' = $learnerCode: Mã HVSV -> $learnerId
			//Cột 'H' = $value: Nếu thi viết là "Số tờ", nếu thi khác là "Điểm"
			//Cột 'J' = $description
			$examinees=[];
			while(true){
				$row++;
				$examineeCode = $sheet->getCell('B'.$row)->getValue();
				if(empty($examineeCode)) //The end
					break;

				$examinee = new stdClass();
				$examinee->code = $examineeCode;
				$examinee->learnerCode = $sheet->getCell('C'.$row)->getValue();
				$examinee->value = $sheet->getCell('H'.$row)->getValue();
				$examinee->description = $sheet->getCell('J'.$row)->getValue();
				if($importAnomaly)
				{
					$examinee->anomaly = ExamHelper::getAnomalyFromDescription($examinee->description);
					if($examinee->anomaly === false)
					{
						$msg = Text::sprintf("Dòng 'Ghi chú' không hợp lệ: sheet %s, dòng %d", $sheet->getTitle(), $row);
						$this->setMessage($msg, 'error');
						return;
					}
				}
				$examinees[$examinee->learnerCode] = $examinee;
			}


			//4. Call model
			$model = $this->getModel();
			$examroomName = $sheet->getTitle();
			try
			{
				$model->import($examroomId, $examroomName, $examinees, $importAnomaly);
			}
			catch(Exception $e){
				$this->setMessage($e->getMessage(), 'error');
				return;
			}
		}//Hết tất cả các sheet

		//Cập nhật trạng thái môn thi
		$examIds = array_unique($examIds);
		$model = $this->createModel('exam');
		foreach ($examIds as $examId)
		{
			$exam = DatabaseHelper::getExamInfo($examId);
			if ($exam->countConcluded == $exam->countToTake)
			{
				$model->setExamStatus($examId, ExamHelper::EXAM_STATUS_MARK_FULL);
				$msg = Text::sprintf('Môn thi <b>%s</b>: %d/%d thí sinh đã có kết quả thi', $exam->name, $exam->countConcluded, $exam->countToTake);
				$app->enqueueMessage($msg, 'success');
			}
			elseif ($exam->countConcluded > 0)
			{
				$model->setExamStatus($examId, ExamHelper::EXAM_STATUS_MARK_PARTIAL);
				$msg = Text::sprintf('Môn thi <b>%s</b>: %d/%d thí sinh đã có kết quả thi', $exam->name, $exam->countConcluded, $exam->countToTake);
				$app->enqueueMessage($msg );
			}
			if($exam->testtype==ExamHelper::TEST_TYPE_PAPER )
			{
				if ($exam->countHavePaperInfo == $exam->countToTake)
				{
					$model->setExamStatus($examId, ExamHelper::EXAM_STATUS_PAPER_INFO_FULL);
					$msg = Text::sprintf('Môn thi <b>%s</b>: %d/%d thí sinh đã có thông tin bài thi', $exam->name, $exam->countHavePaperInfo, $exam->countToTake);
					$app->enqueueMessage($msg, 'success');
				}
				elseif ($exam->countHavePaperInfo > 0)
				{
					$model->setExamStatus($examId, ExamHelper::EXAM_STATUS_PAPER_INFO_PARTIAL);
					$msg = Text::sprintf('Môn thi <b>%s</b>: %d/%d thí sinh đã có thông tin bài thi', $exam->name, $exam->countHavePaperInfo, $exam->countToTake);
					$app->enqueueMessage($msg);
				}
			}
		}
	}

}
