<?php
namespace Kma\Component\Eqa\Administrator\Model;
use CBOR\TextStringObject;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;

defined('_JEXEC') or die();

class ExamroomModel extends EqaAdminModel {
	public function getExaminees(int $examroomId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.code', 'b.code',       'b.lastname', 'b.firstname', 'c.code', 'd.allowed'),
			array('code',   'learner_code', 'lastname',   'firstname',   'group',  'allowed')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->leftJoin('#__eqa_groups AS c', 'b.group_id=c.id')
			->leftJoin('#__eqa_class_learner AS d', 'a.class_id=d.class_id AND a.learner_id=d.learner_id')
			->where('examroom_id='.$examroomId)
			->order('a.code');
		$db->setQuery($query);
		return $db->loadObjectList();
	}
    public function removeExaminees($examroomId, $learnerIds){
        $app = Factory::getApplication();
        $db =  $this->getDatabase();
        $learnerIdSet = '(' . implode(',', $learnerIds) . ')';

        //1. Kiểm tra điều kiện để xóa
        //a) Nếu có một môn thi nào đó đã được tổ chức thì không cho xóa
        $prohibitStatus = ExamHelper::EXAM_STATUS_EXAM_CONDUCTED;
        $query = $db->getQuery(true)
            ->select('a.learner_id')
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_exams AS b', 'a.exam_id=b.id')
            ->where('a.examroom_id=' . $examroomId . ' AND b.status >= ' . $prohibitStatus . ' AND a.learner_id IN ' . $learnerIdSet)
            ->setLimit(1,0);
        $db->setQuery($query);
        if($db->loadResult() > 0)
        {
            $app->enqueueMessage('Không thể xóa do môn thi đã diễn ra','error');
            return false;
        }
        //b) Nếu có một thí sinh nào đó đã có điểm thi, không cho xóa
        $query = $db->getQuery(true)
            ->select('learner_id')
            ->from('#__eqa_exam_learner')
            ->where('examroom_id=' . $examroomId . ' AND mark_orig IS NOT NULL AND learner_id IN ' . $learnerIdSet)
            ->setLimit(1,0);
        $db->setQuery($query);
        if($db->loadResult() > 0)
        {
            $app->enqueueMessage('Không thể xóa do thí sinh đã có điểm thi','error');
            return false;
        }


        //2. Remove
        $query = $db->getQuery(true)
            ->update('#__eqa_exam_learner')
            ->set('examroom_id = NULL')
            ->where('examroom_id=' . (int)$examroomId . ' AND learner_id IN ' . $learnerIdSet);
        $db->setQuery($query);
        if(!$db->execute())
        {
            $app->enqueueMessage(Text::_('COM_EQA_MSG_DATABASE_ERROR'), 'error');
            return false;
        }
        $msg = Text::sprintf('COM_EQA_MSG_N_EXAMINEES_REMOVED_FROM_EXAMROOM', sizeof($learnerIds));
        $app->enqueueMessage($msg, 'success');
        return true;
    }
    public function addExaminees($examroomId, $examId, $learnerCodes)
    {
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //Try to add the learners to the exam
        $db->transactionStart();
        try {
            $learnerIds = DatabaseHelper::getLearnerMap($learnerCodes);
            if($learnerIds === false)
                throw new Exception(Text::_('COM_EQA_MSG_SOME_LEARNER_CODES_DO_NOT_EXIST'));

            $examroomIds = DatabaseHelper::getExamroomIdsOfExaminees($examId, $learnerIds);
            if($examroomIds === false)
                throw new Exception(Text::_('COM_EQA_MSG_SOME_LEARNERS_ARE_MISSING_FROM_THE_EXAMINEE_LIST'));

            $assignedExaminees = [];
            $addedExaminees = [];
            $examineeCode = DatabaseHelper::getLastExamineeCode($examId);
            foreach ($learnerCodes as $learnerCode)
            {
                $learnerId = $learnerIds[$learnerCode];

                //1. Kiểm tra xem thí sinh của môn thi đã được chia phòng chưa
                if(!empty($examroomIds[$learnerId]))
                {
                    $assignedExaminees[] = $learnerCode;
                    continue;
                }

                //2. Thêm vào phòng thi
                $examineeCode++;
                $query = $db->getQuery(true)
                    ->update('#__eqa_exam_learner')
                    ->set([
                        $db->quoteName('examroom_id') . '=' . $examroomId,
                        $db->quoteName('code') . '=' . $examineeCode
                    ])
                    ->where($db->quoteName('exam_id') . '=' . $examId)
                    ->where($db->quoteName('learner_id') . '=' . $learnerId);
                $db->setQuery($query);
                if(!$db->execute())
                    throw new Exception(Text::_('COM_EQA_MSG_DATABASE_ERROR'));
                $addedExaminees[] = $learnerCode;
            }
        }
        catch (Exception $e){
            $db->transactionRollback();
            $app->enqueueMessage($e->getMessage(),'error');
            return false;
        }

        //Commit
        $db->transactionCommit();
        if(!empty($assignedExaminees)){
            $msg = Text::sprintf('COM_EQA_MSG_N_EXAMINEES_ASSIGNED_BEFORE_S', sizeof($assignedExaminees), implode(',', $assignedExaminees));
            $app->enqueueMessage($msg);
        }
        if(!empty($addedExaminees))
        {
            $msg = Text::sprintf('COM_EQA_MSG_N_EXAMINEES_ADDED_TO_EXAMROOM_S', sizeof($addedExaminees), implode(',', $addedExaminees));
            $app->enqueueMessage($msg, 'success');
        }
        return true;
    }
	public function canExport(int $examroomId):bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$db->setQuery('SELECT monitor1_id, examiner1_id FROM #__eqa_examrooms WHERE id='.$examroomId);
		$obj = $db->loadObject();
		if(empty($obj) ||  (empty($obj->monitor1_id) && empty($obj->examiner1_id)))
			return false;
		return true;
	}

	public function import(int $examroomId, string $examroomName,  array $examinees, bool $importAnomaly)
	{
		//Init
		$db  = DatabaseHelper::getDatabaseDriver();

		//Các bước thực hiện
		//1. Lấy danh sách thí sinh của phòng thi
		//2. Kiểm tra, đảm bảo rằng tất cả các thí sinh đều nằm trong danh sách này
		//3. Phân nhóm thí sinh theo môn thi
		//4. Với mỗi môn thi, tùy theo hình thức thi mà gọi 'importPaperTest' hay 'importNonpaperTest'

		//Bước 1. Lấy danh sách thí sinh của phòng thi
		$columns = $db->quoteName(
			array('a.exam_id', 'a.learner_id', 'b.code',       'a.code', 'a.anomaly'),
			array('exam_id',   'learner_id',   'learner_code', 'code',   'anomaly')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b','b.id=a.learner_id')
			->where('a.examroom_id='.$examroomId);
		$db->setQuery($query);
		$roomExaminees = $db->loadAssocList('learner_code');

		//Bước 2. Kiểm tra, đảm bảo rằng tất cả các thí sinh đều nằm trong danh sách này
		//Đồng thời, bổ sung thông tin 'learner_id', 'anomaly' vào thông tin thí sinh
		foreach ($examinees as $examinee)
		{
			if(empty($roomExaminees[$examinee->learnerCode])){
				$msg = Text::sprintf('Thí sinh <b>%s</b> không tồn tại trong danh sách thí sinh của phòng thi', htmlspecialchars($examinee->learnerCode));
				throw new Exception($msg);
			}
			$examinee->learnerId = $roomExaminees[$examinee->learnerCode]['learner_id'];
			$examinee->currentAanomaly = $roomExaminees[$examinee->learnerCode]['anomaly'];
		}

		//Bước 3. Phân nhóm thí sinh đầu vào ($examinees) theo môn thi ('exam_id')
		$examineesByExamId = [];  //Mảng chứa các mảng thí sinh theo môn thi
		unset($examinee);
		foreach ($examinees as $examinee)
		{
			$examId = $roomExaminees[$examinee->learnerCode]['exam_id'];
			if(!isset($examineesByExamId[$examId]))
				$examineesByExamId[$examId] = [];
			$examineesByExamId[$examId][] = $examinee;
		}

		//Bước 4. Với mỗi môn thi, tùy theo hình thức thi mà gọi 'importPaperTest' hay 'importNonpaperTest'
		foreach ($examineesByExamId as $examId=>$examinees)
		{
			if(DatabaseHelper::isPaperExam($examId))
				$this->importPaperTest($examId, $examroomName, $examineesByExamId[$examId], $importAnomaly);
			else
				$this->importNonpaperTest($examId, $examroomName, $examineesByExamId[$examId], $importAnomaly);
		}
	}
	private function importPaperTest(int $examId, string $examroomName,  array $examinees, bool $importAnomaly): void
	{
		//Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();
		$countAnomaly=0;
		$countPaper=0;  //Bài thi
		$countSheet=0;  //Tờ giấy thi

		$db->transactionStart();
		try
		{
			//Xác định tên môn thi (phục vụ gửi thông báo)
			$db->setQuery('SELECT name FROM #__eqa_exams WHERE id='.$examId);
			$examName = $db->loadResult();
			if(empty($examName))
				throw new Exception('Không tìm thấy môn thi');

			//Xử lý từng thí sinh
			foreach ($examinees as $examinee){
				$learnerCode = $examinee->learnerCode;
				$learnerId = $examinee->learnerId;
				$nsheet = $examinee->value;
				$description = $examinee->description;

				//Kiểm tra tính hợp lệ của cột 'số tờ'
				if(!is_numeric($nsheet) || intval($nsheet)!=$nsheet)
				{
					$msg = Text::sprintf('Môn thi <b>%s</b>. Phòng thi <b>%s</b>: số tờ giấy thi của <b>%s</b> không hợp lệ',
						htmlspecialchars($examName),
						$examroomName, $learnerCode);
					throw new Exception($msg);
				}
				else
					$nsheet = (int)$nsheet;

				if($importAnomaly)
					$anomaly = $examinee->anomaly;
				else
					$anomaly = $examinee->currentAanomaly;
				if($anomaly != ExamHelper::EXAM_ANOMALY_NONE)
					$countAnomaly++;

				//Ghi thông tin thu bài thi viết (upset operation)
				$query = 'INSERT INTO `#__eqa_papers` (`exam_id`, `learner_id`, `nsheet`)'
					. "VALUES ($examId, $learnerId, $nsheet)"
					. 'ON DUPLICATE KEY UPDATE `nsheet` = VALUES(`nsheet`)';

				$db->setQuery($query);
				if(!$db->execute()){
					$msg = Text::sprintf('Môn thi <b>%s</b>. Phòng thi <b>%s</b>: lỗi cập nhật thông tin cho <b>%s</b>',
						htmlspecialchars($examName),
						$examroomName, $learnerCode);
					throw new Exception($msg);
				}

				//Ngoài ra, còn cần lưu $description, $anomaly vào bảng #__eqa_exam_learner
				$setClause = [];
				if(empty($description))
					$description = 'NULL';
				else
					$description = $db->quote($description);
				$setClause[] = 'description = ' . $description;
				if($importAnomaly)
					$setClause[] = 'anomaly = ' . $examinee->anomaly;
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set($setClause)
					->where('exam_id='.$examId . ' AND learner_id=' . $learnerId);
				$db->setQuery($query);
				if(!$db->execute()){
					$msg = Text::sprintf('Môn thi <b>%s</b>. Phòng thi <b>%s</b>: lỗi cập nhật thông tin cho <b>%s</b>',
						htmlspecialchars($examName), $examroomName, $learnerCode);
					throw new Exception($msg);
				}

				//Counting
				if($nsheet>0){
					$countPaper++;
					$countSheet += $nsheet;
				}

			}
		}
		catch (Exception $e)
		{
			$db->transactionRollback();
			throw  new Exception($e->getMessage());
		}

		//Commit on success
		$db->transactionCommit();
		$msg = Text::sprintf('Môn thi <b>%s</b>. Phòng thi viết <b>%s</b>: %d thí sinh, %d bài thi, %d tờ giấy thi; trong đó %d thí sinh có bất thường',
			htmlspecialchars($examName),
			$examroomName,
			sizeof($examinees),
			$countPaper,
			$countSheet,
			$countAnomaly
		);
		$app->enqueueMessage($msg, 'success');
	}
	private function importNonpaperTest(int $examId, string $examroomName,  array $examinees, bool $importAnomaly): void
	{
		//Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();
		$countAnomaly = 0;

		$db->transactionStart();
		try
		{
			//Xác định tên môn thi (phục vụ gửi thông báo)
			$db->setQuery('SELECT name FROM #__eqa_exams WHERE id='.$examId);
			$examName = $db->loadResult();
			if(empty($examName))
				throw new Exception('Không tìm thấy môn thi');

			//Xử lý từng thí sinh
			foreach ($examinees as $examinee){
				$code = $examinee->code;
				$learnerCode = $examinee->learnerCode;
				$mark = GeneralHelper::toFloat($examinee->value);
				$description = $examinee->description;

				//Kiểm tra tính hợp lệ của cột 'điểm'
				if($mark === false || $mark<0 || $mark>10)
				{
					$msg = Text::sprintf('Môn thi <b>%s</b>. Phòng thi <b>%s</b>: điểm thi của <b>%s</b> không hợp lệ',
						htmlspecialchars($examName),
						htmlspecialchars($examroomName), htmlspecialchars($learnerCode));
					throw new Exception($msg);
				}

				/**
				 * Việc import gồm một số bước
				 *  - Ghi điểm $mark vào bảng #__eqa_exam_learner (cột 'mark_orig')
				 *    đồng thời tính toán các giá trị 'mark_final', 'module_grade'
				 *  - Cập nhật số lượt thi, điều kiện tiếp tục thi vào bảng #__eqa_class_learner
				 */
				//a) Tìm id, pam, anomaly của thí sinh
				$columns = $db->quoteName(
					array('a.learner_id', 'a.exam_id', 'c.subject_id', 'a.class_id', 'b.pam', 'a.attempt', 'a.anomaly', 'b.ntaken', 'd.id',     'd.type',     'd.value'),
					array('learner_id',   'exam_id',   'subject_id',   'class_id',   'pam',   'attempt',   'anomaly',   'ntaken',   'stimul_id','stimul_type','stimul_value')
				);
				$query = $db->getQuery(true)
					->select($columns)
					->from('#__eqa_exam_learner AS a')
					->leftJoin('#__eqa_class_learner AS b', 'a.class_id=b.class_id AND a.learner_id=b.learner_id')
					->leftJoin('#__eqa_exams AS c', 'a.exam_id=c.id')
					->leftJoin('#__eqa_stimulations AS d', 'd.id=a.stimulation_id')
					->where('a.exam_id=' . $examId . ' AND a.code=' . $code);
				$db->setQuery($query);
				$obj = $db->loadObject();
				$attempt = (int)$obj->attempt;
				$pam = (float)$obj->pam;
				$subjectId = (int)$obj->subject_id;
				$classId = (int)$obj->class_id;
				$ntaken = (int)$obj->ntaken;
				$learnerId = (int)$obj->learner_id;
				$stimulationId = $obj->stimul_id;
				$stimulationType = $obj->stimul_type;
				$stimulationValue = $obj->stimul_value;
				if($importAnomaly)
					$anomaly = $examinee->anomaly;
				else
					$anomaly = (int)$obj->anomaly;
				if($anomaly != ExamHelper::EXAM_ANOMALY_NONE)
					$countAnomaly++;


				//b) Tính toán và cập nhật điểm
				//   Với lưu ý rằng điểm trong biên bản là điểm sau khi đã xử lý kỷ luật, nên khi tính $finalMark
				//   thì luôn đặt $anomaly là NONE
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
						'anomaly = ' . $anomaly,
						'mark_final = ' . $finalMark,
						'module_mark = ' . $moduleMark,
						'module_grade = ' . $db->quote($moduleGrade),
						'conclusion = ' . $conclusion,
						'description = ' . $description
					])
					->where('exam_id=' . $examId . ' AND code=' . $code);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Môn thi <b>%s</b>. Phòng thi %s: lỗi cập nhật điểm học phần cho <b>%s</b>',
						htmlspecialchars($examName), $examroomName, $learnerCode);
					throw new Exception($msg);
				}

				//c) Cập nhật số lượt thi, điều kiện tiếp tục dự thi
				if(!in_array($anomaly, [ExamHelper::EXAM_ANOMALY_DELAY, ExamHelper::EXAM_ANOMALY_REDO]))
				{
					$ntaken = $attempt;
				}
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
					$msg = Text::sprintf('Môn thi <b>%s</s>, phòng thi <b>%s</b>, %s: lỗi cập nhật thông tin điểm học phần',
						htmlspecialchars($examName), $examroomName, $learnerCode);
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
						$msg = Text::sprintf('Môn thi <b>%s</s>, phòng thi <b>%s</b>:Lỗi ghi nhận điểm khuyến khích cho <b>%s</b>',
							htmlspecialchars($examName), htmlspecialchars($examroomName), $learnerCode);
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
			throw new Exception($e->getMessage());
		}

		$msg = Text::sprintf('Môn thi <b>%s</b>. Phòng thi <b>%s</b>: nhập điểm thành công %d thí sinh, trong đó %d thí sinh có bất thường',
			htmlspecialchars($examName), htmlspecialchars($examroomName), sizeof($examinees), $countAnomaly);
		$app->enqueueMessage($msg, 'success');
	}

	public function getExamineeAnomalies(int $examroomId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('b.id', 'a.code', 'b.code', 'b.lastname', 'b.firstname', 'a.anomaly', 'a.description'),
			array('id', 'code','learner_code', 'lastname', 'firstname', 'anomaly','description')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->where('a.examroom_id='.$examroomId)
			->order('a.code ASC');
		$db->setQuery($query);
		return $db->loadObjectList();
	}

	/*
	 * Chỉ ghi nhận bất thường, không xử lý.
	 * Lý do: nếu CBKT thao tác nhầm, đến khi "undo" sẽ rất phức tạp. Việc xử lý bất thường
	 * chỉ được thực hiện khi nhập điểm
	 */
	public function saveAnomaly(int $examroomId, array $data): bool
	{
		$db = DatabaseHelper::getDatabaseDriver();
		foreach ($data as $learnerId => $learner)
		{
			$setClause = [];
			$setClause[] = $db->quoteName('anomaly') . '=' . $learner['anomaly'];
			if(!empty($learner['description']))
				$setClause[] = $db->quoteName('description') . '=' . $db->quote($learner['description']);
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set($setClause)
				->where([
					'learner_id=' . $learnerId,
					'examroom_id=' . $examroomId
				]);
			$db->setQuery($query);
			if(!$db->execute())
				return false;
		}
		return true;
	}
}
