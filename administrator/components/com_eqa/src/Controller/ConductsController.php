<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Collator;
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaAdminController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Helper\RatingHelper;
use Kma\Component\Eqa\Administrator\Model\ConductModel;
use Kma\Component\Eqa\Administrator\Model\ConductsModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
		$weightedMarks = [];
		$sum=0;
		$totalCredits=0;
		foreach ($exams as $exam)
		{
			//Skip pass/fail exams
			if($exam->isPassFail)
				continue;

			$subjectId = $exam->subjectId;
			$mark = $exam->moduleBase4Mark ? $exam->moduleBase4Mark*$exam->creditNumber : 0;
			if(array_key_exists($subjectId, $weightedMarks))
			{
				if($mark>$weightedMarks[$subjectId])
				{
					$sum -= $weightedMarks[$subjectId];
					$weightedMarks[$subjectId] = $mark;
					$sum+=$mark;
				}
			}
			else
			{
				$weightedMarks[$subjectId] = $mark;
				$sum+=$mark;
				$totalCredits += $exam->creditNumber;
			}
		}
		return round($sum/$totalCredits,2);
	}
	private function sortByName(array &$conducts):void
	{
		$collator = new Collator('vi_VN');
		$comparator = function($a, $b) use ($collator) {
			$result = $collator->compare($a->firstname, $b->firstname);
			if($result==0)
				$result = $collator->compare($a->lastname, $b->lastname);
			return $result;
		};
		usort($conducts, $comparator);
	}
	public function import(): void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Check permission
			if(!$this->app->getIdentity()->authorise('core.create', $this->option))
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

	public function exportClassesReport():void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Get filters. There must be academicyear_id and term fields.
			$input = $this->app->input;
			$filters = $input->post->get('filter', [], 'array');
			if(!is_numeric($filters['academicyear_id']) || !is_numeric($filters['term']))
				throw new Exception('Thiếu thông tin năm học hoặc/và học kỳ');
			$academicyearId = intval($filters['academicyear_id']);
			$term = intval($filters['term']);

			/**
			 * Load model and then get all the records for selected term
			 * @var ConductsModel $model
			 */
			$model = $this->getModel('Conducts');
			$conducts = $model->getListByTerm($academicyearId,$term);
			if (count($conducts)==0)
				throw new Exception('Không tìm thấy kết quả rèn luyện cho học kỳ và năm học đã chọn');

			//Group by class (group) code
			$groupedConducts = [];
			foreach ($conducts as $conduct)
			{
				$groupCode = $conduct->group;
				if(!isset($groupedConducts[$groupCode]))
					$groupedConducts[$groupCode]=[];
				$groupedConducts[$groupCode][] = $conduct;
			}

			//Create spreadsheet and remove the default worksheet
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			$academicyear = $conducts[0]->academicyear;
			$termCode = $conducts[0]->termCode;


			//Write each class report into a separate worksheet
			$groupCodes = array_keys($groupedConducts);
			sort($groupCodes);
			foreach ($groupCodes as $groupCode)
			{
				$groupConducts = $groupedConducts[$groupCode];
				$sheet = $spreadsheet->createSheet();
				$sheet->setTitle(IOHelper::sanitizeSheetTitle($groupCode));
				$title = 'Kết quả phân loại HVSV - Lớp ' . $groupCode;
				$studyYear = DatabaseHelper::getCourseStudyYear($groupConducts[0]->course, $academicyear);
				$this->sortByName($groupConducts);
				IOHelper::writeConductReport($sheet, $academicyear, $termCode, $title, $studyYear, $groupConducts);
			}

			//Let user download the spreadsheet
			$fileName = 'Phân loại HVSV từng lớp. '. $academicyear;
			if($termCode != DatetimeHelper::TERM_NONE)
				$fileName .= '. ' . DatetimeHelper::decodeTerm($termCode);
			$fileName .= '.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			jexit();
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=conducts',false));
		}
	}
	public function exportCoursesReport():void
	{
		try
		{
			//Check token
			$this->checkToken();

			//Get filters. There must be academicyear_id and term fields.
			$input = $this->app->input;
			$filters = $input->post->get('filter', [], 'array');
			if(!is_numeric($filters['academicyear_id']) || !is_numeric($filters['term']))
				throw new Exception('Thiếu thông tin năm học hoặc/và học kỳ');
			$academicyearId = intval($filters['academicyear_id']);
			$term = intval($filters['term']);

			/**
			 * Load model and then get all the records for selected term
			 * @var ConductsModel $model
			 */
			$model = $this->getModel('Conducts');
			$conducts = $model->getListByTerm($academicyearId,$term);
			if (count($conducts)==0)
				throw new Exception('Không tìm thấy kết quả rèn luyện cho học kỳ và năm học đã chọn');

			//Group by course code
			$groupedConducts = [];
			foreach ($conducts as $conduct)
			{
				$courseCode = $conduct->course;
				if(!isset($groupedConducts[$courseCode]))
					$groupedConducts[$courseCode]=[];
				$groupedConducts[$courseCode][] = $conduct;
			}

			//Create spreadsheet and remove the default worksheet
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			$academicyear = $conducts[0]->academicyear;
			$termCode = $conducts[0]->termCode;


			//Write each class report into a separate worksheet
			$courseCodes = array_keys($groupedConducts);
			sort($courseCodes);
			foreach ($courseCodes as $courseCode)
			{
				$courseConducts = $groupedConducts[$courseCode];
				$sheet = $spreadsheet->createSheet();
				$sheet->setTitle(IOHelper::sanitizeSheetTitle($courseCode));
				$title = 'Kết quả phân loại HVSV - Khóa ' . $courseCode;
				$studyYear = DatabaseHelper::getCourseStudyYear($courseCode, $academicyear);
				$this->sortByName($courseConducts);
				IOHelper::writeConductReport($sheet, $academicyear, $termCode, $title, $studyYear, $courseConducts);
			}

			//Let user download the spreadsheet
			$fileName = 'Phân loại HVSV từng khóa. '. $academicyear;
			if($termCode != DatetimeHelper::TERM_NONE)
				$fileName .= '. ' . DatetimeHelper::decodeTerm($termCode);
			$fileName .= '.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			jexit();
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(),'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=conducts',false));
		}
	}
}
