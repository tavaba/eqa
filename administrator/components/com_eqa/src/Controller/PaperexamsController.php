<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;

class PaperexamsController extends EqaAdminController {
	public function uploadMarkByMask()
	{
		$fileFormField = 'excelfile';
		$componentConfig = ComponentHelper::getParams('com_eqa');
		$examMarkPrecision = $componentConfig->get('params.precision_exam',1);

		//Check token
		$this->checkToken();

		//Check permissions
		if(!$this->app->getIdentity()->authorise('core.edit',$this->option))
		{
			$msg = Text::_('COM_EQA_MSG_UNAUTHORISED');
			$this->setMessage($msg,'error');
			$this->setRedirect(JRoute('index.php?option=com_eqa',false));
			return;
		}

		//Redirect in any cases
		$this->setRedirect(\JRoute::_('index.php?option=com_eqa', false));

		//Check input files
		$files = $this->input->files;
		$file = $files->get($fileFormField);
		if(empty($file['tmp_name'])){
			$this->setMessage(Text::_('COM_EQA_MSG_ERROR_NO_FILE_UPLOADED'), 'error');
			return;
		}

		//Try to open file
		try {
			// Check if the file is Excel 97 (.xls)
			$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
			if ($fileExtension === 'xls') {
				$reader = new Xls();
			} else {
				// Assume it's an Excel 2007 or later (.xlsx)
				$reader = IOFactory::createReader('Xlsx');
			}
			$spreadsheet = $reader->load($file['tmp_name']);
		}
		catch (Exception $e){
			$msg = '<b>' . htmlentities($file['name']) . '</b> : ' . $e->getMessage();
			$this->setMessage($msg, 'error');
			return;
		}

		//Process the file
		$examInfoPattern = '/^Môn thi: [\s\S]+ \(Mã môn thi: ([0-9]+)\)$/u';
		$maskColumnName = 'Số phách';
		$model = $this->getModel();
		$examIds = [];
		foreach ($spreadsheet->getAllSheets() as $sheet){
			//1. Tìm kiếm môn thi
			$highestRow = $sheet->getHighestDataRow('A');
			$row=1;
			$examId = null;
			for(; $row<=$highestRow; $row++)
			{
				$cellValue = $sheet->getCell([1, $row])->getValue();
				if(!empty($cellValue)){
					$matched = preg_match($examInfoPattern, $cellValue, $matches);
					if($matched)
					{
						$examId = $matches[1];
						break;
					}
				}
			}
			if($row > $highestRow) //Không tìm thấy
			{
				$msg = Text::sprintf('Sheet <b>%s</b>: không tìm thấy môn thi', $sheet->getTitle());
				$this->app->enqueueMessage($msg,'error');
				continue; //Bỏ qua sheet hiện thời
			}
			if(!in_array($examId, $examIds))
				$examIds[] = $examId;

			//2. Tìm kiếm vị trí dòng heading của phần chấm điểm
			for (; $row <= $highestRow; $row++)
			{
				$cellValue = $sheet->getCell([1, $row])->getValue();
				if($cellValue == $maskColumnName)
				{
					$headingRow = $row;
					break;
				}
			}
			if($row > $highestRow) //Không tìm thấy
			{
				$msg = Text::sprintf('Sheet <b>%s</b>: không tìm thấy phách', $sheet->getTitle());
				$this->app->enqueueMessage($msg,'error');
				continue; //Bỏ qua sheet hiện thời
			}

			//3. Đọc số liệu vào mảng ['mask'=>'mark']
			$highestColumn = $sheet->getHighestDataColumn();
			$highestColumn = Coordinate::columnIndexFromString($highestColumn);
			$marks = [];
			$invalidData = false;
			for($col=1; $col<$highestColumn; $col++)
			{
				//a)Tìm cột chứa số phách
				$cellValue = $sheet->getCell([$col, $headingRow])->getValue();
				if($cellValue != $maskColumnName)
					continue; //Chuyển sang cột kế tiếp

				//b)Đọc phách ở cột tìm thấy (và điểm ở cột bên cạnh)
				$row = $headingRow + 2;
				while(true)
				{
					$mask = $sheet->getCell([$col, $row])->getValue();
					if(empty($mask))
						break;
					$mark = $sheet->getCell([$col+1, $row])->getValue();
					$mark = GeneralHelper::toFloat($mark, $examMarkPrecision);
					if (!GeneralHelper::isInteger($mask) || $mark === false)
					{
						$invalidData = true;
						break;
					}
					$marks[(int)$mask] = $mark;
					$row++;
				}
				if($invalidData) //Không đọc sheet này nữa
					break;
			}
			if($invalidData)
			{
				$msg = Text::sprintf('Sheet <b>%s</b>, dòng %d: dữ liệu không hợp lệ', $sheet->getTitle(), $row);
				$this->app->enqueueMessage($msg,'error');
				continue; //Bỏ qua sheet hiện thời
			}

			//4. Ghi điểm
			if($model->importMarkByMask($examId, $marks))
			{
				$msg = Text::sprintf('<b>%s</b>: Nhập điểm thành công cho %d thí sinh', $sheet->getTitle(), sizeof($marks));
				$this->app->enqueueMessage($msg, 'success');
			}
		}

		//Cập nhật trạng thái (các) môn thi
		$model = $this->createModel('exam');
		foreach ($examIds as $examId)
		{
			$exam = DatabaseHelper::getExamInfo($examId);
			$total = $exam->countTotal;
			$concluded = $exam->countConcluded;
			if($concluded == $total)
				$model->setExamStatus($examId, ExamHelper::EXAM_STATUS_MARK_FULL);
			else
				$model->setExamStatus($examId, ExamHelper::EXAM_STATUS_MARK_PARTIAL);
			$msg = Text::sprintf('Môn thi <b>%s</b>: %d/%d thí sinh đã có kết quả', $exam->name, $concluded, $total);
			$this->app->enqueueMessage($msg);
		}
	}
}
