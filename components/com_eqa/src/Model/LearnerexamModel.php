<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Enum\FeeMode;
use Kma\Component\Eqa\Administrator\Enum\MarkConstituent;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\PaymentCodeHelper;

class LearnerexamModel extends AdminModel{
	/**
	 * Gửi yêu cầu phúc khảo cho một môn thi.
	 *
	 * Nếu phí phúc khảo > 0, sinh payment_code ngẫu nhiên 8 ký tự [A-Z0-9]
	 * duy nhất trong bảng #__eqa_regradings và tính payment_amount theo cấu hình
	 * (theo môn hoặc theo số tín chỉ).
	 *
	 * @param   int     $examId       ID môn thi.
	 * @param   string  $learnerCode  Mã HVSV.
	 *
	 * @return void
	 * @throws Exception
	 * @since  2.0.7
	 */
	public function RequestRegrading(int $examId, string $learnerCode): void
	{
		$app = Factory::getApplication();
		$db  = DatabaseHelper::getDatabaseDriver();

		// Kiểm tra tồn tại môn thi và điều kiện cho phép phúc khảo
		$columns = $db->quoteName(
			['a.name', 'a.testtype', 'a.subject_id', 'b.ppaa_req_enabled', 'b.ppaa_req_deadline'],
			['name',   'testtype',   'subject_id',   'ppaa_req_enabled',   'ppaa_req_deadline']
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_exams', 'a'))
			->leftJoin(
				$db->quoteName('#__eqa_examseasons', 'b'),
				$db->quoteName('b.id') . ' = ' . $db->quoteName('a.examseason_id')
			)
			->where($db->quoteName('a.id') . ' = ' . $examId);
		$db->setQuery($query);
		$exam = $db->loadObject();

		if (empty($exam))
			throw new Exception("Không tìm thấy môn thi với mã {$examId}");

		if (!$exam->ppaa_req_enabled)
		{
			$msg = sprintf('Môn <b>%s</b>: Việc phúc khảo, đính chính đang vô hiệu đối với kỳ thi này',
				htmlspecialchars($exam->name));
			throw new Exception($msg);
		}

		if ($exam->ppaa_req_deadline && DatetimeHelper::isTimeOver($exam->ppaa_req_deadline))
		{
			$msg = sprintf('Môn <b>%s</b>: Đã quá thời hạn phúc khảo, đính chính',
				htmlspecialchars($exam->name));
			throw new Exception($msg);
		}

		if (!in_array($exam->testtype, [TestType::Paper->value, TestType::MachineHybrid->value]))
		{
			$msg = sprintf(
				'Không thể phúc khảo môn <b>%s</b>. Chỉ có thể yêu cầu phúc khảo môn thi tự luận!',
				htmlspecialchars($exam->name)
			);
			throw new Exception($msg);
		}

		// Lấy learner_id
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__eqa_learners'))
			->where($db->quoteName('code') . ' = ' . $db->quote($learnerCode));
		$db->setQuery($query);
		$learnerId = (int) $db->loadResult();

		if (empty($learnerId))
		{
			$msg = sprintf('Không tìm thấy HVSV với mã <b>%s</b>.', $learnerCode);
			throw new Exception($msg);
		}

		// Kiểm tra HVSV có bài thi ở môn này không
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from($db->quoteName('#__eqa_exam_learner'))
			->where($db->quoteName('exam_id')   . ' = ' . $examId)
			->where($db->quoteName('learner_id') . ' = ' . $learnerId);
		$db->setQuery($query);

		if (empty($db->loadResult()))
		{
			$msg = sprintf(
				'Môn <b>%s</b>: Bạn không dự thi môn này',
				htmlspecialchars($exam->name)
			);
			throw new Exception($msg);
		}

		// Kiểm tra đã có yêu cầu phúc khảo chưa
		$query = $db->getQuery(true)
			->select('COUNT(1)')
			->from($db->quoteName('#__eqa_regradings'))
			->where($db->quoteName('exam_id')   . ' = ' . $examId)
			->where($db->quoteName('learner_id') . ' = ' . $learnerId);
		$db->setQuery($query);

		if ($db->loadResult() > 0)
		{
			$msg = sprintf(
				'Môn <b>%s</b>: Bạn đã yêu cầu phúc khảo.',
				htmlspecialchars($exam->name)
			);
			throw new Exception($msg);
		}

		// -------------------------------------------------------------------------
		// Tính phí phúc khảo và sinh payment_code
		// -------------------------------------------------------------------------
		$feeMode = ConfigHelper::getRegradingFeeMode();
		$feeRate = (int) ConfigHelper::getRegradingFeeRate();

		$paymentAmount = 0;
		$paymentCode   = null;

		if ($feeRate > 0)
		{
			if ($feeMode === FeeMode::PerCredit)
			{
				// Lấy số tín chỉ từ môn học tương ứng để tính phí
				$query = $db->getQuery(true)
					->select($db->quoteName('credits'))
					->from($db->quoteName('#__eqa_subjects'))
					->where($db->quoteName('id') . ' = ' . (int) $exam->subject_id);
				$db->setQuery($query);
				$credits       = (int) $db->loadResult();
				$paymentAmount = $feeRate * $credits;
			}
			else
			{
				// FeeMode::PerExam — phí cố định mỗi môn
				$paymentAmount = $feeRate;
			}

			if ($paymentAmount > 0)
			{
				$paymentCode = PaymentCodeHelper::generateUnique(
					$db,
					'#__eqa_regradings',
					'payment_code'
				);
			}
		}

		// -------------------------------------------------------------------------
		// Ghi yêu cầu phúc khảo
		// -------------------------------------------------------------------------
		$query = $db->getQuery(true)
			->insert($db->quoteName('#__eqa_regradings'))
			->columns($db->quoteName([
				'exam_id',
				'learner_id',
				'status',
				'payment_amount',
				'payment_code',
				'payment_completed',
			]))
			->values(implode(',', [
				$examId,
				$learnerId,
				PpaaStatus::Init->value,
				$paymentAmount,
				$paymentCode !== null ? $db->quote($paymentCode) : 'NULL',
				0,  // payment_completed = FALSE
			]));
		$db->setQuery($query);

		if (!$db->execute())
		{
			$msg = sprintf(
				'Môn <b>%s</b>: Lỗi tạo yêu cầu phúc khảo',
				htmlspecialchars($exam->name)
			);
			throw new Exception($msg);
		}

		// Thông báo thành công
		$app->enqueueMessage(
			Text::sprintf(
				'Môn <b>%s</b>: Tạo yêu cầu phúc khảo thành công',
				htmlspecialchars($exam->name)
			),
			'success'
		);
	}

	/**
	 * @throws Exception
	 */
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
			throw new Exception('Không tìm thấy môn thi được yêu cầu');

		if(!$exam->ppaa_req_enabled)
			throw new Exception('Việc phúc khảo, đính chính đang vô hiệu đối với kỳ thi này');

		if ($exam->ppaa_req_deadline && DatetimeHelper::isTimeOver($exam->ppaa_req_deadline))
			throw new Exception('Đã quá thời hạn phúc khảo, đính chính');

		//Get learner id
		$db->setQuery('SELECT `id` FROM `#__eqa_learners` WHERE `code`=' . $db->quote($learnerCode));
		$learnerId = $db->loadResult();
		if(empty($learnerId))
		{
			$msg = sprintf('Không tìm thấy HVSV với mã <b>%s</b>.', $learnerCode);
			throw new Exception($msg);
		}

		//Kiểm tra xem HVSV này có bài thi mang đang yêu cầu đính chính hay không
		$db->setQuery("SELECT COUNT(1) FROM `#__eqa_exam_learner` WHERE `exam_id`=$examId AND `learner_id`=$learnerId");
		if(empty($db->loadResult()))
			throw new Exception('Bạn không tham dự môn thi được yêu cầu');

		//Kiểm tra xem đã có yêu cầu đính chính nội dung này chưa
		$db->setQuery("SELECT COUNT(1) FROM `#__eqa_gradecorrections` WHERE `exam_id`=$examId AND `learner_id`=$learnerId AND `constituent`=$consituent");
		if($db->loadResult()>0)
		{
			$msg = Text::sprintf('Bạn đã yêu cầu đính chính <b>%s</b> của môn <b>%s</b>.',
				MarkConstituent::from($consituent)->getLabel(),
				htmlspecialchars($exam->name));
			throw new Exception($msg);
		}

		//Ghi yêu cầu đính chính
		//Get current time for the field 'created_at'
		$time = DatetimeHelper::getCurrentUtcTime();
		$userId = Factory::getApplication()->getIdentity()->id;
		$columnValues = [$examId, $learnerId, $consituent, $db->quote($reason), PpaaStatus::Init->value, $db->quote($time), $userId];
		$tupe = implode(',', $columnValues);
		$query = $db->getQuery(true)
			->insert('#__eqa_gradecorrections')
			->columns(['`exam_id`', '`learner_id`', '`constituent`','`reason`', '`status`', '`created_at`', '`created_by`'])
			->values($tupe);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception('Lỗi tạo yêu cầu đính chính');

		//Thông báo thành công
		$msg = Text::sprintf('Đã tạo yêu cầu đính chính <b>%s</b> của môn <b>%s</b>',
			MarkConstituent::from($consituent)->getLabel(),
			htmlspecialchars($exam->name));
		$app->enqueueMessage($msg,'success');
	}
}
