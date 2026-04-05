<?php

namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\PaymentCodeHelper;
/**
 * Model front-end cho trang Thi sát hạch (AssessmentPortal).
 *
 * Cung cấp:
 *   - Danh sách kỳ sát hạch "Đang/Sắp diễn ra" và "Đã tham gia" cho một người học.
 *   - Tính toán trạng thái đăng ký, số slot khả dụng, thời điểm cập nhật phí.
 *   - Các thao tác đăng ký và hủy đăng ký (soft cancel).
 *
 * Định nghĩa "hoàn thành nghĩa vụ nộp phí":
 *   fee = 0  → tự động coi là đã hoàn thành (không cần payment_completed).
 *   fee > 0  → phải có payment_completed = TRUE.
 *
 * Định nghĩa "slot khả dụng":
 *   max_candidates - COUNT(bản ghi có cancelled=FALSE và đã hoàn thành nghĩa vụ nộp phí)
 *
 * @since 2.0.5
 */
class AssessmentPortalModel extends BaseModel
{
	// =========================================================================
	// Hằng số trạng thái đăng ký — dùng trong View và Template
	// =========================================================================

	/** Người học chưa đăng ký, đang trong thời hạn và còn slot */
	public const STATUS_OPEN          = 'open';

	/** Người học chưa đăng ký; chưa đến thời hạn đăng ký */
	public const STATUS_NOT_YET       = 'not_yet';

	/** Người học chưa đăng ký; đang trong hạn nhưng chế độ đăng ký bị tắt */
	public const STATUS_SUSPENDED     = 'suspended';

	/** Người học chưa đăng ký; đã quá thời hạn đăng ký */
	public const STATUS_EXPIRED       = 'expired';

	/** Người học chưa đăng ký; hết slot (nhưng vẫn còn trong hạn và đăng ký bật) */
	public const STATUS_FULL          = 'full';

	/** Người học đã đăng ký, đã hoàn thành nghĩa vụ nộp phí */
	public const STATUS_PAID          = 'paid';

	/** Người học đã đăng ký, chưa nộp phí, vẫn còn slot và còn trong hạn */
	public const STATUS_REGISTERED    = 'registered';

	/** Người học đã đăng ký, chưa nộp phí, nhưng hết hạn hoặc tắt đăng ký */
	public const STATUS_PENDING       = 'pending';

	/** Kỳ thi đã qua (thuộc nhóm "Đã tham gia") */
	public const STATUS_PAST          = 'past';

	// =========================================================================
	// Đọc dữ liệu
	// =========================================================================

	/**
	 * Lấy danh sách kỳ sát hạch liên quan đến người học, chia thành 2 nhóm:
	 *   - $result->active : "Đang/Sắp diễn ra" (ngày thi chưa qua HOẶC đã đăng ký mà chưa quá ngày thi)
	 *   - $result->past   : "Đã tham gia" (đã đăng ký VÀ ngày thi đã qua)
	 *
	 * Mỗi phần tử đã được bổ sung các trường tính toán:
	 *   - registrationRecord : object|null  — bản ghi #__eqa_assessment_learner (null nếu chưa đăng ký)
	 *   - availableSlots     : int|null     — null nếu không giới hạn
	 *   - registrationStatus : string       — một trong các STATUS_* constants
	 *   - lastPaymentUpdate  : string|null  — MAX(modified_at) của các bản ghi đã paid
	 *
	 * @param  string  $learnerCode
	 * @return object{active: object[], past: object[]}
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function getAssessmentsForLearner(string $learnerCode): object
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// 1. Lấy learner_id từ code
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__eqa_learners'))
			->where($db->quoteName('code') . ' = ' . $db->quote($learnerCode));
		$db->setQuery($query);
		$learnerId = (int) $db->loadResult();
		if ($learnerId <= 0) {
			throw new Exception('Không tìm thấy thông tin người học với mã: ' . $learnerCode);
		}

		// 2. Lấy tất cả kỳ sát hạch đang published, sắp xếp start_date DESC
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('a.id'),
				$db->quoteName('a.title'),
				$db->quoteName('a.type'),
				$db->quoteName('a.result_type'),
				$db->quoteName('a.start_date'),
				$db->quoteName('a.end_date'),
				$db->quoteName('a.fee'),
				$db->quoteName('a.max_candidates'),
				$db->quoteName('a.registration_start'),
				$db->quoteName('a.registration_end'),
				$db->quoteName('a.allow_registration'),
				$db->quoteName('a.bank_napas_code'),
				$db->quoteName('a.bank_account_number'),
				$db->quoteName('a.bank_account_owner'),
			])
			->from($db->quoteName('#__eqa_assessments', 'a'))
			->where($db->quoteName('a.published') . ' = 1')
			->order($db->quoteName('a.start_date') . ' DESC');
		$db->setQuery($query);
		$assessments = $db->loadObjectList();

		if (empty($assessments)) {
			return (object) ['active' => [], 'past' => []];
		}

		// 3. Lấy tất cả bản ghi đăng ký của người học (kể cả đã hủy)
		$assessmentIds = array_map(static fn($a) => (int) $a->id, $assessments);
		$idList        = implode(',', $assessmentIds);

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('al.id'),
				$db->quoteName('al.assessment_id'),
				$db->quoteName('al.payment_amount'),
				$db->quoteName('al.payment_code'),
				$db->quoteName('al.payment_completed'),
				$db->quoteName('al.cancelled'),
				$db->quoteName('al.created_at'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->where($db->quoteName('al.learner_id') . ' = ' . $learnerId)
			->where($db->quoteName('al.assessment_id') . ' IN (' . $idList . ')');
		$db->setQuery($query);
		$registrations = $db->loadObjectList('assessment_id'); // map: assessment_id → record

		// 4. Lấy số người đã hoàn thành nghĩa vụ nộp phí cho từng kỳ (để tính slot)
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('al.assessment_id'),
				'COUNT(1) AS ' . $db->quoteName('confirmed_count'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->leftJoin(
				$db->quoteName('#__eqa_assessments', 'a') .
				' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('al.assessment_id')
			)
			->where($db->quoteName('al.assessment_id') . ' IN (' . $idList . ')')
			->where($db->quoteName('al.cancelled') . ' = 0')
			->where(
				'(' .
				$db->quoteName('a.fee') . ' = 0' .
				' OR ' . $db->quoteName('al.payment_completed') . ' = 1' .
				')'
			)
			->group($db->quoteName('al.assessment_id'));
		$db->setQuery($query);
		$confirmedCounts = $db->loadAssocList('assessment_id', 'confirmed_count');

		// 5. Lấy MAX(modified_at) của bản ghi đã paid cho từng kỳ
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('al.assessment_id'),
				'MAX(' . $db->quoteName('al.modified_at') . ') AS ' . $db->quoteName('last_payment_update'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->leftJoin(
				$db->quoteName('#__eqa_assessments', 'a') .
				' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('al.assessment_id')
			)
			->where($db->quoteName('al.assessment_id') . ' IN (' . $idList . ')')
			->where($db->quoteName('al.cancelled') . ' = 0')
			->where(
				'(' .
				$db->quoteName('a.fee') . ' = 0' .
				' OR ' . $db->quoteName('al.payment_completed') . ' = 1' .
				')'
			)
			->group($db->quoteName('al.assessment_id'));
		$db->setQuery($query);
		$lastPaymentUpdates = $db->loadAssocList('assessment_id', 'last_payment_update');

		// 6. Phân loại từng kỳ sát hạch
		// Lấy thời điểm "hôm nay" và "bây giờ" theo giờ hệ thống (OS timezone)
		$todayLocal = DatetimeHelper::getCurrentSystemClockTime('Y-m-d');
		$nowUtc     = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

		$active = [];
		$past   = [];

		foreach ($assessments as $a) {
			$reg            = $registrations[$a->id] ?? null;
			$confirmedCount = (int) ($confirmedCounts[$a->id] ?? 0);
			$isFree         = ((int) $a->fee === 0);

			// Slot khả dụng
			if ((int) $a->max_candidates <= 0) {
				$a->availableSlots = null; // Không giới hạn
			} else {
				$a->availableSlots = max(0, (int) $a->max_candidates - $confirmedCount);
			}

			// Thời điểm cập nhật phí gần nhất
			$a->lastPaymentUpdate = $lastPaymentUpdates[$a->id] ?? null;

			// Bản ghi đăng ký (null nếu chưa đăng ký, hoặc đã hủy thì vẫn giữ để khôi phục)
			$a->registrationRecord = $reg;

			// Đã hoàn thành nghĩa vụ nộp phí?
			$isPaymentDone = $reg !== null
				&& !(bool) $reg->cancelled
				&& ($isFree || (bool) $reg->payment_completed);

			// Đang trong thời hạn đăng ký?
			// registration_start/end được lưu UTC trong DB → dùng isTimeOver với isUTC=true
			$regEndPassed   = !empty($a->registration_end)
				&& DatetimeHelper::isTimeOver($a->registration_end, true);
			$regNotStarted  = !empty($a->registration_start)
				&& !DatetimeHelper::isTimeOver($a->registration_start, true);

			$withinRegPeriod = (bool) $a->allow_registration
				&& !$regEndPassed
				&& !$regNotStarted;

			// Ngày thi đã qua? (start_date/end_date là DATE không có timezone
			// → so sánh với ngày hiện tại theo giờ hệ thống)
			$examPassed = $a->end_date < $todayLocal;

			// Người học đã đăng ký hợp lệ (chưa hủy)?
			$isRegistered = $reg !== null && !(bool) $reg->cancelled;

			// Phân nhóm: "Đã tham gia" khi đã đăng ký VÀ ngày thi đã qua
			if ($isRegistered && $examPassed) {
				$a->registrationStatus = self::STATUS_PAST;
				$past[]                = $a;
				continue;
			}

			// Còn lại: "Đang/Sắp diễn ra"
			if ($examPassed && !$isRegistered) {
				continue; // Quá hạn, chưa đăng ký → bỏ qua
			}

			// Xác định trạng thái chi tiết
			if ($isPaymentDone) {
				$a->registrationStatus = self::STATUS_PAID;
			} elseif ($isRegistered) {
				$canStillAct = $withinRegPeriod
					&& ($a->availableSlots === null || $a->availableSlots > 0);

				$a->registrationStatus = $canStillAct
					? self::STATUS_REGISTERED
					: self::STATUS_PENDING;
			} else {
				// Chưa đăng ký — xác định lý do
				if ($regNotStarted) {
					// Chưa đến thời hạn đăng ký → tính thời gian còn lại
					$a->registrationStatus  = self::STATUS_NOT_YET;
					$a->registrationStartsIn = $this->calcTimeRemaining(
						$a->registration_start
					);
				} elseif ($regEndPassed) {
					// Đã quá thời hạn đăng ký
					$a->registrationStatus = self::STATUS_EXPIRED;
				} elseif (!(bool) $a->allow_registration) {
					// Đang trong hạn nhưng chế độ đăng ký bị tắt
					$a->registrationStatus = self::STATUS_SUSPENDED;
				} elseif ($a->availableSlots !== null && $a->availableSlots <= 0) {
					// Hết slot
					$a->registrationStatus = self::STATUS_FULL;
				} else {
					// Đang mở, còn slot
					$a->registrationStatus = self::STATUS_OPEN;
				}
			}

			$active[] = $a;
		}

		return (object) ['active' => $active, 'past' => $past];
	}

	// =========================================================================
	// Đăng ký
	// =========================================================================

	/**
	 * Đăng ký một người học vào một kỳ sát hạch.
	 *
	 * Logic:
	 *   - Nếu đã có bản ghi (kể cả đã hủy) → khôi phục (cancelled = FALSE), giữ payment_code cũ.
	 *   - Nếu chưa có bản ghi → tạo mới với payment_code ngẫu nhiên (nếu fee > 0).
	 *
	 * Không kiểm tra slot ở đây (đã kiểm tra ở View trước khi gọi) — chấp nhận vượt giới hạn.
	 *
	 * @param  int     $assessmentId
	 * @param  string  $learnerCode
	 * @return void
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function register(int $assessmentId, string $learnerCode): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// Lấy learner_id
		$learnerId = $this->getLearnerIdByCode($db, $learnerCode);

		// Lấy thông tin kỳ sát hạch
		$assessment = $this->loadAssessment($db, $assessmentId);

		// Kiểm tra điều kiện đăng ký
		// registration_start/end lưu UTC trong DB → dùng isTimeOver với isUTC=true
		if (!(bool) $assessment->allow_registration) {
			throw new Exception('Kỳ sát hạch hiện không mở đăng ký.');
		}
		if (!empty($assessment->registration_end)
			&& DatetimeHelper::isTimeOver($assessment->registration_end, true)) {
			throw new Exception('Đã hết thời hạn đăng ký.');
		}
		if (!empty($assessment->registration_start)
			&& !DatetimeHelper::isTimeOver($assessment->registration_start, true)) {
			throw new Exception('Chưa đến thời điểm bắt đầu đăng ký.');
		}

		// Kiểm tra bản ghi đã tồn tại chưa
		$existing = $this->loadRegistrationRecord($db, $assessmentId, $learnerId);

		if ($existing !== null) {
			if (!(bool) $existing->cancelled) {
				throw new Exception('Bạn đã đăng ký kỳ sát hạch này rồi.');
			}
			// Khôi phục bản ghi đã hủy — giữ nguyên payment_code, ghi modified_by
			$now    = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
			$userId = (int) Factory::getApplication()->getIdentity()->id;
			$query  = $db->getQuery(true)
				->update($db->quoteName('#__eqa_assessment_learner'))
				->set($db->quoteName('cancelled')   . ' = 0')
				->set($db->quoteName('modified_at')  . ' = ' . $db->quote($now))
				->set($db->quoteName('modified_by')  . ' = ' . $userId)
				->where($db->quoteName('id') . ' = ' . (int) $existing->id);
			$db->setQuery($query);
			$db->execute();
			return;
		}

		// Tạo bản ghi mới
		$now    = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
		$userId = (int) Factory::getApplication()->getIdentity()->id;
		$fee    = (int) $assessment->fee;
		$paymentCode = null;
		if ($fee > 0) {
			$paymentCode = PaymentCodeHelper::generateUnique($db, '#__eqa_assessment_learner', 'payment_code');
		}

		$query = $db->getQuery(true)
			->insert($db->quoteName('#__eqa_assessment_learner'))
			->columns($db->quoteName([
				'assessment_id', 'learner_id',
				'payment_amount', 'payment_code', 'payment_completed',
				'cancelled', 'created_at', 'created_by', 'modified_at', 'modified_by',
			]))
			->values(implode(',', [
				$assessmentId,
				$learnerId,
				$fee,
				$paymentCode !== null ? $db->quote($paymentCode) : 'NULL',
				0,          // payment_completed = FALSE
				0,          // cancelled = FALSE
				$db->quote($now),
				$userId,    // created_by
				$db->quote($now),
				$userId,    // modified_by
			]));
		$db->setQuery($query);
		$db->execute();
	}

	// =========================================================================
	// Hủy đăng ký
	// =========================================================================

	/**
	 * Hủy đăng ký (soft cancel) của một người học khỏi một kỳ sát hạch.
	 *
	 * Chỉ cho phép hủy khi:
	 *   - Bản ghi tồn tại và chưa bị hủy.
	 *   - Chưa nộp phí (payment_completed = FALSE) — nếu đã nộp, không được phép hủy.
	 *
	 * @param  int     $assessmentId
	 * @param  string  $learnerCode
	 * @return void
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function cancel(int $assessmentId, string $learnerCode): void
	{
		$db        = DatabaseHelper::getDatabaseDriver();
		$learnerId = $this->getLearnerIdByCode($db, $learnerCode);
		$existing  = $this->loadRegistrationRecord($db, $assessmentId, $learnerId);

		if ($existing === null || (bool) $existing->cancelled) {
			throw new Exception('Không tìm thấy đăng ký hợp lệ để hủy.');
		}

		if ((bool) $existing->payment_completed) {
			throw new Exception('Bạn đã nộp phí, không thể hủy đăng ký. Vui lòng liên hệ cán bộ tổ chức thi nếu cần hỗ trợ.');
		}

		$now    = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
		$userId = (int) Factory::getApplication()->getIdentity()->id;
		$query  = $db->getQuery(true)
			->update($db->quoteName('#__eqa_assessment_learner'))
			->set($db->quoteName('cancelled')  . ' = 1')
			->set($db->quoteName('modified_at') . ' = ' . $db->quote($now))
			->set($db->quoteName('modified_by') . ' = ' . $userId)
			->where($db->quoteName('id') . ' = ' . (int) $existing->id);
		$db->setQuery($query);
		$db->execute();
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * @throws Exception
	 */
	private function getLearnerIdByCode(\Joomla\Database\DatabaseDriver $db, string $learnerCode): int
	{
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__eqa_learners'))
			->where($db->quoteName('code') . ' = ' . $db->quote($learnerCode));
		$db->setQuery($query);
		$id = (int) $db->loadResult();
		if ($id <= 0) {
			throw new Exception('Không tìm thấy thông tin người học: ' . $learnerCode);
		}
		return $id;
	}

	/**
	 * @throws Exception
	 */
	private function loadAssessment(\Joomla\Database\DatabaseDriver $db, int $assessmentId): object
	{
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__eqa_assessments'))
			->where($db->quoteName('id') . ' = ' . $assessmentId)
			->where($db->quoteName('published') . ' = 1');
		$db->setQuery($query);
		$obj = $db->loadObject();
		if ($obj === null) {
			throw new Exception('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId . '.');
		}
		return $obj;
	}

	/**
	 * Tải bản ghi đăng ký (kể cả đã hủy). Trả về null nếu chưa có bản ghi nào.
	 */
	private function loadRegistrationRecord(
		\Joomla\Database\DatabaseDriver $db,
		int $assessmentId,
		int $learnerId
	): ?object {
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('learner_id') . ' = ' . $learnerId);
		$db->setQuery($query);
		return $db->loadObject() ?: null;
	}


	/**
	 * Tính khoảng thời gian còn lại đến một thời điểm UTC trong tương lai.
	 *
	 * Trả về chuỗi dạng "X ngày, X giờ, X phút" hoặc các thành phần có giá trị > 0.
	 * Trả về chuỗi rỗng nếu thời điểm đó đã qua.
	 *
	 * @param  string  $utcDatetime  Thời điểm đích, dạng UTC ('Y-m-d H:i:s').
	 * @return string
	 * @since  2.0.5
	 */
	private function calcTimeRemaining(string $utcDatetime): string
	{
		$now    = new \DateTime('now', new \DateTimeZone('UTC'));
		$target = new \DateTime($utcDatetime, new \DateTimeZone('UTC'));

		if ($target <= $now) {
			return '';
		}

		$diff    = $now->diff($target);
		$parts   = [];

		if ($diff->days > 0) {
			$parts[] = $diff->days . ' ngày';
		}
		if ($diff->h > 0) {
			$parts[] = $diff->h . ' giờ';
		}
		if ($diff->i > 0 || empty($parts)) {
			$parts[] = $diff->i . ' phút';
		}

		return implode(', ', $parts);
	}
}