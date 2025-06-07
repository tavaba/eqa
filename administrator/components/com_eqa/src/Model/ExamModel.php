<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Collator;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\RoomHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;

defined('_JEXEC') or die();

class ExamModel extends EqaAdminModel{
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
    public function addExaminees(int $examId, string $classCode, array $learnerCodes, int $attempt): bool
    {
	    if (DatabaseHelper::isCompletedExam($examId))
		    throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể thêm thí sinh');

        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //Find the class by its code ($classCode)
        $db->setQuery('SELECT * FROM #__eqa_classes WHERE code='.$db->quote($classCode));
        $class = $db->loadObject();
        if(empty($class))
        {
            $msg = Text::_('COM_EQA_MSG_CLASS_CODE_DOES_NOT_EXIST');
            $msg .= ': <b>' . htmlentities($classCode) . '</b>';
            $app->enqueueMessage($msg,'error');
            return false;
        }

        //Check to ensure that the class and the exam belong to the same subject
        $db->setQuery('SELECT * FROM #__eqa_exams WHERE id='.$examId);
        $exam = $db->loadObject();
        if($class->subject_id != $exam->subject_id){
            $msg = Text::sprintf('Lớp học phần <b>%s</b> không phù hợp với môn thi <b>%s</b>',
                htmlentities($class->name),
                htmlentities($exam->name));
            $app->enqueueMessage($msg,'error');
            return false;
        }

        //Try to add the learners to the exam
        $db->transactionStart();
        try {
	        foreach ($learnerCodes as $learnerCode){
                //Get the 'learner'
				$db->setQuery('SELECT id, debtor FROM #__eqa_learners WHERE code=' . $db->quote($learnerCode));
				$learner = $db->loadObject();
				if(empty($learner))
				{
					$msg = Text::sprintf('Không tìm thấy HVSV có mã <b>%s</b>', $learnerCode);
					throw new Exception($msg);
				}

                //Get learner info from the class
                $db->setQuery('SELECT * FROM #__eqa_class_learner WHERE class_id='.$class->id.' AND learner_id='.$learner->id);
                $classLearner = $db->loadObject();
                if(empty($classLearner)) {
                    $msg = Text::sprintf('<b>%s</b> không tồn tại trong lớp %s',
                        htmlentities($learnerCode),
                        htmlentities($class->name)
                    );
                    throw new Exception($msg);
                }
				if($classLearner->expired){
					$msg = Text::sprintf('Trong lớp %s, HVSV <b>%s</b> đã hết quyền dự thi', $classCode, $learnerCode);
					throw new Exception($msg);
				}

                //Add learner to the exam
                $query = $db->getQuery(true)
                    ->insert('#__eqa_exam_learner')
                    ->columns('exam_id, class_id, learner_id, debtor, attempt')
                    ->values(implode(',',[$examId, $class->id, $learner->id, $learner->debtor, $attempt]));
                $db->setQuery($query);
                if(!$db->execute()){
                    $msg = Text::_('COM_EQA_MSG_INSERT_INTO_DATABASE_FAILED');
                    throw new Exception($msg);
                }
            }

            //Commit
            $db->transactionCommit();
            $msg = Text::sprintf('COM_EQA_MSG_N_ITEMS_IMPORT_SUCCESS', sizeof($learnerCodes));
            $app->enqueueMessage($msg,'success');
            return true;
        }
        catch (Exception $e){
            $db->transactionRollback();
            $app->enqueueMessage($e->getMessage(),'error');
            return false;
        }
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
				'anomaly=' . ExamHelper::EXAM_ANOMALY_DELAY,
				'conclusion=' . ExamHelper::CONCLUSION_RESERVED
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
				'a.anomaly=' . ExamHelper::EXAM_ANOMALY_DELAY
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
				'anomaly=' . ExamHelper::EXAM_ANOMALY_NONE,
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
		if($status == ExamHelper::EXAM_STATUS_PAM_BUT_QUESTION)
			$status = ExamHelper::EXAM_STATUS_QUESTION_AND_PAM;
		elseif($status < ExamHelper::EXAM_STATUS_QUESTION_AND_PAM)
			$status = ExamHelper::EXAM_STATUS_QUESTION_BUT_PAM;

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
		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể chia phòng');

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
		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể chia phòng');

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
			$conclusion = ExamHelper::CONCLUSION_PASSED;
			$moduleMark = ExamHelper::calculateModuleMark($subjectId, $stimulationValue, $stimulationValue, $attempt);
			$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set([
					'stimulation_id=' . $stimulationId,
					'mark_orig=' . $stimulationValue,
					'mark_final=' . $stimulationValue,
					'module_mark=' . $moduleMark,
					'conclusion=' . $conclusion,
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
			$conclusion = ExamHelper::CONCLUSION_PASSED;
			$moduleMark = ExamHelper::calculateModuleMark($subjectId, $pam, $stimulationValue, $attempt);
			$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set([
					'stimulation_id=' . $stimulationId,
					'mark_orig=' . $stimulationValue,
					'mark_final=' . $stimulationValue,
					'module_mark=' . $moduleMark,
					'conclusion=' . $conclusion,
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
		//Mà còn phải xóa cả điểm (nếu có): final_mark, module_mark, module_grade, conclusion
		//để CBKT biết mà cập nhật lại
		$learnerIds = array_column($stimulations, 'learner_id');
		$learnerIdSet = '(' . implode(',', $learnerIds) . ')';
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set([
				'stimulation_id=NULL',
				'final_mark=NULL',
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
	public function updateStimulations($examId): bool
	{
		//1. Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		if (DatabaseHelper::isCompletedExam($examId))
		{
			$msg='Môn thi hoặc kỳ thi đã kết thúc. Không thể cập nhật thông tin khuyến khích';
			$app->enqueueMessage($msg, 'error');
			return false;
		}

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
		if(empty($examinees)){
			$app->enqueueMessage('Không có thí sinh nào của môn thi này được khuyến khích');
			return false;
		}

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
			$app->enqueueMessage($e->getMessage(), 'error');
			return false;
		}

		//5. Return
		$countApplied = $countAppliedExemption + $countAppliedTransfer + $countAppliedAddition;
		$countTotal = sizeof($examinees);
		$msg = Text::sprintf('%d/%d khuyến khích được áp dụng: %d/%d Miễn thi, %d/%d Cộng điểm, %d/%d Quy đổi điểm',
			$countApplied, $countTotal,
			$countAppliedExemption, sizeof($exemptions),
			$countAppliedAddition, sizeof($additions),
			$countAppliedTransfer, sizeof($transfers)
		);
		$type = $countApplied == $countTotal ? 'success' : 'info';
		$app->enqueueMessage($msg, $type);
		return true;
	}

	public function updateDebt($examId): bool
	{
		//1. Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//2. Kiểm tra điều kiện
		if (DatabaseHelper::isCompletedExam($examId))
		{
			$msg='Môn thi hoặc kỳ thi đã kết thúc. Không thể cập nhật thông tin nợ phí';
			$app->enqueueMessage($msg, 'error');
			return false;
		}

		//3. Lấy thông tin nợ phí hiện thời từ 2 nơi: môn thi, người học
		$columns = $db->quoteName(
			array('a.learner_id', 'b.code',      'a.debtor',    'b.debtor', 'a.module_mark'),
			array('learnerId',    'learnerCode', 'currentDebt', 'newDebt',  'moduleMark')
		);
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
			$app->enqueueMessage($e->getMessage(), 'error');
		}

		if(!empty($listUnset))
		{
			$msg = Text::sprintf('%d HVSV đã hết nợ: %s',
				sizeof($listUnset),
				implode(', ', $listUnset)
			);
			$app->enqueueMessage($msg, 'success');
		}
		if(!empty($listSet))
		{
			$msg = Text::sprintf('%d HVSV đã phát sinh nợ: %s',
				sizeof($listSet),
				implode(', ', $listSet)
			);
			$app->enqueueMessage($msg, 'success');
		}
		if(!empty($listCannotChange))
		{
			$msg = Text::sprintf('%d HVSV có thay đổi trạng thái nợ phí nhưng không được cập nhật vì đã có kết quả: %s',
				sizeof($listCannotChange),
				implode(', ', $listCannotChange)
			);
			$app->enqueueMessage($msg, 'warning');
		}
		if(empty($listSet) && empty($listUnset))
		{
			$app->enqueueMessage('Thông tin nợ phí không thay đổi', 'success');
		}
		return true;
	}

	public function setExamStatus(int $examId, int $status): bool
	{
		if (DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi hoặc kỳ thi đã kết thúc. Không thể cập nhật trạng thái');

		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->update('#__eqa_exams')
			->set($db->quoteName('status') . '=' . $status)
			->where('id=' . $examId);
		$db->setQuery($query);
		return $db->execute();
	}

	public function recheckStatus(int $examId): bool
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Lấy status hiện tại, kết hợp lấy tên môn thi
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('name'),
				$db->quoteName('status'),
			])
			->from('#__eqa_exams')
			->where('id=' . $examId);
		$db->setQuery($query);
		$item = $db->loadObject();
		$curentStatus = $item->status;
		$examName = $item->name;

		//Nếu không phải "dở điểm" thì bỏ qua
		if($curentStatus != ExamHelper::EXAM_STATUS_MARK_PARTIAL)
			return false;

		/**
		 * Kết luận các trường hợp chưa kết luận
		 * - Không đạt quá trình: hết lượt
		 * - Nợ học phí, vắng thi: mất lượt
		 * - Hoãn thi
		 */
		//1. Xử lý các trường hợp không đạt quá trình
		$columns = $db->quoteName(
			array('a.class_id', 'a.learner_id'),
			array('class_id', 'learner_id')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', '(b.class_id=a.class_id AND b.learner_id=a.learner_id)')
			->where([
				$db->quoteName('a.exam_id') . '=' . $examId,
				$db->quoteName('b.allowed') . '=0'
			]);
		$db->setQuery($query);
		$prohibitedLearners = $db->loadObjectList();
		if(!empty($prohibitedLearners)){
			foreach ($prohibitedLearners as $learner)
			{
				//Cấm thi ở lớp học phần
				$query = $db->getQuery(true)
					->update('#__eqa_class_learner')
					->set('expired=1')
					->where([
						'class_id=' . $learner->class_id,
						'learner_id=' . $learner->learner_id
				]);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật thông tin môn thi <b>%s</b>', htmlspecialchars($examName));
					$app->enqueueMessage($msg, 'error');
					return false;
				}

				//Kết luận ở môn thi
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set('conclusion=' . ExamHelper::CONCLUSION_FAILED_EXPIRED)
					->where([
						'exam_id=' . $examId,
						'learner_id=' . $learner->learner_id
					]);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật thông tin môn thi <b>%s</b>', htmlspecialchars($examName));
					$app->enqueueMessage($msg, 'error');
					return false;
				}
			}
		}

		//2. Xử lý các trường hợp nợ học phí hoặc vắng thi không lý do
		$columns = $db->quoteName(
			array('a.class_id', 'a.learner_id', 'a.attempt'),
			array('class_id', 'learner_id', 'attempt')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', '(b.class_id=a.class_id AND b.learner_id=a.learner_id)')
			->where([
				'a.exam_id=' . $examId,
				'b.allowed<>0',
				'(a.debtor <> 0 OR a.anomaly=' . ExamHelper::EXAM_ANOMALY_ABSENT . ')'
			]);
		$db->setQuery($query);
		$examinees = $db->loadObjectList();
		if(!empty($examinees)){
			$maxAttempts = ConfigHelper::getMaxExamAttempts();
			foreach ($examinees as $learner)
			{
				//Kết luận ở lớp học phần: trừ 1 lượt thi
				if($learner->attempt >= $maxAttempts)
					$expired=1;
				else
					$expired=0;
				$query = $db->getQuery(true)
					->update('#__eqa_class_learner')
					->set([
						'expired=' . $expired,
						'ntaken=' . $learner->attempt
					])
					->where([
						'class_id=' . $learner->class_id,
						'learner_id=' . $learner->learner_id
					]);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật thông tin môn thi <b>%s</b>', htmlspecialchars($examName));
					$app->enqueueMessage($msg, 'error');
					return false;
				}

				//Kết luận ở môn thi: trượt 1 lượt thi
				if($learner->attempt >= $maxAttempts)
					$conclusion = ExamHelper::CONCLUSION_FAILED_EXPIRED;
				else
					$conclusion = ExamHelper::CONCLUSION_FAILED;
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set([
						'mark_orig=0',
						'mark_final=0',
						'module_mark=0',
						'module_grade=\'F\'',
						'conclusion=' . $conclusion
					])
					->where([
						'exam_id=' . $examId,
						'learner_id=' . $learner->learner_id
					]);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật thông tin môn thi <b>%s</b>', htmlspecialchars($examName));
					$app->enqueueMessage($msg, 'error');
					return false;
				}
			}
		}

		//3. Xử lý các trường hợp hoãn thi
		$query = $db->getQuery(true)
			->update('#__eqa_exam_learner')
			->set('conclusion=' . ExamHelper::CONCLUSION_RESERVED)
			->where([
				'exam_id=' . $examId,
				'anomaly=' . ExamHelper::EXAM_ANOMALY_DELAY
			]);
		$db->setQuery($query);
		if(!$db->execute())
		{
			$msg = Text::sprintf('Lỗi cập nhật thông tin môn thi <b>%s</b>', htmlspecialchars($examName));
			$app->enqueueMessage($msg, 'error');
			return false;
		}

		//Đếm lại điểm
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_exam_learner WHERE conclusion IS NULL AND exam_id=' . $examId);
		$count = $db->loadResult();
		if($count==0)
		{
			$query = $db->getQuery(true)
				->update('#__eqa_exams')
				->set('status=' . ExamHelper::EXAM_STATUS_MARK_FULL)
				->where('id=' . $examId);
			$db->setQuery($query);
			if(!$db->execute())
			{
				$msg = Text::sprintf('Lỗi cập nhật thông tin môn thi <b>%s</b>', htmlspecialchars($examName));
				$app->enqueueMessage($msg, 'error');
				return false;
			}
			$msg = Text::sprintf('Đã cập nhật thông tin môn thi <b>%s</b>', htmlspecialchars($examName));
			$app->enqueueMessage($msg, 'success');
		}
		return true;
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
	 * @throws Exception
	 * @since version
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


		//Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();


		$db->transactionStart();
		try
		{
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

			//3. Với mỗi thí sinh trong $examinees, tiến hành cập nhật điểm
			foreach ($examinees as $examinee){
				$learnerCode = $examinee->learnerCode;
				$mark = $examinee->mark;
				$description = $examinee->description;
				/**
				 * Việc import gồm một số bước
				 *  - Ghi điểm $mark vào bảng #__eqa_exam_learner (cột 'mark_orig')
				 *    đồng thời tính toán các giá trị 'mark_final', 'module_grade' (gồm cộng điểm ưu tiên, nếu có)
				 *  - Cập nhật số lượt thi, điều kiện tiếp tục thi vào bảng #__eqa_class_learner
				 *  - Đánh dấu đã sử dụng chế độ ưu tiên, nếu có
				 */
				//a) Tìm id, pam, anomaly, stimulation của thí sinh
				$columns = $db->quoteName(
					array('a.learner_id', 'c.subject_id', 'a.class_id', 'b.pam', 'a.attempt', 'a.anomaly', 'b.ntaken', 'd.id',     'd.type',     'd.value'),
					array('id',           'subject_id',   'class_id',   'pam',   'attempt',   'anomaly',   'ntaken',   'stimul_id','stimul_type','stimul_value')
				);
				$query = $db->getQuery(true)
					->select($columns)
					->from('#__eqa_exam_learner AS a')
					->leftJoin('#__eqa_class_learner AS b', 'a.class_id=b.class_id AND a.learner_id=b.learner_id')
					->leftJoin('#__eqa_exams AS c', 'a.exam_id=c.id')
					->leftJoin('#__eqa_stimulations AS d', 'd.id = a.stimulation_id')
					->where('a.exam_id=' . $examId . ' AND a.code=' . $examinee->code);
				$db->setQuery($query);
				$obj = $db->loadObject();
				if(empty($obj))
				{
					$msg = Text::sprintf('Không tìm thấy thông tin thí sinh <b>%s</b> trong CSDL môn thi', $learnerCode);
					throw new Exception($msg);
				}

				//Trích xuất, bổ sung thông tin thí sinh
				$learnerId = $obj->id;
				$pam = $obj->pam;
				$anomaly = $obj->anomaly;
				$attempt = $obj->attempt;
				$subjectId = $obj->subject_id;
				$classId = $obj->class_id;
				$ntaken = $obj->ntaken;
				$stimulationId = $obj->stimul_id;
				$stimulationType = $obj->stimul_type;
				$stimulationValue = $obj->stimul_value;

				//b) Tính toán và cập nhật điểm
				//Vì là môn thi iTest nên việc trừ điểm kỷ (nếu có) đã được thực hiện từ trước
				//Do đó, $finalMark ở đây sẽ luôn đợc tính với EXAM_ANOMALY_NONE
				$addValue = $stimulationType==StimulationHelper::TYPE_ADD ? $stimulationValue : 0;
				$finalMark = ExamHelper::calculateFinalMark($mark, ExamHelper::EXAM_ANOMALY_NONE, $attempt, $addValue);
				$moduleMark = ExamHelper::calculateModuleMark($subjectId, $pam, $finalMark, $attempt);
				$conclusion = ExamHelper::conclude($moduleMark, $finalMark, $anomaly, $attempt);
				$moduleGrade = ExamHelper::calculateModuleGrade($moduleMark, $conclusion);
				if(empty($description))
					$description = 'NULL';
				else
					$description = $db->quote($description);
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set([
						'mark_orig = ' . $mark,
						'mark_final = ' . $finalMark,
						'module_mark = ' . $moduleMark,
						'module_grade = ' . $db->quote($moduleGrade),
						'conclusion = ' . $conclusion,
						'description = ' . $description
					])
					->where('exam_id=' . $examId . ' AND learner_id=' . $learnerId);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật điểm học phần cho thí sinh <b>%s</b>', $learnerCode);
					throw new Exception($msg);
				}

				//c) Cập nhật số lượt thi, điều kiện tiếp tục dự thi
				if(!in_array($anomaly, [ExamHelper::EXAM_ANOMALY_DELAY, ExamHelper::EXAM_ANOMALY_REDO]))
					$ntaken = $attempt;
				$expired = 0;
				if($conclusion == ExamHelper::CONCLUSION_PASSED || $conclusion == ExamHelper::CONCLUSION_FAILED_EXPIRED)
					$expired=1;
				$query = $db->getQuery(true)
					->update('#__eqa_class_learner')
					->set([
						'ntaken = ' . $ntaken,
						'expired = ' . $expired
					])
					->where([
						'class_id = ' . $classId,
						'learner_id = ' . $learnerId
					]);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật thông tin điểm học phần cho <b>%s</b>', $learnerCode);
					throw new Exception($msg);
				}

				//d) Ghi nhận chế độ khuyến khích đã được sử dụng
				//Nếu SV được cộng điểm, kết quả đánh giá học phần là "ĐẠT" thì ghi nhận
				//chế độ khuyến khích của SV đã được sử dụng
				if($stimulationType==StimulationHelper::TYPE_ADD && $conclusion == ExamHelper::CONCLUSION_PASSED)
				{
					$query = $db->getQuery(true)
						->update('#__eqa_stimulations')
						->set('used=1')
						->where('id=' . $stimulationId);
					$db->setQuery($query);
					if(!$db->execute())
					{
						$msg = Text::sprintf('Lỗi ghi nhận điểm khuyến khích cho <b>%s</b>', $learnerCode);
						throw new Exception($msg);
					}
				}
			}
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
			array('a.code', 'b.code',       'b.lastname', 'b.firstname', 'd.code', 'c.pam1', 'c.pam2', 'c.pam', 'e.type',           'a.anomaly', 'a.mark_final', 'a.module_mark', 'a.module_grade', 'c.description'),
			array('code',   'learner_code', 'lastname',   'firstname',   'group',  'pam1',   'pam2',   'pam',   'stimulation_type', 'anomaly',   'mark_final',   'module_mark',   'module_grade',   'description')
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
}
