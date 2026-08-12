<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Component\Eqa\Administrator\Enum\Action;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Library\Kma\DataObject\LogEntry;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class MmproductionsController extends AdminController
{
	public function import(){
		//Check token
		$this->checkToken();

		//Set redicrect in any case
		$this->setRedirect(Route::_('index.php?option=com_eqa&view=mmproductions',false));

		//Check permissions
		if(!$this->app->getIdentity()->authorise('core.create', $this->option))
		{
			$this->setMessage(Text::_('COM_EQA_MSG_UNAUTHORISED'),'error');
			$this->writeLog(new LogEntry(
				action: Action::IMPORT_MM_PRODUCTIONS,
				objectType: ObjectType::Mmproduction->value,
				isSuccess: false,
				errorMessage: Text::_('COM_EQA_MSG_UNAUTHORISED'),
			));
			return;
		}

		//Get data
		//1. Exam Id
		$examId = $this->input->getInt('exam_id');
		if(empty($examId))
		{
			$this->setMessage('Không xác định được môn thi', 'error');
			$this->writeLog(new LogEntry(
				action: Action::IMPORT_MM_PRODUCTIONS,
				objectType: ObjectType::Mmproduction->value,
				isSuccess: false,
				errorMessage: 'Không xác định được môn thi',
			));
			return;
		}

		//2. Lấy file
		$fileFormField = 'excelfile';
		$file = $this->input->files->get($fileFormField);
		if(empty($file['tmp_name'])){
			$this->setMessage('Không xác định được tập tin', 'error');
			$this->writeLog(new LogEntry(
				action: Action::IMPORT_MM_PRODUCTIONS,
				objectType: ObjectType::Mmproduction->value,
				isSuccess: false,
				objectId: $examId,
				errorMessage: 'Không xác định được tập tin',
			));
			return;
		}

		//3. Đọc dữ liệu từ file
		//[] = ['examiner', 'role', 'quantity']
		$spreadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);
		if(empty($spreadsheet))
		{
			$this->setMessage('Lỗi đọc file. Hãy kiểm tra lại định dạng file','error');
			$this->writeLog(new LogEntry(
				action: Action::IMPORT_MM_PRODUCTIONS,
				objectType: ObjectType::Mmproduction->value,
				isSuccess: false,
				objectId: $examId,
				errorMessage: 'Loi doc file. Hay kiem tra lai dinh dang file',
			));
			return;
		}

		$sheet = $spreadsheet->getSheet(0);
		$highestRow = $sheet->getHighestDataRow();
		$highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
		$row=1;

		//Tìm dòng tiêu đề
		while($row<=$highestRow && empty($sheet->getCell([1, $row])->getValue()))
			$row++;
		if($row == $highestRow)
		{
			$this->setMessage('File rỗng','error');
			$this->writeLog(new LogEntry(
				action: Action::IMPORT_MM_PRODUCTIONS,
				objectType: ObjectType::Mmproduction->value,
				isSuccess: false,
				objectId: $examId,
				errorMessage: 'File rong',
			));
			return;
		}

		//Tìm cột "Người chấm 1"
		$col=1;
		while($col<=$highestColumn && $sheet->getCell([$col, $row])->getValue()!='Người chấm 1')
			$col++;
		if($col == $highestColumn)
		{
			$this->setMessage('Không tìm thấy thông tin người chấm','error');
			$this->writeLog(new LogEntry(
				action: Action::IMPORT_MM_PRODUCTIONS,
				objectType: ObjectType::Mmproduction->value,
				isSuccess: false,
				objectId: $examId,
				errorMessage: 'Khong tim thay thong tin nguoi cham',
			));
			return;
		}

		//Đọc dữ liệu từ file
		$primaryExaminerColumn = $col;
		$secondaryExaminerColumn = $col+1;
		$primaryExaminers=[];
		$secondaryExaminers=[];
		while ($row < $highestRow)
		{
			$row++;
			$name1 = $sheet->getCell([$primaryExaminerColumn, $row])->getValue();
			$name2 = $sheet->getCell([$secondaryExaminerColumn, $row])->getValue();
			if(empty($name1) && empty($name2))
				break;
			if(empty($name1) || empty($name2))
			{
				$msg = sprintf('Dòng %d: không có đủ 2 người chấm', $row);
				$this->$this->setMessage($msg, 'error');
			}

			//Đếm sản lượng chấm 1
			if(array_key_exists($name1, $primaryExaminers))
				$primaryExaminers[$name1]++;
			else
				$primaryExaminers[$name1]=1;

			//Đếm sản lượng chấm 2
			if(array_key_exists($name2, $secondaryExaminers))
				$secondaryExaminers[$name2]++;
			else
				$secondaryExaminers[$name2]=1;
		}

		//4. Ghi nhận
		$model = $this->getModel();
		$model->importMmp($examId, $primaryExaminers, 1);
		$model->importMmp($examId, $secondaryExaminers, 2);

		$this->writeLog(new LogEntry(
			action: Action::IMPORT_MM_PRODUCTIONS,
			objectType: ObjectType::Mmproduction->value,
			isSuccess: true,
			objectId: $examId,
			extraData: [
				'primary_examiner_count'   => count($primaryExaminers),
				'secondary_examiner_count' => count($secondaryExaminers),
			],
		));
	}
}
