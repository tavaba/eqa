<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Collator;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Enum\ExamStatus;
use Kma\Library\Kma\Model\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\RoomHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;

defined('_JEXEC') or die();

class ExamModel extends AdminModel{
	public function prepareTable($table)
	{
		if(empty($table->questiondeadline))
			$table->questiondeadline=null;
		if(empty($table->questiondate))
			$table->questiondate=null;
		if(empty($table->questionsender_id))
			$table->questionsender_id=null;
		if(empty($table->questionauthor_id))
			$table->questionauthor_id=null;
		if(empty($table->nquestion))
			$table->nquestion=null;
		if (empty($table->allowed_rooms)) {
			$table->allowed_rooms = null;
		}
	}

	/**
	 * Ghi đè để encode allowed_rooms trước khi lưu
	 *
	 * @param   array  $data  Form data
	 *
	 * @return  bool
	 * @since   2.0.1
	 */
	public function save($data): bool
	{
		// Encode allowed_rooms: array → JSON string; rỗng → null (xử lý trong prepareTable)
		if (isset($data['allowed_rooms'])) {
			if (is_array($data['allowed_rooms'])) {
				$filteredIds = array_filter(array_map('intval', $data['allowed_rooms']));
				$filteredIds = array_values(array_unique($filteredIds));
				$data['allowed_rooms'] = !empty($filteredIds)
					? json_encode($filteredIds, JSON_UNESCAPED_UNICODE)
					: null;
			} elseif (empty($data['allowed_rooms'])) {
				$data['allowed_rooms'] = null;
			}
		}

		return parent::save($data);
	}

	/**
	 * Ghi đè để parse allowed_rooms
	 *
	 * @param   mixed  $pk  Primary key
	 *
	 * @return  \stdClass|bool
	 * @since   2.0.1
	 */
	public function getItem($pk = null): bool|\stdClass
	{
		$item = parent::getItem($pk);

		if ($item === false) {
			return false;
		}

		// Parse allowed_rooms từ JSON string sang array để RoomField (multiple=true) hoạt động
		if (!empty($item->allowed_rooms)) {
			$decoded = json_decode($item->allowed_rooms, true);
			$item->allowed_rooms = is_array($decoded) ? $decoded : [];
		} else {
			$item->allowed_rooms = [];
		}

		return $item;
	}

	/**
	 * Add examinees into an exam. This must search for 'ntaken', 'expired'.
	 * This also calls updateDebt() and updateStimulation() after adding.
	 * Existing examinees are ignored.
	 *
	 * @param   int    $examId
	 * @param   array  $classLearners Each object must contain at least these fields:
	 *                 'classId', 'learnerId','ntaken'. If the field 'expired' also exist
	 *                 then it will be used to decide whether to add the learner to the exam
	 *                 or not; otherwise all learners in the list are considered as NOT expired
	 *                 and they all will be added to the exam.
	 * @return int How many examinees have been successfully added
	 *
	 * @throws Exception
	 * @since 1.1.2
	 */
	public function addExaminees(int $examId, array $classLearners): int
	{
		$db = $this->getDatabase();

		//1. Prepare data
		$columns = $db->quoteName(['exam_id', 'class_id', 'learner_id', 'attempt']);
		$values = [];
		foreach ($classLearners as $item){
			if(!isset($item->expired) || !$item->expired)
				$values[] = implode(',',[$examId,$item->classId,$item->learnerId, $item->ntaken+1]);
		}
		if(count($values)==0)
			return 0;

		//2. Add examinees to the exam, skipping the existing ones
		$query = $db->getQuery(true)
			->insert('#__eqa_exam_learner')
			->columns($columns)
			->values($values);
		// Get the raw SQL and modify it to use INSERT IGNORE
		$sql = (string) $query;
		$sql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql);
		$db->setQuery($sql);
		$db->execute();

		//Remember the number of examinees added
		$countAdded = $db->getAffectedRows();

		//Update debt and stimulation
		$this->updateDebt($examId);
		$this->updateStimulations($examId);

		//Return the number of examinees added
		return $countAdded;
	}
    public function removeExaminees(int $examId, array $learnerIds): bool
    {
	    if (DatabaseHelper::isCompletedExam($examId))
		    throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể xóa thí sinh');

        $app = Factory::getApplication();
        $db = $this->getDatabase();
        $db->transactionStart();
        try{
            //Remove examinees
            $query = $db->getQuery(true)
                ->delete('#__eqa_exam_learner')
                ->where('exam_id='.$examId.' AND learner_id IN ('.implode(',',$learnerIds).')');
            $db->setQuery($query);
            $db->execute();

            //Commit
            $db->transactionCommit();
        }
        catch (Exception $e){
            $db->transactionRollback();
            $app->enqueueMessage($e->getMessage(),'error');
            return false;
        }
        $msg = Text::sprintf('COM_EQA_N_ITEMS_DELETED',sizeof($learnerIds));
        $app->enqueueMessage($msg,'success');
        return true;
    }

    /**
     * Thêm (thủ công) HVSV của một lớp học phần vào một môn thi (thường chỉ sử dụng
     * trong trường hợp bổ sung thí sinh hoãn thi từ kỳ trước)
     *
     * @param int      $examId
     * @param string   $classCode
     * @param   array  $learnerCodes
     *
     * @return bool
     * @since 1.0.3
     */
    public function addExamineesFromClass(int $examId, string $classCode, array $learnerCodes, int $attempt, bool $ignoreError, bool $addExpired): bool
    {
	    if (DatabaseHelper::isCompletedExam($examId))
		    throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể thêm thí sinh');

        $db = DatabaseHelper::getDatabaseDriver();

        //Find the class by its code ($classCode)
        $db->setQuery('SELECT * FROM #__eqa_classes WHERE code='.$db->quote($classCode));
        $class = $db->loadObject();
        if(empty($class))
        {
            $msg = Text::sprintf('Không tìm thấy lớp học phần <b>%s</b>', htmlentities($classCode));
			throw new Exception($msg);
        }

        //Check to ensure that the class and the exam belong to the same subject
        $db->setQuery('SELECT * FROM #__eqa_exams WHERE id='.$examId);
        $exam = $db->loadObject();
        if($class->subject_id != $exam->subject_id){
            $msg = Text::sprintf('Lớp học phần <b>%s</b> không phù hợp với môn thi <b>%s</b>',
                htmlentities($class->name),
                htmlentities($exam->name));
            throw new Exception($msg);
        }

        //Try to add the learners to the exam
	    $app = Factory::getApplication();
	    $countAdded=0;
	    foreach ($learnerCodes as $learnerCode)
	    {
		    //Get learner info
		    $columns = $db->quoteName(
				array('b.id', 'a.expired', 'b.debtor'),
			    array('id',   'expired',   'debtor')
		    );
			$query = $db->getQuery(true)
				->select($columns)
				->from('#__eqa_class_learner AS a')
				->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
				->where('a.class_id='.$class->id.' AND b.code=' . $db->quote($learnerCode));
		    $db->setQuery($query);
			$classLearner = $db->loadObject();

			//The required learner must exist
			if(empty($classLearner))
			{
				$msg = Text::sprintf('HVSV <b>%s</b> không tồn tại trong lớp học phần <b>%s</b>', $learnerCode, $classCode);
				if($ignoreError)
				{
					$app->enqueueMessage($msg,'warning');
					continue;
				}
				else
					throw new Exception($msg);
			}

		    if ($classLearner->expired && !$addExpired)
		    {
			    $msg = Text::sprintf('Trong lớp %s, HVSV <b>%s</b> đã hết quyền dự thi', $classCode, $learnerCode);
				if($ignoreError)
				{
					$app->enqueueMessage($msg,'warning');
					continue;
				}
				else
				    throw new Exception($msg);
		    }

			//Check if the learner is already added to this exam
		    $db->setQuery("SELECT COUNT(1) FROM #__eqa_exam_learner WHERE exam_id=$examId AND learner_id={$classLearner->id}");
		    if($db->loadResult())
		    {
				$msg = Text::sprintf('HVSV <b>%s</b> đã có trong danh sách môn thi <b>%s</b>', $learnerCode, $exam->name);
			    if($ignoreError)
			    {
				    $app->enqueueMessage($msg,'warning');
				    continue;
			    }
			    else
				    throw new Exception($msg);
		    }

		    //Add learner to the exam
		    $query = $db->getQuery(true)
			    ->insert('#__eqa_exam_learner')
			    ->columns('exam_id, class_id, learner_id, debtor, attempt')
			    ->values(implode(',', [$examId, $class->id, $classLearner->id, $classLearner->debtor, $attempt]));
		    $db->setQuery($query);
		    if (!$db->execute())
		    {
			    $msg = Text::sprintf('Thêm HVSV <b>%s</b> vào môn thi <b>%s</b> thất bại', $learnerCode, $exam->name);
			    if($ignoreError)
			    {
				    $app->enqueueMessage($msg,'warning');
				    continue;
			    }
			    else
				    throw new Exception($msg);
		    }
			else
				$countAdded++;
	    }
		return $countAdded;
    }
	public function addFailedExaminees(int $examId): bool
	{
		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể thêm thí sinh');

		$app = Factory::getApplication();
		$db = $this->getDatabase();

		//Các bước tiếp theo cần thực hiện
		//1. Xác định ID của môn học
		//2. Lấy danh sách tất cả các lớp học phần của học phần đó
		//3. Lấy danh sách tất cả các HVSV trong các lớp học phần đã xác định
		//   mà đã thi tối thiểu một lần ('not_taken'>0) và vẫn còn quyền dự thi ('expired'=0)
		//4. Thêm các HVSV đã chọn vào môn thi

		$db->transactionStart();
		try{
			//1. Xác định ID của môn học
			$query = $db->getQuery(true)
				->select('subject_id')
				->from('#__eqa_exams')
				->where('id='.$examId);
			$db->setQuery($query);
			$subjectId = $db->loadResult();
			if (empty($subjectId))
				throw new Exception("Không tìm thấy môn học");

			//2. Lấy danh sách tất cả các lớp học phần của học phần đó
			$query = $db->getQuery(true)
				->select('id')
				->from('#__eqa_classes')
				->where('subject_id='.$subjectId);
			$db->setQuery($query);
			$classIds = $db->loadColumn();
			if (empty($classIds))
				throw new Exception("Không tìm thấy lớp học phần nào");

			//3. Lấy danh sách tất cả các HVSV trong các lớp học phần đã xác định
			//   mà đã thi tối thiểu một lần ('ntaken'>0) và vẫn còn quyền dự thi ('expired'=0)
			$columns = $db->quoteName(
				array('a.class_id', 'a.learner_id', 'a.pam1', 'a.pam2', 'a.pam', 'a.ntaken', 'b.debtor'),
				array('classId',    'learnerId',     'pam1',  'pam2',   'pam',   'ntaken',   'debtor')
			);
			$query = $db->getQuery(true)
				->select($columns)
				->from('#__eqa_class_learner as a')
				->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
				->where([
					'a.class_id IN (' . implode(',', $classIds) . ')',
					'a.ntaken > 0',
					'a.expired=0'
				]);
			$db->setQuery($query);
			$examinees = $db->loadObjectList();
			if (empty($examinees))
				throw new Exception("Không tìm thấy HVSV nào");

			//4. Thêm các HVSV đã chọn vào môn thi
			$insertTuples = [];
			foreach ($examinees as $examinee){
				$insertTuple = [
					$examId,                    //exam_id
					$examinee->classId,         //class_id
					$examinee->learnerId,       //learner_id
					$examinee->debtor,          //debtor
					$examinee->ntaken+1         //attempt
				];
				$insertTuples[] = implode(',', $insertTuple);
			}
			$query = $db->getQuery(true)
				->insert('#__eqa_exam_learner')
				->columns('exam_id, class_id, learner_id, debtor, attempt')
				->values($insertTuples);
			$db->setQuery($query);
			if(!$db->execute()){
				throw new Exception('Lỗi thêm thí sinh vào môn thi');
			}
		}
		catch(Exception $e){
			$db->transactionRollback();
			$app->enqueueMessage($e->getMessage(), 'error');
			return false;
		}
		finally{
			$db->transactionCommit();
			return true;
		}
	}

	/**
	 * Hoãn thi cho một số thí sinh của một môn thi.
	 * Những thí sinh đã có bất thường, hoặc không đủ điều kiện dự thi,
	 * hoặc đang nợ học phí sẽ KHÔNG được hoãn thi và sẽ được thông báo rõ ràng.
	 *
	 * @param   int    $examId
	 * @param   array  $examineeIds
	 *
	 * @return bool
	 *
	 * @throws Exception
	 * @since 1.0.0
	 */
	public function delayExaminees(int $examId, array $examineeIds):bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$app = Factory::getApplication();

		//Xác định những thí sinh đã có bất thường
		//Hoặc không đủ điều kiện dự thi
		//Hoặc đang nợ học phí
		$examineeIdSet = '(' . implode(',', $examineeIds) . ')';
		$columns = $db->quoteName(
			array('a.learner_id', 'b.code'),
			array('id',           'code')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_class_learner AS c', 'c.class_id=a.class_id AND c.learner_id=a.learner_id')
			->where([
				'a.exam_id=' . $examId,
				'a.learner_id IN ' . $examineeIdSet,
				'(a.anomaly>0 OR b.debtor>0 OR c.allowed=0)'
			]);
		$db->setQuery($query);
		$excludedLearners = $db->loadAssocList();
		$excludedLearnerCodes = array_column($excludedLearners, 'code');
		if(!empty($excludedLearnerCodes))
		{
			$msg = Text::sprintf('Không thể hoãn thi cho %d thí sinh vì đã có bất thường, hoặc điểm quá trình không đạt, hoặc đang nợ học phí: <b>%s</b>',
				sizeof($excludedLearnerCodes),
				implode(', ', $excludedLearnerCodes)
			);
			$app->enqueueMessage($msg,'warning');
		}

		//Hoãn thi cho những thí sinh chưa có bất thường, không bị cấm thi, không nợ học phí
		$excludedLearnerIds = array_column($excludedLearners, 'id');
		$delayedExamineeIds = array_diff($examineeIds, $excludedLearnerIds);
		$delayedExamineeIdSet = '(' . implode(',', $delayedExamineeIds) . ')';
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set([
				'anomaly=' . Anomaly::Deferred->value,
				'conclusion=' . Conclusion::Postponed->value
			])
			->where([
				'exam_id=' . $examId,
				'learner_id IN ' . $delayedExamineeIdSet,
				'anomaly=0'
			]);
		$db->setQuery($query);
		if(!$db->execute())
		{
			$app->enqueueMessage('Hoãn thi KHÔNG thành công', 'error');
			return false;
		}
		else{
			$msg = Text::sprintf('Hoãn thi thành công cho %d thí sinh', sizeof($delayedExamineeIds));
			$app->enqueueMessage($msg, 'success');
			return true;
		}
	}
	public function undoDelayExaminees($examId, $examineeIds): bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$app = Factory::getApplication();

		//Xác định những thí sinh trước đó đã được hoãn thi
		$examineeIdSet = '(' . implode(',', $examineeIds) . ')';
		$columns = $db->quoteName(
			array('a.learner_id', 'b.code'),
			array('id', 'code')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->where([
				'a.exam_id=' . $examId,
				'a.learner_id IN ' . $examineeIdSet,
				'a.anomaly=' . Anomaly::Deferred->value,
			]);
		$db->setQuery($query);
		$learners = $db->loadAssocList();
		if(empty($learners))
		{
			$msg = "Hủy hoãn thi không thành công. Chỉ có thể hủy hoãn thi đối với các thí sinh đã hoãn thi trước đó";
			$app->enqueueMessage($msg, 'error');
			return false;
		}

		//Thực hiện hủy hoãn thi
		$learnerIds = array_column($learners,'id');
		$learnerIdSet = '(' . implode(',', $learnerIds) . ')';
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set([
				'anomaly=' . Anomaly::None->value,
				'conclusion=NULL'
			])
			->where([
				'exam_id=' . $examId,
				'learner_id IN ' . $learnerIdSet
			]);
		$db->setQuery($query);
		if(!$db->execute()){
			$msg = "Lỗi truy vấn CSDL khi hủy hoãn thi";
			$app->enqueueMessage($msg,'error');
			return false;
		}
		else{
			$learnerCodes = array_column($learners, 'code');
			$countSuccess = sizeof($learnerIds);
			$msg = Text::sprintf('Hủy hoãn thi thành công cho %d thí sinh: <b>%s</b>',
				$countSuccess,
				implode(', ', $learnerCodes)
			);
			$app->enqueueMessage($msg, 'success');

			$countFailed = sizeof($examineeIds) - $countSuccess;
			if($countFailed>0)
			{
				$msg = Text::sprintf('%d thí sinh không thể hủy hoãn thi. Lưu ý rằng chỉ có thể hủy hoãn thi đối với những thí sinh đang hoãn thi',
					$countFailed
				);
				$app->enqueueMessage($msg,'warning');
			}
			return true;
		}
	}


	public function updateExamQuestion(int $examId, int $questionAuthorId, int $questionSenderId, int $questionQuantity, string $questionDate):bool
	{
		//Init
		$db = DatabaseHelper::getDatabaseDriver();
		$app = Factory::getApplication();

		//Lấy thông tin về exam hiện thời
		$query = $db->getQuery(true)
			->from('#__eqa_exams')
			->select('id, name, usetestbank, status')
			->where('id=' . $examId);
		$db->setQuery($query);
		$exam = $db->loadObject();
		if(empty($exam))
		{
			$app->enqueueMessage('Không tìm thấy môn thi','error');
			return false;
		}

		//Nếu dùng ngân hàng thì không thể nhận đề
		if($exam->usetestbank){
			$msg = Text::sprintf('Môn thi <b>%s</b> sử dụng ngân hàng đề nên không thể nhận đề',$exam->name);
			$app->enqueueMessage($msg,'error');
			return false;
		}

		//Tính toán lại trạng thái môn thi
		$status = $exam->status;
		if($status == ExamStatus::PamPendingQuestion->value)
			$status = ExamStatus::QuestionAndPamReady->value;
		elseif($status < ExamStatus::QuestionAndPamReady->value)
			$status = ExamStatus::QuestionPendingPam->value;

		//Cập nhật thông tin đề thi và trạng thái môn thi
		$query = $db->getQuery(true)
			->update('#__eqa_exams')
			->set([
				'questionauthor_id=' . $questionAuthorId,
				'questionsender_id=' . $questionSenderId,
				'nquestion=' . $questionQuantity,
				'questiondate=' . $db->quote($questionDate),
				'status=' . $status
			])
			->where('id=' . $examId);
		$db->setQuery($query);
		if(!$db->execute())
		{
			$msg = Text::sprintf('Cập nhật thông tin thất bại cho môn thi <b>%s</b>', $exam->name);
			$app->enqueueMessage($msg,'error');
			return false;
		}

		//Success
		$msg = Text::sprintf('Cập nhật thông tin thành công cho môn thi <b>%s</b>', $exam->name);
		$app->enqueueMessage($msg, 'success');
		return true;
	}
	public function distribute(int $examId, $data):bool
	{
		if ($this->isWithSomeMarks($examId))
			throw new Exception('Đã có điểm thi. Không thể chia phòng thi.');

		if(!$this->isWithAllPams($examId))
			throw new Exception('Chưa đủ điểm quá trình. Không thể chia phòng thi.');

		$app = Factory::getApplication();
		$db = $this->getDatabase();

		//PHẦN A. KIỂM TRA TÍNH HỢP LỆ CỦA DỮ LIỆU
		//Check input validity
		$validInput = !empty($examId) && !empty($data);
		$validInput = $validInput && isset($data['distribute_allowed_only']) && isset($data['create_new_examrooms']) && isset($data['count_distributed']) && isset($data['examinee_code_start']) && isset($data['examsessions']);
		$validInput = $validInput && is_array($data['examsessions']);
		if(!$validInput)
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_INVALID_DATA'),'error');
			return false;
		}

		//Check if there are duplicated rooms within an exam session
		//Or examsessions are duplicated
		$examsessionIds = [];
		foreach ($data['examsessions'] as $examsession){
			$examsessionIds[] = $examsession['examsession_id'];
			$roomIds = array();
			foreach ($examsession['rooms'] as $room)
				$roomIds[] = $room['room_id'];
			if(count($roomIds) != count(array_unique($roomIds)))
			{
				$app->enqueueMessage(Text::_('COM_EQA_MSG_DUPLICATED_ROOMS'),'error');
				return false;
			}
		}
		if (count($examsessionIds) != count(array_unique($examsessionIds)))
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_DUPLICATED_EXAMSESSIONS'),'error');
			return false;
		}


		//Get the exam infor
		$exam = DatabaseHelper::getExamInfo($examId);
		if(empty($exam)){
			$app->enqueueMessage(Text::_('COM_EQA_MSG_INVALID_DATA'),'error');
			return false;
		}

		$optionDistributeAllowedOnly = $data['distribute_allowed_only'];
		$optionCreateNewExamrooms = $data['create_new_examrooms'];

		//Check for the quantity correspondence
		if($optionDistributeAllowedOnly)
			$numberToDistribute = $exam->countToTake;
		else
			$numberToDistribute = $exam->countTotal;

		if($numberToDistribute != $data['count_distributed'])
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_INVALID_DISTRIBUTION'),'error');
			return false;
		}


		/*
		 * PHẦN B. THỰC HIỆN CHIA PHÒNG
		 */

		//0. Reset thông tin chia phòng cho toàn bộ thí sinh của môn thi
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set(array(
				$db->quoteName('code') . ' = NULL',
				$db->quoteName('examroom_id') . ' = NULL'
			))
			->where('exam_id='.$examId);
		$db->setQuery($query);
		$db->execute();

		//1. Load danh sách thí sinh của môn thi
		$examinees = DatabaseHelper::getExamExaminees($examId, $optionDistributeAllowedOnly);
		if(empty($examinees))
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_ERROR_OCCURRED'),'error');
			return false;
		}

		//2. Ngẫu nhiên hóa danh sách thí sinh để chia về cá phòng thi
		shuffle($examinees);

		//3. Gán sinh viên vào các phòng thi và đánh số báo danh
		//3.1. Định nghĩa comparator để phục vụ sắp xếp
		$collator = new Collator('vi_VN');
		$comparator = function($a, $b) use ($collator) {
			$r = $collator->compare($a->firstname, $b->firstname);
			if ($r !== 0)
				return $r;
			else
				return $collator->compare($a->lastname, $b->lastname);
		};

		$db->transactionStart();
		$examineeCode = $data['examinee_code_start'];
		$startIndex=0;
		try {
			foreach ($data['examsessions'] as $examsession){
				$examsessionId = $examsession['examsession_id'];
				foreach ($examsession['rooms'] as $room){
					//3.2. Tạo phòng thi (nếu chưa có) và lấy id của phòng thi
					$roomId = $room['room_id'];
					$nExaminee = $room['nexaminee'];
					$existingExamineeIds = [];

					//a) Kiểm tra xem với ca thi $examsessionId đã có tồn tại phòng thi với $roomId hay chưa
					//Nếu đã tồn tại thì lấy $examroomId và lấy danh sách thí sinh có trong phòng thi đó
					//Danh sách này sẽ được sử dụng để kiểm tra nhằm đảm bảo rằng một thí sinh không thể
					//được phân công nhiều hơn 1 lần vào cùng một phòng thi
					$query = $db->getQuery(true)
						->select('*')
						->from('#__eqa_examrooms')
						->where('examsession_id='.$examsessionId . ' AND room_id='.$roomId);
					$db->setQuery($query);
					$examroom = $db->loadObject();
					if(!empty($examroom)){
						$examroomId = $examroom->id;            //get exam room's ID

						if($optionCreateNewExamrooms){ //Nếu yêu cầu tạo phòng mới thì báo lỗi
							$msg = Text::sprintf('COM_EQA_MSG_EXAMSESSION_S_ALREADY_USES_ROOM_S',
								DatabaseHelper::getExamsessionName($examsessionId),
								DatabaseHelper::getRoomCode($roomId)
							);
							throw new Exception($msg);
						}

						$db->setQuery('SELECT learner_id FROM #__eqa_exam_learner WHERE examroom_id=' . $examroomId);
						$existingExamineeIds = $db->loadColumn();
					}

					//b) Nếu chưa có thì tạo phòng thi và xác định id của phòng thi mới ($examroomId)
					else {
						$roomCode = RoomHelper::getRoomCode($roomId);   //Mặc định cho examroom's name
						$values = array(
							$db->quote($roomCode),
							$roomId, $examsessionId);
						$tuple = implode(',', $values);
						$query = $db->getQuery(true)
							->insert('#__eqa_examrooms')
							->columns($db->quoteName(array('name','room_id','examsession_id')))
							->values($tuple);
						$db->setQuery($query);
						if(!$db->execute())                     //Create a new record
							throw new Exception(Text::_('COM_EQA_MSG_DATABASE_ERROR'));
						$examroomId = $db->insertid();          //Lấy $examroomId
					}

					//3.3. Trích lấy phần thí sinh của phòng thi
					$roomExaminees = array_slice($examinees, $startIndex, $nExaminee);
					$startIndex += $nExaminee;

					//3.4. Kiểm tra xem có thí sinh nào trùng lặp khi thêm vào phòng thi hay không
					$found = null;
					foreach ($roomExaminees as $item) {
						if (in_array($item->id, $existingExamineeIds, true)) {
							$found = $item->learner_code;
							break;
						}
					}
					if ($found) {
						throw new Exception('Thí sinh ' . htmlspecialchars($found) . ' đã tồn tại trong phòng thi');
					}

					//3.5. Sắp xếp theo họ và tên
					usort($roomExaminees, $comparator);

					//3.6. Ghi SBD, phòng thi cho thí
					//Tăng tuần tự SBD trong quá trình này
					for($i=0; $i<$nExaminee; $i++)
					{
						$examinee = $roomExaminees[$i];

						//Gán (hoặc gán lại) phòng thi, SBD cho thí sinh
						$query = $db->getQuery(true)
							->update('#__eqa_exam_learner')
							->set(array(
								'code='.$examineeCode,
								'examroom_id='.$examroomId
							))
							->where('exam_id=' . $examId . ' AND learner_id='.$examinee->id);
						$db->setQuery($query);
						if(!$db->execute())
							throw new Exception(Text::_('COM_EQA_MSG_DATABASE_ERROR'));
						$examineeCode++;
					}

					//Cập nhật lại môn thi của phòng thi
					DatabaseHelper::updateExamroomExams($examroomId);

				}   //End of an exam session
			}       //End of al the $data
		}
		catch (Exception $e){
			$db->transactionRollback();
			$app->enqueueMessage($e->getMessage(), 'error');
			return false;
		}

		$db->transactionCommit();
		$app->enqueueMessage(Text::_('COM_EQA_MSG_TASK_SUCCESS'),'success');
		return true;
	}
	public function distribute2(int $examId, $data):bool
	{
		if ($this->isWithSomeMarks($examId))
			throw new Exception('Đã có điểm thi. Không thể chia phòng thi.');

		if(!$this->isWithAllPams($examId))
			throw new Exception('Chưa đủ điểm quá trình. Không thể chia phòng thi.');

 		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//PHẦN A. KIỂM TRA TÍNH HỢP LỆ CỦA DỮ LIỆU
		//Check input validity
		$validInput = !empty($examId) && !empty($data);
		$validInput = $validInput && isset($data['distribute_allowed_only']) && isset($data['create_new_examrooms'])  && isset($data['examinee_code_start']) && isset($data['examsessions']);
		$validInput = $validInput && is_array($data['examsessions']);
		if(!$validInput)
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_INVALID_DATA'),'error');
			return false;
		}

		$optionDistributeAllowedOnly = $data['distribute_allowed_only'];
		$optionCreateNewExamrooms = $data['create_new_examrooms'];


		//Check if examsessions or credit classes are duplicated
		//and if rooms are occupied
		$examsessionIds = [];
		$classIds = [];
		foreach ($data['examsessions'] as $examsession){
			$examsessionIds[] = $examsession['examsession_id'];
			$roomIds = array();
			foreach ($examsession['rooms'] as $room)
			{
				$roomIds[] = $room['room_id'];
				$classIds[] = $room['class_id'];
			}

			//Check if rooms are occupied for this session
			if(!$optionCreateNewExamrooms)
				continue;
			$roomIdSet = '(' . implode(',', $roomIds) . ')';
			$query = $db->getQuery(true)
				->select('count(1)')
				->from('#__eqa_exam_learner AS a')
				->leftJoin('#__eqa_examrooms AS b', 'a.examroom_id=b.id')
				->where('b.room_id IN ' . $roomIdSet);
			$db->setQuery($query);
			if($db->loadResult() > 0){
				$msg = Text::sprintf('COM_EQA_MSG_EXAMSESSION_S_ALREADY_USES_ROOM_S',
					DatabaseHelper::getExamsessionName($examsession['examsession_id']),
					DatabaseHelper::getRoomCode($room['room_id'])
				);
				$app->enqueueMessage($msg,'error');
				return false;
			}
		}
		if (count($examsessionIds) != count(array_unique($examsessionIds)))
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_DUPLICATED_EXAMSESSIONS'),'error');
			return false;
		}
		if (count($classIds) != count(array_unique($classIds)))
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_DUPLICATED_CLASSES'),'error');
			return false;
		}



		/*
		 * PHẦN B. THỰC HIỆN CHIA PHÒNG
		 */

		//1. Reset thông tin chia phòng cho toàn bộ thí sinh của môn thi
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set(array(
				$db->quoteName('code') . ' = NULL',
				$db->quoteName('examroom_id') . ' = NULL'
			))
			->where('exam_id='.$examId);
		$db->setQuery($query);
		$db->execute();

		//2. Định nghĩa comparator để phục vụ sắp xếp thí sinh
		$collator = new Collator('vi_VN');
		$comparator = function($a, $b) use ($collator) {
			$r = $collator->compare($a->firstname, $b->firstname);
			if ($r !== 0)
				return $r;
			else
				return $collator->compare($a->lastname, $b->lastname);
		};

		//3. Bắt đầu
		$countExaminee = 0;
		$countExamroom = 0;
		$db->transactionStart();
		$examineeCode = $data['examinee_code_start'];
		try {
			foreach ($data['examsessions'] as $examsession){
				$examsessionId = $examsession['examsession_id'];

				//3.1. Do có thể ghép nhiều lớp học phần vào cùng 1 phòng thi
				//nên cần xác định các phòng thi được sử dụng
				//(Trong một ca thi thì phòng thi được xác định bởi phòng vật lý)
				$roomIds = [];
				foreach ($examsession['rooms'] as $room){
					$roomIds[] = $room['room_id'];
				}
				$roomIds = array_unique($roomIds);

				//3.2. Load danh sách thí sinh từng phòng thi
				$examinees = [];
				foreach ($roomIds as $roomId)
					$examinees[$roomId] = [];
				foreach ($examsession['rooms'] as $room)
				{
					$roomId  = $room['room_id'];
					$classId = $room['class_id'];

					//Lấy thí sinh thuộc lớp học phần $classId
					$columns = $db->quoteName(
						array('a.learner_id', 'b.lastname', 'b.firstname'),
						array('id', 'lastname', 'firstname')
					);
					$query = $db->getQuery(true)
						->select($columns)
						->from('#__eqa_exam_learner AS a')
						->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
						->leftJoin('#__eqa_class_learner AS c', 'a.class_id=c.class_id AND a.learner_id=c.learner_id')
						->leftJoin('#__eqa_stimulations AS d', 'a.stimulation_id=d.id')
						->where('a.exam_id=' . $examId . ' AND a.class_id='.$classId);
					if($optionDistributeAllowedOnly)
						$query->where([
							'c.allowed<>0',
							'b.debtor=0',
							'(d.type IS NULL OR d.type=' . StimulationHelper::TYPE_ADD . ')'
						]);

					$db->setQuery($query);
					$classExaminees =  $db->loadObjectList();

					$examinees[$roomId] += $classExaminees;
				}

				//3.3. Ghi thông tin thí sinh từng phòng thi
				foreach ($roomIds as $roomId){
					//3.3.1 Lấy danh sách thí sinh và Sắp xếp theo họ và tên
					$roomExaminees = $examinees[$roomId];
					if(empty($roomExaminees))
						continue;
					usort($roomExaminees, $comparator);
					$countExaminee += count($roomExaminees);
					$countExamroom++;

					//3.3.2. Tạo phòng thi (nếu chưa có) và lấy id của phòng thi
					//a) Kiểm tra xem với ca thi $examsessionId đã có tồn tại phòng thi với $roomId hay chưa
					//Nếu đã tồn tại thì lấy $examroomId
					$query = $db->getQuery(true)
						->select('*')
						->from('#__eqa_examrooms')
						->where('examsession_id='.$examsessionId . ' AND room_id='.$roomId);
					$db->setQuery($query);
					$examroom = $db->loadObject();
					if(!empty($examroom)){
						$examroomId = $examroom->id;            //get exam room's ID
					}

					//b) Nếu chưa có thì tạo phòng thi và xác định id của phòng thi mới ($examroomId)
					//   Đồng thời tăng số lượng phòng thi của ca thi
					else {
						$roomCode = RoomHelper::getRoomCode($roomId);   //Mặc định cho examroom's name
						$values = array(
							$db->quote($roomCode),
							$roomId, $examsessionId);
						$tuple = implode(',', $values);
						$query = $db->getQuery(true)
							->insert('#__eqa_examrooms')
							->columns($db->quoteName(array('name','room_id','examsession_id')))
							->values($tuple);
						$db->setQuery($query);
						if(!$db->execute())                     //Create a new record
							throw new Exception(Text::_('COM_EQA_MSG_DATABASE_ERROR'));
						$examroomId = $db->insertid();          //Lấy $examroomId
					}

					//3.3.3. Ghi SBD, phòng thi cho thí
					//Tăng tuần tự SBD trong quá trình này
					foreach ($roomExaminees as $examinee)
					{
						//Gán phòng thi, SBD cho thí sinh
						$query = $db->getQuery(true)
							->update('#__eqa_exam_learner')
							->set(array(
								'code='.$examineeCode,
								'examroom_id='.$examroomId
							))
							->where('exam_id=' . $examId . ' AND learner_id='.$examinee->id);
						$db->setQuery($query);
						if(!$db->execute())
							throw new Exception(Text::_('COM_EQA_MSG_DATABASE_ERROR'));
						$examineeCode++;
					}

					//Cập nhật lại môn thi của phòng thi
					DatabaseHelper::updateExamroomExams($examroomId);

				}   //End of an exam session
			}       //End of al the $data
		}
		catch (Exception $e){
			$db->transactionRollback();
			$app->enqueueMessage($e->getMessage(), 'error');
			return false;
		}

		$db->transactionCommit();
		$msg = Text::sprintf('COM_EQA_MSG_N_EXAMINEES_DISTRIBUTED_INTO_N_EXAMROOMS',$countExaminee,$countExamroom);
		$app->enqueueMessage($msg,'success');
		return true;
	}

	private function applyStimulTransfers(DatabaseDriver $db, $examId, $subjectId, $examinees):int
	{
		//LƯU Ý: Cần phải set trạng thái "Nợ học phí" trước khi gọi phương thức này
		//Các bước cần thực hiện
		//  1. Kiểm tra điều kiện áp dụng (nợ phí)
		//  2. Cập nhật thông tin thí sinh môn thi (stimulation, mark_orig, mark_final, module_mark, conclusion, mark_grade)
		//  3. Cập nhật thông tin người học trong lớp học (expired, description)
		//  4. Cập nhật thông tin khuyến khích (used)


		$countApplied=0;
		foreach ($examinees as $examinee)
		{
			//1. Nếu đang nợ học phí thì bỏ qua
			if($examinee['debtor'])
				continue;

			//2. Tính toán các giá trị cần thiết và cập nhật cho thí sinh của môn thi
			$classId = $examinee['class_id'];
			$learnerId = $examinee['learner_id'];
			$attempt = $examinee['attempt'];
			$stimulationId = $examinee['stimulation_id'];
			$stimulationValue = $examinee['stimulation_value'];
			$admissionYear = $attempt>1 ? DatabaseHelper::getLearnerAdmissionYear($learnerId) : 0;
			$conclusion = Conclusion::Passed;
			$moduleMark = ExamHelper::calculateModuleMark($subjectId, $stimulationValue, $stimulationValue, $attempt, $admissionYear);
			$moduleBase4Mark = ExamHelper::calculateBase4Mark($moduleMark);
			$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set([
					'stimulation_id=' . $stimulationId,
					'mark_orig=' . $stimulationValue,
					'mark_final=' . $stimulationValue,
					'module_mark=' . $moduleMark,
					'module_base4_mark=' . $moduleBase4Mark,
					'conclusion=' . $conclusion->value,
					'module_grade=' . $db->quote($moduleGrade)
				])
				->where([
					'exam_id=' . $examId,
					'learner_id=' . $learnerId
				]);
			$db->setQuery($query);
			if(!$db->execute())
			{
				throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin thí sinh môn thi');
			}

			//3. Cập nhật thông tin trong lớp học phần
			$query = $db->getQuery(true)
				->update('#__eqa_class_learner')
				->set([
					'expired=1',
					'description=' . $db->quote('Quy đổi điểm')
				])
				->where([
					'class_id=' . $classId,
					'learner_id=' . $learnerId
				]);
			$db->setQuery($query);
			if(!$db->execute())
			{
				throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin trong lớp học phần');
			}

			//4. Đánh dấu là mục khuyến khích đã được sử dụng
			$query = $db->getQuery(true)
				->update('#__eqa_stimulations')
				->set('used=1')
				->where('id=' . $stimulationId);
			$db->setQuery($query);
			if(!$db->execute())
			{
				throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin trong bảng khuyến khích');
			}

			//Count
			$countApplied++;
		}

		//Return
		return $countApplied;
	}
	private function applyStimulExemptions(DatabaseDriver $db, $examId, $subjectId, $examinees):int
	{
		//LƯU Ý: Cần phải set trạng thái "Nợ học phí" trước khi gọi phương thức này
		//Các bước cần thực hiện
		//  1. Kiểm tra điều kiện áp dụng (điểm quá trình, nợ phí)
		//  2. Cập nhật thông tin thí sinh môn thi (stimulation, mark_orig, mark_final, module_mark, conclusion, mark_grade)
		//  3. Cập nhật thông tin người học trong lớp học (expired, description)
		//  4. Cập nhật thông tin khuyến khích (used)

		$countApplied = 0;
		foreach ($examinees as $examinee)
		{
			//1. Nếu không đủ điều kiện dự thi hoặc đang nợ học phí thì bỏ qua
			if($examinee['debtor'] || !$examinee['allowed'])
				continue;

			//2. Tính toán các giá trị cần thiết và cập nhật cho thí sinh của môn thi
			$classId = $examinee['class_id'];
			$learnerId = $examinee['learner_id'];
			$pam = $examinee['pam'];
			$attempt = $examinee['attempt'];
			$stimulationId = $examinee['stimulation_id'];
			$stimulationValue = $examinee['stimulation_value'];
			$admissionYear = $attempt>1 ? DatabaseHelper::getLearnerAdmissionYear($learnerId) : 0;
			$conclusion = Conclusion::Passed;
			$moduleMark = ExamHelper::calculateModuleMark($subjectId, $pam, $stimulationValue, $attempt, $admissionYear);
			$moduleBase4Mark = ExamHelper::calculateBase4Mark($moduleMark);
			$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set([
					'stimulation_id=' . $stimulationId,
					'mark_orig=' . $stimulationValue,
					'mark_final=' . $stimulationValue,
					'module_mark=' . $moduleMark,
					'module_base4_mark=' . $moduleBase4Mark,
					'conclusion=' . $conclusion->value,
					'module_grade=' . $db->quote($moduleGrade)
				])
				->where([
					'exam_id=' . $examId,
					'learner_id=' . $learnerId
				]);
			$db->setQuery($query);
			if(!$db->execute())
			{
				throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin thí sinh môn thi');
			}

			//3. Cập nhật thông tin trong lớp học phần
			$query = $db->getQuery(true)
				->update('#__eqa_class_learner')
				->set([
					'expired=1',
					'description=' . $db->quote('Miễn thi')
				])
				->where([
					'class_id=' . $classId,
					'learner_id=' . $learnerId
				]);
			$db->setQuery($query);
			if(!$db->execute())
			{
				throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin trong lớp học phần');
			}

			//4. Đánh dấu là mục khuyến khích đã được sử dụng
			$query = $db->getQuery(true)
				->update('#__eqa_stimulations')
				->set('used=1')
				->where('id=' . $stimulationId);
			$db->setQuery($query);
			if(!$db->execute())
			{
				throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin bảng khuyến khích');
			}

			//Count
			$countApplied++;
		}

		return $countApplied;
	}
	private function applyStimulAdditions(DatabaseDriver $db, $examId, $examinees):int
	{
		$countApplied=0;
		foreach ($examinees as $examinee)
		{
			//1. Nếu không đủ điều kiện dự thi hoặc đang nợ học phí thì bỏ qua
			if($examinee['debtor'] || !$examinee['allowed'])
				continue;

			//2. Chỉ ghi nhận "Có khuyến khích", không làm gì hơn
			$learnerId = $examinee['learner_id'];
			$stimulationId = $examinee['stimulation_id'];
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set('stimulation_id=' . $stimulationId)
				->where([
					'exam_id=' . $examId,
					'learner_id=' . $learnerId
				]);
			$db->setQuery($query);
			if(!$db->execute())
				throw new Exception('Lỗi truy vấn CSDL');

			//Count
			$countApplied++;
		}
		return $countApplied;
	}
	private function undoApplyStimulTransfersOrExemptions(DatabaseDriver $db, $examId, $stimulations): int
	{
		/**
		 * Các nội dung cần thực hiện
		 * - Cập nhật môn thi (set NULL: stimulation_id, mark_orig, mark_final, module_mark, module_grade, conclusion)
		 * - Cập nhật thông tin lớp học phần ('expired'=>false, 'description'=>null)
		 * - Cập nhật thông tin khuyến khích ('used' => false)
		 */
		if(empty($stimulations))
			return 0;

		//2. Cập nhật thông thí sinh
		$learnerIds = array_column($stimulations, 'learner_id');
		$learnerIdSet = '(' . implode(',', $learnerIds) . ')';
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set([
				'stimulation_id=NULL',
				'mark_orig=NULL',
				'mark_final=NULL',
				'module_mark=NULL',
				'module_grade=NULL',
				'conclusion=NULL'
			])
			->where([
				'exam_id=' . $examId,
				'learner_id IN ' . $learnerIdSet
			]);
		$db->setQuery($query);
		if(!$db->execute())
		{
			throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin môn thi');
		}

		//3. Cập nhật thông tin lớp học phần ('expired'=>false, 'description'=>NULL)
		foreach ($stimulations as $stimulation)
		{
			$classId = $stimulation['class_id'];
			$learnerId = $stimulation['learner_id'];
			$query = $db->getQuery(true)
				->update('#__eqa_class_learner')
				->set([
					'expired=0',
					'description=NULL'
				])
				->where([
					'class_id=' . $classId,
					'learner_id=' . $learnerId
				]);
			$db->setQuery($query);
			if(!$db->execute())
			{
				throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin lớp học phần');
			}
		}

		//4. Cập nhật thông tin khuyến khích ('used' => false)
		$stimulationIds = array_column($stimulations, 'id');
		$stimulationIdSet = '(' . implode(',', $stimulationIds) . ')';
		$query = $db->getQuery(true)
			->update('#__eqa_stimulations')
			->set('used=0')
			->where('id IN ' . $stimulationIdSet);
		$db->setQuery($query);
		if(!$db->execute())
		{
			throw new Exception('Lỗi truy vấn CSDL khi cập nhật thông tin khuyến khích');
		}

		return sizeof($stimulations);
	}
	private function undoApplyStimulAdditions(DatabaseDriver $db, $examId, $stimulations):int
	{
		//1. Kiểm tra điều kiện
		if(empty($stimulations))
			return 0;

		//2. Không chỉ đơn giản là xóa thông tin khuyến khích đối với thí sinh
		//Mà còn phải xóa cả điểm (nếu có): mark_final, module_mark, module_grade, conclusion
		//để CBKT biết mà cập nhật lại
		$learnerIds = array_column($stimulations, 'learner_id');
		$learnerIdSet = '(' . implode(',', $learnerIds) . ')';
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set([
				'stimulation_id=NULL',
				'mark_final=NULL',
				'module_mark=NULL',
				'module_grade=NULL',
				'conclusion=NULL'
			])
			->where([
				'exam_id=' . $examId,
				'learner_id IN ' . $learnerIdSet
			]);
		$db->setQuery($query);
		if(!$db->execute())
		{
			throw new Exception('Lỗi truy vấn CSDL khi xóa thông tin khuyến khích trong môn thi');
		}

		return sizeof($stimulations);
	}
	private function undoApplyStimulations(DatabaseDriver $db, int $examId)
	{
		//1. Lấy danh sách khuyến khích đã áp dụng cho môn thi
		$columns = $db->quoteName(
			array('b.id', 'b.type', 'a.learner_id', 'a.class_id'),
			array('id',   'type',   'learner_id',   'class_id')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_stimulations AS b', 'b.id=a.stimulation_id')
			->where([
				'a.exam_id=' . $examId,
				'a.stimulation_id IS NOT NULL'
			]);
		$db->setQuery($query);
		$stimulations = $db->loadAssocList();
		if(empty($stimulations))
			return 0;

		//2. Phân nhóm các khuyến khích
		$stimulTransfersOrExeptions = [];
		$stimulAdditions = [];
		foreach ($stimulations as &$stimulation)
		{
			switch ($stimulation['type'])
			{
				case StimulationHelper::TYPE_EXEMPT:
				case StimulationHelper::TYPE_TRANS:
					$stimulTransfersOrExeptions[] = $stimulation;
					break;
				case StimulationHelper::TYPE_ADD:
					$stimulAdditions[] = $stimulation;
					break;
				default:
					throw new Exception('Loại khuyến khích không hợp lệ');
			}
		}
		unset($stimulation);

		//3. Xóa khuyến khích
		$this->undoApplyStimulTransfersOrExemptions($db, $examId, $stimulTransfersOrExeptions);
		$this->undoApplyStimulAdditions($db, $examId, $stimulAdditions);

		//Return
		return sizeof($stimulations);
	}

	/**
	 * @param $examId
	 *
	 * @return string A messessages for the caller
	 *
	 * @throws Exception
	 * @since 1.1.0
	 */
	public function updateStimulations($examId): string
	{
		//1. Init
		$db = DatabaseHelper::getDatabaseDriver();

		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi đã kết thúc. Không thể cập nhật trạng thái khuyến khích điểm');

		//2. Xác định môn học và thông tin thí sinh được khuyến khích
		//2.1. Determine the subject of this exam
		$db->setQuery('SELECT subject_id FROM #__eqa_exams WHERE id=' . $examId);
		$subjectId = $db->loadResult();

		//2.2. Lấy danh sách thông tin thí sinh của môn thi được khuyến khích
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('a.learner_id', 'learner_id'),
				$db->quoteName('a.class_id', 'class_id'),
				$db->quoteName('a.attempt', 'attempt'),
				$db->quoteName('a.debtor', 'debtor'),
				$db->quoteName('a.anomaly', 'anomaly'),
				$db->quoteName('b.id', 'stimulation_id'),
				$db->quoteName('b.type', 'stimulation_type'),
				$db->quoteName('b.value', 'stimulation_value'),
				$db->quoteName('c.pam', 'pam'),
				$db->quoteName('c.allowed', 'allowed')
			])
			->from('#__eqa_exam_learner AS a')
			->innerJoin('#__eqa_stimulations AS b', 'b.learner_id=a.learner_id')
			->leftJoin('#__eqa_class_learner AS c', 'c.class_id=a.class_id AND c.learner_id=a.learner_id')
			->where([
				'b.subject_id=' . $subjectId,
				'a.exam_id=' . $examId
			]);
		$db->setQuery($query);
		$examinees = $db->loadAssocList();
		if(empty($examinees))
			return 'Không có thí sinh nào của môn thi này được khuyến khích';

		//3.3. Phân loại khuyến khích
		$exemptions = [];
		$transfers = [];
		$additions = [];
		foreach ($examinees as $examinee){
			$stimulationType = $examinee['stimulation_type'];
			switch ($stimulationType)
			{
				case StimulationHelper::TYPE_EXEMPT:
					$exemptions[] = $examinee;
					break;
				case StimulationHelper::TYPE_ADD:
					$additions[] = $examinee;
					break;
				case StimulationHelper::TYPE_TRANS:
					$transfers[] = $examinee;
					break;
				default:
					throw new Exception(Text::sprintf('Loại khuyến khích không hợp lệ: %d', $stimulationType));
			}
		}


		$db->transactionStart();
		try
		{
			//3. Xóa các chế độ khuyến khích đã áp dụng cho môn thi
			$this->undoApplyStimulations($db, $examId);

			//4. Áp dụng khuyến kích
			$countAppliedExemption = $this->applyStimulExemptions($db, $examId, $subjectId, $exemptions);
			$countAppliedTransfer = $this->applyStimulTransfers($db, $examId, $subjectId, $transfers);
			$countAppliedAddition =  $this->applyStimulAdditions($db, $examId, $additions);

			//Commit
			$db->transactionCommit();
		}
		catch (Exception $e){
			$db->transactionRollback();
			throw $e;
		}

		//5. Return
		$countApplied = $countAppliedExemption + $countAppliedTransfer + $countAppliedAddition;
		$countTotal = sizeof($examinees);
		$msg = sprintf('%d/%d khuyến khích được áp dụng: %d/%d Miễn thi, %d/%d Cộng điểm, %d/%d Quy đổi điểm',
			$countApplied, $countTotal,
			$countAppliedExemption, sizeof($exemptions),
			$countAppliedAddition, sizeof($additions),
			$countAppliedTransfer, sizeof($transfers)
		);
		return $msg;
	}

	/**
	 * @param $examId
	 *
	 * @return array An array of messages
	 *
	 * @throws Exception
	 * @since 1.1.0
	 */
	public function updateDebt($examId): array
	{
		//1. Init
		$db = DatabaseHelper::getDatabaseDriver();

		//2. Kiểm tra điều kiện
		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể cập nhật thông tin nợ phí');

		//3. Lấy thông tin nợ phí hiện thời từ 2 nơi: môn thi, người học
		$columns = [
			$db->quoteName('a.learner_id',      'learnerId'),
			$db->quoteName('b.code',            'learnerCode'),
			$db->quoteName('a.debtor',          'currentDebt'),
			$db->quoteName('b.debtor',          'newDebt'),
			$db->quoteName('a.module_mark',     'moduleMark')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->where('a.exam_id=' . $examId);
		$db->setQuery($query);
		$examinees = $db->loadObjectList();

		//4. Cập nhật lại thông tin nợ phí
		$listUnset = [];
		$listSet = [];
		$listCannotChange = [];
		$db->transactionStart();
		try
		{
			foreach ($examinees as $examinee)
			{
				if($examinee->currentDebt == $examinee->newDebt)
					continue;

				if(!is_null($examinee->moduleMark))
				{
					$listCannotChange[] = $examinee->learnerCode;
					continue;
				}

				//Thống kê
				if ($examinee->newDebt==0)
					$listUnset[] = $examinee->learnerCode;
				else
					$listSet[] = $examinee->learnerCode;

				//Cập nhật
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set('debtor=' . $examinee->newDebt)
					->where([
						'exam_id=' . $examId,
						'learner_id=' . $examinee->learnerId
					]);
				$db->setQuery($query);
				if(!$db->execute())
					throw new Exception('Phát sinh lỗi khi cập nhật thông tin nợ phí');
			}

			//Commit
			$db->transactionCommit();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();
			throw $e;
		}

		//Return some information to the caller
		$messages = [];

		if(!empty($listUnset))
		{
			$messages[] = Text::sprintf('%d HVSV đã hết nợ: %s',
				sizeof($listUnset),
				implode(', ', $listUnset)
			);
		}
		if(!empty($listSet))
		{
			$messages[] = Text::sprintf('%d HVSV đã phát sinh nợ: %s',
				sizeof($listSet),
				implode(', ', $listSet)
			);
		}
		if(!empty($listCannotChange))
		{
			$messages[] = Text::sprintf('%d HVSV có thay đổi trạng thái nợ phí nhưng không được cập nhật vì đã có kết quả: %s',
				sizeof($listCannotChange),
				implode(', ', $listCannotChange)
			);
		}
		if(empty($listSet) && empty($listUnset))
			$messages[] = 'Thông tin nợ phí không thay đổi';
		return $messages;
	}

	/**
	 * Cập nhật lại thông tin nợp phí của thí sinh dựa trên thông tin nộp phí
	 * dự thi lần 2. Thí sinh được ghi nợ nếu thuộc 1 trong 2 trường hợp: Bản thân đang nợ
	 * học phí (bảng _learners) hoặc chưa đóng phí thi lần 2 (bảng _secondattempts)
	 * @param $examId
	 *
	 * @return array
	 *
	 * @throws Exception
	 * @since 2.0.3
	 */
	public function updateSecondAttemptPaymentStatus($examId): array
	{
		//1. Init
		$db = DatabaseHelper::getDatabaseDriver();

		//2. Kiểm tra điều kiện
		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể cập nhật thông tin nợ phí');

		//3. Lấy thông tin nợ phí hiện thời từ 3 nơi: môn thi, người học
		$columns = [
			$db->quoteName('a.learner_id',          'learnerId'),
			$db->quoteName('b.code',                'learnerCode'),
			$db->quoteName('a.debtor',              'currentDebt'),
			$db->quoteName('b.debtor',              'generalDebt'),
			$db->quoteName('a.module_mark',         'moduleMark'),
			$db->quoteName('c.payment_amount',      'secondAttemptPaymentAmount'),
			$db->quoteName('c.payment_completed',   'secondAttemptPaymentCompleted'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_secondattempts AS c', 'c.learner_id=a.learner_id AND c.class_id=a.class_id')
			->where('a.exam_id=' . $examId);
		$db->setQuery($query);
		$examinees = $db->loadObjectList();

		//4. Cập nhật lại thông tin nợ phí
		$listUnset = [];
		$listSet = [];
		$listCannotChange = [];
		$db->transactionStart();
		try
		{
			foreach ($examinees as $examinee)
			{
				$newDebt = ($examinee->generalDebt || ($examinee->secondAttemptPaymentAmount>0 && !$examinee->secondAttemptPaymentCompleted)) ? 1 : 0;
				if($examinee->currentDebt == $newDebt)
					continue;

				if(!is_null($examinee->moduleMark))
				{
					$listCannotChange[] = $examinee->learnerCode;
					continue;
				}

				//Thống kê
				if ($newDebt==0)
					$listUnset[] = $examinee->learnerCode;
				else
					$listSet[] = $examinee->learnerCode;

				//Cập nhật
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set('debtor=' . $newDebt)
					->where([
						'exam_id=' . $examId,
						'learner_id=' . $examinee->learnerId
					]);
				$db->setQuery($query);
				if(!$db->execute())
					throw new Exception('Phát sinh lỗi khi cập nhật thông tin nợ phí');
			}

			//Commit
			$db->transactionCommit();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();
			throw $e;
		}

		//Return some information to the caller
		$messages = [];

		if(!empty($listUnset))
		{
			$messages[] = Text::sprintf('%d HVSV đã hết nợ: %s',
				sizeof($listUnset),
				implode(', ', $listUnset)
			);
		}
		if(!empty($listSet))
		{
			$messages[] = Text::sprintf('%d HVSV đã phát sinh nợ: %s',
				sizeof($listSet),
				implode(', ', $listSet)
			);
		}
		if(!empty($listCannotChange))
		{
			$messages[] = Text::sprintf('%d HVSV có thay đổi trạng thái nợ phí nhưng không được cập nhật vì đã có kết quả: %s',
				sizeof($listCannotChange),
				implode(', ', $listCannotChange)
			);
		}
		if(empty($listSet) && empty($listUnset))
			$messages[] = 'Thông tin nợ phí không thay đổi';
		return $messages;
	}
	public function setDebt(int $examId, int $learnerId, bool $value):bool
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//Can change debt status if the final result is not concluded
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__eqa_exam_learner')
			->where([
				'exam_id='.$examId,
				'learner_id='.$learnerId,
				'conclusion IS NULL'
			]);
		$db->setQuery($query);
		if($db->loadResult() == 0)
			return false;

		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set('debtor='.(int)$value)
			->where([
				'exam_id='.$examId,
				'learner_id='.$learnerId
			]);
		$db->setQuery($query);
		return $db->execute();
	}

	public function getExamStatus(int $examId): ExamStatus
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('status')
			->from('#__eqa_exams')
			->where('id=' . $examId);
		$db->setQuery($query);
		$statusValue = $db->loadResult();
		return ExamStatus::from($statusValue);
	}
	public function setExamStatus(int $examId, ExamStatus $status): bool
	{
		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể cập nhật trạng thái');

		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->update('#__eqa_exams')
			->set($db->quoteName('status') . '=' . $status->value)
			->where('id=' . $examId);
		$db->setQuery($query);
		return $db->execute();
	}
	public function isWithAllPams(int $examId):bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->where([
				'a.exam_id='.$examId,
				'b.pam IS NULL'
			])
			->setLimit(1);
		$db->setQuery($query);
		return $db->loadResult()==0;
	}
	public function isWithSomeMarks(int $examId):bool
	{
		$db = $this->getDatabase();

		$inner = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__eqa_exam_learner'))
			->where($db->quoteName('exam_id') . ' = ' . $examId)
			->where($db->quoteName('mark_orig') . ' IS NOT NULL')
			->setLimit(1);

		return (bool) $db->setQuery(
			$db->getQuery(true)->select('IF(EXISTS(' . $inner . '), 1, 0)')
		)->loadResult();
	}
	public function isWithAllMarks(int $examId):bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->where([
				'b.allowed=1',
				'a.exam_id='.$examId,
				'a.conclusion IS NULL'
			])
			->setLimit(1);
		$db->setQuery($query);
		return $db->loadResult()==0;
	}
	public function isWithAllConclusions(int $examId): bool
	{
		$db = $this->getDatabase();

		$hasMissingConclusion = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__eqa_exam_learner'))
			->where($db->quoteName('exam_id') . ' = ' . $examId)
			->where($db->quoteName('conclusion') . ' IS NULL')
			->setLimit(1);

		return !(bool) $db->setQuery(
			$db->getQuery(true)->select('EXISTS(' . $hasMissingConclusion . ')')
		)->loadResult();
	}

	/**
	 * Tính toán điểm học phần và kết luận kết quả học phần cho tất cả thí sinh
	 * của một môn thi. Method này có thể được gọi sau khi điểm thi đã được cập nhật
	 * hoặc sau bất kỳ thay đổi nào liên quan đến thí sinh.
	 *
	 * Trình tự xử lý cho mỗi thí sinh:
	 *  1. Không đủ điều kiện dự thi (cl.allowed = FALSE) → Ineligible
	 *  2. Khuyến khích quy đổi điểm (TYPE_TRANS) → module_mark = st.value
	 *  3. Khuyến khích miễn thi (TYPE_EXEMPT) → mark_final = st.value, tính module_mark
	 *  4. Hoãn thi / Làm lại bài (Deferred/Retake) → Deferred, set NULL
	 *  5. Chưa có điểm thi → bỏ qua
	 *  6. Đã có điểm thi → tính toán đầy đủ
	 *
	 * @param   int   $examId                    ID của môn thi cần xử lý
	 * @param   bool  $disciplineAlreadyApplied  Điểm thi đã bao gồm việc xử lý kỷ luật hay chưa
	 *
	 * @return ExamStatus
	 * @throws Exception
	 * @since 2.1.0
	 */
	public function conclude(int $examId, bool $disciplineAlreadyApplied): ExamStatus
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// Lấy toàn bộ thí sinh của môn thi, kèm thông tin cần thiết để tính toán
		$columns = [
			$db->quoteName('el.learner_id',       'learnerId'),
			$db->quoteName('el.class_id',         'classId'),
			$db->quoteName('el.attempt',          'attempt'),
			$db->quoteName('el.anomaly',          'anomaly'),
			$db->quoteName('el.mark_orig',        'markOrig'),
			$db->quoteName('el.mark_ppaa',        'markPpaa'),
			$db->quoteName('cl.pam',              'pam'),
			$db->quoteName('cl.allowed',          'allowed'),
			$db->quoteName('cl.ntaken',           'ntaken'),
			$db->quoteName('c.subject_id',        'subjectId'),
			$db->quoteName('cs.admissionyear',    'admissionYear'),
			$db->quoteName('st.id',               'stimulationId'),
			$db->quoteName('st.type',             'stimulationType'),
			$db->quoteName('st.value',            'stimulationValue'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->leftJoin(
				$db->quoteName('#__eqa_class_learner', 'cl'),
				'cl.class_id = el.class_id AND cl.learner_id = el.learner_id'
			)
			->leftJoin(
				$db->quoteName('#__eqa_classes', 'c'),
				'c.id = el.class_id'
			)
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'l'),
				'l.id = el.learner_id'
			)
			->leftJoin(
				$db->quoteName('#__eqa_groups', 'g'),
				'g.id = l.group_id'
			)
			->leftJoin(
				$db->quoteName('#__eqa_courses', 'cs'),
				'cs.id = g.course_id'
			)
			->leftJoin(
				$db->quoteName('#__eqa_stimulations', 'st'),
				'st.id = el.stimulation_id'
			)
			->where($db->quoteName('el.exam_id') . ' = ' . (int) $examId);
		$db->setQuery($query);
		$examinees = $db->loadObjectList();

		if (empty($examinees)) {
			return $this->getExamStatus($examId);
		}

		$db->transactionStart();
		try {
			foreach ($examinees as $examinee) {
				$learnerId       = (int) $examinee->learnerId;
				$classId         = (int) $examinee->classId;
				$attempt         = (int) $examinee->attempt;
				$anomaly         = (int) $examinee->anomaly;
				$subjectId       = (int) $examinee->subjectId;
				$admissionYear   = (int) ($examinee->admissionYear ?? 0);
				$pam             = (float) ($examinee->pam ?? 0.0);
				$ntaken          = (int) $examinee->ntaken;
				$markOrig        = $examinee->markOrig;   // có thể NULL
				$markPpaa        = $examinee->markPpaa;   // có thể NULL
				$stimulationId   = $examinee->stimulationId;
				$stimulationType = (int) ($examinee->stimulationType ?? StimulationHelper::TYPE_NONE);
				$stimulationValue = (float) ($examinee->stimulationValue ?? 0.0);
				$allowed         = (bool) $examinee->allowed;

				// ----------------------------------------------------------------
				// BƯỚC 1: Không đủ điều kiện dự thi
				// ----------------------------------------------------------------
				if (!$allowed) {
					$this->updateExamLearnerConclusion(
						$db, $examId, $learnerId,
						elUpdates: ['conclusion' => Conclusion::Ineligible->value]
					);
					$this->updateClassLearnerExpired($db, $classId, $learnerId, expired: true);
					continue;
				}

				// ----------------------------------------------------------------
				// BƯỚC 2: Khuyến khích quy đổi điểm (TYPE_TRANS)
				// ----------------------------------------------------------------
				if ($stimulationId !== null && $stimulationType === StimulationHelper::TYPE_TRANS) {
					$moduleMark      = $stimulationValue;
					$moduleBase4Mark = ExamHelper::calculateBase4Mark($moduleMark);
					$conclusion      = ExamHelper::calculateConclusion($moduleMark, $stimulationValue, $anomaly, $attempt);
					$moduleGrade     = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);

					$this->updateExamLearnerConclusion($db, $examId, $learnerId, [
						'mark_orig'        => $stimulationValue,
						'mark_final'       => $stimulationValue,
						'module_mark'      => $moduleMark,
						'module_base4_mark'=> $moduleBase4Mark,
						'module_grade'     => $db->quote($moduleGrade),
						'conclusion'       => $conclusion->value,
					]);
					$this->updateClassLearnerExpired($db, $classId, $learnerId, expired: true);
					$this->markStimulationUsed($db, (int) $stimulationId);
					continue;
				}

				// ----------------------------------------------------------------
				// BƯỚC 3: Khuyến khích miễn thi (TYPE_EXEMPT)
				// ----------------------------------------------------------------
				if ($stimulationId !== null && $stimulationType === StimulationHelper::TYPE_EXEMPT) {
					$markFinal       = $stimulationValue;
					$moduleMark      = ExamHelper::calculateModuleMark($subjectId, $pam, $markFinal, $attempt, $admissionYear);
					$moduleBase4Mark = ExamHelper::calculateBase4Mark($moduleMark);
					$conclusion      = ExamHelper::calculateConclusion($moduleMark, $markFinal, $anomaly, $attempt);
					$moduleGrade     = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);

					$this->updateExamLearnerConclusion($db, $examId, $learnerId, [
						'mark_orig'         => $stimulationValue,
						'mark_final'        => $markFinal,
						'module_mark'       => $moduleMark,
						'module_base4_mark' => $moduleBase4Mark,
						'module_grade'      => $db->quote($moduleGrade),
						'conclusion'        => $conclusion->value,
					]);
					$this->updateClassLearnerExpired($db, $classId, $learnerId, expired: true);
					$this->markStimulationUsed($db, (int) $stimulationId);
					continue;
				}

				// ----------------------------------------------------------------
				// BƯỚC 4: Hoãn thi (DELAY) hoặc làm lại bài (REDO)
				// ----------------------------------------------------------------
				if (in_array($anomaly, [Anomaly::Deferred->value, Anomaly::Retake->value], true)) {
					// conclude() với mark = 0 sẽ trả về Conclusion::Deferred
					$conclusion  = ExamHelper::calculateConclusion(0, 0, $anomaly, $attempt);
					$moduleGrade = ExamHelper::calculateModuleGrade(0, $conclusion);

					$this->updateExamLearnerConclusion($db, $examId, $learnerId, [
						'mark_orig'         => 'NULL',
						'mark_final'        => 'NULL',
						'module_mark'       => 'NULL',
						'module_base4_mark' => 'NULL',
						'module_grade'      => $db->quote($moduleGrade),
						'conclusion'        => $conclusion->value,
					]);
					// Không cập nhật ntaken, không set expired
					continue;
				}

				// ----------------------------------------------------------------
				// BƯỚC 5: Chưa có điểm thi → bỏ qua
				// ----------------------------------------------------------------
				if ($markOrig === null) {
					continue;
				}

				// ----------------------------------------------------------------
				// BƯỚC 6: Đã có điểm thi – trường hợp thông thường
				// ----------------------------------------------------------------

				// 6.1. Xác định điểm cộng khuyến khích (nếu có)
				$addValue = ($stimulationId !== null && $stimulationType === StimulationHelper::TYPE_ADD)
					? $stimulationValue
					: 0.0;

				// 6.2. Nếu điểm thi đã bao gồm xử lý kỷ luật (trừ 25%/50%) từ trước,
				//      đặt lại $anomaly = NONE để calculateFinalMark() không trừ thêm lần nữa.
				//      Lưu ý: giữ nguyên $anomaly gốc cho ExamHelper::conclude() ở bước sau
				//      để kết luận vẫn phản ánh đúng tình trạng kỷ luật của thí sinh.
				$anomalyForCalculationFinalMark = $anomaly;
				if ($disciplineAlreadyApplied
					&& in_array($anomaly, [Anomaly::Penalized25->value, Anomaly::Penalized50->value], true)
				) {
					$anomalyForCalculationFinalMark = Anomaly::None->value;
				}

				// 6.3. Tính toán các điểm số
				$examMark        = ($markPpaa !== null) ? (float) $markPpaa : (float) $markOrig;
				$markFinal       = ExamHelper::calculateFinalMark($examMark, $anomalyForCalculationFinalMark, $attempt, $addValue, $admissionYear);
				$moduleMark      = ExamHelper::calculateModuleMark($subjectId, $pam, $markFinal, $attempt, $admissionYear);
				$moduleBase4Mark = ExamHelper::calculateBase4Mark($moduleMark);
				$conclusion      = ExamHelper::calculateConclusion($moduleMark, $markFinal, $anomaly, $attempt);
				$moduleGrade     = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);

				// 6.4. Cập nhật #__eqa_exam_learner
				$this->updateExamLearnerConclusion($db, $examId, $learnerId, [
					'mark_final'        => $markFinal,
					'module_mark'       => $moduleMark,
					'module_base4_mark' => $moduleBase4Mark,
					'module_grade'      => $db->quote($moduleGrade),
					'conclusion'        => $conclusion->value,
				]);

				// 6.5. Cập nhật #__eqa_class_learner (ntaken, expired)
				$expired   = in_array($conclusion, [Conclusion::Passed, Conclusion::RetakeCourse], true) ? 1 : 0;
				$newNtaken = (!in_array($anomaly, [Anomaly::Deferred->value, Anomaly::Retake->value], true))
					? $attempt
					: $ntaken;
				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_class_learner'))
					->set([
						$db->quoteName('ntaken')  . ' = ' . $newNtaken,
						$db->quoteName('expired') . ' = ' . $expired,
					])
					->where($db->quoteName('class_id')   . ' = ' . $classId)
					->where($db->quoteName('learner_id') . ' = ' . $learnerId);
				$db->setQuery($query);
				if (!$db->execute()) {
					throw new Exception(
						sprintf('Lỗi cập nhật lớp học phần cho người học id=%d', $learnerId)
					);
				}

				// 6.6. Ghi nhận khuyến khích cộng điểm đã được sử dụng (nếu có và đạt)
				if ($stimulationId !== null
					&& $stimulationType === StimulationHelper::TYPE_ADD
					&& $conclusion === Conclusion::Passed
				) {
					$this->markStimulationUsed($db, (int) $stimulationId);
				}
			} // end foreach


			$status = null;
			if ($this->isWithAllConclusions($examId)) {
				//Nếu tất cả thí sinh đều đã có kết luận thì tự động cập nhật
				// trạng thái môn thi thành 'Tất cả đã có kết luận'
				$status = ExamStatus::AllConcluded;
				$this->setExamStatus($examId, ExamStatus::AllConcluded);
			}
			else if($this->isWithSomeMarks($examId)) {
				// Nếu có ít nhất 1 thí sinh đã có điểm thi (mark_orig)
				// thì cập nhật trạng thái thành 'Đã có một phần điểm thi'
				$status = ExamStatus::MarkPartial;
				$this->setExamStatus($examId, ExamStatus::MarkPartial);
			}

			//Commit and return the final status of the exam after conclusion
			$db->transactionCommit();
			if($status === null)
				$status = $this->getExamStatus($examId);
			return $status;
		} catch (Exception $e) {
			$db->transactionRollback();
			throw $e;
		}
	}

	/**
	 * Cập nhật các cột kết quả trong #__eqa_exam_learner cho một thí sinh.
	 *
	 * @param DatabaseDriver $db
	 * @param int            $examId
	 * @param int            $learnerId
	 * @param array          $elUpdates  Mảng cặp 'tên_cột' => giá_trị (giá trị đã được escape/quote sẵn nếu cần)
	 *
	 * @throws Exception
	 * @since 2.0.4
	 */
	private function updateExamLearnerConclusion($db, int $examId, int $learnerId, array $elUpdates): void {
		$sets = [];
		foreach ($elUpdates as $col => $val) {
			// Giá trị NULL, số, hoặc chuỗi đã quote → gán trực tiếp
			$sets[] = $db->quoteName($col) . ' = ' . $val;
		}

		$query = $db->getQuery(true)
			->update($db->quoteName('#__eqa_exam_learner'))
			->set($sets)
			->where($db->quoteName('exam_id')    . ' = ' . (int) $examId)
			->where($db->quoteName('learner_id') . ' = ' . (int) $learnerId);
		$db->setQuery($query);

		if (!$db->execute()) {
			throw new Exception(
				sprintf('Lỗi cập nhật kết quả môn thi cho người học id=%d', $learnerId)
			);
		}
	}

	/**
	 * Cập nhật trạng thái expired trong #__eqa_class_learner.
	 *
	 * @param DatabaseDriver $db
	 * @param int            $classId
	 * @param int            $learnerId
	 * @param bool           $expired
	 *
	 * @throws Exception
	 * @since 2.0.4
	 */
	private function updateClassLearnerExpired($db, int $classId, int $learnerId, bool $expired): void
	{
		$query = $db->getQuery(true)
			->update($db->quoteName('#__eqa_class_learner'))
			->set($db->quoteName('expired') . ' = ' . (int) $expired)
			->where($db->quoteName('class_id')   . ' = ' . $classId)
			->where($db->quoteName('learner_id') . ' = ' . $learnerId);
		$db->setQuery($query);

		if (!$db->execute()) {
			throw new Exception(
				sprintf('Lỗi cập nhật trạng thái expired cho người học id=%d', $learnerId)
			);
		}
	}

	/**
	 * Đánh dấu một mục khuyến khích đã được sử dụng.
	 *
	 * @param DatabaseDriver $db
	 * @param int            $stimulationId
	 *
	 * @throws Exception
	 * @since 2.0.4
	 */
	private function markStimulationUsed($db, int $stimulationId): void
	{
		$query = $db->getQuery(true)
			->update($db->quoteName('#__eqa_stimulations'))
			->set($db->quoteName('used') . ' = 1')
			->where($db->quoteName('id') . ' = ' . $stimulationId);
		$db->setQuery($query);

		if (!$db->execute()) {
			throw new Exception(
				sprintf('Lỗi cập nhật trạng thái sử dụng khuyến khích id=%d', $stimulationId)
			);
		}
	}

	/**
	 * Nhập kết quả thi từ hệ thống thi iTest.
	 * Khi nhập điểm sẽ tính toán kết quả học phần (bao gồm cả điểm ưu tiên nếu có)
	 * đánh giá đạt/không đạt; xác định quyền thi tiếp hay hết quyền dự thi.
	 *
	 * @param   int    $examId
	 * @param   array  $examinees Mảng các $obj [code, learnerCode, mark, description]
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function importitest(int $examId, array $examinees): bool
	{
		/* Logic of thí function:
		 * 1. Đọc từ CSDL thông tin thí sinh của môn thi $examId
		 * 2. Kiểm tra, đảm bảo rằng tất cả thí sinh có trong $examinees đều có trong CSDL môn thi
		 *    (trùng khớp cặp thuộc tính 'code' và 'learnerCode'). Nếu không trùng khớp thì báo lỗi
		 *   và thoát.
		 * 3. Với mỗi thí sinh trong $examinees, tiến hành cập nhật điểm theo quy tắc sau:
		 */


		try
		{
			//Init
			$app = Factory::getApplication();
			$db = DatabaseHelper::getDatabaseDriver();


			//1. Đọc từ CSDL thông tin thí sinh của môn thi $examId
			$columns = $db->quoteName(
				array('a.code', 'b.code'),
				array('code',   'learner_code')
			);
			$query = $db->getQuery(true)
				->select($columns)
				->from('#__eqa_exam_learner AS a')
				->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
				->where('a.exam_id=' . $examId);
			$db->setQuery($query);
			$items = $db->loadAssocList('learner_code','code');

			//2. Kiểm tra, đảm bảo rằng tất cả thí sinh có trong $examinees đều có trong CSDL môn thi
			//   (trùng khớp cặp thuộc tính 'code' và 'learnerCode'). Nếu không trùng khớp thì báo lỗi
			//   và thoát.
			foreach ($examinees as $examinee)
			{
				if (!isset($items[$examinee->learnerCode]))
				{
					$msg = Text::sprintf('Không tìm thấy thông tin thí sinh <b>%s</b> trong CSDL môn thi', $examinee->learnerCode);
					throw new Exception($msg);
				}
				if ($items[$examinee->learnerCode] != $examinee->code)
				{
					$msg = Text::sprintf('Thông tin thí sinh <b>%s</b> không khớp với CSDL môn thi', $examinee->learnerCode);
					throw new Exception($msg);
				}
			}

			//3. Tải bảng tra cứu learnerCode → learner_id cho môn thi này
			$query = $db->getQuery(true)
				->select($db->quoteName(['el.learner_id', 'l.code'], ['learner_id', 'learner_code']))
				->from($db->quoteName('#__eqa_exam_learner', 'el'))
				->leftJoin(
					$db->quoteName('#__eqa_learners', 'l'),
					$db->quoteName('l.id') . ' = ' . $db->quoteName('el.learner_id')
				)
				->where($db->quoteName('el.exam_id') . ' = ' . $examId);
			$db->setQuery($query);
			$lookup = $db->loadAssocList('learner_code', 'learner_id');

			//Start transaction
			$db->transactionStart();

			//4. Với mỗi thí sinh trong $examinees, tiến hành cập nhật điểm
			foreach ($examinees as $examinee) {
				$learnerCode = $examinee->learnerCode;

				if (!isset($lookup[$learnerCode])) {
					throw new Exception(
						sprintf('Không tìm thấy thí sinh <b>%s</b> trong môn thi', htmlspecialchars($learnerCode))
					);
				}

				$learnerId  = (int) $lookup[$learnerCode];
				$descValue  = empty($examinee->description)
					? 'NULL'
					: $db->quote($examinee->description);

				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_exam_learner'))
					->set($db->quoteName('mark_orig') . ' = ' . (float) $examinee->mark)
					->set($db->quoteName('description') . ' = ' . $descValue)
					->where($db->quoteName('exam_id') . ' = ' . $examId)
					->where($db->quoteName('learner_id') . ' = ' . $learnerId);
				$db->setQuery($query);

				if (!$db->execute()) {
					throw new Exception(
						sprintf('Lỗi ghi điểm cho thí sinh <b>%s</b>', htmlspecialchars($learnerCode))
					);
				}
			}

			//5. Sau khi cập nhật điểm cho tất cả thí sinh,
			// tiến hành tính toán kết quả học phần và kết luận cho từng thí sinh
			// Lưu ý rằng điểm thi của thí sinh đã bao gồm việc xử lý kỷ luật (nếu có)
			// từ trước, nên khi conclude() sẽ không trừ thêm lần nữa.
			$this->conclude($examId, true);

			//Commit on success
			$db->transactionCommit();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();
			$msg = $e->getMessage();
			$app->enqueueMessage($msg, 'error');
			return false;
		}

		$msg = Text::sprintf('Nhập điểm thành công %d thí sinh', sizeof($examinees));
		$app->enqueueMessage($msg, 'success');
		return true;
	}
	public function getExamResult(int $examId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.code', 'b.code',       'b.lastname', 'b.firstname', 'd.code', 'c.pam1', 'c.pam2', 'c.pam', 'e.type',           'a.attempt', 'a.anomaly', 'a.mark_final', 'a.module_mark', 'a.module_grade', 'c.description'),
			array('code',   'learner_code', 'lastname',   'firstname',   'group',  'pam1',   'pam2',   'pam',   'stimulation_type', 'attempt',   'anomaly',   'mark_final',   'module_mark',   'module_grade',   'description')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_class_learner AS c', 'c.class_id=a.class_id AND c.learner_id=a.learner_id')
			->leftJoin('#__eqa_groups AS d', 'd.id=b.group_id')
			->leftJoin('#__eqa_stimulations AS e', 'e.id=a.stimulation_id')
			->where('a.exam_id=' . $examId)
			->order('firstname, lastname');
		$db->setQuery($query);
		return $db->loadObjectList();
	}


	public function canRequestPpaa(int $examId):bool
	{
		/*
		 * A PPAA request can be sent to an exam if the following conditions are met:
		 * - The corresponding examseason has not been completed yet
		 *   (column 'completed' in #__eqa_examseasons table is FALSE)
		 * - The corresponding examseason is opened for PPAA requests
		 *   (column 'ppaa_req_enabled' in #__eqa_examseasons table is TRUE).
		 * - The PPAA request deadline for the corresponding examseason has not passed yet.
		 *   (column 'ppaa_req_deadline' in #__eqa_examseasons table).
		 */
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select(['b.completed', 'b.ppaa_req_enabled', 'b.ppaa_req_deadline'])
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'b.id=a.examseason_id')
			->where('a.id=' . $examId);
		$db->setQuery($query);
		$info = $db->loadObject();
		if(empty($info))
			return false;
		if($info->completed)
			return false;
		if(!$info->ppaa_req_enabled)
			return  false;
		if(time() > strtotime($info->ppaa_req_deadline))
			return false;
		return true;
	}
	protected function canCompleteResult(array|object $examinees): bool
	{
		if(is_object($examinees))
			$examinees = [$examinees];

		foreach ($examinees as $examinee)
		{

			//Extract required info
			if(is_object($examinee))
			{
				$pam = $examinee->pam;
				$stimulType = $examinee->stimulType;
				$finalMark = $examinee->finalMark;
			}
			else //It is an associative array
			{
				$pam = $examinee['pam'];
				$stimulType = $examinee['stimul_type'];
				$finalMark = $examinee['mark_final'];
			}

			//Make decision
			if(is_null($pam) && $stimulType != StimulationHelper::TYPE_TRANS)
				return false;

			if(is_numeric($finalMark) && $stimulType != StimulationHelper::TYPE_TRANS && $stimulType != StimulationHelper::TYPE_EXEMPT)
				return false;
		}
		return true;
	}
	public function completeResult(int $examId, bool $throwIfCannotComplete)
	{
		if(DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi đã kết thúc');

		$examInfo = DatabaseHelper::getExamInfo($examId);
		if(empty($examInfo))
			throw new Exception('Không tìm thấy môn thi');

		$db = DatabaseHelper::getDatabaseDriver();

		//1.Get examinees
		$columns = [
			'a.class_id              AS classId',
			'a.learner_id            AS learnerId',
			'a.attempt               AS attempt',
			'a.debtor                AS isDebtor',
			'`c`.`type`              AS stimulType',
			'b.pam                   AS pam',
			'a.mark_final            AS finalMark',
			'a.anomaly               AS anomaly'
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_stimulations AS c', 'c.id=a.stimulation_id')
			->where('a.exam_id=' . $examId);
		$db->setQuery($query);
		$examinees = $db->loadObjectList();
		if(empty($examinees))
			throw new Exception('Không tìm thấy thông tin thí sinh');

		//


	}
	public function getExaminees(int $examId):array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('b.id, b.code, b.lastname, b.firstname')
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->where('a.exam_id='.$examId);
		$db->setQuery($query);
		return $db->loadObjectList();
	}

	public function updateExamineePpaa(int $examId, int $learnerId, int $ppaaCode): bool
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set('ppaa='.$ppaaCode)
			->where([
				'exam_id='.$examId,
				'learner_id='.$learnerId]
			);
		$db->setQuery($query);
		return $db->execute();
	}
}
