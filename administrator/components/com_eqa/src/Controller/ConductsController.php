<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Helper\RatingHelper;
use Kma\Component\Eqa\Administrator\Model\ConductModel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use stdClass;

class ConductsController extends EqaAdminController {
	private function importSheet(Worksheet $sheet, int $academicyearId, int $term): void
	{
		$data = $sheet->toArray();
		$firstRow = 0;

		//Tìm dòng đầu tiên chứa dữ liệu (STT ở cột A bằng 1)
		while(isset($data[$firstRow]) && $data[$firstRow][0]!=1)
			++$firstRow; //Skip empty rows at top
		if($data[$firstRow][0]!=1)
		{
			$msg = sprintf('Trên sheet <b>%s</b> không có dữ liệu hợp lệ', htmlspecialchars($sheet->getTitle()));
			throw new Exception($msg);
		}

		/**
		 * Đọc và nhập dữ liệu
		 * @var ConductModel $model
		 */
		$model = $this->getModel();
		$r = $firstRow;
		while (is_numeric($data[$r][0]))
		{
			$row = $data[$r];
			$item = new stdClass();
			$item->learnerCode = strtoupper(trim($row[1]));   //Cột B: Mã HVSV
			$item->excusedAbsenceCount = intval($row[3]);     //Cột D: Số buổi nghỉ có phép
			$item->unexcusedAbsenceCount = intval($row[4]);   //Cột E: Số buổi nghỉ không phép
			$item->awardCount = intval($row[8]);              //Cột I: số lần khen thưởng
			$item->disciplinaryCount = intval($row[9]);       //Cột K: số lần kỷ luật
			$item->conductScore = intval($row[12]);           //Cột M: Điểm rèn luyện
			$item->conductRating = RatingHelper::rateConductScore($item->conductScore);
			$item->note=$row[14];                             //Cột O: Ghi chú
			$model->importItem($academicyearId, $term,$item,true);
			$r++;
		}
	}
	private function countResitExams(array $exams):int
	{
		$count = 0;
		foreach ($exams as $exam)
			if($exam->conclusion == ExamHelper::CONCLUSION_FAILED)
				++$count;
		return $count;
	}
	private function countRetakeSubjects(array $exams):int
	{
		$count = 0;
		foreach ($exams as $exam)
			if($exam->conclusion == ExamHelper::CONCLUSION_FAILED_EXPIRED)
				++$count;
		return $count;
	}
	private function calculateTermMark(array $exams):float
	{
		//For one subject pick only one exam with highest mark,
		//ignoring pass/fail exams
		$marks = [];
		$sum=0;
		foreach ($exams as $exam)
		{
			//Skip pass/fail exams
			if($exam->isPassFail)
				continue;

			$subjectId = $exam->subjectId;
			$mark = $exam->moduleBase4Mark?:0;
			if(array_key_exists($subjectId, $marks))
			{
				if($mark>$marks[$subjectId])
				{
					$sum -= $marks[$subjectId];
					$marks[$subjectId] = $mark;
					$sum+=$mark;
				}
			}
			else
			{
				$marks[$subjectId] = $mark;
				$sum+=$mark;
			}
		}
		return round($sum/count($marks),2);
	}
	public function import(): void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Check permission
			if(!$this->app->getIdentity()->authorise('core.create',$this->option))
				throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));

			//Try get first portion of form data
			$input = $this->app->input;
			$academicyearId = $input->getInt('academicyear_id',null);

			//PHASE 1: Show form
			if(is_null($academicyearId)) //Show form to select academic year
			{
				$redirectUrl = Route::_('index.php?option=com_eqa&view=conducts&layout=import', false);
				$this->setRedirect($redirectUrl);
				return;
			}

			//PHASE 2: Import conduct records from Excel file
			//Continue to get the rest of form data
			$term = $input->getString('term',null);
			if(is_null($term))
				throw new Exception('Không xác định được học kỳ');

			$files = $input->files->get('files');
			if(empty($files[0]['tmp_name']))
				throw new Exception('Không tìm thấy tệp tin');

			foreach ($files as $file)
			{
				$spreadsheet = IOHelper::loadSpreadsheet($file['tmp_name']);
				foreach ($spreadsheet->getAllSheets() as $sheet)
				{
					$this->importSheet($sheet,$academicyearId,$term);
				}
			}

			//Set message and redirect back to list view
			$this->setMessage('Nhập thành công');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=conducts', false));
			return;
		}
		catch(Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=conducts', false));
			return;
		}
	}

	public function caclculateAcacdemicResults():void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Check permission
			if (!$this->app->getIdentity()->authorise('core.edit', $this->option))
				throw new Exception('Bạn không có quyền thực hiện tác vụ này');

			//Get IDs of selection items
			$input = $this->app->input;
			$ids = $input->post->get('cid',[],'array');
			$ids = array_filter($ids,'intval'); //Filter out non-integer values
			$ids = array_unique($ids); //Remove duplicate entries
			if(count($ids)==0) throw new Exception('Chưa chọn mục nào để tính điểm rèn luyện');

			/** @var ConductModel $model */
			$model = $this->getModel();
			foreach ($ids as $id)
			{
				$item = $model->getItem($id);
				$learnerId = $item->learner_id;
				$academicyearId = $item->academicyear_id;
				$term = $item->term;
				$learnerExams = $model->getLearnerExams($learnerId, $academicyearId, $term);
				$countResits = $this->countResitExams($learnerExams);
				$countRetakes = $this->countRetakeSubjects($learnerExams);
				$termMark = $this->calculateTermMark($learnerExams);
				$termRating = RatingHelper::rateAcademicScore($termMark);
				$model->updateAcademicResults($id,$countResits,$countRetakes,$termMark,$termRating);
			}

			//Set message and redirect back to list view
			$this->setMessage('Tính điểm học tập thành công');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=conducts',false));
			return;
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=conducts',false));
			return;
		}
	}
}
