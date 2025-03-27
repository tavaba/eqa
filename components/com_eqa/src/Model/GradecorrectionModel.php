<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

class GradecorrectionModel extends EqaAdminModel{
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
	public function acceptRequests(array $ids)
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
			->from('#__eqa_gradecorrections AS a')
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

			$query = $db->getQuery(true)
				->update('#__eqa_gradecorrections')
				->set('`status`=' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED)
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
			$msg = Text::sprintf('%d yêu cầu đính chính điểm đã được chấp nhận', $countSuccess);
			$app->enqueueMessage($msg, 'success');
		}
		else
		{
			$msg = [];
			if($countSuccess)
				$msg[] = Text::sprintf('%d yêu cầu đính chính điểm đã được chấp nhận', $countSuccess);
			if($countDeny)
				$msg[] = Text::sprintf('%d trường hợp chưa hết thời hạn HVSV gửi yêu cầu', $countDeny);
			if($countError)
				$msg[] = Text::sprintf('%d trường hợp bị lỗi', $countError);
			$app->enqueueMessage(implode('; ', $msg), 'error');
		}
	}
	public function rejectRequest(int $id, string $description)
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = $db->quoteName(
			array('a.id', 'c.ppaa_req_enabled', 'c.ppaa_req_deadline', 'a.status'),
			array('id',   'enabled',            'deadline',            'status')
		);
		$query  = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_gradecorrections AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->where('a.id = ' . $id);
		$db->setQuery($query);
		$request = $db->loadObject();
		if(!$request)
		{
			$app->enqueueMessage('Không tìm thấy yêu cầu để xử lý', 'error');
			return;
		}

		if(!$this->isTimeToAcceptOrReject($request))
		{
			$app->enqueueMessage('Chưa hết thời hạn để HVSV gửi yêu cầu', 'error');
			return;
		}

		$query = $db->getQuery(true)
			->update('#__eqa_gradecorrections')
			->set([
				'`status`=' . ExamHelper::EXAM_PPAA_STATUS_REJECTED,
				'`description`=' . $db->quote($description)
			])
			->where('id=' . (int)$request->id);
		$db->setQuery($query);
		if($db->execute())
		{
			$app->enqueueMessage('Yêu cầu đã bị từ chối', 'success');
		}
		else{
			$app->enqueueMessage('Lỗi truy vấn CSDL', 'error');
		}
	}
}
