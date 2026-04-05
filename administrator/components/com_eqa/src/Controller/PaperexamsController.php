<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Model\ExamModel;
use Kma\Component\Eqa\Administrator\Model\PaperexamModel;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Library\Kma\Helper\IOHelper;
use Kma\Library\Kma\Helper\NumberHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PaperexamsController extends AdminController {
	public function uploadMarkByMask()
	{
		$fileFormField = 'excelfile';
		$examMarkPrecision = ConfigHelper::getExamMarkPrecision();

		//Check token
		$this->checkToken();

		//Check permissions
		if(!$this->app->getIdentity()->authorise('core.edit', $this->option))
		{
			$msg = Text::_('COM_EQA_MSG_UNAUTHORISED');
			$this->setMessage($msg,'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa',false));
			return;
		}

		//Redirect in any cases
		$this->setRedirect(Route::_('index.php?option=com_eqa', false));

		//Check input files
		$files = $this->input->files;
		$file = $files->get($fileFormField);
		if(empty($file['tmp_name'])){
			$this->setMessage(Text::_('COM_EQA_MSG_ERROR_NO_FILE_UPLOADED'), 'error');
			return;
		}

		//Try to open file
		$spreadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);

		/**
		 * Process the file
		 * @var PaperexamModel $paperExamModel
		 * @var ExamModel $examModel
		 */
		$examInfoPattern = '/^Môn thi: [\s\S]+ \(Mã môn thi: ([0-9]+)\)$/u';
		$maskColumnName = 'Số phách';
		$paperExamModel = $this->getModel();
		$examModel = $this->createModel('exam');
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
				$msg = sprintf('Sheet <b>%s</b>: không tìm thấy môn thi', $sheet->getTitle());
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
				$msg = sprintf('Sheet <b>%s</b>: không tìm thấy phách', $sheet->getTitle());
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
					$mark = NumberHelper::toFloat($mark, $examMarkPrecision);
					if (!NumberHelper::isInteger($mask) || $mark === false)
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
				$msg = sprintf('Sheet <b>%s</b>, dòng %d: dữ liệu không hợp lệ', $sheet->getTitle(), $row);
				$this->app->enqueueMessage($msg,'error');
				continue; //Bỏ qua sheet hiện thời
			}

			//4. Ghi điểm
			try
			{
				$paperExamModel->importMarkByMask($examId, $marks);
				$examModel->conclude($examId, false);
				$msg = sprintf('<b>%s</b>: Nhập điểm thành công cho %d thí sinh', $sheet->getTitle(), sizeof($marks));
				$this->app->enqueueMessage($msg, 'success');
			}
			catch(Exception $e)
			{
				$this->app->enqueueMessage($e->getMessage(), 'error');
			}
		}
	}
}
