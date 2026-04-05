<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Utilities\ArrayHelper;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

defined('_JEXEC') or die();

class RegradingModel extends AdminModel {
	public function canDelete($record=null): bool
	{
		/*
		 * A record can be delete if the following conditions are met
		 * - The corresponding request was sent by the current user
		 *   (column 'created_by' in #__eqa_regradings table)
		 * - A regrading mark has been set and approved
		 *   (column 'result' in #__eqa_regradings IS NOT NULL)
		 * - The corresponding examseason has not been completed yet
		 *   (column 'completed' in #__eqa_examseasons table is FALSE)
		 */
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.created_by',          'createdBy'),
			$db->quoteName('a.result',              'result'),
			$db->quoteName('c.completed',           'completed'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->where('a.id='.(int)$record->id);
		$db->setQuery($query);
		$info = $db->loadObject();

		if(empty($info))
			return false;

		//A record can be deleted only by the user who submitted it
		$currentUserId = Factory::getApplication()->getIdentity()->id;
		if($currentUserId != $info->createdBy)
			throw new Exception('Yêu cầu phúc khảo chỉ có thể được xóa bởi người đã khởi tạo nó');
		if($info->result !== null)
			throw new Exception('Đã có kết quả phúc khảo nên không thể xóa');
		if($info->completed)
			throw new Exception('Kỳ thi đã hoàn thành nên không thể xóa');
		return true;
	}

	public function delete(&$pks): bool
	{
		//1. Clear PPAA info in the #__eqa_exam_learner table
		$db = DatabaseHelper::getDatabaseDriver();
		$pks = ArrayHelper::toInteger($pks);
		$query = $db->getQuery(true)
			->select('exam_id AS examId, learner_id AS learnerId')
			->from('#__eqa_regradings')
			->where('id IN (' . implode(',', $pks) . ')');
		$db->setQuery($query);
		$examinees = $db->loadObjectList();
		if(empty($examinees))
			return false; //Nothing more to do here

		foreach ($examinees as $examinee){
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set('ppaa_status = 0')
				->where('exam_id='.$examinee->examId.' AND learner_id='.$examinee->learnerId);
			$db->setQuery($query);
			if(!$db->execute())
				return false;
		}

		//2. Delete records
		return parent::delete($pks);
	}

	/**
	 * Ghi nhận yêu cầu đính chính được chấp nhận
	 * @param   int     $itemId
	 * @param   User    $currentUser
	 * @param   string  $currentTime
	 *
	 *
	 * @throws Exception
	 * @since 1.1.10
	 */
	public function accept(int $itemId, User $currentUser, string $currentTime): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get information about the grade correction request
		$columns = [
			$db->quoteName('a.status',              'status'),
			$db->quoteName('a.payment_amount',      'paymentAmount'),
			$db->quoteName('a.payment_code',        'paymentCode'),
			$db->quoteName('a.payment_completed',   'paymentCompleted'),
			$db->quoteName('b.code',                'learnerCode'),
			'CONCAT_WS(" ", b.lastname, b.firstname)'      . ' AS ' . $db->quoteName('fullname'),
			$db->quoteName('d.completed',           'examseasonCompleted')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id')
			->where('a.id = ' . $itemId);
		$db->setQuery($query);
		$item = $db->loadObject();

		//2. Check if can accept
		if(empty($item))
			throw new Exception("Không tìm thấy yêu cầu phúc khảo");
		$requestInfo = "{$item->learnerCode}-{$item->fullname}({$item->paymentCode})";
		if($item->examseasonCompleted)
			throw new Exception("{$requestInfo}: Kỳ thi đã kết thúc nên không thể chấp nhận yêu cầu phúc khảo");
		if($item->status == PpaaStatus::Done->value)
			throw new Exception("{$requestInfo}: Yêu cầu đã xử lý xong, không thể quay ngược trạng thái");

		//3. Update database
		$query = $db->getQuery(true)
			->update('#__eqa_regradings')
			->set([
				'status = ' . PpaaStatus::Accepted->value,
				'handled_at = ' . $db->quote($currentTime),
				'handled_by = ' . (int)$currentUser->id,
				'handled_by_username = ' . $db->quote($currentUser->username),
			])
			->where('id = ' . $itemId);
		if($item->paymentAmount >0)
			$query->set('payment_completed=1');

		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception("Xảy ra lỗi khi chấp nhận yêu cầu phúc khảo");

		//4. Return success message
		$app = Factory::getApplication();
		$msg = sprintf('Yêu cầu phúc khảo của <b>%s (%s)</b> đã được chấp nhận',
			$item->fullname, $item->learnerCode
		);
		$app->enqueueMessage($msg, "success");
	}
	public function reject(int $itemId, User $currentUser, string $currentTime): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get information about the grade correction request
		$columns = [
			$db->quoteName('a.status',              'status'),
			$db->quoteName('a.payment_amount',      'paymentAmount'),
			$db->quoteName('a.payment_code',        'paymentCode'),
			$db->quoteName('a.payment_completed',   'paymentCompleted'),
			$db->quoteName('b.code',                'learnerCode'),
			'CONCAT_WS(" ", b.lastname, b.firstname)'      . ' AS ' . $db->quoteName('fullname'),
			$db->quoteName('d.completed',           'examseasonCompleted')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id')
			->where('a.id = ' . $itemId);
		$db->setQuery($query);
		$item = $db->loadObject();

		//2. Check if can reject
		if(!isset($item))
			throw new Exception("Không tìm thấy yêu cầu phúc khảo");
		$requestInfo = "{$item->learnerCode}-{$item->fullname}({$item->paymentCode})";
		if($item->examseasonCompleted)
			throw new Exception("{$requestInfo}: Kỳ thi đã kết thúc nên không thể từ chối yêu cầu phúc khảo");
		if($item->status == PpaaStatus::Done->value)
			throw new Exception("{$requestInfo}: Yêu cầu đã xử lý xong, không thể quay ngược trạng thái");

		//3. Update database
		$query = $db->getQuery(true)
			->update('#__eqa_regradings')
			->set([
				'status = ' . PpaaStatus::Rejected->value,
				'payment_completed=0',
				'handled_by = ' . (int)$currentUser->id,
				'handled_by_username = ' . $db->quote($currentUser->username),
				'handled_at = ' . $db->quote($currentTime)
			])
			->where('id = ' . $itemId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception("Xảy ra lỗi khi từ chối yêu cầu phúc khảo");

		//4. Return success message
		$app = Factory::getApplication();
		$msg = sprintf('Yêu cầu phúc khảo của <b>%s (%s)</b> đã bị từ chối',
			$item->fullname, $item->learnerCode
		);
		$app->enqueueMessage($msg, "success");
	}
}
