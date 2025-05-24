<?php

namespace Kma\Component\Eqa\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;
use Kma\Component\Eqa\Administrator\Interface\ExamroomInfo;
use Kma\Component\Eqa\Administrator\Interface\GradeCorrectionInfo;
use Kma\Component\Eqa\Administrator\Interface\LearnerInfo;

abstract class DatabaseHelper
{
    /**
     * Casting kết quả của get('DatabaseDriver') thành DatabaseInterface
     * @return DatabaseDriver
     * @since 1.0
     */
    static public function getDatabaseDriver(): DatabaseDriver{
        return Factory::getContainer()->get('DatabaseDriver');
    }

	public static function getAcademicyearCode(int $academicyear_id)
	{
		static $academicyears;
		if(empty($academicyears))
		{
			$db = DatabaseHelper::getDatabaseDriver();
			$query = $db->getQuery(true)
				->select('id, code')
				->from('#__eqa_academicyears');
			$db->setQuery($query);
			$academicyears = $db->loadAssocList('id','code');
		}
		return $academicyears[$academicyear_id];
	}
    static public function getClassInfo($id){
        if(empty($id))
            return  null;

        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->from('#__eqa_classes')
            ->select('*')
            ->where('id = '. (int)$id);
        $db->setQuery($query);
        return $db->loadObject();
    }
	static public function getExamName(int $examId)
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT name FROM #__eqa_exams WHERE id='.$examId);
		return $db->loadResult();
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
	static public function getExamInfo($examId): ExamInfo|null
	{
		if(empty($examId))
			return  null;

		$db = self::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'd.code', 'd.credits', 'a.name', 'a.testtype', 'a.usetestbank', 'a.duration', 'b.name',  'b.term',  'c.code', 'a.status'),
			array('id',   'code',   'credits',   'name',   'testtype',   'usetestbank',   'duration', 'examseason', 'term', 'academicyear', 'status')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id = b.id')
			->leftJoin('#__eqa_academicyears AS c', 'b.academicyear_id=c.id')
			->leftJoin('#__eqa_subjects AS d', 'd.id=a.subject_id')
			->where('a.id = '.(int)$examId);
		$db->setQuery($query);
		$obj = $db->loadObject();

		$exam = new ExamInfo();
		$exam->id = $obj->id;
		$exam->code = $obj->code;
		$exam->credits = $obj->credits;
		$exam->name = $obj->name;
		$exam->testtype = $obj->testtype;
		$exam->useTestBank = $obj->usetestbank;
		$exam->duration = $obj->duration;
		$exam->examseason = $obj->examseason;
		$exam->term = $obj->term;
		$exam->academicyear = $obj->academicyear;
		$exam->status = $obj->status;


		/**
		 * Load additional statistic fields
		 *      - countTotal: tổng số thí sinh
		 *      - countNotAllowed: số HVSV không được thi (đánh giá quá trình)
		 *      - countExempted: số HVSV được miễn thi
		 */

		//1. Tổng số thí sinh
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_exam_learner WHERE exam_id='.(int)$examId);
		$exam->countTotal = $db->loadResult();

		//2. Thí sinh được thi
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', '(a.class_id = b.class_id AND a.learner_id=b.learner_id)')
			->where('a.exam_id='.(int)$examId.' AND b.allowed<>0');
		$db->setQuery($query);
		$exam->countAllowed = $db->loadResult();

		//3. Tổng thí sinh nợ phí, học phí
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner')
			->where('exam_id=' . $examId . ' AND debtor<>0');
		$db->setQuery($query);
		$exam->countDebtors=$db->loadResult();

		//4. Thí sinh được miễn thi, được quy đổi điểm
		$freeTypes = [
			StimulationHelper::TYPE_EXEMPT,
			StimulationHelper::TYPE_TRANS
		];
		$freeTypeSet = '(' . implode(',', $freeTypes) . ')';
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_stimulations AS b', 'a.stimulation_id=b.id')
			->where('a.exam_id='.$examId . ' AND b.type IN ' . $freeTypeSet);
		$db->setQuery($query);
		$exam->countExempted = $db->loadResult();

		//Thí sinh cần dự thi
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', '(a.class_id = b.class_id AND a.learner_id=b.learner_id)')
			->leftJoin('#__eqa_stimulations AS d', 'a.stimulation_id=d.id')
			->where([
				'a.exam_id=' . (int)$examId,
				'b.allowed<>0',
				'a.debtor=0',
				'(a.stimulation_id IS NULL OR d.type='.StimulationHelper::TYPE_ADD . ')'
			]);
		$db->setQuery($query);
		$exam->countToTake = $db->loadResult();

		//Nếu là thi viết thì đếm số lượng thí sinh đã có thông tin về bài thi
		if($exam->testtype == ExamHelper::TEST_TYPE_PAPER)
		{
			$query = $db->getQuery(true)
				->select('COUNT(1)')
				->from('#__eqa_papers')
				->where('exam_id='.$examId);
			$db->setQuery($query);
			$exam->countHavePaperInfo = $db->loadResult();
		}

		//Thí sinh đã có kết quả
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_exam_learner')
			->where('conclusion IS NOT NULL AND exam_id='.$examId);
		$db->setQuery($query);
		$exam->countConcluded = $db->loadResult();

		//return
		return $exam;
	}
    static public function getExamseasonInfo($id)
    {
        if(empty($id))
            return  null;

        $db = self::getDatabaseDriver();
        $columns = $db->quoteName(
            array('a.id', 'a.name',    'b.code',   'a.term'),
            array(' id',   'name',  'academicyear', 'term')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_examseasons AS a')
            ->leftJoin('#__eqa_academicyears AS b', 'a.academicyear_id=b.id')
            ->where('a.id='.(int)$id);
        $db->setQuery($query);
        return $db->loadObject();
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
	static public function getExamNoPaperCount(int $examId): int
	{
		$db = self::getDatabaseDriver();
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_papers WHERE nsheet = 0 AND exam_id='.$examId);
		return $db->loadResult();
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
	static public function getExamroomInfo($id): ExamroomInfo|null
	{
		if(empty($id))
			return null;

		$db = self::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'a.name', 'a.exam_ids', 'c.code',  'd.start', 'e.attempt', 'e.term', 'f.code',      'e.name',    'd.name',     'd.id',          'a.monitor1_id', 'a.monitor2_id', 'a.monitor3_id', 'a.examiner1_id', 'a.examiner2_id'),
			array('id',   'name',   'exam_ids',   'building','examtime', 'attempt',  'term',   'academicyear','examseason','examsession','examsession_id','monitor1_id',   'monitor2_id',   'monitor3_id',   'examiner1_id',   'examiner2_id')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_examrooms AS a')
			->leftJoin('#__eqa_rooms AS b', 'a.room_id=b.id')
			->leftJoin('#__eqa_buildings AS c', 'b.building_id=c.id')
			->leftJoin('#__eqa_examsessions AS d', 'a.examsession_id=d.id')
			->leftJoin('#__eqa_examseasons AS e', 'd.examseason_id=e.id')
			->leftJoin('#__eqa_academicyears AS f', 'e.academicyear_id=f.id')
			->where('a.id='. $id);
		$db->setQuery($query);
		$obj = $db->loadObject();
		$examroom = new ExamroomInfo();
		$examroom->id = $obj->id;
		$examroom->name = $obj->name;
		$examroom->building = $obj->building;
		$examroom->examTime = $obj->examtime;
		$examroom->academicyear = $obj->academicyear;
		$examroom->term = $obj->term;
		$examroom->attempt = $obj->attempt;
		$examroom->examseason = $obj->examseason;
		$examroom->examsession = $obj->examsession;
		$examroom->examsessionId = $obj->examsession_id;
		$examroom->monitor1Id = $obj->monitor1_id;
		$examroom->monitor2Id = $obj->monitor2_id;
		$examroom->monitor3Id = $obj->monitor3_id;
		$examroom->examiner1Id = $obj->examiner1_id;
		$examroom->examiner2Id = $obj->examiner2_id;

		//Đếm số thí sinh trong phòng thi
		$db->setQuery('SELECT COUNT(1) FROM `#__eqa_exam_learner` WHERE `examroom_id`='.(int)$id);
		$examroom->examineeCount = $db->loadResult();

		//Lấy danh sách các môn thi
		if(!empty($obj->exam_ids))
		{
			$examroom->examIds = explode(',', $obj->exam_ids);
			$examroom->exams = self::getExamNames($examroom->examIds);
		}
		else
		{
			$examroom->examIds=[];
			$examroom->exams = [];
		}

		//Hình thức thi
		$examroom->testtype = self::getExamroomTesttype($id);

		//Thời gian làm bài thi
		$examroom->testDuration = self::getExamroomTestDuration($id);

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
		$durations = array_filter($durations);

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
	static public function getExamsessionInfo(int $examsessionId)
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

		return $obj;
	}
	static public function getExamsessionExamrooms(int $examsessionId){
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('id, name, monitor1_id, monitor2_id, monitor3_id, examiner1_id, examiner2_id')
			->from('#__eqa_examrooms')
			->where('examsession_id='.$examsessionId);
		$db->setQuery($query);
		return $db->loadObjectList();
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
	static public function getExamsessionExamroomCounts(array $examsessionIds): array
	{
		//1. Lấy tất cả các cặp (examsession, examsession)
		$examsessionsIdSet = '(' . implode(',', $examsessionIds) . ')';
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('DISTINCT a.examroom_id, b.examsession_id')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_examrooms AS b', 'a.examroom_id = b.id')
			->where('a.examroom_id IS NOT NULL AND  b.examsession_id IN ' . $examsessionsIdSet);
		$db->setQuery($query);
		$items = $db->loadObjectList();

		//2. Khởi tạo bộ đếm
		$count = [];
		foreach ($examsessionIds as $examsessionsId)
			$count[$examsessionsId] = 0;

		//3. Đếm
		foreach ($items as $item){
			$count[$item->examsession_id]++;
		}

		//Return
		return $count;
	}
	static public function getExamsessionExamineeCounts(array $examsessionIds): array
    {
        //1. Lấy tất cả các cặp (learner, examsession)
        $examsessionsIdSet = '(' . implode(',', $examsessionIds) . ')';
        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('a.learner_id, b.examsession_id')
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_examrooms AS b', 'a.examroom_id = b.id')
            ->where('a.examroom_id IS NOT NULL AND b.examsession_id IN ' . $examsessionsIdSet);
        $db->setQuery($query);
        $items = $db->loadObjectList();

        //2. Khởi tạo bộ đếm
        $count = [];
        foreach ($examsessionIds as $examsessionsId)
            $count[$examsessionsId] = 0;

        //3. Đếm
        foreach ($items as $item){
            $count[$item->examsession_id]++;
        }

        //Return
        return $count;
    }
    static public function getExamineeCountOfExamrooms(array $examroomIds)
    {
        //1. Lấy tất cả các cặp (learner, examsession)
        $examroomIdSet = '(' . implode(',', $examroomIds) . ')';
        $db = self::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('learner_id, examroom_id')
            ->from('#__eqa_exam_learner')
            ->where('examroom_id IN ' . $examroomIdSet);
        $db->setQuery($query);
        $items = $db->loadObjectList();

        //2. Khởi tạo bộ đếm
        $count = [];
        foreach ($examroomIds as $examroomId)
            $count[$examroomId] = 0;

        //3. Đếm
        foreach ($items as $item){
            $count[$item->examroom_id]++;
        }

        //Return
        return $count;
    }

	/**
	 * @param   array   $employeeIds
	 * @param   string  $resultType Nhận các giá trị 'assoc', 'object'
	 *
	 *
	 * @since version
	 */
	static public function getEmployeeInfos(array $employeeIds, string $resultType='assoc')
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

		if($resultType === 'assoc')
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
	static public function getDefaultExamseason()
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('*')
			->from('#__eqa_examseasons')
			->where($db->quoteName('default') . '>0');
		$db->setQuery($query);
		return $db->loadObject();
	}
	static public function getDefaultExamseasonId()
	{
		$db = self::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('id')
			->from('#__eqa_examseasons')
			->where($db->quoteName('default') . '>0');
		$db->setQuery($query);
		return $db->loadResult();
	}
	static public function isCompletedExamsession(int $examsessionId) : bool
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
		return ExamHelper::TEST_TYPE_PAPER == $db->loadResult();
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
		if($res['exam_status']==ExamHelper::EXAM_STATUS_COMPLETED)
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
}