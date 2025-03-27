<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class LearnerexamModel extends EqaAdminModel{
	public function RequestRegrading(int $examId, string $learnerCode): void
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Kiểm tra xem có tồn tại môn thi không. Nếu có thì việc yêu cầu phúc khảo có được phép hay không
		$columns = $db->quoteName(
			array('a.name', 'a.testtype', 'b.ppaa_req_enabled', 'b.ppaa_req_deadline'),
			array('name', 'testtype', 'ppaa_req_enabled', 'ppaa_req_deadline')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'b.id = a.examseason_id')
			->where('a.id='.$examId);
		$db->setQuery($query);
		$exam = $db->loadObject();
		if(empty($exam))
		{
			$msg = Text::sprintf('Không tìm thấy môn thi với mã "%d"', $examId);
			$app->enqueueMessage($msg, 'error');
			return;
		}

		if(!$exam->ppaa_req_enabled)
		{
			$msg = Text::sprintf('Môn <b>%s</b>: Việc phúc khảo, đính chính đang vô hiệu đối với kỳ thi này',
				htmlspecialchars($exam->name));
			$app->enqueueMessage($msg, 'error');
			return;
		}

		$timeover=false;
		if (!empty($exam->ppaa_req_deadline))
		{
			$deadlineTime = new Date($exam->ppaa_req_deadline, 'Asia/Ho_Chi_Minh');
			$currentTime = new Date('now');
			if($deadlineTime && $deadlineTime < $currentTime)
				$timeover=true;
		}
		if($timeover)
		{
			$msg = Text::sprintf('Môn <b>%s</b>: Đã quá thời hạn phúc khảo, đính chính',
				htmlspecialchars($exam->name));
			$app->enqueueMessage($msg, 'error');
			return;
		}

		if(!in_array($exam->testtype, [ExamHelper::TEST_TYPE_PAPER, ExamHelper::TEST_TYPE_MACHINE_HYBRID]))
		{
			$msg = Text::sprintf('Môn <b>%s</b>: Chỉ có thể yêu cầu phúc khảo môn thi tự luận',
				htmlspecialchars($exam->name));
			$app->enqueueMessage($msg, 'error');
			return;
		}

		//Get learner id
		$db->setQuery('SELECT `id` FROM `#__eqa_learners` WHERE `code`=' . $db->quote($learnerCode));
		$learnerId = $db->loadResult();
		if(empty($learnerId))
		{
			$msg = Text::sprintf('Không tìm thấy HVSV với mã <b>%s</b>.', $learnerCode);
			$app->enqueueMessage($msg, 'error');
			return;
		}

		//Kiểm tra xem HVSV này có bài thi mang đang yêu cầu phúc khảo hay không
		$db->setQuery("SELECT COUNT(1) FROM `#__eqa_exam_learner` WHERE `exam_id`=$examId AND `learner_id`=$learnerId");
		if(empty($db->loadResult()))
		{
			$msg = Text::sprintf('Môn <b>%s</b>: Bạn không dự thi môn này',
				htmlspecialchars($exam->name));
			$app->enqueueMessage($msg, 'error');
			return;
		}

		//Kiểm tra xem đã có yêu cầu phúc khảo môn này chưa
		$db->setQuery("SELECT COUNT(1) FROM `#__eqa_regradings` WHERE `exam_id`=$examId AND `learner_id`=$learnerId");
		if($db->loadResult()>0)
		{
			$msg = Text::sprintf('Môn <b>%s</b>: Bạn đã yêu cầu phúc khảo.', htmlspecialchars($exam->name));
			$app->enqueueMessage($msg, 'warning');
			return;
		}

		//Ghi yêu cầu phúc khảo
		$columnValues = [$examId, $learnerId,ExamHelper::EXAM_PPAA_STATUS_INIT];
		$tupe = implode(',', $columnValues);
		$query = $db->getQuery(true)
			->insert('#__eqa_regradings')
			->columns(['`exam_id`', '`learner_id`', '`status`'])
			->values($tupe);
		$db->setQuery($query);
		if(!$db->execute())
		{
			$msg = Text::sprintf('Môn <b>%s</b>: Lỗi tạo yêu cầu phúc khảo', htmlspecialchars($exam->name));
			$app->enqueueMessage($msg, 'error');
			return;
		}

		//Thông báo thành công
		$msg = Text::sprintf('Môn <b>%s</b>: Tạo yêu cầu phúc khảo thành công', htmlspecialchars($exam->name));
		$app->enqueueMessage($msg,'success');
	}
	public function RequestCorrection(int $examId, string $learnerCode, int $consituent, string $reason): void
	{
		$app = Factory::getApplication();
		$db = DatabaseHelper::getDatabaseDriver();

		//Kiểm tra xem có tồn tại môn thi không. Nếu có thì việc yêu cầu đính chính có được phép hay không
		$columns = $db->quoteName(
			array('a.name', 'a.testtype', 'b.ppaa_req_enabled', 'b.ppaa_req_deadline'),
			array('name', 'testtype', 'ppaa_req_enabled', 'ppaa_req_deadline')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exams AS a')
			->leftJoin('#__eqa_examseasons AS b', 'b.id = a.examseason_id')
			->where('a.id='.$examId);
		$db->setQuery($query);
		$exam = $db->loadObject();
		if(empty($exam))
		{
			$app->enqueueMessage('Không tìm thấy môn thi được yêu cầu', 'error');
			return;
		}

		if(!$exam->ppaa_req_enabled)
		{
			$app->enqueueMessage('Việc phúc khảo, đính chính đang vô hiệu đối với kỳ thi này', 'error');
			return;
		}

		$timeover=false;
		if (!empty($exam->ppaa_req_deadline))
		{
			$deadlineTime = new Date($exam->ppaa_req_deadline, 'Asia/Ho_Chi_Minh');
			$currentTime = new Date('now');
			if($deadlineTime && $deadlineTime < $currentTime)
				$timeover=true;
		}
		if($timeover)
		{
			$app->enqueueMessage('Đã quá thời hạn phúc khảo, đính chính', 'error');
			return;
		}

		//Get learner id
		$db->setQuery('SELECT `id` FROM `#__eqa_learners` WHERE `code`=' . $db->quote($learnerCode));
		$learnerId = $db->loadResult();
		if(empty($learnerId))
		{
			$msg = Text::sprintf('Không tìm thấy HVSV với mã <b>%s</b>.', $learnerCode);
			$app->enqueueMessage($msg, 'error');
			return;
		}

		//Kiểm tra xem HVSV này có bài thi mang đang yêu cầu đính chính hay không
		$db->setQuery("SELECT COUNT(1) FROM `#__eqa_exam_learner` WHERE `exam_id`=$examId AND `learner_id`=$learnerId");
		if(empty($db->loadResult()))
		{
			$app->enqueueMessage('Bạn không tham dự môn thi được yêu cầu','error');
			return;
		}

		//Kiểm tra xem đã có yêu cầu đính chính nội dung này chưa
		$db->setQuery("SELECT COUNT(1) FROM `#__eqa_gradecorrections` WHERE `exam_id`=$examId AND `learner_id`=$learnerId AND `constituent`=$consituent");
		if($db->loadResult()>0)
		{
			$msg = Text::sprintf('Bạn đã yêu cầu đính chính <b>%s</b> của môn <b>%s</b>.',
				ExamHelper::decodeMarkConstituent($consituent),
				htmlspecialchars($exam->name));
			$app->enqueueMessage($msg, 'warning');
			return;
		}

		//Ghi yêu cầu đính chính
		$columnValues = [$examId, $learnerId, $consituent, $db->quote($reason), ExamHelper::EXAM_PPAA_STATUS_INIT];
		$tupe = implode(',', $columnValues);
		$query = $db->getQuery(true)
			->insert('#__eqa_gradecorrections')
			->columns(['`exam_id`', '`learner_id`', '`constituent`','`reason`', '`status`'])
			->values($tupe);
		$db->setQuery($query);
		if(!$db->execute())
		{
			$app->enqueueMessage('Lỗi tạo yêu cầu đính chính', 'error');
			return;
		}

		//Thông báo thành công
		$msg = Text::sprintf('Đã tạo yêu cầu đính chính <b>%s</b> của môn <b>%s</b>',
			ExamHelper::decodeMarkConstituent($consituent),
			htmlspecialchars($exam->name));
		$app->enqueueMessage($msg,'success');
	}
}
