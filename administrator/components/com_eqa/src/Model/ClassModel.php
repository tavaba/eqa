<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Collator;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;

defined('_JEXEC') or die();

class ClassModel extends EqaAdminModel {
    protected function prepareTable($table)
    {
	    if(empty($table->lecturer_id))
			$table->lecturer_id=null;
	    if(empty($table->start))
			$table->start=null;
		if(empty($table->finish))
			$table->finish=null;
		if(empty($table->topicdeadline))
			$table->topicdeadline=null;
		if(empty($table->topicdate))
			$table->topicdate=null;
		if(empty($table->thesisdate))
			$table->thesisdate=null;
		if(empty($table->pamdeadline))
			$table->pamdeadline=null;
		if(empty($table->pamdate))
			$table->pamdate=null;
        parent::prepareTable($table);
    }

	/**
	 * Thêm HVSV vào một lớp học phần. Có sử dụng transaction.
	 *
	 * @param   int    $classId       ID của lớp học phần.
	 * @param   array  $learnerCodes  Mảng chứa các mã HVSV cần thêm vào lớp dạng $rowIndex => $learnerCode.
	 * @return array   [$countTotal, $countAdded, $countExisting]
	 * @throws Exception Controller chịu trách nhiệm xử lý lỗi
	 * @since 1.2.0
	 */
	public function importLearners(int $classId, array $learnerCodes, array $learnerMap=[]): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Check if the class exists and is published
		$db->setQuery("SELECT COUNT(*) FROM `#__eqa_classes` WHERE `id`=$classId AND `published`=1");
		$count = (int) $db->loadResult();
		if($count==0)
			throw new Exception('Lớp không tồn tại hoặc đã bị vô hiệu hóa');

		//2. Check if any of these learners does not exist in the database
		//   If there are absentees, throw an exception with a list of whole codes,
		//   so the user can fix all of the errors at once instead of fixing them one-by-one
		if(empty($learnerMap))
			$learnerMap = DatabaseHelper::getLearnerMap([],8000);   //Lấy tối đa 8000 bản ghi
		if(empty($learnerMap))
			throw new Exception('Không tìm thấy dữ liệu học viên');
		$absentees = [];
		foreach ($learnerCodes as $rowIndex => $learnerCode)
		{
			if(!array_key_exists($learnerCode, $learnerMap))
				$absentees[] = $learnerCode . "({$rowIndex})";
		}
		if(!empty($absentees))
		{
			$msg = Text::sprintf('Có %d HVSV không tồn tại trong CSDL: %s',
				count($absentees),
				htmlentities(implode('; ', $absentees))
			);
			throw new Exception($msg);
		}

		//3. Load all existing learners for this class
		$db->setQuery("SELECT `learner_id` FROM `#__eqa_class_learner` WHERE `class_id`=$classId");
		$existingLearnerIds = $db->loadColumn();

		//4. Try to add learners to the class
		$db->transactionStart();
		$countExisting=0;
		try
		{
			foreach ($learnerCodes as $rowIndex => $learnerCode)        //$rowIndex is the index of row in the input Excel file
			{
				//1. Get the learner id
				$learnerId = $learnerMap[$learnerCode];

				//2. Check if the learner has already been added to the class
				if(in_array($learnerId, $existingLearnerIds))
				{
					$countExisting++;
					continue; //Skip this row since it's already in the database
				}

				//3. Add the learner to the class
				$db->setQuery("INSERT INTO `#__eqa_class_learner`(`class_id`, `learner_id`) VALUES($classId,$learnerId)");
				if(!$db->execute())
					throw new Exception("Thêm HVSV thất bại (dữ liệu tại dòng {$rowIndex})");
			}
			$countTotal = count($learnerCodes);
			$countAdded=$countTotal-$countExisting;

			//5. Update class size
			$newSize = count($existingLearnerIds) + $countAdded;
			$query = $db->getQuery(true)
				->update('#__eqa_classes')
				->set('`size` = '.$newSize)
				->where('id = '.$classId);
			$db->setQuery($query);
			if(!$db->execute())
				throw new Exception("Cập nhật sĩ số thất bại");

			//6. Commit changes
			$db->transactionCommit();
			return [$countTotal, $countAdded, $countExisting];
		}
		catch(Exception $e){
			$db->transactionRollback();
			throw $e;
		}
	}

	/**
	 * Nhập điểm quá trình cho lớp học phần. Có sử dụng transaction.
	 * @param   int    $classId         ID của lớp
	 * @param   array  $data            ['row_index', 'learner_code', 'pam1', 'pam2','pam', 'allowed', 'description']
	 * @param   bool   $setPamDateToday Lấy ngày hôm nay là ngày bàn giao điểm quá trình
	 *
	 * @return array
	 *
	 * @throws Exception
	 * @since version
	 */
	public function importPams(int $classId, array $data, bool $setPamDateToday): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Check if the class exists and is published
		$db->setQuery("SELECT COUNT(*) FROM `#__eqa_classes` WHERE `id`=$classId AND `published`=1");
		$count = (int) $db->loadResult();
		if($count==0)
			throw new Exception('Lớp không tồn tại hoặc đã bị vô hiệu hóa');

		//2. Ensure that no one from the class has been assigned an examinee code yet
		//   (no exam hasn't begun)
		$db->setQuery("SELECT * FROM `#__eqa_exam_learner` WHERE `class_id`=$classId AND `code` IS NOT NULL LIMIT 1");
		$item = $db->loadObject();
		if(!empty($item))
			throw new Exception('Đã tổ chức thi, không thể nhập ĐQT');

		//3. Load the mapping between learner ids and their codes for this class
		$query = $db->getQuery(true)
			->select('a.learner_id AS id, b.code AS code')
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->where('a.class_id = '.$classId);
		$db->setQuery($query);
		$classLearnerMap = $db->loadAssocList('code','id');     //key: learner code, value: learner id
		if(empty($classLearnerMap))
			throw new Exception('Lớp học phần rỗng, không thể nhập ĐQT');

		//4. Check if any of these learners does not exist in the class
		//   If there are absentees, throw an exception with a list of whole codes,
		//   so the user can fix all of the errors at once instead of fixing them one-by-one
		$absentees = [];
		foreach ($data as $item)
		{
			$rowIndex = $item['row_index'];
			$learnerCode = $item['learner_code'];

			if(!array_key_exists($learnerCode, $classLearnerMap))
				$absentees[] = $learnerCode . "({$rowIndex})";
		}
		if(!empty($absentees))
		{
			$msg = Text::sprintf('Có %d HVSV không tồn tại trong lớp học phần: %s',
				count($absentees),
				htmlentities(implode('; ', $absentees))
			);
			throw new Exception($msg);
		}

		//4. Try to add learners to the class
		$db->transactionStart();
		try
		{
			foreach ($data as $item)        //$rowIndex is the index of row in the input Excel file
			{
				//1. Prepare data
				$rowIndex = $item['row_index'];
				$learnerCode = $item['learner_code'];
				$pam1 = $item['pam1'];
				$pam2 = $item['pam2'];
				$pam = $item['pam'];
				$allowed = $item['allowed'];
				$description = $item['description'];

				$setData = [
					$db->quoteName('pam1') . '=' . floatval($pam1),
					$db->quoteName('pam2') . '=' . floatval($pam2),
					$db->quoteName('pam')  . '=' . floatval($pam),
				];
				if(empty($description))
					$setData[] = $db->quoteName('description') . '= NULL';
				else
					$setData[] = $db->quoteName('description') . '=' . $db->quote($description);

				if($allowed)
				{
					$setData[] = $db->quoteName('allowed') . '=1';      //Cho phép thi
				}
				else
				{
					$setData[] = $db->quoteName('allowed') . '=0';      //Cho phép thi
					$setData[] = $db->quoteName('expired') . '=1';      //Cho phép thi
				}

				//2. Get the learner id
				$learnerId = $classLearnerMap[$learnerCode];

				//3. Update PAM
				$query = $db->getQuery(true)
					->update('#__eqa_class_learner')
					->set($setData)
					->where('class_id = '.$classId.' AND learner_id = '.$learnerId);
				$db->setQuery($query);
				if(!$db->execute())
					throw new Exception("Nhập ĐQT thất bại (dữ liệu tại dòng {$rowIndex})");
			}

			//4. Update the the number of learners who already have PAM ('npam')
			//a) Count how many learners have been assigned PAM
			$db->setQuery("SELECT COUNT(*) FROM `#__eqa_class_learner` WHERE `class_id`=$classId AND `pam` IS NOT NULL");
			$npam = (int) $db->loadResult();
			//b) Set npam
			$db->setQuery("UPDATE `#__eqa_classes` SET `npam`={$npam} WHERE `id`={$classId}");
			if(!$db->execute())
				throw new Exception("Cập nhật số lượng HVSV có ĐQT thất bại");

			$classSize = count($classLearnerMap);
			$countUpdated = count($data);
			//5. Update class PAM date if all learners have been assigned PAM
			if($setPamDateToday && $npam==$classSize)
			{
				$now = DatetimeHelper::getCurrentHanoiDatetime();
				$today = DatetimeHelper::getFullDate($now);
				$query = $db->getQuery(true)
					->update('#__eqa_classes')
					->set($db->quoteName('pamdate') . '=' . $db->quote($today))
					->where('id = '.$classId);
				$db->setQuery($query);
				if(!$db->execute())
					throw new Exception("Cập nhật ngày bàn giao ĐQT thất bại");
			}

			//6. Commit changes
			$db->transactionCommit();
			return [$classSize, $countUpdated, $npam];
		}
		catch(Exception $e){
			$db->transactionRollback();
			throw $e;
		}
	}
    public function addLearners(int $classId, array $learnerCodes): void
    {
        $db = $this->getDatabase();

        //Lấy danh sách HVSV các khóa, các lớp đang published
        $query = $db->getQuery(true)
            ->from('#__eqa_learners AS a')
            ->leftJoin('#__eqa_groups AS b', 'a.group_id = b.id')
            ->leftJoin('#__eqa_courses AS c', 'b.course_id = c.id')
            ->select('a.id AS id, a.code AS code')
            ->where('b.published>0 AND c.published>0');
        $db->setQuery($query);
        $learnerIds = $db->loadAssocList('code','id');

        $countAbsence = 0;
        $countError = 0;
        $countSuccess = 0;
        $listAbsence = '';
        $listError = '';
        foreach ($learnerCodes as $learnerCode){
            //Kiểm tra xem learner có tồn tại hay không
            if(!isset($learnerIds[$learnerCode])){
                $countAbsence++;
                $listAbsence .= $learnerCode . '; ';
                continue;
            }
            $db->transactionStart();
            $learnerId = $learnerIds[$learnerCode];
            try {
                //Thêm vào lớp học phần
                $query = $db->getQuery(true)
                    ->insert('#__eqa_class_learner')
                    ->columns('class_id, learner_id')
                    ->values("$classId, $learnerId");
                $db->setQuery($query);
                $db->execute();

                //Cập nhật sĩ số
                $query = $db->getQuery(true)
                    ->update('#__eqa_classes')
                    ->set('`size` = `size` + 1')
                    ->where('id = '.$classId);
                $db->setQuery($query);
                $db->execute();

                $db->transactionCommit();
                $countSuccess++;
            }
            catch (Exception $e){
                $db->transactionRollback();
                $countError++;
                $listError .= $learnerCode . '; ';
            }
        }

        //Set messages
        $app = Factory::getApplication();
        if($countSuccess>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_SUCCESS', $countSuccess);
            $app->enqueueMessage($msg,'success');
        }
        if($countAbsence>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_ABSENT',$countAbsence);
            $msg .= ': ' . $listAbsence;
            $app->enqueueMessage($msg,'error');
        }
        if($countError>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_FAILED',$countError);
            $msg .= ': ' . $listError;
            $app->enqueueMessage($msg,'error');
        }
    }
    public function removeLearner(int $classId, int $learnerId) : bool{
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //Check if this learner (of this class) present in any exam
        $columns = $db->quoteName(
            array('b.code', 'b.firstname', 'b.lastname'),
            array('code','firstname','lastname')
        );
        $query = $db->getQuery(true)
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
            ->select($columns)
            ->where('a.class_id='.$classId. ' AND a.learner_id='.$learnerId);
        $db->setQuery($query);
        $learner = $db->loadObject();
        if(!empty($learner)){
            $temp = htmlentities("$learner->lastname $learner->firstname ($learner->code)");
            $msg = Text::sprintf('COM_EQA_MSG_CANNOT_DELETE_S_BECAUSE_OF_EXAM', $temp);
            $app->enqueueMessage($msg,'error');
            return false;
        }

        //Try to remove the learner from the class
        $db->transactionStart();
        try
        {
            //Remove the leaner from the class
            $query = $db->getQuery(true)
                ->delete('#__eqa_class_learner')
                ->where('class_id = '.(int)$classId.' AND learner_id = '.(int)$learnerId);
            $db->setQuery($query);
            $db->execute();

            //Decrement the class size
            $query = $db->getQuery(true)
                ->update('#__eqa_classes')
                ->set('`size` = `size`-1')
                ->where('id = '.$classId);
            $db->setQuery($query);
            $db->execute();

            //Inform
            $db->setQuery('SELECT * FROM #__eqa_learners WHERE id='.$learnerId);
            $learner = $db->loadObject();
            $learnerInfo = htmlentities("$learner->lastname $learner->firstname ($learner->code)");
            $msg = Text::sprintf('COM_EQA_MSG_S_REMOVED_FROM_THE_CLASS', $learnerInfo);
            $app->enqueueMessage($msg,'success');

            //Commit
            $db->transactionCommit();
            return true;
        }
        catch (Exception $e){
            $db->transactionRollback();
            $msg = Text::_('COM_EQA_MSG_ERROR_TASK_FAILED');
            $app->enqueueMessage($msg,'error');
            return false;
        }
    }
    public function setAllowed(int $classId, array $learnerIds, bool $allowed):bool
    {
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //HVSV chỉ có thể được CHO THI hay CẤM THI khi chưa có trong danh sách thi
        //Vì thế, trước hết sẽ lập danh sách HVSV của lớp học này đã có mặt trong các môn thi (1 hoặc nhiều lần)
        $query = $db->getQuery(true)
            ->select('learner_id')
            ->from('#__eqa_exam_learner')
            ->where('class_id=' . $classId . ' AND mark_orig IS NOT NULL');
        $db->setQuery($query);
        $exceptedLearnerIds = $db->loadColumn();

        //Chia danh sách HVSV ban đầu thành 2 phần: đã/chưa có trong danh sách thi
        $acceptedIds = [];
        $rejectedIds = [];
        foreach ($learnerIds as $id){
            if(in_array($id, $exceptedLearnerIds))
                $rejectedIds[] = $id;
            else
                $acceptedIds[] = $id;
        }

        //Xử lý trường hợp không hợp lệ
        if(!empty($rejectedIds)){
            $rejectedIdSet = '(' . implode(',', $rejectedIds) . ')';
            $query = $db->getQuery(true)
                ->from('#__eqa_learners')
                ->select('code')
                ->where('id IN '.$rejectedIdSet);
            $db->setQuery($query);
            $rejectedCodes = implode(', ', $db->loadColumn());
            $msg = Text::sprintf('COM_EQA_MSG_CANNOT_ALLOW_OR_DENY_S_TAKE_EXAM', htmlentities($rejectedCodes));
            $app->enqueueMessage($msg,'error');
        }

        //Thiết lập thuộc tính 'allowed' cho các trươờng hợp hợp lệ
        if(!empty($acceptedIds))
        {
            $acceptedIdSet = '(' . implode(',', $acceptedIds) . ')';

            //Lấy danh sách HVSV để xuất thông báo
            $query = $db->getQuery(true)
                ->from('#__eqa_learners')
                ->select('code')
                ->where('id IN '.$acceptedIdSet);
            $db->setQuery($query);
            $acceptedCodes = implode(', ', $db->loadColumn());

            //Thực hiện thay đổi 'allowed' thì cũng cần thay đổi 'expired'
            $valueAllowed = $allowed ? 1 : 0;
            $valueExpired = $allowed ? 0 : 1;
            $query = $db->getQuery(true)
                ->update('#__eqa_class_learner')
                ->set(array(
                    $db->quoteName('allowed').'='.$valueAllowed,
                    $db->quoteName('expired').'='.$valueExpired
                ))
                ->where('class_id = '. $classId . ' AND learner_id IN '. $acceptedIdSet);
            $db->setQuery($query);
            if($db->execute()) {
                $msg = Text::_('COM_EQA_MSG_TASK_SUCCESS') . ': ' . htmlentities($acceptedCodes);
                $app->enqueueMessage($msg, 'success');
                return true;
            }
            else {
                $msg = Text::_('COM_EQA_MSG_ERROR_TASK_FAILED') . ': ' . htmlentities($acceptedCodes);
                $app->enqueueMessage($msg, 'error');
                return false;
            }
        }

        return true;
    }

	public function getClassLearners(int $classId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array(),
			array()
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->where('a.class_id = ' . $classId);
		$db->setQuery($query);
		$learners = $db->loadObjectList();
		if(empty($learners))
			return null;

		//Sort by firstname, then by lastname
		$collator = new Collator("vi_VN");
		$comparator = function($a,$b) use ($collator){
			$round1 = $collator->compare($a->fistname,$b->firstname);
			if($round1 != 0)
				return $round1;
			return $collator->compare($a->lastname,$b->lastname);
		};
		usort($learners, $comparator);

		//Concatenate fullname and code into one field
		$data = [];
		foreach($learners as $i=>$learner){
			$name = trim(htmlspecialchars("$learner->lastname $learner->firstname"));
			$name = "$learner->code - $name)";
			$data[$i][] = [
				'id' => $learner->id,
				'name' => $name
			];
		}

		//Return the data
		return $data;
	}
	public function addForGroupOrCohort(string $targetType, int $targetId, int $subjectId, int $term, int $academicyearId): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get target code and learners
		if($targetType==='group')
		{
			//1.1. Get group code
			$db->setQuery('SELECT code FROM #__eqa_groups WHERE id='.$targetId);
			$targetCode = $db->loadResult();

			//1.2. Get learners
			$db->setQuery('SELECT id FROM #__eqa_learners WHERE group_id='.$targetId);
			$learnerIds = $db->loadColumn();
		}
		elseif ($targetType==='cohort')
		{
			//1.1. Get cohort code
			$db->setQuery('SELECT code FROM #__eqa_cohorts WHERE id='.$targetId);
			$targetCode = $db->loadResult();

			//1.2. Get learners
			$db->setQuery('SELECT learner_id FROM #__eqa_cohort_learner WHERE cohort_id='.$targetId);
			$learnerIds = $db->loadColumn();
		}
		else
			throw new Exception('Invalid target type');

		//2. Get subject code
		$db->setQuery('SELECT code, name FROM #__eqa_subjects WHERE id='.$subjectId);
		$subject = $db->loadObject();

		//3. Get academic year code
		$db->setQuery('SELECT code FROM #__eqa_academicyears WHERE id='.$academicyearId);
		$academicYearCode = $db->loadResult();
		$firstYear = substr($academicYearCode,2, 2);       //Get last two digits of academic year code

		//4. Calculate class code and class name
		$classCode = Text::sprintf('%s-%d-%s(%s-01)', $subject->code, $term, $firstYear, $targetCode);
		$className = Text::sprintf('%s-%d-%s(%s-01)', $subject->name, $term, $firstYear, $targetCode);

		$db->transactionStart();
		try
		{
			//5. Create a new class and get its ID
			$columns = $db->quoteName(array('coursegroup','code','name','subject_id','term','academicyear_id','size'));
			$values=[
				$db->quote($targetCode),
				$db->quote($classCode),
				$db->quote($className),
				$subjectId,
				$term,
				$academicyearId,
				count($learnerIds)
			];
			$tupe = implode(',', $values);
			$query = $db->getQuery(true)
				->insert('#__eqa_classes')
				->columns($columns)
				->values($tupe);
			$db->setQuery($query);
			if(!$db->execute()){
				throw new Exception('Tạo lớp học phần mới thất bại');
			}
			$classId = $db->insertid();

			//6. Add learners to the class
			$tupes = [];
			foreach ($learnerIds as $learnerId){
				$tupes[] = $classId.",".$learnerId;
			}
			$query = $db->getQuery(true)
				->insert('#__eqa_class_learner')
				->columns('class_id, learner_id')
				->values($tupes);
			$db->setQuery($query);
			if(!$db->execute()){
				throw new Exception('Thêm HVSV vào lớp học phần mới thất bại');
			}

			//7. Commit transaction
			$db->transactionCommit();
		}
		catch(Exception $e)
		{
			//Roll back transaction
			$db->transactionRollback();
			throw $e;
		}
	}
}
