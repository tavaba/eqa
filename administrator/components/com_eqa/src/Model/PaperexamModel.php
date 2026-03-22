<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Component\Eqa\Administrator\DataObject\PackageInfo;

defined('_JEXEC') or die();

class PaperexamModel extends AdminModel{
	public function mask(int $examId, $maskStart, $maskInterval, $packageDefaultSize, $packageMinSize): bool
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Check if paper exam
		$exam = DatabaseHelper::getExamInfo($examId);
		if(empty($exam))
		{
			$app->enqueueMessage('Môn thi không hợp lệ','error');
			return false;
		}
		if($exam->testtype != TestType::Paper->value)
		{
			$app->enqueueMessage('Đây không phải môn thi viết nên không thể làm phách','error');
			return false;
		}

		//Check if information is full for masking
		$nexaminee = DatabaseHelper::getExamExamineeCount($examId, true);
		$npaper = DatabaseHelper::getExamPaperCount($examId);
		$nnopaper = DatabaseHelper::getExamNoPaperCount($examId);
		if($npaper + $nnopaper != $nexaminee){
			$msg = Text::sprintf('Chưa đủ thông tin thu bài (%d/%d thí sinh) nên không thể làm phách',$npaper + $nnopaper,$nexaminee);
			$app->enqueueMessage($msg, 'error');
			return false;
		}

		//Nếu đã có phân công CBChT thì không cho đánh phách nữa
		$query = $db->getQuery(true)
			->from('#__eqa_papers AS a')
			->leftJoin('#__eqa_packages AS b', 'a.package_id=b.id')
			->select('b.examiner1_id')
			->where([
				'a.exam_id='.$examId,
				'b.examiner1_id IS NOT NULL'
			])
			->setLimit(1);
		$db->setQuery($query);
		if(!empty($db->loadResult())){
			$app->enqueueMessage('Đã phân công CBChT, không thể làm phách', 'error');
			return false;
		}

		//Xóa thông tin dồn túi trước đây (nếu có)
		//a) Lấy danh sách packages
		$query = $db->getQuery(true)
			->from('#__eqa_papers')
			->select('DISTINCT package_id')
			->where('package_id IS NOT NULL AND exam_id='.$examId);
		$db->setQuery($query);
		$packageIds = $db->loadColumn();
		if(!empty($packageIds)){
			//b) Xóa thông tin chia túi
			$db->setQuery('UPDATE #__eqa_papers SET package_id=NULL WHERE exam_id=' . $examId);
			if(!$db->execute())
			{
				$app->enqueueMessage('Lỗi xóa thông tin dồn túi cũ','error');
				return false;
			}
			//c) Xóa túi
			$packageIdSet = '(' . implode(',', $packageIds) . ')';
			$db->setQuery('DELETE FROM #__eqa_packages WHERE id IN ' . $packageIdSet);
			if(!$db->execute())
			{
				$app->enqueueMessage('Lỗi xóa túi bài thi cũ', 'error');
				return false;
			}
		}


		//Do masking
		//1. Lấy danh sách tất cả thí sinh có bài thi
		//   Danh sách này được sắp theo thứ tự tăng dần của số báo danh
		//   Kích thước mảng phải bằng $npaper
		$query = $db->getQuery(true)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_papers AS b', 'a.exam_id=b.exam_id AND a.learner_id=b.learner_id')
			->select('a.learner_id')
			->where('b.exam_id=' . $examId . ' AND b.nsheet>0')
			->order('a.code ASC');
		$db->setQuery($query);
		$learnerIds = $db->loadColumn();

		/**
		 * 2. Đánh phách
		 * Tạo một mảng $masks[] có kích thước bằng mảng $learnerIds[]
		 * Khi đó, thí sinh $learnerIds[i] sẽ có số phách là $masks[i]
		 */
		//a) Tính toán số đoạn phách
		$nfragment = intdiv($npaper, $maskInterval);
		if(0 == $npaper % $maskInterval)
			$lastFragementLen = $maskInterval;
		else
		{
			$lastFragementLen = $npaper % $maskInterval;
			$nfragment++;
		}
		//b) Xáo trộn các đoạn phách
		$fragmentIndexes = [];
		for($i=1; $i<=$nfragment; $i++)
			$fragmentIndexes[] = $i;
		if(!shuffle($fragmentIndexes))
		{
			$app->enqueueMessage('Phát sinh lỗi khi xáo trộn phách', 'error');
			return false;
		}
		//c) Trải phách
		$masks = [];
		foreach ($fragmentIndexes as $index)
		{
			//Giá trị phách đầu tiên của đoạn
			$m = $maskStart + ($index-1) * $maskInterval;

			//Độ dài đoạn phách
			if($index == $nfragment)
				$len = $lastFragementLen;
			else
				$len = $maskInterval;

			//Trải phách
			for($i=0; $i<$len; $i++)
				$masks[] = $m++;
		}
		//d) Ghép phách
		$papers = [];
		for($i=0; $i<$npaper; $i++)
		{
			$papers[] =
				[
					'learner_id' => $learnerIds[$i],
					'mask' => $masks[$i]
				];
		}
		//e) Sắp xếp lại theo thứ tự tăng dần của số phách
		$comparator = function ($a, $b){
			return $a['mask'] - $b['mask'];
		};
		usort($papers, $comparator);

		//3. Dồn túi
		//a) Tính toán số túi
		$npackage = intdiv($npaper, $packageDefaultSize);
		$lastPackageSize = $packageDefaultSize;
		$r = $npaper % $packageDefaultSize;
		if($r > 0 && $r < $packageMinSize)
		{
			$lastPackageSize += $r;
		}
		elseif($r >= $packageMinSize){
			$npackage++;
			$lastPackageSize = $r;
		}

		//b) Tạo các túi bài thi và dồn túi
		$index=0;
		for($i=1; $i<=$npackage; $i++)
		{
			//Tạo túi bài thi
			$query = $db->getQuery(true)
				->insert('#__eqa_packages')
				->columns('exam_id, number')
				->values($examId . ',' . $i);
			$db->setQuery($query);
			if(!$db->execute())
			{
				$app->enqueueMessage('Lỗi tạo túi bài thi','error');
				return false;
			}
			$packageId = $db->insertid();

			//Dồn túi (đồng thời ghi nhận số phách)
			$len = $packageDefaultSize;
			if($i == $npackage)
				$len = $lastPackageSize;
			for($j=0; $j<$len; $j++)
			{
				$query = $db->getQuery(true)
					->update('#__eqa_papers')
					->set([
						'mask=' . $papers[$index]['mask'],
						'package_id = ' . $packageId
					])
					->where([
						'exam_id='.$examId,
						'learner_id='.$papers[$index]['learner_id']
					]);
				$db->setQuery($query);
				if(!$db->execute()){
					$app->enqueueMessage('Lỗi dồn túi', 'error');
					return false;
				}
				$index++;
			}
		}

		//Finish
		$msg = Text::sprintf('Thành công: %d bài thi, %d túi',$npaper, $npackage);
		$app->enqueueMessage($msg, 'success');
		return true;
	}

	public function getMaskMap(int $examId, bool $mustHaveMask):array|null
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Kiểm tra xem có phải môn tự luận không
		if(!DatabaseHelper::isPaperExam($examId)){
			$app->enqueueMessage('Không phải môn thi viết', 'error');
			return null;
		}

		//Kiểm tra xem đã đánh phách, dồn túi chưa
		if(!DatabaseHelper::isPaperExamWithMaskingDone($examId)){
			$app->enqueueMessage('Chưa đánh phách, dồn túi', 'error');
			return null;
		}

		//Lấy dữ liệu và trả về
		$columns = $db->quoteName(
			array('c.code',       'c.lastname', 'c.firstname', 'b.code', 'a.mask', 'a.nsheet'),
			array('learner_code', 'lastname',   'firstname',   'code',   'mask',   'nsheet' )
		);
		$whereClause = [
			'a.exam_id=' . $examId,
			'b.code IS NOT NULL'
		];
		if($mustHaveMask)
			$whereClause[] = 'a.mask IS NOT NULL';
		$query = $db->getQuery(true)
			->from('#__eqa_papers AS a')
			->leftJoin('#__eqa_exam_learner AS b','a.exam_id=b.exam_id AND a.learner_id=b.learner_id')
			->leftJoin('#__eqa_learners AS c', 'a.learner_id=c.id')
			->select($columns)
			->where($whereClause)
			->order('code ASC');
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	public function getPackages($examId)
	{
		if(empty($examId))
			return null;

		$db = DatabaseHelper::getDatabaseDriver();

		//Lấy danh sách túi bài thi từ #__eqa_papers
		$db->setQuery('SELECT DISTINCT package_id FROM #__eqa_papers WHERE package_id IS NOT NULL AND exam_id='.$examId);
		$packageIds = $db->loadColumn();
		if(empty($packageIds))
			return null;

		//Lấy thông tin túi bài thi
		$packageIdSet = '(' . implode(',', $packageIds) . ')';
		$query = $db->getQuery(true)
			->select($db->quoteName(['id','number','examiner1_id','examiner2_id']))
			->from('#__eqa_packages')
			->where($db->quoteName('id') . ' IN ' . $packageIdSet)
			->order($db->quoteName('number') . ' ASC');
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	public function saveExaminers(int $examId, array $data):bool
	{
		//Init
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Lấy số lượng túi bài thi của môn thi
		$npackage = DatabaseHelper::getExamPackageCount($examId);

		//Check data validity
		$msg = Text::_('COM_EQA_MSG_INVALID_DATA');
		if(sizeof($data) != $npackage)
		{
			$app->enqueueMessage($msg, 'error');
			return false;
		}
		foreach ($data as $package){
			if(empty($package['examiner1_id']) || empty($package['examiner2_id']) || $package['examiner1_id']==$package['examiner2_id'])
			{
				$app->enqueueMessage($msg,'error');
				return false;
			}
		}

		//Lưu thông tin
		$db->transactionStart();
		try
		{
			foreach ($data as $packageNumber => $examiners)
			{
				$query = $db->getQuery(true)
					->update('#__eqa_packages')
					->set([
						'examiner1_id=' . (int)$examiners['examiner1_id'],
						'examiner2_id=' . (int)$examiners['examiner2_id']
					])
					->where('exam_id='.$examId . ' AND number = ' . (int)$packageNumber);
				$db->setQuery($query);
				if(!$db->execute())
					throw new \Exception(Text::_('COM_EQA_MSG_DATABASE_ERROR'));
			}

			//Commit
			$db->transactionCommit();
		}
		catch (\Exception $e){
			$db->transactionRollback();
			$app->enqueueMessage($e->getMessage());
			return false;
		}
		$app->enqueueMessage('Phân công chấm thi thành công','success');
		return true;
	}
	public function isExaminerAssigningDone(int $examId):bool
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//Package count
		$db->setQuery('SELECT COUNT(1) FROM #__eqa_packages WHERE exam_id='.$examId);
		$npackage = $db->loadResult();
		if(0 == $npackage)
			return false;

		//Check
		$query  = $db->getQuery(true)
			->select('COUNT(1)')
			->from('#__eqa_packages')
			->where([
				'exam_id=' . $examId,
				'examiner1_id IS NOT NULL',
				'examiner2_id IS NOT NULL'
			]);
		$db->setQuery($query);
		$count = $db->loadResult();

		//return
		return $npackage == $count;
	}
	public function getPackageInfo($examId, $packageNumber): PackageInfo|null
	{
		$info = new PackageInfo();
		$info->examId = $examId;
		$info->number = $packageNumber;
		$db = DatabaseHelper::getDatabaseDriver();

		//$examiner1_id, $examiner2_id
		$query = $db->getQuery(true)
			->from('#__eqa_packages')
			->select(['id','examiner1_id','examiner2_id'])
			->where([
				'exam_id=' . $examId,
				'number=' . $packageNumber
			]);
		$db->setQuery($query);
		$package = $db->loadObject();
		if(empty($package))
			return null;
		$info->id = $package->id;
		$info->firstExaminerId = $package->examiner1_id;
		$info->secondExaminerId = $package->examiner2_id;

		//Examiners' names
		$db->setQuery("SELECT CONCAT(`lastname`,' ', `firstname`) AS `fullname` FROM #__eqa_employees WHERE id=$info->firstExaminerId");
		$info->firstExaminerFullname = $db->loadResult();
		$db->setQuery("SELECT CONCAT(`lastname`,' ', `firstname`) AS `fullname` FROM #__eqa_employees WHERE id=$info->secondExaminerId");
		$info->secondExaminerFullname = $db->loadResult();

		//$firstMask, $npaper
		$query = $db->getQuery(true)
			->from('#__eqa_papers')
			->select('mask, nsheet')
			->where('package_id='.(int)$package->id)
			->order('mask ASC');
		$db->setQuery($query);
		$papers = $db->loadObjectList();
		if(empty($papers))
			return null;
		$info->firstMask = $papers[0]->mask;
		$info->paperCount = sizeof($papers);

		//$nsheet
		$sheetCount = 0;
		foreach ($papers as $paper)
			$sheetCount += $paper->nsheet;
		$info->sheetCount = $sheetCount;

		//$exam
		$columns = $db->quoteName(
			array('a.name', 'b.name', 'b.term', 'b.academicyear'),
			array('name', 'examseason', 'term', 'academicyear')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
			->where('a.id='.$examId);
		$db->setQuery($query);
		$exam = $db->loadObject();
		$info->examName = $exam->name;
		$info->examseasonName = $exam->examseason;
		$info->term = $exam->term;
		$info->academicyearCode = DatetimeHelper::decodeAcademicYear($exam->academicyear);

		//Return
		return $info;
	}
	public function importMarkByMask(int $examId, array $marks): void
	{
		/**
		 * Controller phải kiểm tra tính hợp lệ của KIỂU DỮ LIỆU trong mảng $marks trước
		 * khi gọi phương thức này.
		 * $marks là mảng liên kểt [$mask => $mark]
		 *
		 * Việc import gồm một số bước
		 *  - Ghi điểm $mark vào bảng #__eqa_papers
		 *  - Ghi điểm $mark vào bảng #__eqa_exam_learner (cột 'mark_orig')
		 *    đồng thời tính toán các giá trị 'mark_final', 'module_grade'
		 *  - Cập nhật số lượt thi, điều kiện tiếp tục thi vào bảng #__eqa_class_learner
		 */

		//Init
		$db = DatabaseHelper::getDatabaseDriver();

		//Check if can import
		if(DatabaseHelper::isCompletedExam($examId))
			throw new Exception('Môn thi đã kết thúc, không thể nhập điểm');

		//Xác định bảng mapping $mask - $learnerId để tránh phải truy vấn nhiều lần trong vòng lặp
		$query = $db->getQuery(true)
			->select('mask, learner_id')
			->from('#__eqa_papers')
			->where('exam_id=' . $examId);
		$db->setQuery($query);
		$maskLearnerMap = $db->loadAssocList('mask','learner_id');

		//Xác định subject id để phục vụ tính toán điểm học phần
		$db->setQuery('SELECT subject_id FROM #__eqa_exams WHERE id='.$examId);
		$subjectId = $db->loadResult();


		$db->transactionStart();
		try
		{
			foreach ($marks as $mask => $mark)
			{
				//1. Cập nhật điểm vào bảng #__eqa_papers
				$query = $db->getQuery(true)
					->update('#__eqa_papers')
					->set('mark = ' . $mark)
					->where('exam_id=' . $examId . ' AND mask=' . $mask);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật điểm bài thi cho số phách <b>%d</b>', $mask);
					throw new Exception($msg);
				}

				//2. Cập nhật điểm vào bảng #__eqa_exam_learner
				$learnerId = (int)$maskLearnerMap[$mask];
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set('mark_orig = ' . $mark)
					->where('exam_id=' . $examId . ' AND learner_id=' . $learnerId);
				$db->setQuery($query);
				if(!$db->execute())
				{
					$msg = Text::sprintf('Lỗi cập nhật điểm học phần cho số phách <b>%d</b>', $mask);
					throw new Exception($msg);
				}
			}
			//Commit
			$db->transactionCommit();
		}
		catch (Exception $e)
		{
			$db->transactionRollback();
			throw $e;
		}
	}
}
