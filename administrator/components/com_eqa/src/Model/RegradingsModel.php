<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Component\Eqa\Administrator\Interface\PpaaEntryInfo;
use Kma\Component\Eqa\Administrator\Interface\Regradingrequest;
use stdClass;

defined('_JEXEC') or die();

class RegradingsModel extends EqaListModel
{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields']=array('a.id');
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'a.id', $direction = 'DESC')
	{
		parent::populateState($ordering, $direction);
	}

	public function canViewList(): bool
	{
		//1. Check if the user has manage permission on this component
		$acceptedPermissions = ['core.manage', 'eqa.supervise'];
		if(GeneralHelper::checkPermissions($acceptedPermissions))
			return true;

		//2. Or if he/she is the selected learner that views his/her own information
		//a. There must be a learner ID
		$selectedLearnerId = $this->getState('filter.learner_id');
		if(empty($selectedLearnerId))
			return false;

		//b. And the corresponding learner code must exist...
		$db = DatabaseHelper::getDatabaseDriver();
		$db->setQuery('SELECT `code` FROM #__eqa_learners WHERE id='.$selectedLearnerId);
		$learnerCode = $db->loadResult();
		if(empty($learnerCode))
			return false;

		//c. ... and match with signed-in user's learner code
		$signedInLearnerCode = GeneralHelper::getSignedInLearnerCode();
		if (empty($signedInLearnerCode) || ($learnerCode != $signedInLearnerCode))
			return false;
		return true;
	}
	protected function initListQuery(): DatabaseQuery
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.id')           . ' AS ' . $db->quoteName('id'),
			$db->quoteName('a.exam_id')      . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('d.name')         . ' AS ' . $db->quoteName('examName'),
			$db->quoteName('a.learner_id')   . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('b.code')         . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('b.lastname')     . ' AS ' . $db->quoteName('learnerLastname'),
			$db->quoteName('b.firstname')    . ' AS ' . $db->quoteName('learnerFirstname'),
			$db->quoteName('a.status')       . ' AS ' . $db->quoteName('statusCode'),
			$db->quoteName('c.mark_orig')    . ' AS ' . $db->quotename('origMark'),
			$db->quoteName('a.handled_by')   . ' AS ' . $db->quotename('handlerId'),
			$db->quoteName('a.examiner1_id') . ' AS ' . $db->quotename('examiner1Id'),
			$db->quoteName('a.examiner2_id') . ' AS ' . $db->quotename('examiner2Id'),
			$db->quoteName('a.result')       . ' AS ' . $db->quotename('ppaaMark'),
			$db->quoteName('a.description')  . ' AS ' . $db->quotename('description')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id = a.learner_id')
			->leftJoin('#__eqa_exam_learner AS c', 'c.exam_id=a.exam_id AND c.learner_id=a.learner_id')
			->leftJoin('#__eqa_exams AS d', 'd.id=a.exam_id')
			->leftJoin('#__eqa_classes AS e', 'e.id=c.class_id')
			->leftJoin('#__eqa_class_learner AS f', 'f.class_id=e.id AND f.learner_id=b.id');
		return $query;
	}
	public function getListQuery()
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $this->initListQuery();

		//Trong trường hợp model được gọi bởi View mà cần giới hạn kết quả cho một thí sinh cụ thể
		//(ở frontend có view như thế dành riêng cho từng thí sinh),
		//Thì View sẽ set giá trị cấu hình này để giới hạn việc truy vấn các bản ghi của một thí sinh cụ thể.
		$learnerId = $this->getState('filter.learner_id');
		if(is_int($learnerId))
			$query->where('a.learner_id=' . $learnerId);

		//Filtering
		$examseasonId = $this->getState('filter.examseason_id');
		if(is_numeric($examseasonId))
		{
			if($examseasonId==0)
				$query->where('d.examseason_id = ' . DatabaseHelper::getDefaultExamseason()->id);
			else
				$query->where('d.examseason_id = '.(int)$examseasonId);
		}


		$status = $this->getState('filter.status');
		if(is_numeric($status))
			$query->where('a.status=' . (int)$status);

		//Ordering
		$orderingCol = $query->db->escape($this->getState('list.ordering','a.id'));
		$orderingDir = $query->db->escape($this->getState('list.direction','desc'));
		$query->order($db->quoteName($orderingCol).' '.$orderingDir);

		//Additional ordering
		$query->order('b.firstname ASC');
		$query->order('b.lastname ASC');

		return $query;
	}
	public function getAllItems(bool $onlyAccepted=false) : array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Limit results to some specific examseasons
		$examseasonId = $this->getState('filter.examseason_id');
		if(!is_numeric($examseasonId))
			throw new Exception("Bạn cần chọn kỳ thi để tải danh sách");

		//2. Build the query
		$query = $this->initListQuery();
		if($examseasonId==0)
			$examseasonId = DatabaseHelper::getDefaultExamseason()->id;
		$query->where('d.examseason_id='.$examseasonId);
		if ($onlyAccepted)
			$query->where('a.status='.ExamHelper::EXAM_PPAA_STATUS_ACCEPTED);

		//3. Execute
		$db->setQuery($query);
		return $db->loadObjectList();
	}

	public function getSelectedExamseasonId(): int|null
	{
		$filter = $this->getState('filter.examseason_id');
		if(is_numeric($filter))
			return $filter;
		return null;
	}
	public function getFilteredExamseasonId(): ?int
	{
		$filter = $this->getState('filter.examseason_id');
		if(is_numeric($filter))
			return $filter;
		return null;
	}
	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.examseason_id');
		$id .= ':' . $this->getState('filter.learner_id');
		$id .= ':' . $this->getState('filter.status');
		return parent::getStoreId($id);
	}

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
	public function getPaperRegradings(int $examseasonId, bool $onlyAccepted = true): array
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
	public function getRegradingExams(int $examseasonId): array
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

	/**
	 * @param   int          $examId
	 * @param   int          $learnerId
	 * @param   int          $classId
	 * @param   float        $pam
	 * @param   int          $attempt
	 * @param   float        $addValue
	 * @param   int          $anomaly
	 * @param   float        $oldMark
	 * @param   int          $oldConclusion
	 * @param   float        $newMark
	 * @param   string|null  $changeDescription
	 *
	 *
	 * @throws Exception
	 * @since 1.1.10
	 */
	protected function applyRegradingResult(int $examId, int $learnerId, int $classId, float $pam, int $attempt, float $addValue, int $anomaly, float $oldMark, int $oldConclusion, float $newMark, ?string $changeDescription)
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//2. Ghi điểm phúc khảo vào bảng #__eqa_regradings
		$query = $db->getQuery(true)
			->update('#__eqa_regradings')
			->set($db->quoteName('result') . '=' . $newMark)
			->set($db->quoteName('status') . '=' . ExamHelper::EXAM_PPAA_STATUS_DONE)
			->where('exam_id=' . $examId)
			->where('learner_id=' . $learnerId);
		if(!empty($changeDescription))
			$query->set($db->quoteName('description') . '=' . $db->quote($changeDescription));
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception('Lỗi khi lưu điểm phúc khảo');

		//3. Nếu sau phúc khảo mà điểm không đổi thì chỉ ghi điểm phúc khảo vào bảng #__eqa_exam_learner
		if($oldMark == $newMark)
		{
			$db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set($db->quoteName('ppaa') . '=' . ExamHelper::EXAM_PPAA_REVIEW)
				->set($db->quoteName('mark_ppaa') . '=' . $newMark)
				->where('exam_id=' . $examId)
				->where('learner_id=' . $learnerId);
			$db->setQuery($query)->execute();
			return;
		}

		//4. Nếu điểm sau phúc khảo có thay đổi thì cần tính toán lại các điểm có liên quan
		//4.1. Tính toán lại điểm thi, điểm học phần và kết luận
		$admissionYear = $attempt>1 ? DatabaseHelper::getLearnerAdmissionYear($learnerId) : 0;
		$finalMark = ExamHelper::calculateFinalMark($newMark, $anomaly, $attempt, $addValue, $admissionYear);
		$moduleMark = ExamHelper::calculateModuleMark($learnerId, $pam, $finalMark, $attempt, $admissionYear);
		$conclusion = ExamHelper::conclude($moduleMark, $finalMark, $anomaly, $attempt);
		$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);

		//4.2. Cập nhật điểm phúc khảo vào bảng #__eqa_exam_learner
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set($db->quoteName('ppaa') . '=' . ExamHelper::EXAM_PPAA_REVIEW)
			->set($db->quoteName('mark_ppaa') . '=' . $newMark)
			->set($db->quoteName('mark_final') . '=' . $finalMark)
			->set($db->quoteName('module_mark') . '=' . $moduleMark)
			->set($db->quoteName('module_grade') . '=' . $db->quote($moduleGrade))
			->set($db->quoteName('conclusion') . '=' . $conclusion)
			->where('exam_id=' . $examId)
			->where('learner_id=' . $learnerId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception('Lỗi khi cập nhật điểm môn thi');

		//5. Nếu sau phúc khảo 'conclusion' có thay đổi thì cần cập nhận thông tin vào bảng #__eqa_class_learner
		//   Mà cụ thể là thông tin về quyền thi tiếp. Các thông tin khác không thể thay đổi vì phúc khảo.
		if($oldConclusion == $conclusion)
			return;

		if($conclusion == ExamHelper::CONCLUSION_PASSED || $conclusion == ExamHelper::CONCLUSION_FAILED_EXPIRED)
			$expired=1;
		else
			$expired=0;
		$query = $db->getQuery(true)
			->update('#__eqa_class_learner')
			->set($db->quoteName('expired') . '=' . $expired)
			->where('class_id=' . $classId)
			->where('learner_id=' . $learnerId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception('Lỗi khi cập nhật thông tin vào lớp học phần');
	}

	/**
	 * Ghi kết quả chấm phúc khảo theo số phách
	 *
	 * @param int $examseasonId
	 * @param array $data Là một array, mỗi phần tử có kiểu PpaaEntry
	 *
	 * @throws Exception
	 * @since 1.1.10
	 */
	public function savePaperRegradingResult(int $examId, array $regradingData)
	{
		/**
		 * Cách thực hiện:
		 * 1. Lấy thông tin cần thiết về từng thí sinh
		 * 2. Cập nhật điểm phúc khảo, lý do thay đổi điểm (nếu có) vào bảng #__eqa_regradings
		 * 3. Nếu điểm không thay đổi thì chỉ cập nhật điểm phúc khảo vào bảng #__eqa_exam_learner
		 * 4. Nếu điểm thay đổi thì cần tính toán lại các điểm có liên quan như điểm thi, điểm học phần và kết luận
		 * 5. Nếu sau phúc khảo 'conclusion' có thay đổi thì cần cập nhận thông tin vào bảng #__eqa_class_learner
		 */
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Xác định learner_id dựa trên mask
		$masks = [];
		foreach ($regradingData as $entry)
		{
			$entry = PpaaEntryInfo::cast($entry);
			$masks[] = $entry->mask;
		}
		$columns = $db->quoteName(
			array('a.learner_id', 'a.mask','b.class_id', 'd.subject_id', 'e.pam', 'b.attempt','c.type',     'c.value',     'b.anomaly', 'b.conclusion'),
			array('id',           'mask',  'classId',    'subjectId',    'pam',   'attempt',  'stimulType', 'stimulValue', 'anomaly',   'conclusion')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_papers AS a')
			->leftJoin('#__eqa_exam_learner AS b', 'b.exam_id=a.exam_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_stimulations AS c', 'c.id=b.stimulation_id')
			->leftJoin('#__eqa_classes AS d', 'd.id=b.class_id')
			->leftJoin('#__eqa_class_learner AS e', 'e.class_id=d.id AND e.learner_id=a.learner_id')
			->where('a.exam_id=' . $examId. ' AND a.mask IN (' . implode(',', $masks) . ')');
		$db->setQuery($query);
		$examinees = $db->loadObjectList('mask'); //Mảng kết hợp, key là mask
		if(empty($examinees))
			throw new Exception('Không tìm thấy thông tin của thí sinh trong danh sách thí sinh của môn thi');

		//Xử lý từng entry
		foreach ($regradingData as $entry)
		{
			$entry = PpaaEntryInfo::cast($entry);
			$examinee = $examinees[$entry->mask]; //Lấy thông tin của thí sinh dựa trên mask
			$addValue = $examinee->stimulType==StimulationHelper::TYPE_ADD ? $examinee->stimulValue : 0;
			$this->applyRegradingResult($examId, $examinee->id, $examinee->classId, $examinee->pam, $examinee->attempt, $addValue, $examinee->anomaly, $entry->oldMark, $examinee->conclusion, $entry->newMark, $entry->changeDescription);
		}
	}

	/**
	 * @param   int    $examId
	 * @param   array  $examResults An associative array with learner code as key and mark as value
	 * @return  int    Số lượng yêu cầu phúc khảo đã được xử lý
	 *
	 * @throws Exception
	 * @since version
	 */
	public function saveHybridRegradingResult(int $examId, array $examResults): int
	{
		/**
		 * Cách thực hiện:
		 * 1. Lấy thông tin cần thiết về từng thí sinh có yêu cầu phúc khảo môn thi $examId. Điều kiện
		 *    là yêu cầu phải được chấp thuận trước đó, hoặc đã được xử lý thành công trước đó. Nếu
		 *    đã xử lý thành công thì xử lý lại như bình thường.
		 * 2. Cập nhật kết quả phúc khảo vào CSDL cho từng thí sinh.
		 */
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Lấy thông tin cần thiết về từng thí sinh có yêu cầu phúc khảo môn thi $examId
		$columns = $db->quoteName(
			array('a.learner_id', 'f.code',      'b.class_id', 'd.subject_id', 'e.pam', 'b.attempt','c.type',     'c.value',     'b.mark_orig',  'b.anomaly', 'b.conclusion'),
			array('learnerId',    'learnerCode', 'classId',    'subjectId',    'pam',   'attempt',  'stimulType', 'stimulValue', 'origMark',     'anomaly',   'conclusion')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exam_learner AS b', 'b.exam_id=a.exam_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_stimulations AS c', 'c.id=b.stimulation_id')
			->leftJoin('#__eqa_classes AS d', 'd.id=b.class_id')
			->leftJoin('#__eqa_class_learner AS e', 'e.class_id=d.id AND e.learner_id=a.learner_id')
			->leftJoin('#__eqa_learners AS f', 'f.id=a.learner_id')
			->where('a.exam_id=' . $examId. ' AND a.status IN (' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED . ',' . ExamHelper::EXAM_PPAA_STATUS_DONE . ')');
		$db->setQuery($query);
		$examinees = $db->loadObjectList('learnerCode'); //Mảng kết hợp, key là learnerCode
		if(empty($examinees))
			throw new Exception('Môn thi không có yêu cầu phúc khảo nào đã được chấp nhận');

		//2. Cập nhật kết quả phúc khảo vào CSDL cho từng thí sinh
		foreach ($examinees as $examinee)
		{
			if(!isset($examResults[$examinee->learnerCode]))
				throw new Exception('Không tìm thấy điểm phúc khảo cho thí sinh '. htmlspecialchars($examinee->learnerCode));

			$learnerId = $examinee->learnerId;
			$classId = $examinee->classId;
			$pam = $examinee->pam;
			$attempt = $examinee->attempt;
			$addValue = $examinee->stimulType==StimulationHelper::TYPE_ADD ? $examinee->stimulValue : 0;
			$anomaly = $examinee->anomaly;
			$oldMark = $examinee->origMark;
			$oldConclusion = $examinee->conclusion;
			$newMark = $examResults[$examinee->learnerCode];
			$changeDescription = null;
			$this->applyRegradingResult($examId, $learnerId, $classId, $pam, $attempt, $addValue, $anomaly, $oldMark, $oldConclusion, $newMark, $changeDescription);
		}

		//3. Trả về số lượng yêu cầu phúc khảo đã được xử lý
		return count($examinees);
	}
}
