<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;

class SubjectsController extends EqaAdminController{
	public function import(): void
	{
		$redirectUrl = JRoute::_('index.php?option=com_eqa&view=subjects', false);
		$this->setRedirect($redirectUrl);
		try{
			//1. Check token
			$this->checkToken();

			//2. Check permissions
			if(!$this->app->getIdentity()->authorise('core.create', $this->option))
				throw new Exception('Bạn không có quyền nhập môn học');

			//3. Retrieve data from request
			$updateExisting = $this->app->input->getBool('overwrite', false);
			$file = $this->app->input->files->get('excelfile');
			if(empty($file) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
				throw new Exception('Không tìm thấy file tải lên');

			//4. Load the spreadsheet and normalize its data
			$speadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);
			$worksheet = $speadsheet->getSheet(0);
			$sheetData = $worksheet->toArray('');               //Coi các ô trống là empty string
			$lastRowIndex = count($sheetData)-1;
			$data = [];
			$unitCodePattern = '/^[A-Z]+$/';
			$subjectCodePattern = '/^[A-Z0-9.]+$/';
			for($r=1; $r<=$lastRowIndex; ++$r)
			{
				$row = $sheetData[$r];
				$colIndex=0;

				$unitCode = trim($row[$colIndex++]);
				if(!preg_match($unitCodePattern,$unitCode))
				{
					$msg = Text::sprintf('Dòng %d: Mã đơn vị "%s" không hợp lệ', $r+1, htmlspecialchars($unitCode));
					throw new Exception($msg);
				}

				$subjectCode = trim($row[$colIndex++]);
				if(!preg_match($subjectCodePattern,$subjectCode))
				{
					$msg = Text::sprintf('Dòng %d: Mã môn học "%s" không hợp lệ', $r+1, htmlspecialchars($subjectCode));
					throw new Exception($msg);
				}

				$subjectName = trim(strip_tags($row[$colIndex++]));
				if(empty($subjectName))
				{
					$msg = Text::sprintf('Dòng %d: Tên môn học không hợp lệ', $r+1);
					throw new Exception($msg);
				}

				$degreeAbbr = trim($row[$colIndex++]);
				$degree = match ($degreeAbbr) {
					'ĐH' => 7,
					'CH' => 8,
					'TS' => 9,
					default => throw new Exception(Text::sprintf('Dòng %d: Bậc học "%s" không hợp lệ', $r+1, htmlspecialchars($degreeAbbr))),
				};

				$creditNumber = GeneralHelper::toFloat(trim($row[$colIndex++]));
				if($creditNumber===false)
				{
					$msg = Text::sprintf('Dòng %d: Số tín chỉ "%s" không hợp lệ', $r+1, htmlspecialchars($creditNumber));
					throw new Exception($msg);
				}

				$testtypeText = trim($row[$colIndex++]);
				$testType = ExamHelper::getTestTypeCode($testtypeText);
				if(is_null($testType)){
					$msg = Text::sprintf('Dòng %d: Hình thức thi "%s" không hợp lệ', $r+1, htmlspecialchars($testtypeText));
					throw new Exception($msg);
				}

				$testBankYear = trim($row[$colIndex++]);
				if(empty($testBankYear))
					$testBankYear = null;
				else if(GeneralHelper::isInteger($testBankYear))
					$testBankYear = (int)$testBankYear;
				else{
					$msg = Text::sprintf('Dòng %d: Năm đề thi "%s" không hợp lệ', $r+1, htmlspecialchars($testBankYear));
					throw new Exception($msg);
				}

				$data[] = [
					'row_index'=>$r+1,
					'unit_code'=>$unitCode,
					'subject_code'=>$subjectCode,
					'subject_name'=>$subjectName,
					'degree'=>$degree,
					'credit_hours'=>$creditNumber,
					'final_test_type'=>$testType,
					'test_bank_year'=>$testBankYear,
				];
			}

			//5. Save data into database
			if(empty($data))
				throw new Exception('Không có dữ liệu để nhập');
			$username = $this->app->getIdentity()->username;
			$time = DatetimeHelper::getCurrentHanoiDatetime();
			$model = $this->getModel('Subjects');
			$model->import($data, $updateExisting, $username, $time);
		}
		catch(Exception $e){
			$this->setMessage($e->getMessage(), 'error');
		}
	}
}
