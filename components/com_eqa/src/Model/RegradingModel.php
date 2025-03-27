<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

class RegradingModel extends EqaAdminModel{
	public function canDelete($record): bool
	{
		//Chỉ có thể xóa nếu yêu cầu chưa được tiếp nhận hay từ chối
		if($record->status > ExamHelper::EXAM_PPAA_STATUS_INIT)
			return false;

		//Chỉ thí sinh mới có thể tự xóa yêu cầu của mình
		$username = GeneralHelper::getCurrentUsername();
		$learnerId = DatabaseHelper::getLearnerId($username);
		if($learnerId != $record->learner_id)
			return false;

		//Chỉ có thể xóa nếu chế độ phúc khảo đang được kích hoạt và chưa quá thời hạn
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('ppaa_req_enabled', 'ppaa_req_deadline'),
			array('enabled',          'deadline')
		);
		$query = $db->getQuery(true)
			->from('#__eqa_examseasons')
			->select($columns)
			->where('id=' . (int)$record->examseason_id);
		$db->setQuery($query);
		$ppaa  = $db->loadObject();
		if(empty($ppaa))
			return false; //Error
		if(!$ppaa->enabled)
			return false; //Disabled
		if(GeneralHelper::isTimeOver($ppaa->deadline))
			return false; //Time over

		//Thỏa mãn đồng thời tất cả các điều kiện trên mới được
		return true;
	}
	private function isTimeToAcceptOrReject($request):bool
	{
		//Check time
		if($request->status >= ExamHelper::EXAM_PPAA_STATUS_DONE)
			return false;
		if($request->enabled && !GeneralHelper::isTimeOver($request->deadline))
			return false;

		return true;
	}
	public function handleRequests(array $ids, bool $accepted)
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

			$newStatus = $accepted ? ExamHelper::EXAM_PPAA_STATUS_ACCEPTED : ExamHelper::EXAM_PPAA_STATUS_REJECTED;
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
}
