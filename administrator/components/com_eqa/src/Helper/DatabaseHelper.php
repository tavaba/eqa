<?php

namespace Kma\Component\Eqa\Administrator\Helper;

use Kma\Component\Eqa\Administrator\DataObject\ExamsessionInfo;
use Kma\Component\Eqa\Administrator\Enum\ExamStatus;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Component\Eqa\Administrator\DataObject\ExamInfo;
use Kma\Component\Eqa\Administrator\DataObject\ExamroomInfo;
use Kma\Component\Eqa\Administrator\DataObject\ExamseasonInfo;
use Kma\Component\Eqa\Administrator\DataObject\GradeCorrectionInfo;
use Kma\Component\Eqa\Administrator\DataObject\LearnerInfo;
use Kma\Library\Kma\Helper\DatabaseHelper as DatabaseHelperBase;
use Kma\Library\Kma\Helper\DatetimeHelper;

abstract class DatabaseHelper extends DatabaseHelperBase
{
	static public function getLearnerAdmissionYear(int $learnerId): int|null
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('c.admissionyear')
			->from('#__eqa_learners AS a')
			->leftJoin('#__eqa_groups AS b', 'b.id=a.group_id')
			->leftJoin('#__eqa_courses AS c', 'c.id=b.course_id')
			->where('a.id=' . $learnerId);
		$db->setQuery($query);
		return $db->loadResult();
	}

	/**
	 * Lấy thông tin tóm tắt của một lớp học phần theo ID.
	 *
	 * @param   int|null  $id  ID lớp học phần.
	 *
	 * @return  object|null
	 * @since   1.0
	 */
	static public function getClassInfo($id): object|null
	{
		if (empty($id)) {
			return null;
		}

		$id = (int) $id;
		$db = self::getDatabaseDriver();

		$subqueryCountAllowed = "SELECT COUNT(1) FROM #__eqa_class_learner"
			. " WHERE class_id={$id} AND allowed=1";

		$columns = $db->quoteName(
			['a.id', 'a.code', 'a.name', 'b.code',      'b.name',       'e.name', 'b.credits', 'a.term', 'a.academicyear', 'a.size', 'a.lecturer_id'],
			['id',   'code',   'name',   'subjectCode', 'subjectName',  'unit',   'credits',   'term',   'academicyear',   'size',   'lecturerId']
		);

		$query = $db->getQuery(true)
			->from('#__eqa_classes AS a')
			->leftJoin('#__eqa_subjects AS b', 'b.id = a.subject_id')
			->leftJoin('#__eqa_units AS e', 'e.id = b.unit_id')
			->select($columns)
			->select('(' . $subqueryCountAllowed . ') AS countAllowed')
			->where('a.id = ' . $id);

		$db->setQuery($query);
		$obj = $db->loadObject();

		if ($obj) {
			// Chuyển INT sang chuỗi hiển thị, ví dụ: 2025 → "2025-2026"
			$obj->academicyear = DatetimeHelper::decodeAcademicYear((int) $obj->academicyear);
		}

		return $obj;
	}

	/**
	 * Tính năm học thứ mấy của một khóa học trong một năm học cho trước.
	 *
	 * @param   string|int  $courseCodeOrId   Mã hoặc ID khóa học.
	 * @param   int         $currentAcademicyear  Năm học hiện tại (encoded, ví dụ: 2025).
	 *
	 * @return  int  Năm học thứ N (tính từ 1).
	 * @since   1.0
	 */
	static public function getCourseStudyYear(string|int $courseCodeOrId, int $currentAcademicyear): int
	{
		$db = self::getDatabaseDriver();

		// 1. Lấy năm nhập học của khóa học
		$query = $db->getQuery(true)
			->select($db->quoteName('admissionyear'))
			->from($db->quoteName('#__eqa_courses'));

		if (is_numeric($courseCodeOrId)) {
			$query->where('id = ' . (int) $courseCodeOrId);
		} else {
			$query->where('code = ' . $db->quote($courseCodeOrId));
		}

		$db->setQuery($query);
		$admissionYear = (int) $db->loadResult();

		// 2. Tính và trả về năm học thứ N
		// $currentAcademicyear đã là năm đầu tiên của năm học (encoded INT)
		return $currentAcademicyear - $admissionYear + 1;
	}

	static public function getExamNames(array $examIds)
	{
		if(empty($examIds))
			return  [];

		$db = self::getDatabaseDriver();
		$examIdSet = '(' . implode(',', $examIds) . ')';
		$db->setQuery('SELECT name FROM #__eqa_exams WHERE id IN ' . $examIdSet);
		return $db->loadColumn();
	}
	static public function getExamTestTime(int $examId)
	{
		$db = self::getDatabaseDriver();
		$columns = $db->quoteName(
			array('c.start'),
			array('start')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_examrooms AS b', 'b.id = a.examroom_id')
			->leftJoin('#__eqa_examsessions AS c', 'c.id=b.examsession_id')
			->where('a.exam_id=' . $examId . ' AND a.examroom_id IS NOT NULL')
			->setLimit(1);
		$db->setQuery($query);
		$time = $db->loadResult();
		return $time;
	}

	/**
	 * Lấy thông tin chi tiết của một môn thi theo ID.
	 *
	 * @param   int  $examId  ID môn thi.
	 *
	 * @return  ExamInfo|null
	 * @since   1.0
	 */
	static public function getExamInfo(int $examId): ExamInfo|null
	{
		if (empty($examId)) {
			return null;
		}

		$db      = self::getDatabaseDriver();
		$columns = $db->quoteName(
			['a.id', 'd.code', 'd.credits', 'a.name', 'a.testtype', 'a.usetestbank', 'a.duration', 'a.examseason_id', 'b.name',     'b.term', 'b.academicyear', 'a.status'],
			['id',   'code',   'credits',   'name',   'testtype',   'usetestbank',   'duration',   'examseasonId',   'examseason', 'term',   'academicyear',   'status']
		);

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id = b.id')
			->leftJoin('#__eqa_subjects AS d', 'd.id = a.subject_id')
			->where('a.id = ' . (int) $examId);

		$db->setQuery($query);
		$obj = $db->loadObject();

		if (!$obj) {
			return null;
		}

		// Decode INT → chuỗi "YYYY-YYYY"
		$obj->academicyear = DatetimeHelper::decodeAcademicYear((int) $obj->academicyear);

		$examInfo = new ExamInfo($obj);


		//Tiếp tục với các thuộc tính đếm số lượng
		//1. Tổng số thí sinh
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner')
			->where('exam_id='.$examId);
		$db->setQuery($query);
		$examInfo->countTotal = $db->loadResult();

		//2. Tổng số thí sinh được thi (ở lớp học phần)
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id = a.class_id AND b.learner_id=a.learner_id')
			->where([
				'a.exam_id='.$examId,
				'b.allowed<>0',
			]);
		$db->setQuery($query);
		$examInfo->countAllowed = $db->loadResult();

		//3. Tổng số thí sinh nợ phí
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner')
			->where([
				'exam_id='.$examId,
				'debtor<>0',
			]);
		$db->setQuery($query);
		$examInfo->countDebtors = $db->loadResult();

		//4. Tổng số thí sinh không phải thi (miễn thi, đổi điểm)
		$exemptSet = '(' . implode(',', [StimulationHelper::TYPE_EXEMPT, StimulationHelper::TYPE_TRANS]) . ')';
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_stimulations AS b', 'a.stimulation_id=b.id')
			->where([
				'a.exam_id='.$examId,
				'b.type IN ' . $exemptSet,
			]);
		$db->setQuery($query);
		$examInfo->countExempted = $db->loadResult();

		//5. Tổng số thí sinh sẽ (phải/được) thi
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_stimulations AS c', 'a.stimulation_id=c.id')
			->where([
				'a.exam_id='.$examId,
				'a.debtor=0',
				'b.allowed<>0',
				'(a.stimulation_id IS NULL OR c.type=' . StimulationHelper::TYPE_ADD . ')'
			]);
		$db->setQuery($query);
		$examInfo->countToTake = $db->loadResult();

		//6. Tổng số thí sinh đã có thông tin về bài thi
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_papers')
			->where('exam_id='.$examId);
		$db->setQuery($query);
		$examInfo->countHavePaperInfo = $db->loadResult();

		//7. Tổng số thí sinh đã có kết luận
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner')
			->where('exam_id='.$examId)
			->where('conclusion IS NOT NULL');
		$db->setQuery($query);
		$examInfo->countConcluded = $db->loadResult();


		return $examInfo;
	}
    static public function getExamExaminees(int $examId, bool $allowedOnly = false)
    {
        $db = self::getDatabaseDriver();
	    $columns = $db->quoteName(
		    array('a.learner_id','a.code','b.code',       'b.lastname', 'b.firstname', 'a.attempt', 'c.pam1', 'c.pam2','c.pam','c.allowed', 'a.debtor','d.name',  'e.name',     'e.start',  'a.mark_final', 'a.module_mark', 'a.module_grade', 'a.conclusion'),
		    array('id',         'code',   'learner_code', 'lastname',    'firstname',   'attempt',  'pam1',   'pam2', 'pam',   'allowed',   'debtor',  'examroom','examsession','examstart','mark_final',   'module_mark',   'module_grade',   'conclusion')
	    );
	    $query = $db->getQuery(true)
		    ->select($columns)
		    ->from('#__eqa_exam_learner AS a')
		    ->leftJoin('#__eqa_learners AS b','a.learner_id = b.id')
		    ->leftJoin('#__eqa_class_learner AS c', 'a.learner_id=c.learner_id AND a.class_id=c.class_id')
		    ->leftJoin('#__eqa_examrooms AS d', 'a.examroom_id=d.id')
		    ->leftJoin('#__eqa_examsessions AS e', 'd.examsession_id=e.id')
		    ->leftJoin('#__eqa_stimulations AS f', 'a.stimulation_id=f.id')
		    ->where('a.exam_id = '.$examId)
		    ->order('b.firstname ASC, b.lastname ASC');
        if($allowedOnly)
            $query->where([
	            'allowed<>0',
				'a.debtor=0',
	            '(f.type IS NULL OR f.type=' . StimulationHelper::TYPE_ADD . ')'
            ]);
        $db->setQuery($query);
        return $db->loadObjectList();
    }
	static public function getExamExamineeCount(int $examId, bool $distributedOnly=false): int
	{
		$db = self::getDatabaseDriver();
		$query = 'SELECT COUNT(1) FROM #__eqa_exam_learner WHERE exam_id='.$examId;
		if($distributedOnly)
			$query .= ' AND examroom_id IS NOT NULL';
		$db->setQuery($query);
		$nexaminee = $db->loadResult();
		return $nexaminee;
	}
	static public function getLastExamResultOfLearnerOfClass(int $classId, int $learnerId):array|null
	{
		$db = self::getDatabaseDriver();
		$columns = [
			'mark_final',
			'attempt',
			'module_mark',
			'module_base4_mark',
			'module_grade',
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner')
			->where("class_id=$classId AND learner_id=$learnerId AND conclusion IS NOT NULL")
			->order('exam_id DESC')
			->setLimit(1);
		$db->setQuery($query);
		return $db->loadAssoc();
	}
	static public function getExamNoPaperCount(int $examId): int
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_papers WHERE nsheet = 0 AND exam_id='.$examId);
		return $db->loadResult();
	}
	static public function getExamPaperCount(int $examId): int
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_papers WHERE nsheet > 0 AND exam_id='.$examId);
		$items = $db->loadColumn();
		if(empty($items))
			return 0;
		$nsheet=0;
		foreach ($items as $item)
			$nsheet += $item;
		return $nsheet;
	}
	static public function getExamSheetCount(int $examId): int
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT nsheet FROM #__eqa_papers WHERE exam_id='.$examId);
		$items = $db->loadColumn();
		if(empty($items))
			return 0;
		$nsheet=0;
		foreach ($items as $item)
			$nsheet += $item;
		return $nsheet;
	}
	static public function getExamPackageCount(int $examId): int
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_packages WHERE exam_id='.$examId);
		return $db->loadResult();
	}

	/**
	 * Lấy thông tin đầy đủ của một phòng thi, tự động phân biệt KTHP và sát hạch.
	 *
	 * Luồng xử lý:
	 *   1. Lấy các trường cơ bản của examroom + examsession + phòng vật lý.
	 *   2. Đếm thí sinh từ cả hai bảng (exam_learner và assessment_learner).
	 *   3. Nếu có thí sinh sát hạch → populate thông tin kỳ sát hạch.
	 *      Ngược lại → populate thông tin kỳ thi KTHP (examseason).
	 *
	 * @param  int|null  $id  ID phòng thi.
	 *
	 * @return ExamroomInfo|null  null nếu $id rỗng hoặc không tìm thấy bản ghi.
	 * @since 1.0
	 */
	public static function getExamroomInfo($id): ExamroomInfo|null
	{
		if (empty($id)) {
			return null;
		}

		$db = self::getDatabaseDriver();

		// -------------------------------------------------------------------------
		// 1. Lấy thông tin cơ bản: examroom + phòng vật lý + ca thi
		// -------------------------------------------------------------------------
		$columns = $db->quoteName(
			['a.id', 'a.name', 'a.exam_ids',
				'c.code',
				'd.start', 'd.name',     'd.id',
				'a.monitor1_id', 'a.monitor2_id', 'a.monitor3_id',
				'a.examiner1_id', 'a.examiner2_id'],
			['id',   'name',   'exam_ids',
				'building',
				'examtime', 'examsession', 'examsession_id',
				'monitor1_id', 'monitor2_id', 'monitor3_id',
				'examiner1_id', 'examiner2_id']
		);

		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_examrooms', 'a'))
			->leftJoin($db->quoteName('#__eqa_rooms', 'b')         . ' ON ' . $db->quoteName('a.room_id')        . ' = ' . $db->quoteName('b.id'))
			->leftJoin($db->quoteName('#__eqa_buildings', 'c')     . ' ON ' . $db->quoteName('b.building_id')    . ' = ' . $db->quoteName('c.id'))
			->leftJoin($db->quoteName('#__eqa_examsessions', 'd')  . ' ON ' . $db->quoteName('a.examsession_id') . ' = ' . $db->quoteName('d.id'))
			->where($db->quoteName('a.id') . ' = ' . (int) $id);

		$db->setQuery($query);
		$obj = $db->loadObject();

		if ($obj === null) {
			return null;
		}

		// -------------------------------------------------------------------------
		// 2. Đếm thí sinh từ cả hai bảng
		// -------------------------------------------------------------------------
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from($db->quoteName('#__eqa_exam_learner'))
			->where($db->quoteName('examroom_id') . ' = ' . (int) $id);
		$db->setQuery($query);
		$examExamineeCount = (int) $db->loadResult();

		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('examroom_id') . ' = ' . (int) $id);
		$db->setQuery($query);
		$assessmentExamineeCount = (int) $db->loadResult();

		$isAssessmentRoom = $assessmentExamineeCount > 0;

		// -------------------------------------------------------------------------
		// 3. Populate ExamroomInfo
		// -------------------------------------------------------------------------
		$examroom                = new ExamroomInfo();
		$examroom->id            = $obj->id;
		$examroom->name          = $obj->name;
		$examroom->building      = $obj->building;
		$examroom->examTime      = DatetimeHelper::convertToLocalTime($obj->examtime);
		$examroom->examsession   = $obj->examsession;
		$examroom->examsessionId = $obj->examsession_id;
		$examroom->monitor1Id    = $obj->monitor1_id;
		$examroom->monitor2Id    = $obj->monitor2_id;
		$examroom->monitor3Id    = $obj->monitor3_id;
		$examroom->examiner1Id   = $obj->examiner1_id;
		$examroom->examiner2Id   = $obj->examiner2_id;

		$examroom->isAssessmentRoom = $isAssessmentRoom;
		$examroom->examineeCount    = $isAssessmentRoom ? $assessmentExamineeCount : $examExamineeCount;

		if ($isAssessmentRoom) {
			// ---------------------------------------------------------------------
			// 3a. Phòng thi sát hạch: lấy thông tin kỳ sát hạch
			// ---------------------------------------------------------------------
			$query = $db->getQuery(true)
				->select([
					$db->quoteName('a.id'),
					$db->quoteName('a.title'),
				])
				->from($db->quoteName('#__eqa_assessments', 'a'))
				->innerJoin(
					$db->quoteName('#__eqa_assessment_learner', 'al') .
					' ON ' . $db->quoteName('al.assessment_id') . ' = ' . $db->quoteName('a.id')
				)
				->where($db->quoteName('al.examroom_id') . ' = ' . (int) $id)
				->setLimit(1);
			$db->setQuery($query);
			$assessment = $db->loadObject();

			$examroom->assessmentId    = $assessment->id    ?? null;
			$examroom->assessmentTitle = $assessment->title ?? null;

			// Đặt các field KTHP về null để tránh nhầm lẫn
			$examroom->academicyear = null;
			$examroom->term         = null;
			$examroom->examseason   = null;
			$examroom->attempt      = null;
			$examroom->examIds      = null;
			$examroom->exams        = null;

		} else {
			// ---------------------------------------------------------------------
			// 3b. Phòng thi KTHP: lấy thông tin kỳ thi (examseason)
			// ---------------------------------------------------------------------
			$query = $db->getQuery(true)
				->select($db->quoteName(
					['e.attempt', 'e.term', 'e.academicyear', 'e.name'],
					['attempt',   'term',   'academicyear',   'examseason']
				))
				->from($db->quoteName('#__eqa_examsessions', 'd'))
				->leftJoin(
					$db->quoteName('#__eqa_examseasons', 'e') .
					' ON ' . $db->quoteName('d.examseason_id') . ' = ' . $db->quoteName('e.id')
				)
				->where($db->quoteName('d.id') . ' = ' . (int) $obj->examsession_id);
			$db->setQuery($query);
			$season = $db->loadObject();

			$examroom->academicyear = $season->academicyear ?? null;
			$examroom->term         = $season->term         ?? null;
			$examroom->examseason   = $season->examseason   ?? null;
			$examroom->attempt      = $season->attempt      ?? null;

			// Lấy danh sách môn thi
			if (!empty($obj->exam_ids)) {
				$examroom->examIds = explode(',', $obj->exam_ids);
				$examroom->exams   = self::getExamNames($examroom->examIds);
			} else {
				$examroom->examIds = [];
				$examroom->exams   = [];
			}

			// Lấy hình thức thi và thời gian thi
			$examroom->testtype     = self::getExamroomTesttype((int) $id);
			$examroom->testDuration = self::getExamroomTestDuration((int) $id);

			// Đặt các field sát hạch về null
			$examroom->assessmentId    = null;
			$examroom->assessmentTitle = null;
		}

		return $examroom;
	}
	static public function getExamroomAttempt($examroomId)
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_exams AS b', 'a.exam_id=b.id')
			->leftJoin('#__eqa_examseasons AS c', 'b.examseason_id=c.id')
			->select('c.attempt')
			->where('a.examroom_id='.$examroomId)
			->setLimit(1,0);
		$db->setQuery($query);
		return $db->loadResult();
	}
	static public function getExamroomTesttype(int $examroomId){
		$db = self::getDatabaseDriver();

		//Phòng thi có thể có nhiều môn thi nhưng phải cùng loại
		//Do vậy, chỉ cần tìm một môn thi và kiểm tra hình thức thi của nó
		$query = $db->getQuery(true)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_exams AS b', 'a.exam_id=b.id')
			->select('b.testtype')
			->where('a.examroom_id='.$examroomId)
			->setLimit(1,0);
		$db->setQuery($query);
		return $db->loadResult();
	}
	static public function getExamroomTestDuration(int $examroomId){
		//Thời gian thi của phòng thi là thời gian
		//của môn thi dài nhất

		//1. Tìm các môn thi
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->from('#__eqa_exam_learner')
			->select('exam_id')
			->where('examroom_id='.$examroomId);
		$db->setQuery($query);
		$examIds = $db->loadColumn();
		$examIds = array_unique($examIds);

		if(empty($examIds))
			return 0;

		//2. Tìm thời gian thi của các môn thi
		$examIdSet = '(' . implode(',',$examIds) . ')';
		$query = $db->getQuery(true)
			->from('#__eqa_exams')
			->select('duration')
			->where('id IN ' . $examIdSet);
		$db->setQuery($query);
		$durations = $db->loadColumn();
		$durations = array_filter($durations,'intval');

		//3. Trả về max
		if(empty($durations))
			return 0;
		return max($durations);
	}
	static public function getExamroomExamIds(int $examroomId)
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT exam_id FROM #__eqa_exam_learner WHERE examroom_id=' . $examroomId);
		$examIds = $db->loadColumn();
		return array_unique($examIds);
	}
	static public function getExamroomIdsOfExaminees(int $examId, array $learnerIds)
	{
		$db = self::getDatabaseDriver();
		$learnerIdSet = '(' . implode(',', $learnerIds) . ')';
		$query = $db->getQuery(true)
			->select('learner_id, examroom_id')
			->from('#__eqa_exam_learner')
			->where($db->quoteName('exam_id') . '=' . $examId)
			->where($db->quoteName('learner_id') . ' IN ' . $learnerIdSet);
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		$res=[];
		foreach ($rows as $row)
			$res[$row->learner_id] = $row->examroom_id;
		if(sizeof($res) != sizeof($learnerIds))
			return false;
		return $res;
	}

	/**
	 * Lấy thông tin kỳ thi theo ID, hoặc kỳ thi mặc định nếu không truyền ID.
	 *
	 * @param   int|null  $id  ID kỳ thi. Nếu null, lấy kỳ thi có default=1.
	 *
	 * @return  ExamseasonInfo|null
	 * @since   1.0
	 */
	static public function getExamseasonInfo(?int $id = null): ExamseasonInfo|null
	{
		$db      = self::getDatabaseDriver();
		$columns = $db->quoteName(
			['a.id', 'a.name', 'a.academicyear', 'a.term', 'a.completed', 'a.ppaa_req_enabled', 'a.ppaa_req_deadline'],
			['id',   'name',   'academicyear',   'term',   'completed',   'ppaa_req_enabled',   'ppaa_req_deadline']
		);

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_examseasons AS a');

		if (empty($id)) {
			$query->where($db->quoteName('a.default') . ' = 1');
		} else {
			$query->where('a.id = ' . (int) $id);
		}

		$db->setQuery($query);
		$obj = $db->loadObject();

		if (!$obj) {
			return null;
		}

		$examseason                   = new ExamseasonInfo();
		$examseason->id               = $obj->id;
		$examseason->name             = $obj->name;
		// Decode INT → chuỗi "YYYY-YYYY" để giữ nguyên kiểu dữ liệu string của field
		$examseason->academicyear     = DatetimeHelper::decodeAcademicYear((int) $obj->academicyear);
		$examseason->term             = $obj->term;
		$examseason->completed        = $obj->completed;
		$examseason->ppaaRequestEnabled  = $obj->ppaa_req_enabled;
		$examseason->ppaaRequestDeadline = $obj->ppaa_req_deadline?DatetimeHelper::convertToLocalTime($obj->ppaa_req_deadline):null;

		return $examseason;
	}
	static public function getExamseasonIdByExamroom(int $examroomId){
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('b.examseason_id')
			->from('#__eqa_examrooms AS a')
			->leftJoin('#__eqa_examsessions AS b', 'a.examsession_id=b.id')
			->where('a.id='.$examroomId);
		$db->setQuery($query);
		return $db->loadResult();
	}
	static public function getExamseasonExams(int $examseasonId)
	{
		$db = self::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'b.code', 'a.name'),
			array('id',   'code',   'name')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_subjects AS b','a.subject_id=b.id')
			->where('examseason_id='.$examseasonId);
		$db->setQuery($query);
		return $db->loadObjectList('id');
	}
	static public function getExamsessionName(int $examsessionId)
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT name FROM #__eqa_examsessions WHERE id='.$examsessionId);
		return $db->loadResult();
	}
	static public function getExamsessionInfo_bak(int $examsessionId)
	{
		$db = self::getDatabaseDriver();

		//Tên và thời gian
		$columns = $db->quoteName(
			array('name', 'start')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_examsessions')
			->where('id='.$examsessionId);
		$db->setQuery($query);
		$obj = $db->loadObject();

		//Số lượng phòng thi
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_examrooms WHERE examsession_id='.$examsessionId);
		$obj->countExamroom = $db->loadResult();

		//Danh sách các môn thi
		//Đồng thời xác định tổng số thí sinh đã được phân vào các phòng thi của ca thi
		$query = $db->getQuery(true)
			->select('exam_id')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_examrooms AS b', 'a.examroom_id=b.id')
			->where('b.examsession_id='.$examsessionId);
		$db->setQuery($query);
		$examIds = $db->loadColumn();
		$obj->countExaminee = sizeof($examIds);
		$obj->examIds = array_unique($examIds);

		//Convert start time to local time
		$obj->start = DatetimeHelper::convertToLocalTime($obj->start);

		return $obj;
	}
	/**
	 * Lấy thông tin tóm tắt của một ca thi theo ID.
	 *
	 * Tự động phân biệt ca thi KTHP và ca thi sát hạch dựa trên
	 * examseason_id / assessment_id trong bảng #__eqa_examsessions.
	 *
	 * @param  int  $examsessionId
	 *
	 * @return ExamsessionInfo|null  null nếu không tìm thấy.
	 * @since 1.0
	 * @updated 2.0.6  Trả về ExamsessionInfo thay vì stdClass; hỗ trợ sát hạch.
	 */
	public static function getExamsessionInfo(int $examsessionId): ?ExamsessionInfo
	{
		$db = self::getDatabaseDriver();

		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'name', 'start', 'examseason_id', 'assessment_id']))
			->from($db->quoteName('#__eqa_examsessions'))
			->where($db->quoteName('id') . ' = ' . $examsessionId);
		$db->setQuery($query);
		$row = $db->loadObject();

		if ($row === null) {
			return null;
		}

		$info                      = new ExamsessionInfo();
		$info->id                  = (int) $row->id;
		$info->name                = $row->name;
		$info->start               = $row->start;
		$info->isAssessmentSession = !empty($row->assessment_id);

		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from($db->quoteName('#__eqa_examrooms'))
			->where($db->quoteName('examsession_id') . ' = ' . $examsessionId);
		$db->setQuery($query);
		$info->countExamroom = (int) $db->loadResult();

		if ($info->isAssessmentSession) {
			// Ca thi sát hạch
			$query = $db->getQuery(true)
				->select($db->quoteName(['id', 'title']))
				->from($db->quoteName('#__eqa_assessments'))
				->where($db->quoteName('id') . ' = ' . (int) $row->assessment_id);
			$db->setQuery($query);
			$assessment = $db->loadObject();

			$info->assessmentId    = $assessment ? (int) $assessment->id    : null;
			$info->assessmentTitle = $assessment ? $assessment->title        : null;

			$query = $db->getQuery(true)
				->select('COUNT(1)')
				->from($db->quoteName('#__eqa_assessment_learner', 'al'))
				->innerJoin(
					$db->quoteName('#__eqa_examrooms', 'er') .
					' ON ' . $db->quoteName('er.id') . ' = ' . $db->quoteName('al.examroom_id')
				)
				->where($db->quoteName('er.examsession_id') . ' = ' . $examsessionId);
			$db->setQuery($query);
			$info->countExaminee = (int) $db->loadResult();

			$info->examIds = null;

			// Với sát hạch, "môn thi" là tên kỳ sát hạch
			$info->exams = !empty($info->assessmentTitle)
				? [$info->assessmentTitle]
				: [];

		} else {
			// Ca thi KTHP
			$query = $db->getQuery(true)
				->select($db->quoteName('a.exam_id'))
				->from($db->quoteName('#__eqa_exam_learner', 'a'))
				->leftJoin(
					$db->quoteName('#__eqa_examrooms', 'b') .
					' ON ' . $db->quoteName('a.examroom_id') . ' = ' . $db->quoteName('b.id')
				)
				->where($db->quoteName('b.examsession_id') . ' = ' . $examsessionId);
			$db->setQuery($query);
			$examIds = $db->loadColumn();

			$info->countExaminee = count($examIds);
			$info->examIds       = array_unique($examIds);

			// Lấy tên các môn thi
			$info->exams = !empty($info->examIds)
				? self::getExamNames($info->examIds)
				: [];

			$info->assessmentId    = null;
			$info->assessmentTitle = null;
		}

		return $info;
	}
	static public function getExamsessionMonitorCount(int $examsessionId)
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT * FROM #__eqa_examrooms WHERE examsession_id='.$examsessionId);
		$examrooms = $db->loadObjectList();
		$count=0;
		foreach ($examrooms as $r)
		{
			if(!empty($r->monitor1_id))
				$count++;
			if(!empty($r->monitor2_id))
				$count++;
			if(!empty($r->monitor3_id))
				$count++;
		}
		return $count;
	}
	static public function getExamsessionExaminerCount(int $examsessionId)
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT * FROM #__eqa_examrooms WHERE examsession_id='.$examsessionId);
		$examrooms = $db->loadObjectList();
		$count=0;
		foreach ($examrooms as $r)
		{
			if(!empty($r->examiner1_id))
				$count++;
			if(!empty($r->examiner2_id))
				$count++;
		}
		return $count;
	}

	/**
	 * @param   array   $employeeIds
	 * @param   string  $resultType Nhận các giá trị 'assoc', 'object'
	 *
	 *
	 * @since version
	 */
	static public function getEmployeeInfos(array $employeeIds, bool $returnAssoc=true)
	{
		$employeeIdSet = '(' . implode(',', $employeeIds) . ')';
		$db = self::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'a.lastname', 'a.firstname', 'b.code'),
			array('id',   'lastname',   'firstname',   'unit')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_employees AS a')
			->leftJoin('#__eqa_units AS b', 'b.id = a.unit_id')
			->where('a.id IN ' . $employeeIdSet);
		$db->setQuery($query);

		if($returnAssoc)
			return $db->loadAssocList('id');
		return $db->loadObjectList('id');

	}
	static public function getGradeCorrectionInfo(int $gradeCorrectionId): GradeCorrectionInfo|null
	{
		$db = self::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'a.exam_id', 'b.name', 'a.learner_id', 'c.code',      'c.lastname',      'c.firstname',      'a.constituent', 'a.reason', 'a.status', 'a.description'),
			array('id',   'examId',    'exam',   'learnerId',    'learnerCode', 'learnerLastname', 'learnerFirstname', 'constituent',   'reason',   'status',   'description')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_exams AS b','b.id=a.exam_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->where('a.id=' . $gradeCorrectionId);
		$db->setQuery($query);
		$obj = $db->loadObject();
		if(!$obj)
			return null;
		return new GradeCorrectionInfo($obj);
	}

	static public function getLearnerMap(array $learnerCodes=[], int $limit=0){
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->from('#__eqa_learners')
			->select('id, code');

		//Dựng map cho sô HVSV nhất định
		if(!empty($learnerCodes)){
			$learnerCodes = $db->quote($learnerCodes);
			$learnerCodeSet = '(' . implode(',', $learnerCodes) . ')';
			$query->where('code IN ' . $learnerCodeSet);
		}

		//Dựng map cho số HVSV mới được tạo gần nhất (id cao nhất)
		if(empty($learnerCodes) && $limit>0)
		{
			$query->order('id DESC')
				->setLimit($limit);
		}
		$db->setQuery($query);
		$res = $db->loadAssocList('code','id');
		if(!empty($learnerCodes) && sizeof($res) != sizeof($learnerCodes))
			return false;
		return $res;
	}
	static public function getLearnerId(string $learnerCode): int|null
	{
		$db     = self::getDatabaseDriver();
		$query  = $db->getQuery(true)
			->select('id')
			->from('#__eqa_learners')
			->where('code = ' . $db->quote($learnerCode));
		$db->setQuery($query);
		return $db->loadResult();
	}
	static public function getLearnerInfo(string|int $learnerCodeOrId): LearnerInfo|null
	{
		$db     = self::getDatabaseDriver();
		$columns = array(
			$db->quoteName('a.id') . ' AS ' . $db->quoteName('id'),
			$db->quoteName('a.code') . ' AS ' . $db->quoteName('code'),
			$db->quoteName('a.lastname') . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('a.firstname') . ' AS ' . $db->quoteName('firstname')
		);
		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_learners AS a');
		if(is_integer($learnerCodeOrId))
			$query->where('id=' . $learnerCodeOrId);
		else
			$query->where('code=' . $db->quote($learnerCodeOrId));
		$db->setQuery($query);
		$obj = $db->loadObject();
		if(empty($obj))
			return null;
		$learnerInfo = new LearnerInfo($obj);
		return $learnerInfo;
	}
	static public function getLearnerIds(array $learnerCodes, bool $allMustExist=true)
	{
		$db           = self::getDatabaseDriver();
		$learnerCodes = $db->quote($learnerCodes);
		$learnerCodeSet = '(' . implode(',', $learnerCodes) . ')';
		$query        = $db->getQuery(true)
			->select('id')
			->from('#__eqa_learners')
			->where('code IN ' . $learnerCodeSet);
		$db->setQuery($query);
		$res = $db->loadColumn();
		if($allMustExist && sizeof($res) != sizeof($learnerCodes))
			return false;
		return $res;
	}
    static public function getLastExamineeCode(int $examId)
    {
        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('code')
            ->from('#__eqa_exam_learner')
            ->where('code IS NOT NULL')
            ->where('exam_id='.$examId)
            ->order('code DESC')
            ->setLimit(1,0);
        $db->setQuery($query);
        return $db->loadResult();
    }
    static public function getSubjectMap(){
        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->from('#__eqa_subjects')
            ->select('id, code');
        $db->setQuery($query);
        return $db->loadAssocList('code','id');
    }
    static public function getClassId(string $classCode){
        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->from('#__eqa_classes')
            ->select('id')
            ->where('code='.$db->quote($classCode));
        $db->setQuery($query);
        return $db->loadResult();
    }
    static public function getClassLearners(int $classId){
        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->from('#__eqa_class_learner')
            ->select('*')
            ->where('class_id='.$classId);
        $db->setQuery($query);
        return $db->loadAssocList('learner_id');
    }
    static public function getAcademicyearId(int $startYear){
        if($startYear<2000)
            $startYear+=2000;
        $code = $startYear.'-'.($startYear+1);
        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__eqa_academicyears')
            ->where($db->quoteName('code').'='.$db->quote($code));
        $db->setQuery($query);
        return $db->loadResult();
    }

	static public function getRoomCode(int $roomId){
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT code FROM #__eqa_rooms WHERE id='.$roomId);
		return $db->loadResult();
	}
	static public function getDefaultExamseason(): ExamseasonInfo|null
	{
		return self::getExamseasonInfo();
	}
	static public function isCompletedExamsession_bak(int $examsessionId) : bool
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('b.completed')
			->from('#__eqa_examsessions AS a')
			->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
			->where('a.id='.$examsessionId);
		$db->setQuery($query);
		return $db->loadResult();
	}
	/**
	 * Kiểm tra xem một ca thi đã kết thúc hay chưa.
	 *
	 * Với ca thi KTHP: dựa vào trường 'completed' của kỳ thi (examseason).
	 * Với ca thi sát hạch: dựa vào trường 'completed' của kỳ sát hạch (assessment).
	 * Nếu không xác định được ca thi hoặc ngữ cảnh → trả về false (cho phép tiếp tục).
	 *
	 * @param  int  $examsessionId
	 *
	 * @return bool
	 * @since 1.0
	 */
	static public function isCompletedExamsession(int $examsessionId): bool
	{
		$db = self::getDatabaseDriver();

		// Lấy thông tin ca thi: examseason_id và assessment_id
		$query = $db->getQuery(true)
			->select($db->quoteName(['examseason_id', 'assessment_id']))
			->from($db->quoteName('#__eqa_examsessions'))
			->where($db->quoteName('id') . ' = ' . $examsessionId);
		$db->setQuery($query);
		$session = $db->loadObject();

		if ($session === null) {
			return false;
		}

		if (!empty($session->examseason_id)) {
			// Ca thi KTHP: kiểm tra trạng thái completed của examseason
			$query = $db->getQuery(true)
				->select($db->quoteName('completed'))
				->from($db->quoteName('#__eqa_examseasons'))
				->where($db->quoteName('id') . ' = ' . (int) $session->examseason_id);
			$db->setQuery($query);
			return (bool) $db->loadResult();
		}

		if (!empty($session->assessment_id)) {
			// Ca thi sát hạch: kiểm tra trạng thái completed của assessment
			$query = $db->getQuery(true)
				->select($db->quoteName('completed'))
				->from($db->quoteName('#__eqa_assessments'))
				->where($db->quoteName('id') . ' = ' . (int) $session->assessment_id);
			$db->setQuery($query);
			return (bool) $db->loadResult();
		}

		// Không xác định được ngữ cảnh → không chặn
		return false;
	}
	static public function isDebtor(int $learnerId):bool
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT debtor FROM #__eqa_learners WHERE id='.$learnerId);
		return $db->loadResult();
	}
	static public function isPaperExam(int $examId):bool
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT testtype FROM #__eqa_exams WHERE id='.$examId);
		return TestType::Paper->value == $db->loadResult();
	}
	static public function isPaperExamWithMaskingDone(int $examId):bool
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->from('#__eqa_papers')
			->select('mask')
			->where('mask IS NOT NULL AND exam_id=' . $examId)
			->setLimit(1);
		$db->setQuery($query);
		return $db->loadResult() > 0;
	}
	public static function isCompletedExam(int $examId): bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.status',   'b.completed'),
			array('exam_status','examseason_completed')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
			->where('a.id='.$examId);
		$db->setQuery($query);
		$res = $db->loadAssoc();
		if(empty($res))
			return false;
		if($res['exam_status']==ExamStatus::Completed->value)
			return true;
		if($res['examseason_completed'])
			return true;
		return false;
	}

	/**
	 * @param   int    $examId
	 * @param   array  $testData  Mảng liên kết [learnerCode] => code
	 *
	 * @return bool
	 *
	 * @since version
	 */
	static public function checkExamCorrectness(int $examId, array $testData): bool
	{
		$db = self::getDatabaseDriver();
		$codes = array_map('intval', $testData);
		$codeSet = '(' . implode(',', $codes) . ')';
		$columns = $db->quoteName(
			array('b.code', 'a.code'),
			array('learner_code', 'code')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->where([
				'a.exam_id = ' . $examId,
				'a.code IN ' . $codeSet
			]);
		$db->setQuery($query);
		$realCodes = $db->loadAssocList('learner_code','code');
		foreach ($testData as $learnerCode => $testCode)
		{
			if(!isset($realCodes[$learnerCode]) || $realCodes[$learnerCode] != $testCode)
				return false;
		}
		return true;
	}
	static public function checkExamroomCorrectness(int $examroomId, array $testData): bool
	{
		$db = self::getDatabaseDriver();
		$codes = array_map('intval', $testData);
		$codeSet = '(' . implode(',', $codes) . ')';
		$columns = $db->quoteName(
			array('b.code', 'a.code'),
			array('learner_code', 'code')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->where([
				'a.examroom_id = ' . $examroomId,
				'a.code IN ' . $codeSet
			]);
		$db->setQuery($query);
		$realCodes = $db->loadAssocList('learner_code','code');
		foreach ($testData as $learnerCode => $testCode)
		{
			if(!isset($realCodes[$learnerCode]) || $realCodes[$learnerCode] != $testCode)
				return false;
		}
		return true;
	}
	static public function setClassPamDate(int $classId, string $date=''): bool
	{
		if(empty($date))
			$date = date('Y-m-d');
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->update('#__eqa_classes')
			->set($db->quoteName('pamdate') . '=' . $db->quote($date))
			->where($db->quoteName('id') . '=' . $classId);
		$db->setQuery($query);
		return $db->execute();
	}
	static public function updateClassNPam(int $classId): int|bool
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->from('#__eqa_class_learner')
			->select('COUNT(1)')
			->where([
				$db->quoteName('class_id') . '=' . $classId,
				$db->quoteName('pam') . ' IS NOT NULL'
			]);
		$db->setQuery($query);
		$npam = $db->loadResult();

		$query = $db->getQuery(true)
			->update('#__eqa_classes')
			->set($db->quoteName('npam') . '=' . $npam)
			->where($db->quoteName('id') . '=' . $classId);
		$db->setQuery($query);
		if($db->execute())
			return $npam;
		return false;
	}
	static public function updateExamroomExams(int $examroomId)
	{
		$db = self::getDatabaseDriver();

		//Find all the exams
		$db->setQuery('SELECT exam_id FROM #__eqa_exam_learner WHERE examroom_id='.$examroomId);
		$ids = $db->loadColumn();
		$ids = array_unique($ids);

		//Save to the examroom record
		$examIds = $db->quote(implode(',',$ids));
		$db->setQuery('UPDATE #__eqa_examrooms SET exam_ids=' . $examIds . ' WHERE id='.$examroomId);
		return $db->execute();
	}

	public static function getGroupInfo(int $groupId)
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('id, code, name')
			->from('#__eqa_groups')
			->where('id = ' . $groupId);
		$db->setQuery($query);
		return $db->loadObject();
	}

	public static function getCourseInfo(int $courseId)
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('id, code, name')
			->from('#__eqa_courses')
			->where('id = ' . $courseId);
		$db->setQuery($query);
		return $db->loadObject();
	}
}