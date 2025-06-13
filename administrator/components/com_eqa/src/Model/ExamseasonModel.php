<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use ZipStream\Exception;

defined('_JEXEC') or die();

class ExamseasonModel extends EqaAdminModel{
    public function getSubjectIdsByExamseasonId(int $examseason_id)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->from('#__eqa_exams')
            ->select('subject_id')
            ->where('examseason_id = '.(int)$examseason_id);
        $db->setQuery($query);
        $ids = $db->loadColumn();
        return $ids;
    }
    public function getSubjectIdsByTerm(int $academicyear_id, int $term)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->from('#__eqa_classes')
            ->select('subject_id')
            ->where('academicyear_id = '.(int)$academicyear_id . ' AND term = '.$term);
        $db->setQuery($query);
        $ids = $db->loadColumn();
        return array_unique($ids, SORT_NUMERIC);
    }
    public function addExams($examseasonId, $cid){
        $app = Factory::getApplication();
	    $db = DatabaseHelper::getDatabaseDriver();

        if(empty($cid)){
            $msg = Text::_('COM_EQA_MSG_NO_ITEM_SPECIFIED');
            $app->enqueueMessage($msg,'warning');
            return;
        }
        $db->transactionStart();
        try{
            foreach ($cid as $subjectId){

                //1. Create an exam and get the exam id
                $db->setQuery('SELECT * FROM #__eqa_subjects WHERE id = '.$subjectId);
                $subject = $db->loadObject();
                $exam = [
                    'subject_id' => $subjectId,
                    'examseason_id' => $examseasonId,
                    'name' => $subject->name,
                    'testtype' => $subject->finaltesttype,
                    'duration' => $subject->finaltestduration,
                    'kmonitor' => $subject->kmonitor,
                    'kassess' => $subject->kassess,
                    'usetestbank' => empty($subject->testbankyear)?0:1,
                    'status' => ExamHelper::EXAM_STATUS_UNKNOWN
                ];
                $table = $this->getTable('exam');
                $table->save($exam);
                $examId = $db->insertid();

                //2. Get all the leaners in all the credit classes of this subject in this academic year and term
                //2.1. Load academic year and term
                $db->setQuery('SELECT * FROM #__eqa_examseasons WHERE id='.(int)$examseasonId);
                $examseason = $db->loadObject();
                //2.2. Get all the credit classes of this subject in this academic year and term
                $db->setQuery('SELECT id FROM #__eqa_classes WHERE academicyear_id='
                    . $examseason->academicyear_id
                    . ' AND term='.$examseason->term
                    . ' AND subject_id='.$subjectId);
                $classIds = $db->loadColumn();

                //2.3. Get all learners (with their class)
	            $classIds = array_map('intval', $classIds);
	            $classIdSet = '(' . implode(',', $classIds) . ')';
				$columns = $db->quoteName(
					array('a.learner_id', 'a.class_id', 'b.debtor'),
					array('learner_id', 'class_id', 'debtor')
				);
				$query = $db->getQuery(true)
					->select($columns)
					->from('#__eqa_class_learner AS a')
					->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
					->where('class_id IN ' . $classIdSet);
				$db->setQuery($query);
                $learners = $db->loadObjectList();

                //3. Add the leaners to this exam
                $columns = $db->quoteName(['exam_id','class_id', 'learner_id', 'debtor', 'attempt']);
                $attempt = $examseason->attempt;
                $values = array();
                foreach ($learners as $learner){
                    $classId = (int)$learner->class_id;
                    $learnerId = (int)$learner->learner_id;
					$debtor = (int)$learner->debtor;
                    $values[] = implode(',',[$examId,$classId,$learnerId, $debtor,$attempt]);
                }
                $query = $db->getQuery(true)
                    ->insert('#__eqa_exam_learner')
                    ->columns($columns)
                    ->values($values);
                $db->setQuery($query);
                $db->execute();
            }

            //Commit
            $db->transactionCommit();
			$msg = Text::sprintf('Thêm thành công %d môn thi', sizeof($cid));
            $app->enqueueMessage($msg, 'success');
        }
        catch (Exception $e){
            $db->transactionRollback();
            $msg = Text::_('COM_EQA_MSG_DATABASE_ERROR');
            $app->enqueueMessage($msg,'error');
        }
    }

	public function addRetakeExams($examseasonId)
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Các bước thực hiện
		//1. Lấy tất cả các HVSV ở tất cả các lớp học phần mà đã dự thi (ntaken>0)
		//   nhưng chưa đạt và còn quyền dự thi (expired=0)
		//2. Nhóm kết quả ở Bước 1 theo môn học
		//3. Với mỗi môn học tạo một môn thi và thêm HVSV tương ứng vào danh sách thi

		//Bước 1. Lấy tất cả các HVSV ở tất cả các lớp học phần mà đã dự thi (ntaken>0)
		//		  nhưng chưa đạt và còn quyền dự thi (expired=0)
		$columns = $db->quoteName(
			array('a.learner_id', 'a.class_id', 'a.ntaken', 'a.expired', 'b.subject_id', 'c.debtor'),
			array('learner_id',   'class_id',   'ntaken',   'expired',   'subject_id',   'debtor')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_classes AS b', 'b.id=a.class_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->where([
				'a.ntaken > 0',
				'a.expired = 0'
			]);
		$db->setQuery($query);
		$learners = $db->loadObjectList();

		//Bước 2. Nhóm kết quả ở Bước 1 theo môn học
		$subjectLearners = []; //Mảng chứa HVSV từng môn học
		foreach ($learners as $learner)
		{ //Với từng HVSV trong danh sách
			$subjectId = (int) $learner->subject_id; //Lấy mã môn học
			if (!isset($subjectLearners[$subjectId]))
				$subjectLearners[$subjectId] = [];
			$subjectLearners[$subjectId][] = $learner;
		}

		//Bước 3. Với mỗi môn học tạo một môn thi và thêm HVSV tương ứng vào danh sách thi
		$db->transactionStart(); //Bắt đầu giao dịch
		try{
			foreach ($subjectLearners as $subjectId=>$learners)
			{
				//3.1. Create an exam and get the exam id
				$db->setQuery('SELECT * FROM #__eqa_subjects WHERE id = '.$subjectId);
				$subject = $db->loadObject();
				$exam = [
					'subject_id' => $subjectId,
					'examseason_id' => $examseasonId,
					'name' => $subject->name,
					'testtype' => $subject->finaltesttype,
					'duration' => $subject->finaltestduration,
					'kmonitor' => $subject->kmonitor,
					'kassess' => $subject->kassess,
					'usetestbank' => empty($subject->testbankyear)?0:1,
					'status' => ExamHelper::EXAM_STATUS_PAM_BUT_QUESTION
				];
				$table = $this->getTable('exam');
				$table->save($exam);
				$examId = $db->insertid();

				//3.2. Thêm HVSV vào danh sách thi
				$columns = $db->quoteName(['exam_id','class_id', 'learner_id', 'debtor', 'attempt']);
				$values = array();
				foreach ($learners as $learner){
					$classId = (int)$learner->class_id;
					$learnerId = (int)$learner->learner_id;
					$debtor = (int)$learner->debtor;
					$attempt = (int)$learner->ntaken+1;
					$values[] = implode(',',[$examId,$classId,$learnerId, $debtor,$attempt]);
				}
				$query = $db->getQuery(true)
					->insert('#__eqa_exam_learner')
					->columns($columns)
					->values($values);
				$db->setQuery($query);
				$db->execute();
			}
		}
		catch(Exception $e){
			$db->transactionRollback();
			$app->enqueueMessage($e->getMessage(),'error');
		}
		finally{
			$db->transactionCommit();
			$app->enqueueMessage('Thêm thành công', 'success');
		}
	}
	public function enablePpaaReq(int $examseasonId):void
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Lấy thông tin hiện thời về việc phúc khảo
		$query = $db->getQuery(true)
			->from('#__eqa_examseasons')
			->select('name AS examseason, ppaa_req_enabled AS enabled, ppaa_req_deadline AS deadline, completed')
			->where('id='.$examseasonId);
		$db->setQuery($query);
		$ppaaReq = $db->loadObject();

		//Nếu kỳ thi đã xong thì thôi
		if($ppaaReq->completed)
		{
			$msg = Text::sprintf('Kỳ thi <b>%s</b> đã kết thúc, không thể phúc khảo.',
				htmlspecialchars($ppaaReq->examseason)
			);
			$app->enqueueMessage($msg, 'error');
			return;
		}

		//Nếu vốn đã mở rồi thì thôi
		if($ppaaReq->enabled)
		{
			$msg = Text::sprintf('Kỳ thi <b>%s</b>: quyền gửi yêu cầu phúc khảo đã được mở. Không có thay đổi nào được thực hiện',
				htmlspecialchars($ppaaReq->examseason)
			);
			$app->enqueueMessage($msg, 'warning');
			return;
		}

		//Nếu chưa mở thì mở
		$query = $db->getQuery(true)
			->update('#__eqa_examseasons')
			->set('ppaa_req_enabled=1')
			->where('id='.$examseasonId);
		$db->setQuery($query);

		//Nếu có lỗi thì thông báo
		if(!$db->execute())
		{
			$app->enqueueMessage('Lỗi truy vấn CSDL', 'error');
			return;
		}

		//Nếu thành công thì kiểm tra thêm về deadline
		$msg = Text::sprintf('Kỳ thi <b>%s</b>: đã mở quyền gửi yêu cầu phúc khảo.',
			htmlspecialchars($ppaaReq->examseason));
		$app->enqueueMessage($msg, 'success');
		if(empty($ppaaReq->deadline))
			$app->enqueueMessage('Thời hạn phúc khảo không xác định', 'warning');
		else
			$app->enqueueMessage('Thời hạn phúc khảo: ' . htmlspecialchars($ppaaReq->deadline),'info');
	}

	public function disablePpaaReq(int $examseasonId):void
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();


		//Lấy thông tin hiện thời về việc phúc khảo
		$query = $db->getQuery(true)
			->from('#__eqa_examseasons')
			->select('name AS examseason, ppaa_req_enabled AS enabled, ppaa_req_deadline AS deadline')
			->where('id='.$examseasonId);
		$db->setQuery($query);
		$ppaaReq = $db->loadObject();

		//Nếu vốn đang đóng rồi thì thôi
		if(!$ppaaReq->enabled)
		{
			$msg = Text::sprintf('Kỳ thi <b>%s</b>: quyền gửi yêu cầu phúc khảo đang đóng. Không có thay đổi nào được thực hiện',
				htmlspecialchars($ppaaReq->examseason)
			);
			$app->enqueueMessage($msg, 'warning');
			return;
		}

		//Nếu đang mở thì đónglại
		$query = $db->getQuery(true)
			->update('#__eqa_examseasons')
			->set('ppaa_req_enabled=0')
			->where('id=' . $examseasonId);
		$db->setQuery($query);

		if(!$db->execute())
		{
			$app->enqueueMessage('Lỗi truy vấn CSDL', 'error');
		}
		else
		{
			$msg = Text::sprintf('Kỳ thi <b>%s</b>: đã đóng quyền gửi yêu cầu phúc khảo.',
				htmlspecialchars($ppaaReq->examseason));
			$app->enqueueMessage($msg, 'success');
		}
	}

    public function setCompleteStatus($cid, bool $status)
    {
        $app = Factory::getApplication();
        try {
            $db = $this->getDatabase();
            $set = '(' . implode(',', $cid) . ')';
            $query = $db->getQuery(true)
                ->update('#__eqa_examseasons')
                ->where('id IN '. $set);
			if($status)
				$query->set([
					'`completed`=1',
					'`default`=0',
					'`ppaa_req_enabled`=0']);     //Completed cannot be default
	        else
				$query->set('completed=0');
            $db->setQuery($query);
            if($db->execute())
                $app->enqueueMessage(Text::_('COM_EQA_MSG_TASK_SUCCESS'),'success');
            else
                $app->enqueueMessage(Text::_('COM_EQA_MSG_ERROR_TASK_FAILED'), 'error');
        }
        catch (\Exception $e){
            $app->enqueueMessage($e->getMessage(), 'error');
        }
    }

	public function getExaminees(int $examseasonId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select([
				'DISTINCT(a.learner_id) AS id',
				'd.code AS code',
				'd.lastname AS lastname',
				'd.firstname AS firstname',
				'`e`.`code` AS `group`',
				'f.code AS course'
			])
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id = a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id = b.examseason_id')
			->leftJoin('#__eqa_learners AS d', 'd.id = a.learner_id')
			->leftJoin('#__eqa_groups AS e', 'e.id=d.group_id')
			->leftJoin('#__eqa_courses AS f', 'f.id=e.course_id')
			->where('c.id='.$examseasonId);
		$db->setQuery($query);
		return $db->loadAssocList();
	}
	public function getQuestionProductions(array $examseasonIds): array|null
	{
		$db = DatabaseHelper::getDatabaseDriver();

		$examseasonIds = array_filter($examseasonIds, 'intval');
		if(empty($examseasonIds))
			return null;

		$examseasonIdSet = '(' . implode(',', $examseasonIds) . ')';

		//Lấy tất cả record về bàn giao đề thi
		$columns = $db->quoteName(
			array('a.questionauthor_id', 'b.lastname', 'b.firstname', 'c.code', 'a.name', 'a.nquestion'),
			array('id',                  'lastname',   'firstname',   'unit',   'exam',   'nquestion')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_employees AS b', 'b.id=a.questionauthor_id')
			->leftJoin('#__eqa_units AS c', 'c.id=b.unit_id')
			->where([
				'a.nquestion>0',
				'a.examseason_id IN ' . $examseasonIdSet
			])
			->order('unit, firstname');
		$db->setQuery($query);
		$questionAuthors = $db->loadAssocList();

		//If empty
		if(empty($questionAuthors))
			return null;

		//Gộp lại theo tác giả đề thi
		$questionProduction = [];
		foreach ($questionAuthors as $author)
		{
			$key = $author['id'];
			if(array_key_exists($key, $questionProduction))
			{
				$questionProduction[$key]['count'] += $author['nquestion'];
				$questionProduction[$key]['details'] .= ', ' . $author['exam'] . ' (' . $author['nquestion'] . ')';
			}
			else
			{
				$questionProduction[$key] = [
					'lastname'  => $author['lastname'],
					'firstname' => $author['firstname'],
					'unit'      => $author['unit'],
					'count'     => $author['nquestion'],
					'details'   => $author['exam'] . ' (' . $author['nquestion'] . ')'
				];
			}
		}

		//Return
		return $questionProduction;
	}

	public function getMonitoringProductions(array $examseasonIds): array|null
	{
		$db = DatabaseHelper::getDatabaseDriver();

		$examseasonIds = array_filter($examseasonIds, 'intval');
		if(empty($examseasonIds))
			return null;

		$examseasonIdSet = '(' . implode(',', $examseasonIds) . ')';

		//Lấy tất cả các records về coi thi
		$columns = $db->quoteName(
			array('a.monitor1_id', 'a.monitor2_id', 'a.monitor3_id', 'a.exam_ids', 'b.start'),
			array('monitor1_id',   'monitor2_id',   'monitor3_id',   'exam_ids',   'start')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_examrooms AS a')
			->leftJoin('#__eqa_examsessions AS b', 'b.id=a.examsession_id')
			->where([
				'b.examseason_id IN ' . $examseasonIdSet
			]);
		$db->setQuery($query);
		$items = $db->loadAssocList();

		//Tính toán hệ số sản lượng coi thi
		$examrooms = [];
		foreach ($items as $item)
		{
			$examIdSet = '(' . $item['exam_ids'] . ')';
			$query = $db->getQuery(true)
				->select('kmonitor')
				->from('#__eqa_exams')
				->where('id IN ' . $examIdSet);
			$db->setQuery($query);
			$kmonitors = $db->loadColumn();
			$item['kmonitor'] = max($kmonitors);
			$examrooms[] = $item;
		}

		//Đếm năng suất, có tính đến hệ số coi thi
		$monitoringProductions = [];
		$kWeekendMonitoring = ConfigHelper::getKWeekendMonitoring();
		foreach ($examrooms as $examroom){
			$examTime = $examroom['start'];
			$examroomMonitoringProduction = $examroom['kmonitor'];
			if(DatetimeHelper::isWeekend($examTime))
				$examroomMonitoringProduction *= $kWeekendMonitoring;

			$monitorId = $examroom['monitor1_id'];
			if(!is_null($monitorId)){
				if(array_key_exists($monitorId, $monitoringProductions))
				{
					$count = $monitoringProductions[$monitorId]['count'] + 1;
					$production = $monitoringProductions[$monitorId]['production'] + $examroomMonitoringProduction;
					$monitoringProductions[$monitorId] = [
						'count' => $count,
						'production' => $production
					];
				}
				else{
					$monitoringProductions[$monitorId] = [
						'count' => 1,
						'production' => $examroomMonitoringProduction
					];
				}
			}

			$monitorId = $examroom['monitor2_id'];
			if(!is_null($monitorId)){
				if(array_key_exists($monitorId, $monitoringProductions))
				{
					$count = $monitoringProductions[$monitorId]['count'] + 1;
					$production = $monitoringProductions[$monitorId]['production'] + $examroomMonitoringProduction;
					$monitoringProductions[$monitorId] = [
						'count' => $count,
						'production' => $production
					];
				}
				else{
					$monitoringProductions[$monitorId] = [
						'count' => 1,
						'production' => $examroomMonitoringProduction
					];
				}
			}

			$monitorId = $examroom['monitor3_id'];
			if(!is_null($monitorId)){
				if(array_key_exists($monitorId, $monitoringProductions))
				{
					$count = $monitoringProductions[$monitorId]['count'] + 1;
					$production = $monitoringProductions[$monitorId]['production'] + $examroomMonitoringProduction;
					$monitoringProductions[$monitorId] = [
						'count' => $count,
						'production' => $production
					];
				}
				else{
					$monitoringProductions[$monitorId] = [
						'count' => 1,
						'production' => $examroomMonitoringProduction
					];
				}
			}
		}

		//Định dạng dữ liệu trả về
		$monitorIds = array_keys($monitoringProductions);
		$monitorIdSet = '(' . implode(',', $monitorIds) . ')';
		$columns = $db->quoteName(
			array('a.id', 'b.code', 'a.lastname', 'a.firstname'),
			array('id',   'unit',   'lastname',   'firstname')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_employees AS a')
			->leftJoin('#__eqa_units AS b', 'b.id=a.unit_id')
			->where('a.id IN ' . $monitorIdSet)
			->order('firstname, lastname');
		$db->setQuery($query);
		$items = $db->loadAssocList();
		$monitors = [];
		foreach ($items as $item)
		{
			$monitorId = $item['id'];
			$item['count'] = $monitoringProductions[$monitorId]['count'];
			$item['production'] = $monitoringProductions[$monitorId]['production'];
			$monitors[] = $item;
		}

		return $monitors;
	}

	public function getMarkingProductions(array $examseasonIds): array|null
	{
		$db = DatabaseHelper::getDatabaseDriver();

		$examseasonIds = array_filter($examseasonIds, 'intval');
		if(empty($examseasonIds))
			return null;

		//Lấy danh sách các môn thi của các kỳ thi này
		$examseasonIdSet = '(' . implode(',', $examseasonIds) . ')';
		$query = $db->getQuery(true)
			->select('id, kassess, name')
			->from('#__eqa_exams')
			->where('examseason_id IN ' . $examseasonIdSet);
		$db->setQuery($query);
		$examInfos = $db->loadAssocList('id');
		$examIds = array_keys($examInfos);
		$examIdSet = '(' . implode(',' , $examIds) . ')';

		/**
		 * Cấu trúc của kết quả cốt lõi sẽ như sau
		 * [employee_id] =>[exam_id] [count1, count2, kassess, name]
		 */
		$markingProductions = [];

		//1. Sản lượng chấm thi thực hành, vấn đáp, tự luận
		//a) Query tự luận
		$columns = $db->quoteName(
			array('a.exam_id', 'b.examiner1_id', 'b.examiner2_id'),
			array('exam_id',   'examiner1_id',   'examiner2_id')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_papers AS a')
			->leftJoin('#__eqa_packages AS b', 'b.id = a.package_id')
			->where([
				'a.exam_id IN ' .  $examIdSet,
				'b.examiner1_id IS NOT NULL',
				'b.examiner2_id IS NOT NULL'
			]);
		$db->setQuery($query);
		$items1 = $db->loadAssocList();

		//b) Query thi thực hành, vấn đáp, đồ án
		$columns = $db->quoteName(
			array('a.exam_id', 'b.examiner1_id', 'b.examiner2_id'),
			array('exam_id',   'examiner1_id',   'examiner2_id')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_examrooms AS b', 'b.id=a.examroom_id')
			->where([
				'a.exam_id IN ' . $examIdSet,
				'b.examiner1_id IS NOT NULL',
				'b.examiner2_id IS NOT NULL'
			]);
		$db->setQuery($query);
		$items2 = $db->loadAssocList();

		//c) Kêt hợp kết quả và xử lý
		$items = array_merge($items1, $items2);
		foreach ($items as $item)
		{
			$examId = $item['exam_id'];

			$examinerId = $item['examiner1_id'];
			if(!array_key_exists($examinerId, $markingProductions))
				$markingProductions[$examinerId] = [];
			if(array_key_exists($examId, $markingProductions[$examinerId]))
				$markingProductions[$examinerId][$examId]['count1']++;
			else
				$markingProductions[$examinerId][$examId]=[
					'count1' => 1,
					'count2' => 0,
					'kassess' => $examInfos[$examId]['kassess'],
					'name' => $examInfos[$examId]['name']
				];

			$examinerId = $item['examiner2_id'];
			if(!array_key_exists($examinerId, $markingProductions))
				$markingProductions[$examinerId] = [];
			if(array_key_exists($examId, $markingProductions[$examinerId]))
				$markingProductions[$examinerId][$examId]['count2']++;
			else
				$markingProductions[$examinerId][$examId]=[
					'count1' => 0,
					'count2' => 1,
					'kassess' => $examInfos[$examId]['kassess'],
					'name' => $examInfos[$examId]['name']
				];
		}

		//2. Sản lượng chấm thi trên máy
		$query = $db->getQuery(true)
			->select('exam_id, examiner_id, role, quantity')
			->from('#__eqa_mmproductions')
			->where('exam_id IN ' . $examIdSet);
		$db->setQuery($query);
		$items = $db->loadAssocList();
		foreach ($items as $item)
		{
			$examId = $item['exam_id'];
			$examinerId = $item['examiner_id'];
			$role = $item['role'];
			$quantity = $item['quantity'];
			if(!array_key_exists($examinerId, $markingProductions))
				$markingProductions[$examinerId] = [];
			if(!array_key_exists($examId, $markingProductions[$examinerId]))
			{
				$markingProductions[$examinerId][$examId] = [
					'count1' => $role==1 ? $quantity : 0,
					'count2' => $role==2 ? $quantity : 0,
					'kassess' => $examInfos[$examId]['kassess'],
					'name' => $examInfos[$examId]['name']
				];
			}
			else{
				if($role==1)
					$markingProductions[$examinerId][$examId]['count1'] += $quantity;
				elseif($role==2)
					$markingProductions[$examinerId][$examId]['count2'] += $quantity;
			}
		}

		//Return
		return $markingProductions;
	}

	public function getMarkStatistic(array $examseasonIds): array
	{
		//Init
		if(empty($examseasonIds))
			return [];
		$db = DatabaseHelper::getDatabaseDriver();

		//Get exam ids
		$examseasonIdSet = '(' . implode(',', $examseasonIds) . ')';
		$query = $db->getQuery(true)
			->select('id, name, testtype')
			->from('#__eqa_exams')
			->where([
				'status >= ' . ExamHelper::EXAM_STATUS_MARK_FULL,
				'examseason_id IN ' . $examseasonIdSet
			]);
		$db->setQuery($query);
		$exams = $db->loadAssocList('id');
		if(empty($exams))
			return [];

		//Lấy thông tin tất cả các thí sinh của tất cả các môn thi
		$examIds = array_keys($exams);
		$examIdSet = '(' . implode(',', $examIds) . ')';
		$columns = $db->quoteName(
			array('a.exam_id', 'a.module_mark', 'a.module_grade', 'a.conclusion'),
			array('exam_id',   'module_mark',   'module_grade',   'conclusion')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->where('a.exam_id IN ' . $examIdSet);
		$db->setQuery($query);
		$examinees = $db->loadAssocList();
		if(empty($examinees))
			return [];

		//Tính toán thống kê
		//$stat = [examId] => ['name', 'total', 'passed', 'failed', 'other', 'avg_passed', 'avg', 'sum_passed', 'sum']
		$mainGrades = ['F', 'D', 'D+', 'C', 'C+', 'B', 'B+', 'A', 'A+'];
		$stat = [];
		foreach ($examinees as $examinee)
		{
			$examId = $examinee['exam_id'];
			$moduleMark = $examinee['module_mark'];
			$moduleGrade = $examinee['module_grade'];
			$conclusion = $examinee['conclusion'];
			if(!array_key_exists($examId, $stat))
				$stat[$examId] = [
					'name' => $exams[$examId]['name'],
					'testtype' => $exams[$examId]['testtype'],
					'total' => 0,
					'passed' => 0,
					'failed' => 0,
					'other'=> 0,
					'avg' => null,
					'avg_pased' => null,
					'sum' => 0,
					'sum_passed' => 0,
					'F' => 0,
					'D' => 0,
					'D+' => 0,
					'C' => 0,
					'C+' => 0,
					'B' => 0,
					'B+' => 0,
					'A' => 0,
					'A+' => 0,
					'I' => 0
				];

			//Đếm phân loại thí sinh và tính tổng điểm
			$stat[$examId]['total']++;
			if(!is_null($moduleMark))
			{
				if($conclusion == ExamHelper::CONCLUSION_PASSED)
				{
					$stat[$examId]['passed']++;
					$stat[$examId]['sum_passed'] += $moduleMark;
				}
				else
					$stat[$examId]['failed']++;
				$stat[$examId]['sum'] += $moduleMark;

				//Grade
				$stat[$examId][$moduleGrade]++;
			}
			else
				$stat[$examId]['other']++;
		}

		//Tính điểm trung bình
		foreach ($stat as &$item)
		{
			if($item['passed']>0 || $item['failed']>0)
			{
				$avg = (float) $item['sum'] / ($item['passed'] + $item['failed']);
				$item['avg'] = round($avg, 2);
			}
			if($item['passed']>0)
			{
				$avg = $item['sum_passed']/$item['passed'];
				$item['avg_passed'] = round($avg, 2);
			}
		}
		unset($item);

		//Return
		return $stat;
	}

	public function getStatistic(array $examseasonIds)
	{
		if(empty($examseasonIds))
			return [];
		$db = DatabaseHelper::getDatabaseDriver();
		$stat = [];
		$examseasonIdSet = '(' . implode(',', $examseasonIds) . ')';

		//Đếm số ca thi
		$db->setQuery('SELECT id FROM #__eqa_examsessions WHERE examseason_id IN ' . $examseasonIdSet);
		$examsessionIds = $db->loadColumn();
		if(empty($examsessionIds))
			return [];
		$stat['Số ca thi'] = sizeof($examsessionIds);

		//Đếm số phòng thi
		$examsessionIdSet = '(' . implode(',', $examsessionIds) . ')';
		$db->setQuery('SELECT id FROM #__eqa_examrooms WHERE examsession_id IN ' . $examsessionIdSet);
		$examroomIds = $db->loadColumn();
		if(empty($examroomIds))
			return [];
		$stat['Số phòng thi'] = sizeof($examroomIds);

		//Get ExamIds
		$query = $db->getQuery(true)
			->select('id, testtype')
			->from('#__eqa_exams')
			->where('examseason_id IN ' . $examseasonIdSet);
		$db->setQuery($query);
		$exams = $db->loadAssocList('id');
		if(empty($exams))
			return [];

		//Phân loại môn thi theo hình thức thi
		$machineExamIds = [];
		$paperExamIds = [];
		$otherExamIds = [];
		foreach ($exams as $id => $exam)
		{
			switch ($exam['testtype']){
				case ExamHelper::TEST_TYPE_MACHINE_OBJECTIVE:
				case ExamHelper::TEST_TYPE_MACHINE_HYBRID:
					$machineExamIds[] = $id;
					break;
				case ExamHelper::TEST_TYPE_PAPER:
					$paperExamIds[] = $id;
					break;
				default:
					$otherExamIds[] = $id;
			}
		}
		$stat['Số lượng môn thi'] = sizeof($exams);
		$stat['Số lượng môn thi máy'] = sizeof($machineExamIds);
		$stat['Số lượng môn thi viết'] = sizeof($paperExamIds);
		$stat['Số lượng môn thi khác'] = sizeof($otherExamIds);

		//Số lượng bài thi
		$examIds = array_keys($exams);
		$examIdSet = '(' . implode(',', $examIds) . ')';
		$columns = $db->quoteName(
			array('b.testtype'),
			array('testtype')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->where([
				'a.code IS NOT NULL',
				'a.exam_id IN ' . $examIdSet
			]);
		$db->setQuery($query);
		$items = $db->loadColumn();
		$countMachine=0;
		$countPaper=0;
		$countOther=0;
		foreach ($items as $item)
		{
			switch ($item)
			{
				case ExamHelper::TEST_TYPE_MACHINE_OBJECTIVE:
				case ExamHelper::TEST_TYPE_MACHINE_HYBRID:
					$countMachine++;
					break;
				case ExamHelper::TEST_TYPE_PAPER:
					$countPaper++;
					break;
				default:
					$countOther++;
			}
		}
		$stat['Tổng số lượt thi'] = sizeof($items);
		$stat['Số lượt thi máy'] = $countMachine;
		$stat['Số lượt thi viết'] = $countPaper;
		$stat['Số lượt thi khác'] = $countOther;



		//Return
		return $stat;
	}
}
