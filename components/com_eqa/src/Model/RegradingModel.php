<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Model\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

class RegradingModel extends AdminModel{
	public function canDelete($record=null): bool
	{
		//Chỉ có thể xóa nếu yêu cầu chưa được tiếp nhận hay từ chối
		if($record->status > PpaaStatus::Init->value)
			return false;

		//Chỉ thí sinh mới có thể tự xóa yêu cầu của mình
		$learnerId = GeneralHelper::getSignedInLearnerId();
		if($learnerId != $record->learner_id)
			return false;

		//Chỉ có thể xóa nếu chế độ phúc khảo đang được kích hoạt và chưa quá thời hạn
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('b.ppaa_req_enabled', 'b.ppaa_req_deadline'),
			array('enabled',          'deadline')
		);
		$query = $db->getQuery(true)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'b.id=a.examseason_id')
			->select($columns)
			->where('a.id=' . (int)$record->exam_id);
		$db->setQuery($query);
		$ppaa  = $db->loadObject();
		if(empty($ppaa))
			return false; //Error
		if(!$ppaa->enabled)
			return false; //Disabled
		if(DatetimeHelper::isTimeOver($ppaa->deadline))
			return false; //Time over

		//Thỏa mãn đồng thời tất cả các điều kiện trên mới được
		return true;
	}
	private function isTimeToAcceptOrReject($request):bool
	{
		//Check time
		if($request->status >= PpaaStatus::Done->value)
			return false;
		if($request->enabled && !DatetimeHelper::isTimeOver($request->deadline))
			return false;

		return true;
	}
	public function handleRequests_bak(array $ids, bool $accepted)
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'c.ppaa_req_enabled', 'c.ppaa_req_deadline', 'a.status'),
			array('id',   'enabled',            'deadline',            'status')
		);
		$idSet = '(' . implode(',', $ids) . ')';
		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->where('a.id IN ' . $idSet);
		$db->setQuery($query);
		$requests = $db->loadObjectList();
		if(empty($requests))
		{
			$app->enqueueMessage('Không tìm thấy yêu cầu để xử lý', 'error');
			return;
		}

		$countDeny=0;
		$countError=0;
		$countSuccess=0;
		foreach ($requests as $request)
		{
			if(!$this->isTimeToAcceptOrReject($request))
			{
				$countDeny++;
				continue;
			}

			$newStatus = $accepted ? PpaaStatus::Accepted->value : PpaaStatus::Rejected->value;
			$query = $db->getQuery(true)
				->update('#__eqa_regradings')
				->set('`status`=' . $newStatus)
				->where('id=' . (int)$request->id);
			$db->setQuery($query);
			if($db->execute())
			{
				$countSuccess++;
			}
			else{
				$countError++;
			}
		}

		if($countSuccess == sizeof($ids))
		{
			$suffix = $accepted ? 'đã được chấp nhận' : 'đã bị từ chối';
			$msg = Text::sprintf('%d yêu cầu phúc khảo %s', $countSuccess, $suffix);
			$app->enqueueMessage($msg, 'success');
		}
		else
		{
			$suffix = $accepted ? 'đã được chấp nhận' : 'đã bị từ chối';
			$msg = [];
			if($countSuccess)
				$msg[] = Text::sprintf('%d yêu cầu phúc khảo %s', $countSuccess, $suffix);
			if($countDeny)
				$msg[] = Text::sprintf('%d trường hợp chưa hết thời hạn HVSV gửi yêu cầu', $countDeny);
			if($countError)
				$msg[] = Text::sprintf('%d trường hợp bị lỗi', $countError);
			$app->enqueueMessage(implode('; ', $msg), 'error');
		}
	}
	public function handleRequest(int $regradingId, bool $accepted)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'c.ppaa_req_enabled', 'c.ppaa_req_deadline', 'a.status'),
			array('id',   'enabled',            'deadline',            'status')
		);
		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->where('a.id =' . $regradingId);
		$db->setQuery($query);
		$request = $db->loadObject();
		if(empty($request))
			throw new Exception('Không tìm thấy yêu cầu để xử lý');

		//Handle
		if(!$this->isTimeToAcceptOrReject($request))
			throw new Exception('Chưa hết thời hạn thí sinh gửi yêu cầu');

		$newStatus = $accepted ? PpaaStatus::Accepted->value : PpaaStatus::Rejected->value;
		$query = $db->getQuery(true)
			->update('#__eqa_regradings')
			->set('`status`=' . $newStatus)
			->where('id=' . (int)$request->id);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception('Đã xảy ra lỗi khi xử lý yêu cầu');
	}
}
