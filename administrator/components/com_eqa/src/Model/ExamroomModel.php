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
            ->where('b.status >= ' . $prohibitStatus . ' AND a.learner_id IN ' . $learnerIdSet)
            ->setLimit(1,0);
        $db->setQuery($query);
        if($db->loadResult() > 0)
        {
            $msg = Text::_('COM_EQA_MSG_DELETION_IS_PROHIBITED');
            $app->enqueueMessage($msg,'error');
            return false;
        }
        //b) Nếu có một thí sinh nào đó đã có điểm thi, không cho xóa
        $query = $db->getQuery(true)
            ->select('learner_id')
            ->from('#__eqa_exam_learner')
            ->where('mark_orig IS NOT NULL AND learner_id IN ' . $learnerIdSet)
            ->setLimit(1,0);
        $db->setQuery($query);
        if($db->loadResult() > 0)
        {
            $msg = Text::_('COM_EQA_MSG_DELETION_IS_PROHIBITED');
            $app->enqueueMessage($msg,'error');
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

	/**
	 * @param   int     $examroomId
	 * @param   string  $examroomName
	 * @param   array   $examinees Mảng các object với các thuộc tính [code, learnerCode, value, description]
	 *
	 * @return bool
	 *
	 * @throws Exception
	 * @since version
	 */
	public function importPaperTest(int $examroomId, string $examroomName,  array $examinees, bool $importAnomaly): bool
	{
		//Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();
		$countAnomaly=0;
		$multipleExam = sizeof(DatabaseHelper::getExamroomExamIds($examroomId))>1;

		$countPaper=0;  //Bài thi
		$countSheet=0;  //Tờ giấy thi
		$db->transactionStart();
		try
		{
			foreach ($examinees as $examinee){
				$code = (int)$examinee->code;
				$learnerCode = $examinee->learnerCode;
				$nsheet = $examinee->value;
				$description = $examinee->description;

				//Kiểm tra tính hợp lệ của cột 'số tờ'
				if(!is_numeric($nsheet) || intval($nsheet)!=$nsheet)
				{
					$msg = Text::sprintf('Phòng thi <b>%s</b>: số tờ giấy thi của <b>%s</b> không hợp lệ', $examroomName, $learnerCode);
					throw new Exception($msg);
				}
				else
					$nsheet = (int)$nsheet;

				//Xác định $examId và $learnerId
				$query = $db->getQuery(true)
					->from('#__eqa_exam_learner')
					->select('exam_id, learner_id, anomaly')
					->where('code='.$code . ' AND examroom_id=' . $examroomId);
				$db->setQuery($query);
				$obj = $db->loadAssoc();
				$examId = (int)$obj['exam_id'];
				$learnerId = (int)$obj['learner_id'];
				if($importAnomaly)
					$anomaly = $examinee->anomaly;
				else
					$anomaly = (int)$obj['anomaly'];
				if($anomaly != ExamHelper::EXAM_ANOMALY_NONE)
					$countAnomaly++;

				//Ghi thông tin thu bài thi viết (upset operation)
				$query = 'INSERT INTO `#__eqa_papers` (`exam_id`, `learner_id`, `nsheet`)'
					. "VALUES ($examId, $learnerId, $nsheet)"
					. 'ON DUPLICATE KEY UPDATE `nsheet` = VALUES(`nsheet`)';

				$db->setQuery($query);
				if(!$db->execute()){
					$msg = Text::sprintf('Phòng thi <b>%s</b>: lỗi cập nhật thông tin cho <b>%s</b>', $examroomName, $learnerCode);
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
					$msg = Text::sprintf('Phòng thi <b>%s</b>: lỗi cập nhật thông tin cho <b>%s</b>', $examroomName, $learnerCode);
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
			$app->enqueueMessage($e->getMessage(), 'error');
			return false;
		}

		//Commit on success
		$db->transactionCommit();
		$msg = Text::sprintf('Phòng thi viết <b>%s</b>: %d thí sinh, %d bài thi, %d tờ giấy thi; trong đó %d thí sinh có bất thường',
			$examroomName,
			sizeof($examinees),
			$countPaper,
			$countSheet,
			$countAnomaly
		);
		$app->enqueueMessage($msg, 'success');
		return true;
	}
	public function importNonpaperTest(int $examroomId, string $examroomName,  array $examinees, bool $importAnomaly): bool
	{
		//Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();
		$countAnomaly = 0;

		$db->transactionStart();
		try
		{
			foreach ($examinees as $item){
				$code = $item->code;
				$learnerCode = $item->learnerCode;
				$mark = GeneralHelper::toFloat($item->value);
				$description = $item->description;

				//Kiểm tra tính hợp lệ của cột 'điểm'
				if($mark === false || $mark<0 || $mark>10)
				{
					$msg = Text::sprintf('Phòng thi <b>%s</b>: điểm thi của <b>%s</b> không hợp lệ', $examroomName, $learnerCode);
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
					->where('a.examroom_id=' . $examroomId . ' AND a.code=' . $code);
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
					$anomaly = $item->anomaly;
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
				$conclusion = ExamHelper::conclude($moduleMark, $mark, $anomaly, $attempt);
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
					->where('examroom_id=' . $examroomId . ' AND code=' . $code);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Phòng thi %s: lỗi cập nhật điểm học phần cho <b>%s</b>', $examroomName, $learnerCode);
					throw new \Exception($msg);
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
					$msg = Text::sprintf('Phòng thi %s, %s: lỗi cập nhật thông tin điểm học phần', $examroomName, $learnerCode);
					throw new \Exception($msg);
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
			$msg = Text::sprintf('Phòng thi <b>%s</b>: %s', $examroomName, $e->getMessage());
			$app->enqueueMessage($msg, 'error');
			return false;
		}

		$msg = Text::sprintf('Phòng thi <b>%s</b>: nhập điểm thành công %d thí sinh, trong đó %d thí sinh có bất thường',
			$examroomName, sizeof($examinees), $countAnomaly);
		$app->enqueueMessage($msg, 'success');
		return true;
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
