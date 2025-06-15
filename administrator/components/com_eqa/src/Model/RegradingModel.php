<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Interface\Regradingrequest;
use stdClass;

defined('_JEXEC') or die();

class RegradingModel extends EqaAdminModel
{
	/**
	 * Lấy thông tin về tất cả các yêu cầu phúc khảo trong kỳ thi $examseasonId
	 * để phục vụ cho việc thu tiền, phê duyệt các yêu cầu phúc khảo
	 * @param int $examseasonId
	 * @param boolean $returnArrayOfStdClass Nếu true thì sẽ trả về mảng các object StdClass
	 *                                      ngược lại thì trả về mảng các object
	 * @return array Mảng các object chứa thông tin về yêu cầu phúc khảo
	 * @since       version
	 */
	public function getRegradingRequests(int $examseasonId, bool $returnArrayOfStdClass=false): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//Get information about all the the exams of the given examseason
		$columns = $db->quoteName(
			array('a.id', 'a.name',   'b.credits'),
			array('id',   'name',      'credits')
		);
		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_subjects AS b', 'b.id=a.subject_id')
			->where('a.examseason_id=' . $examseasonId);
		$db->setQuery($query);
		$exams = $db->loadObjectList('id');

		//Get information about all the regrading requests in the given examseason
		$examIds = array_keys($exams);
		$columns = [
			$db->quoteName('a.exam_id')      . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('a.learner_id')   . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('c.code')         . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('c.lastname')     . ' AS ' . $db->quoteName('learnerLastname'),
			$db->quoteName('c.firstname')    . ' AS ' . $db->quoteName('learnerFirstname'),
			$db->quoteName('e.code')    .      ' AS ' . $db->quoteName('groupCode'),
			$db->quoteName('f.code')    .      ' AS ' . $db->quoteName('courseCode'),
			$db->quoteName('a.status')       . ' AS ' . $db->quoteName('statusCode'),
			$db->quoteName('d.mark_orig')    . ' AS ' . $db->quotename('originalMark'),
		];

		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->leftJoin('#__eqa_exam_learner AS d', 'd.exam_id=a.exam_id AND d.learner_id=a.learner_id')
			->leftJoin('#__eqa_groups AS e', 'e.id = c.group_id')
			->leftJoin('#__eqa_courses AS f', 'f.id = e.course_id')
			->where('a.exam_id IN (' . implode(',', $examIds) . ')');
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if(empty($items)) return []; //Không có gì để trả

		//Lấy bổ sung thông tin 'examName' và 'credits cho mỗi item
		foreach ($items as $item){
			$item->examName = $exams[$item->examId]->name;
			$item->credits = $exams[$item->examId]->credits;
		}

		if(!$returnArrayOfStdClass) return $items; //Trả về dạng object

		//Trả về dạng StdClass
		$stdClassItems = [];
		foreach ($items as $item)
		{
			$stdClassItem = new Regradingrequest($item);
			$stdClassItems[] = $stdClassItem;
		}
		return $stdClassItems;
	}
	public function getPaperExams(int $examseasonId, bool $onlyAccepted = true): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.exam_id')      . ' AS examId',
			$db->quoteName('b.name')         . ' AS examName',
			$db->quoteName('c.code')         . ' AS learnerCode',
			$db->quoteName('c.lastname')     . ' AS learnerLastname',
			$db->quoteName('c.firstname')    . ' AS learnerFirstname',
			$db->quoteName('a.status')       . ' AS statusCode',
			$db->quoteName('d.code')         . ' AS code',           // Số báo danh
			$db->quoteName('e.mask')         . ' AS mask',           // Số phách
			$db->quoteName('d.mark_orig')    . ' AS originalMark',
			$db->quoteName('f.number')       . ' AS packageNumber',
			$db->quoteName('f.examiner1_id') . ' AS oldExaminer1Id',
			$db->quoteName('f.examiner2_id') . ' AS oldExaminer2Id',
			$db->quoteName('a.examiner1_id') . ' AS examiner1Id',
			$db->quoteName('a.examiner2_id') . ' AS examiner2Id'
		];

		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id = a.exam_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->leftJoin('#__eqa_exam_learner AS d', 'd.exam_id=a.exam_id AND d.learner_id=a.learner_id')
			->leftJoin('#__eqa_papers AS e', 'e.exam_id=a.exam_id AND e.learner_id=a.learner_id')
			->leftJoin('#__eqa_packages AS f', 'f.id=e.package_id')
			->where('b.examseason_id='.$examseasonId)
			->where('b.testtype='.ExamHelper::TEST_TYPE_PAPER);
		if($onlyAccepted)
			$query->where('a.status=' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED);
	//	$query->where($db->quoteName('a.status') . '=' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED);
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if(empty($items)) return []; //Không có gì để trả

		//Gom nhóm theo $examId
		$papers = [];
		foreach ($items as $item) {
			if(isset($papers[$item->examId])) { //Nếu đã tồn tại thì gán vào
				$papers[$item->examId][] = $item;
			} else { //Ngược lại thì tạo mới
				$papers[$item->examId] = [$item];
			}
		}
		return $papers;
	}
	public function getHybridRegradings(int $examseasonId, bool $onlyAccepted = true): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.exam_id')      . ' AS examId',
			$db->quoteName('b.name')         . ' AS examName',
			$db->quoteName('c.code')         . ' AS learnerCode',
			$db->quoteName('c.lastname')     . ' AS learnerLastname',
			$db->quoteName('c.firstname')    . ' AS learnerFirstname',
			$db->quoteName('a.status')       . ' AS statusCode',
			$db->quoteName('d.code')         . ' AS code',           // Số báo danh
			$db->quoteName('d.mark_orig')    . ' AS originalMark',
			$db->quoteName('a.examiner1_id') . ' AS examiner1Id',
			$db->quoteName('a.examiner2_id') . ' AS examiner2Id'
		];

		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id = a.exam_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->leftJoin('#__eqa_exam_learner AS d', '(d.exam_id=a.exam_id AND d.learner_id=a.learner_id)')
			->where('b.examseason_id='.$examseasonId)
			->where('b.testtype='.ExamHelper::TEST_TYPE_MACHINE_HYBRID);
		if($onlyAccepted)
			$query->where('a.status=' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED);
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if(empty($items)) return []; //Không có gì để trả

		//Gom nhóm theo $examId
		$works = [];
		foreach ($items as $item) {
			if(isset($works[$item->examId])) { //Nếu đã tồn tại thì gán vào
				$works[$item->examId][] = $item;
			} else { //Ngược lại thì tạo mới
				$works[$item->examId] = [$item];
			}
		}
		return $works;
	}


	/**
	 * Lây thông tin về các môn thi liên quan đến phúc khảo trong kỳ thi $examseasonId
	 * Hàm này được sử dụng trong view Regradingemployees để phân công chấm phúc khảo
	 *
	 * @param   int  $examseasonId
	 * @return array Mỗi item trong mảng là một object chứa các trường sau:
	 *                  - id: Id của môn thi
	 *                  - name: Tên của môn thi
	 *                  - count: Số lượng yêu cầu phúc khảo của môn thi đó
	 *                  - examiner1Id: Id của cán bộ chấm thi thứ nhất
	 *                  - examiner2Id: Id của cán bộ chấm thi thứ hai
	 *                  - examiner1Completed: True nếu đã hoàn tất việc phân công CBChT 1
	 *                  - examiner2Completed: True nếu đã hoàn tất việc phân công CBChT 2
	 * @since version 1.1.9
	 */
	public function getExams(int $examseasonId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//Bước 1: Lấy danh sách tất cả các yêu cầu phúc khảo thuộc kỳ thi $examseasonId
		$columns = $db->quoteName(
			array('a.exam_id', 'b.name',   'a.learner_id', 'a.examiner1_id', 'a.examiner2_id'),
			array('examId',    'examName', 'learnerId',    'examiner1Id',      'examiner2Id')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id = a.exam_id')
			->where([
				'b.examseason_id=' . $examseasonId,
				'a.status<>' . ExamHelper::EXAM_PPAA_STATUS_INIT,
				'a.status<>' . ExamHelper::EXAM_PPAA_STATUS_REJECTED
			]);
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if(empty($items))
			return [];

		//Bước 2: Thống kê thông tin theo môn thi và số lượng yêu cầu của từng môn thi
		$exams = [];
		foreach ($items as $item) {
			if(!isset($exams[$item->examId]))
			{
				//Nếu chưa có dữ liệu thì tạo mới
				$exam = new stdClass();
				$exam->id = $item->examId;
				$exam->name = $item->examName;
				$exam->count=1;
				$exam->examiner1Id = $item->examiner1Id;
				$exam->examiner2Id = $item->examiner2Id;
				if($item->examiner1Id)
					$exam->examiner1Completed = true;
				else
					$exam->examiner1Completed = false;
				if($item->examiner2Id)
					$exam->examiner2Completed = true;
				else
					$exam->examiner2Completed = false;
				$exams[$item->examId] = $exam;
			}
			else
			{
				//Có rồi thì tăng số lượng lên 1
				$exams[$item->examId]->count++;

				//Kiểm tra xem đã đủ thông tin về CBChT hay chưa
				if(!$item->examiner1Id)
					$exams[$item->examId]->examiner1Completed = false;
				if(!$item->examiner2Id)
					$exams[$item->examId]->examiner2Completed = false;
			}
		}

		//Bước 3: Trả kết quả
		return array_values($exams);
	}

	/**
	 * Lưu lại thông tin cán bộ chấm thi cho từng môn thi trong kỳ thi
	 *
	 * @param   int    $examseasonId
	 * @param   array  $data  Là một associative array với key là id của môn thi và value là mảng gồm 2 phần tử:
	 *                        - examiner1_id: Id của cán bộ chấm thi thứ nhất
	 *                        - examiner2_id: Id của cán bộ chấm thi thứ hai
	 *
	 * @throws Exception
	 * @since 1.1.10
	 */
	public function saveExaminers(int $examseasonId, array $data): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Check if any mark has been made. If yes then do not allow to change examiner
		//a) Get exam ids of the given examseason
		$db->setQuery('SELECT id FROM #__eqa_exams WHERE examseason_id='.$examseasonId);
		$examIds = $db->loadColumn();

		//b) Try to get a record where result is not null
		$query = $db->getQuery(true)
			->select('*')
			->from('#__eqa_regradings')
			->where('exam_id IN (' . implode(',', $examIds) . ')')
			->where('result IS NOT NULL')
			->setLimit(1);
		$db->setQuery($query);
		if((int)$db->loadResult() > 0)
			throw new Exception("Không thể thay đổi cán bộ chấm thi vì đã có (một phần) kết quả phúc khảo");

		//2. Save the data
		foreach ($data as $examId => $examiners)
		{
			if(empty($examiners['examiner1_id']) && empty($examiners['examiner2_id']))
				continue;

			$query = $db->getQuery(true)
				->update('#__eqa_regradings');
			if(!empty($examiners['examiner1_id']))
				$query->set('examiner1_id=' . (int)$examiners['examiner1_id']);
			if(!empty($examiners['examiner2_id']))
				$query->set('examiner2_id=' . (int)$examiners['examiner2_id']);
			$query->where([
				'exam_id=' . $examId,
				'status<>' . ExamHelper::EXAM_PPAA_STATUS_INIT,
				'status<>' . ExamHelper::EXAM_PPAA_STATUS_REJECTED
			]);
			$db->setQuery($query)->execute();
		}
	}

	/**
	 * Kiểm tra xem đã phân công cán bộ chấm phúc khảo cho tất cả các môn thi trong kỳ thi chưa.
	 * Nếu đã phân công cho tất cả các môn thi thì trả về true, ngược lại trả về false
	 *
	 * @param int $examseasonId
	 * @return bool
	 * @since 1.1.10
	 */
	public function examinersAssigned(int $examseasonId): bool
	{
		/**
		 * Cách thực hiện:
		 * Trong bảng #__eqa_regradings, tìm một bản ghi mà trường examiner1_id hoặc examiner2_id rỗng
		 * và status khác INIT và REJECTED. Nếu tìm thấy thì trả về false, ngược lại trả về true
		 */
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('*')
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->where('b.examseason_id=' . $examseasonId)
			->where('a.status<>'.ExamHelper::EXAM_PPAA_STATUS_INIT)
			->where('a.status<>'.ExamHelper::EXAM_PPAA_STATUS_REJECTED)
			->where('(a.examiner1_id IS NULL OR a.examiner2_id IS NULL)')
			->setLimit(1);
		$db->setQuery($query);
		$result = $db->loadObject();
		if($result === null)
			return true;
		return false;
	}
}
