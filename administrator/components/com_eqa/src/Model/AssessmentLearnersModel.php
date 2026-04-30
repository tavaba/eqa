<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Library\Kma\BankStatement\BankStatementImportResult;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\IOHelper;
use Kma\Library\Kma\Helper\PaymentCodeHelper;
use Kma\Library\Kma\Helper\ToeicHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Model danh sách thí sinh (người học) của một kỳ sát hạch.
 *
 * assessment_id PHẢI được set vào state trước khi gọi getItems()/getListQuery():
 *   $model->setState('filter.assessment_id', $assessmentId);
 *
 * @since 2.0.5
 */
class AssessmentLearnersModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'al.id', 'al.code', 'lr.code', 'lr.lastname', 'lr.firstname',
            'al.payment_completed', 'al.anomaly', 'al.passed', 'al.score', 'al.level',
	        'al.cancelled', 'er.examsession_id', 'al.examroom_id'
        ];
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'lr.lastname', $direction = 'ASC'): void
    {
        parent::populateState($ordering, $direction);
    }

	// =========================================================================
	// Query
	// =========================================================================

	/**
	 * Xây dựng câu truy vấn danh sách thí sinh của một kỳ sát hạch.
	 *
	 * @return \Joomla\Database\QueryInterface
	 * @throws Exception  Nếu assessment_id chưa được set.
	 * @since 2.0.5
	 */
	public function getListQuery()
	{
		$assessmentId = (int) $this->getState('filter.assessment_id',0);
//		if ($assessmentId <= 0) {
//			throw new Exception('Không xác định được kỳ sát hạch (assessment_id chưa được set).');
//		}

		$db = $this->getDatabase();

		$query = parent::getListQuery();
		$query
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_examrooms', 'er') .
				' ON ' . $db->quoteName('er.id') . ' = ' . $db->quoteName('al.examroom_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_examsessions', 'es') .
				' ON ' . $db->quoteName('es.id') . ' = ' . $db->quoteName('er.examsession_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_rooms', 'rm') .
				' ON ' . $db->quoteName('rm.id') . ' = ' . $db->quoteName('er.room_id')
			)
			->select([
				$db->quoteName('al.id'),
				$db->quoteName('al.assessment_id'),
				$db->quoteName('al.learner_id'),
				$db->quoteName('al.code',               'examinee_code'),
				$db->quoteName('al.examroom_id'),
				$db->quoteName('al.payment_amount'),
				$db->quoteName('al.payment_code'),
				$db->quoteName('al.payment_completed'),
				$db->quoteName('al.anomaly'),
				$db->quoteName('al.score'),
				$db->quoteName('al.raw_result'),
				$db->quoteName('al.level'),
				$db->quoteName('al.passed'),
				$db->quoteName('al.note'),
				$db->quoteName('al.created_at',         'createdAt'),
				$db->quoteName('al.modified_at',        'modifiedAt'),
				$db->quoteName('lr.code',               'learner_code'),
				$db->quoteName('lr.lastname',            'learner_lastname'),
				$db->quoteName('lr.firstname',           'learner_firstname'),
				$db->quoteName('al.cancelled'),
				$db->quoteName('rm.code',                'room_code'),
				$db->quoteName('es.name',                'examsession_name'),
				$db->quoteName('er.examsession_id',      'examsession_id'),
			]);
		if($assessmentId>0)
			$query->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId);

		// ----- Filtering -----

		$search = $this->getState('filter.search');
		if (!empty($search)) {
			$like = $db->quote('%' . trim($search) . '%');
			$query->where(
				'(' .
				$db->quoteName('lr.code') . ' LIKE ' . $like .
				' OR CONCAT(' .
				$db->quoteName('lr.lastname') . ', \' \', ' .
				$db->quoteName('lr.firstname') .
				') LIKE ' . $like .
				')'
			);
		}

		$paymentCompleted = $this->getState('filter.payment_completed');
		if (is_numeric($paymentCompleted)) {
			// Chỉ lọc "đã/chưa nộp" với các bản ghi có yêu cầu đóng phí
			$query->where($db->quoteName('al.payment_amount') . ' > 0');
			$query->where($db->quoteName('al.payment_completed') . ' = ' . (int) $paymentCompleted);
		}

		$anomaly = $this->getState('filter.anomaly');
		if (is_numeric($anomaly)) {
			$query->where($db->quoteName('al.anomaly') . ' = ' . (int) $anomaly);
		}

		$passed = $this->getState('filter.passed');
		if (is_numeric($passed)) {
			$passed = intval($passed);
			if($passed==0 || $passed==1)
				$query->where($db->quoteName('al.passed') . ' = ' . (int) $passed);
			else if($passed==2)
				$query->where($db->quoteName('al.passed') . ' IS NOT NULL');
			else
				$query->where($db->quoteName('al.passed') . ' IS NULL');

		}

		$cancelled = $this->getState('filter.cancelled');
		if (is_numeric($cancelled)) {
			$query->where($db->quoteName('al.cancelled') . ' = ' . (int) $cancelled);
		}

		$examsessionId = $this->getState('filter.examsession_id');
		if (is_numeric($examsessionId) && (int) $examsessionId > 0) {
			$query->where($db->quoteName('er.examsession_id') . ' = ' . (int) $examsessionId);
		}

		// Lọc theo phòng thi cụ thể (dùng cho layout assessmentexaminees của Examroom view)
		$examroomId = $this->getState('filter.examroom_id');
		if (is_numeric($examroomId) && (int) $examroomId > 0) {
			$query->where($db->quoteName('al.examroom_id') . ' = ' . (int) $examroomId);
		}

		// ----- Ordering -----
		$orderingCol = $db->escape($this->getState('list.ordering', 'lr.lastname'));
		$orderingDir = $db->escape($this->getState('list.direction', 'ASC'));
		$query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

		return $query;
	}


	/**
	 * @inheritDoc
	 * @since 2.0.5
	 */
	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.assessment_id');
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.payment_completed');
		$id .= ':' . $this->getState('filter.anomaly');
		$id .= ':' . $this->getState('filter.passed');
		$id .= ':' . $this->getState('filter.cancelled');
		$id .= ':' . $this->getState('filter.examsession_id');
		$id .= ':' . $this->getState('filter.examroom_id');
		return parent::getStoreId($id);
	}




	// =========================================================================
    // Thống kê tổng hợp cho header
    // =========================================================================

	/**
	 * Trả về số liệu thống kê tổng hợp của một kỳ sát hạch.
	 * Tính trên toàn bộ bản ghi của assessment đó (không bị ảnh hưởng bởi filter).
	 *
	 * @return object{
	 *     total: int,
	 *     active: int,
	 *     cancelled: int,
	 *     hasFee: int,
	 *     paid: int,
	 *     unpaid: int,
	 *     totalFeeAmount: int,
	 *     collectedAmount: int,
	 *     passed: int,
	 *     failed: int,
	 *     notYet: int
	 * }
	 * @since 2.0.5
	 */
	public function getStatistics(): object
	{
		$assessmentId = (int) $this->getState('filter.assessment_id');
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				'COUNT(1)                                                                          AS ' . $db->quoteName('total'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0 THEN 1 ELSE 0 END)        AS ' . $db->quoteName('active'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 1 THEN 1 ELSE 0 END)        AS ' . $db->quoteName('cancelled'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_amount') . ' > 0 THEN 1 ELSE 0 END)
                                                                                                   AS ' . $db->quoteName('hasFee'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 1 THEN 1 ELSE 0 END)
                                                                                                   AS ' . $db->quoteName('paid'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 0 THEN 1 ELSE 0 END)
                                                                                                   AS ' . $db->quoteName('unpaid'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          THEN ' . $db->quoteName('payment_amount') . ' ELSE 0 END)               AS ' . $db->quoteName('totalFeeAmount'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_completed') . ' = 1
                          THEN ' . $db->quoteName('payment_amount') . ' ELSE 0 END)               AS ' . $db->quoteName('collectedAmount'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('passed') . ' = 1 THEN 1 ELSE 0 END)            AS ' . $db->quoteName('passed'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('passed') . ' = 0 THEN 1 ELSE 0 END)            AS ' . $db->quoteName('failed'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('passed') . ' IS NULL THEN 1 ELSE 0 END)        AS ' . $db->quoteName('notYet'),
			])
			->from($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('assessment_id') . ' = ' . $assessmentId);

		$db->setQuery($query);
		return $db->loadObject();
	}

	// =========================================================================
    // Kiểm tra điều kiện chỉnh sửa
    // =========================================================================

    /**
     * Kiểm tra kỳ sát hạch có được phép chỉnh sửa danh sách thí sinh không.
     *
     * Điều kiện KHÔNG được phép (read-only):
     *   - completed = TRUE  (cán bộ đã đánh dấu hoàn tất)
     *   - end_date < ngày hôm nay theo giờ hệ thống (kỳ thi đã kết thúc)
     *
     * @param  int  $assessmentId
     *
     * @return bool  TRUE nếu còn được phép chỉnh sửa.
     * @throws Exception
     * @since 2.0.5
     */
    public function isAssessmentEditable(int $assessmentId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('completed'), $db->quoteName('end_date')])
            ->from($db->quoteName('#__eqa_assessments'))
            ->where($db->quoteName('id') . ' = ' . $assessmentId);
        $db->setQuery($query);
        $a = $db->loadObject();

        if ($a === null) {
            throw new Exception('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId);
        }

        if ((bool) $a->completed) {
            return false;
        }

        // end_date là DATE (không có timezone)
        $today = DatetimeHelper::getCurrentUtcTime('Y-m-d');
        if ($a->end_date < $today) {
            return false;
        }

        return true;
    }

	public function canUploadResult(int $assessmentId): bool
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([$db->quoteName('completed'), $db->quoteName('end_date')])
			->from($db->quoteName('#__eqa_assessments'))
			->where($db->quoteName('id') . ' = ' . $assessmentId);
		$db->setQuery($query);
		$a = $db->loadObject();

		if ($a === null) {
			throw new Exception('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId);
		}

		if ((bool) $a->completed) {
			return false;
		}

		// end_date là DATE (không có timezone)
		$today = DatetimeHelper::getCurrentUtcTime('Y-m-d');
		if ($a->end_date > $today) {
			return false;
		}

		return true;
	}

    // =========================================================================
    // Lấy một bản ghi theo id (dùng cho layout setpayment)
    // =========================================================================

    /**
     * Lấy thông tin một bản ghi thí sinh sát hạch theo id, kèm thông tin người học.
     *
     * @param  int  $id
     *
     * @return object
     * @throws Exception
     * @since 2.0.5
     */
    public function getItemById(int $id): object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('al.id'),
                $db->quoteName('al.assessment_id'),
                $db->quoteName('al.payment_amount'),
                $db->quoteName('al.payment_code'),
                $db->quoteName('al.payment_completed'),
                $db->quoteName('al.note'),
                $db->quoteName('lr.code',     'learner_code'),
                $db->quoteName('lr.lastname',  'learner_lastname'),
                $db->quoteName('lr.firstname', 'learner_firstname'),
            ])
            ->from($db->quoteName('#__eqa_assessment_learner', 'al'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
            )
            ->where($db->quoteName('al.id') . ' = ' . $id);

        $db->setQuery($query);
        $record = $db->loadObject();

        if ($record === null) {
	        throw new Exception('Không tìm thấy bản ghi có id = ' . $id);
        }

        return $record;
    }

    // =========================================================================
    // Thêm thủ công thí sinh
    // =========================================================================

    /**
     * Thêm danh sách thí sinh vào kỳ sát hạch theo learner code.
     *
     * @param  int     $assessmentId
     * @param  string  $rawCodes     Chuỗi codes phân tách bởi space/newline/comma/semicolon.
     * @param  int     $operatorId   ID người dùng (created_by / modified_by).
     *
     * @return array{added: string[], skipped: string[], notFound: string[]}
     * @throws Exception
     * @since 2.0.5
     */
    public function addLearners(int $assessmentId, string $rawCodes, int $operatorId): array
    {
        $db = $this->getDatabase();

        // Parse codes
        $codes = array_values(array_unique(array_filter(
            preg_split('/[\s,;]+/', trim($rawCodes)),
            static fn(string $s): bool => $s !== ''
        )));

        if (empty($codes)) {
	        throw new Exception('Không có mã HVSV nào được nhập.');
        }

        // Lấy thông tin kỳ sát hạch
        $query = $db->getQuery(true)
            ->select(['id', 'fee'])
            ->from($db->quoteName('#__eqa_assessments'))
            ->where($db->quoteName('id') . ' = ' . $assessmentId);
        $db->setQuery($query);
        $assessment = $db->loadObject();
        if ($assessment === null) {
	        throw new Exception('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId);
        }
        $fee    = (int) $assessment->fee;
        $isFree = ($fee === 0);

        // Lấy learner_id theo code (batch)
        $quotedCodes = array_map([$db, 'quote'], $codes);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('code')])
            ->from($db->quoteName('#__eqa_learners'))
            ->where($db->quoteName('code') . ' IN (' . implode(',', $quotedCodes) . ')');
        $db->setQuery($query);
        $learnerMap = $db->loadAssocList('code', 'id'); // code → learner_id

        // Lấy bản ghi đã tồn tại (kể cả đã hủy)
        $learnerIds  = array_values($learnerMap);
        $existingMap = [];
        if (!empty($learnerIds)) {
            $query = $db->getQuery(true)
                ->select(['id', 'learner_id', 'cancelled'])
                ->from($db->quoteName('#__eqa_assessment_learner'))
                ->where($db->quoteName('assessment_id') . ' = ' . $assessmentId)
                ->where($db->quoteName('learner_id') . ' IN (' . implode(',', array_map('intval', $learnerIds)) . ')');
            $db->setQuery($query);
            foreach ($db->loadObjectList() as $row) {
                $existingMap[(int) $row->learner_id] = $row;
            }
        }

        // Load tập payment_code đã tồn tại
        $db->setQuery(
            'SELECT ' . $db->quoteName('payment_code') .
            ' FROM ' . $db->quoteName('#__eqa_assessment_learner') .
            ' WHERE ' . $db->quoteName('payment_code') . ' IS NOT NULL'
        );
        $existingCodes = array_flip($db->loadColumn());

        $now     = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $added   = [];
        $skipped = [];
        $notFound = [];

        foreach ($codes as $code) {
            if (!isset($learnerMap[$code])) {
                $notFound[] = $code;
                continue;
            }

            $learnerId = (int) $learnerMap[$code];
            $existing  = $existingMap[$learnerId] ?? null;

            if ($existing !== null && !(bool) $existing->cancelled) {
                $skipped[] = $code;
                continue;
            }

            if ($existing !== null && (bool) $existing->cancelled) {
                // Khôi phục bản ghi đã hủy
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__eqa_assessment_learner'))
                    ->set($db->quoteName('cancelled')  . ' = 0')
                    ->set($db->quoteName('modified_at') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('modified_by') . ' = ' . $operatorId)
                    ->where($db->quoteName('id') . ' = ' . (int) $existing->id);
                $db->setQuery($query);
                $db->execute();
                $added[] = $code;
                continue;
            }

            // INSERT mới
            $paymentCode = null;
            if (!$isFree) {
	            $paymentCode = PaymentCodeHelper::generateUniqueFromSet($existingCodes);
                $existingCodes[$paymentCode] = true;
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
                    0, 0,
                    $db->quote($now), $operatorId,
                    $db->quote($now), $operatorId,
                ]));
            $db->setQuery($query);
            $db->execute();
            $added[] = $code;
        }

        return compact('added', 'skipped', 'notFound');
    }

	// =========================================================================
	// Xóa thí sinh khỏi kỳ sát hạch
	// =========================================================================

	/**
	 * Xóa (xóa hẳn bản ghi) các thí sinh khỏi kỳ sát hạch.
	 *
	 * Điều kiện để được phép xóa (kiểm tra cho TỪNG bản ghi):
	 *   1. Kỳ sát hạch chưa kết thúc và chưa hoàn tất (đã kiểm tra ở controller).
	 *   2. Không đang trong thời gian đăng ký với chức năng đăng ký đang bật
	 *      (allow_registration = TRUE VÀ NOW() nằm trong [registration_start, registration_end]).
	 *   3. Thí sinh chưa nộp tiền (payment_completed = FALSE).
	 *   4. Thí sinh chưa có kết quả thi (score IS NULL AND level IS NULL AND passed IS NULL).
	 *
	 * Nếu BẤT KỲ bản ghi nào vi phạm điều kiện, toàn bộ thao tác bị hủy (không xóa gì cả).
	 *
	 * @param  int    $assessmentId  ID kỳ sát hạch.
	 * @param  int[]  $ids           Danh sách al.id cần xóa.
	 * @param  int    $operatorId    ID người thực hiện (dùng cho log nếu cần).
	 *
	 * @return int  Số bản ghi đã xóa.
	 * @throws Exception  Nếu có bản ghi vi phạm điều kiện hoặc lỗi DB.
	 * @since 2.0.5
	 */
	public function removeLearners(int $assessmentId, array $ids, int $operatorId): int
	{
		if (empty($ids)) {
			throw new Exception('Không có thí sinh nào được chọn.');
		}

		$db = $this->getDatabase();

		// Lấy thông tin assessment (cần allow_registration, registration_start, registration_end)
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('allow_registration'),
				$db->quoteName('registration_start'),
				$db->quoteName('registration_end'),
			])
			->from($db->quoteName('#__eqa_assessments'))
			->where($db->quoteName('id') . ' = ' . $assessmentId);
		$db->setQuery($query);
		$assessment = $db->loadObject();

		if ($assessment === null) {
			throw new Exception('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId . '.');
		}

		// Kiểm tra đang trong thời gian đăng ký không
		// registration_start/end được lưu theo UTC trong DB
		$nowUtc = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
		$isRegistrationOpen = (bool) $assessment->allow_registration
			&& !empty($assessment->registration_start)
			&& !empty($assessment->registration_end)
			&& $nowUtc >= $assessment->registration_start
			&& $nowUtc <= $assessment->registration_end;

		if ($isRegistrationOpen) {
			throw new Exception(
				'Không thể xóa thí sinh trong thời gian đăng ký đang mở. ' .
				'Vui lòng tắt chức năng đăng ký hoặc chờ hết thời gian đăng ký.'
			);
		}

		// Lấy toàn bộ thông tin các bản ghi được chọn để kiểm tra điều kiện
		$safeIds = array_map('intval', $ids);
		$query   = $db->getQuery(true)
			->select([
				$db->quoteName('al.id'),
				$db->quoteName('al.payment_completed'),
				$db->quoteName('al.score'),
				$db->quoteName('al.level'),
				$db->quoteName('al.passed'),
				$db->quoteName('lr.code', 'learner_code'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
			)
			->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('al.id') . ' IN (' . implode(',', $safeIds) . ')');
		$db->setQuery($query);
		$records = $db->loadObjectList();

		if (empty($records)) {
			throw new Exception('Không tìm thấy bản ghi nào phù hợp với danh sách được chọn.');
		}

		// Kiểm tra từng bản ghi
		$violations = [];
		foreach ($records as $rec) {
			$code = htmlspecialchars($rec->learner_code ?? ('id=' . $rec->id));

			if ((bool) $rec->payment_completed) {
				$violations[] = $code . ': đã nộp phí';
				continue;
			}

			$hasResult = ($rec->score !== null)
				|| ($rec->level !== null)
				|| ($rec->passed !== null);
			if ($hasResult) {
				$violations[] = $code . ': đã có kết quả thi';
			}
		}

		if (!empty($violations)) {
			throw new Exception(
				'Không thể xóa vì các thí sinh sau không đủ điều kiện:<br>' .
				implode('<br>', array_map(
					static fn(string $v): string => '&nbsp;&nbsp;• ' . $v,
					$violations
				))
			);
		}

		// Tất cả hợp lệ — xóa hẳn bản ghi
		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('id') . ' IN (' . implode(',', $safeIds) . ')');
		$db->setQuery($deleteQuery);
		$db->execute();

		return $db->getAffectedRows();
	}

	// =========================================================================
	// Chia phòng thi
	// =========================================================================

	/**
	 * Lấy số liệu thống kê phục vụ layout distributerooms.
	 *
	 * Trả về tổng thí sinh, số đã đóng phí, số miễn phí, số chưa đóng phí —
	 * tính trên phạm vi $scopeIds (nếu rỗng thì toàn bộ assessment).
	 *
	 * @param  int    $assessmentId
	 * @param  int[]  $scopeIds  Danh sách al.id được chọn; [] = toàn bộ.
	 *
	 * @return object{total:int, hasFee:int, paid:int, free:int, unpaid:int}
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function getDistributionStats(int $assessmentId, array $scopeIds): object
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				'COUNT(1)                                                                          AS ' . $db->quoteName('total'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0 THEN 1 ELSE 0 END)        AS ' . $db->quoteName('active'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 1 THEN 1 ELSE 0 END)        AS ' . $db->quoteName('cancelled'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_amount') . ' > 0 THEN 1 ELSE 0 END)
                                                                                                   AS ' . $db->quoteName('hasFee'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 1 THEN 1 ELSE 0 END)
                                                                                                   AS ' . $db->quoteName('paid'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_amount') . ' = 0 THEN 1 ELSE 0 END)
                                                                                                   AS ' . $db->quoteName('free'),
				'SUM(CASE WHEN ' . $db->quoteName('cancelled') . ' = 0
                          AND '  . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 0 THEN 1 ELSE 0 END)
                                                                                                   AS ' . $db->quoteName('unpaid'),
			])
			->from($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('assessment_id') . ' = ' . $assessmentId);

		if (!empty($scopeIds)) {
			$query->where(
				$db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $scopeIds)) . ')'
			);
		}

		$db->setQuery($query);
		return $db->loadObject();
	}

	/**
	 * Chia phòng thi và đánh số báo danh cho thí sinh của một kỳ sát hạch.
	 *
	 * Thuật toán:
	 *   1. Xác định danh sách thí sinh đủ điều kiện (lọc phí nếu cần, trong phạm vi selectedIds).
	 *   2. Kiểm tra tổng số khớp với count_distributed từ form.
	 *   3. Kiểm tra SBD mới không trùng với SBD đã có của thí sinh ngoài phạm vi reset.
	 *   4. Reset examroom_id + code của thí sinh trong phạm vi.
	 *   5. Shuffle thí sinh → phân phòng → sắp xếp trong phòng theo Tên/Họ đệm → đánh SBD.
	 *
	 * @param  int    $assessmentId
	 * @param  array  $data         Dữ liệu jform (require_payment, create_new_examrooms,
	 *                               count_distributed, examinee_code_start, examsessions).
	 * @param  int[]  $selectedIds  al.id được chọn; [] = toàn bộ assessment.
	 * @param  int    $operatorId   ID người thực hiện (modified_by).
	 *
	 * @return void
	 * @throws Exception  Nếu dữ liệu không hợp lệ hoặc có lỗi nghiệp vụ.
	 * @since 2.0.5
	 */
	public function distributeAssessmentLearners(
		int $assessmentId,
		array $data,
		array $selectedIds,
		int $operatorId
	): void {
		// ----------------------------------------------------------------
		// A. Kiểm tra tính hợp lệ cơ bản của dữ liệu form
		// ----------------------------------------------------------------
		$requiredKeys = ['require_payment', 'create_new_examrooms', 'count_distributed',
			'examinee_code_start', 'examsessions'];
		foreach ($requiredKeys as $key) {
			if (!isset($data[$key])) {
				throw new Exception('Dữ liệu form không hợp lệ (thiếu trường: ' . $key . ').');
			}
		}

		if (!is_array($data['examsessions']) || empty($data['examsessions'])) {
			throw new Exception('Phải cấu hình ít nhất một ca thi.');
		}

		$requirePayment      = (bool) $data['require_payment'];
		$createNewExamrooms  = (bool) $data['create_new_examrooms'];
		$countDistributed    = (int)  $data['count_distributed'];
		$examineeCodeStart   = (int)  $data['examinee_code_start'];

		if ($examineeCodeStart < 1) {
			throw new Exception('SBD bắt đầu phải là số nguyên dương.');
		}

		// ----------------------------------------------------------------
		// B. Kiểm tra không trùng examsession_id và room_id trong cùng ca thi
		// ----------------------------------------------------------------
		$examsessionIdsSeen = [];
		foreach ($data['examsessions'] as $esData) {
			$esId = (int) ($esData['examsession_id'] ?? 0);
			if ($esId <= 0) {
				throw new Exception('Ca thi không hợp lệ.');
			}
			if (in_array($esId, $examsessionIdsSeen, true)) {
				throw new Exception('Ca thi bị trùng lặp trong cấu hình.');
			}
			$examsessionIdsSeen[] = $esId;

			if (empty($esData['rooms']) || !is_array($esData['rooms'])) {
				throw new Exception('Mỗi ca thi phải có ít nhất một phòng.');
			}

			$roomIdsSeen = [];
			foreach ($esData['rooms'] as $roomData) {
				$rId = (int) ($roomData['room_id'] ?? 0);
				if ($rId <= 0) {
					throw new Exception('Phòng vật lý không hợp lệ.');
				}
				if (in_array($rId, $roomIdsSeen, true)) {
					throw new Exception('Phòng vật lý bị trùng lặp trong cùng một ca thi.');
				}
				$roomIdsSeen[] = $rId;

				$nEx = (int) ($roomData['nexaminee'] ?? 0);
				if ($nEx < 1) {
					throw new Exception('Số thí sinh mỗi phòng phải ít nhất là 1.');
				}
			}
		}

		// ----------------------------------------------------------------
		// C. Lấy danh sách thí sinh đủ điều kiện
		// ----------------------------------------------------------------
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('al.id',            'al_id'),
				$db->quoteName('al.learner_id'),
				$db->quoteName('lr.lastname'),
				$db->quoteName('lr.firstname'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->innerJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
			)
			->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('al.cancelled')     . ' = 0');  // luôn loại thí sinh đã hủy

		if (!empty($selectedIds)) {
			// Lọc theo selectedIds nhưng vẫn đảm bảo không lấy bản ghi cancelled
			// (thí sinh bị hủy trong selectedIds sẽ bị loại bởi điều kiện cancelled=0 ở trên)
			$query->where(
				$db->quoteName('al.id') . ' IN (' . implode(',', array_map('intval', $selectedIds)) . ')'
			);
		}

		if ($requirePayment) {
			// Đủ điều kiện = miễn phí (payment_amount=0) HOẶC đã đóng phí
			$query->where(
				'(' .
				$db->quoteName('al.payment_amount') . ' = 0' .
				' OR ' . $db->quoteName('al.payment_completed') . ' = 1' .
				')'
			);
		}

		$db->setQuery($query);
		$eligibleLearners = $db->loadObjectList();

		// ----------------------------------------------------------------
		// C2. Kiểm tra không có thí sinh nào đã có kết quả thi
		// ----------------------------------------------------------------
		$hasResultQuery = $db->getQuery(true)
			->select('COUNT(1)')
			->from($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('cancelled')     . ' = 0')
			->where('('. $db->quoteName('score') . ' IS NOT NULL OR ' . $db->quoteName('passed') . ' IS NOT NULL)');

		if (!empty($selectedIds)) {
			$hasResultQuery->where(
				$db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $selectedIds)) . ')'
			);
		}

		$db->setQuery($hasResultQuery);
		if ((int) $db->loadResult() > 0) {
			throw new Exception('Không thể chia phòng thi: một hoặc nhiều thí sinh trong phạm vi đã có kết quả thi.');
		}

		// ----------------------------------------------------------------
		// D. Kiểm tra tổng số khớp với count_distributed
		// ----------------------------------------------------------------
		$eligibleCount = count($eligibleLearners);
		if ($eligibleCount !== $countDistributed) {
			throw new Exception(
				sprintf(
					'Số thí sinh đủ điều kiện (%d) không khớp với tổng số thí sinh đã cấu hình trong các phòng (%d). ' .
					'Vui lòng kiểm tra lại tùy chọn đóng phí và số lượng mỗi phòng.',
					$eligibleCount,
					$countDistributed
				)
			);
		}

		if ($eligibleCount === 0) {
			throw new Exception('Không có thí sinh nào đủ điều kiện để chia phòng.');
		}

		// ----------------------------------------------------------------
		// E. Kiểm tra SBD mới không trùng với SBD đã cấp cho thí sinh ngoài phạm vi
		// ----------------------------------------------------------------
		$newCodeEnd = $examineeCodeStart + $eligibleCount - 1;

		$checkQuery = $db->getQuery(true)
			->select($db->quoteName('al.code'))
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('al.cancelled')     . ' = 0')
			->where($db->quoteName('al.code')          . ' IS NOT NULL')
			->where(
				$db->quoteName('al.code') . ' BETWEEN ' . $examineeCodeStart . ' AND ' . $newCodeEnd
			);

		// Loại trừ chính những thí sinh sẽ bị reset (các thí sinh trong phạm vi)
		$eligibleAlIds = array_map(static fn(object $r): int => (int) $r->al_id, $eligibleLearners);
		$checkQuery->where(
			$db->quoteName('al.id') . ' NOT IN (' . implode(',', $eligibleAlIds) . ')'
		);

		$db->setQuery($checkQuery);
		$conflictCodes = $db->loadColumn();

		if (!empty($conflictCodes)) {
			sort($conflictCodes);
			throw new Exception(
				sprintf(
					'Dải SBD từ %d đến %d bị trùng với SBD đã cấp cho thí sinh khác: %s. ' .
					'Vui lòng chọn giá trị "SBD bắt đầu từ" khác.',
					$examineeCodeStart,
					$newCodeEnd,
					implode(', ', $conflictCodes)
				)
			);
		}

		// ----------------------------------------------------------------
		// F. Bắt đầu transaction
		// ----------------------------------------------------------------
		$db->transactionStart();
		try {
			$now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

			// F1. Reset examroom_id + code của thí sinh trong phạm vi
			$resetQuery = $db->getQuery(true)
				->update($db->quoteName('#__eqa_assessment_learner'))
				->set($db->quoteName('examroom_id') . ' = NULL')
				->set($db->quoteName('code')        . ' = NULL')
				->set($db->quoteName('modified_at')  . ' = ' . $db->quote($now))
				->set($db->quoteName('modified_by')  . ' = ' . $operatorId)
				->where(
					$db->quoteName('id') . ' IN (' . implode(',', $eligibleAlIds) . ')'
				);
			$db->setQuery($resetQuery);
			$db->execute();

			// F2. Ngẫu nhiên hóa danh sách thí sinh
			shuffle($eligibleLearners);

			// F3. Collator để sắp xếp tiếng Việt
			$collator   = new \Collator('vi_VN');
			$comparator = static function (object $a, object $b) use ($collator): int {
				$cmp = $collator->compare($a->firstname, $b->firstname);
				if ($cmp !== 0) {
					return $cmp;
				}
				return $collator->compare($a->lastname, $b->lastname);
			};

			$examineeCode = $examineeCodeStart;
			$startIndex   = 0;

			// F4. Phân phòng theo từng ca thi
			foreach ($data['examsessions'] as $esData) {
				$examsessionId = (int) $esData['examsession_id'];

				foreach ($esData['rooms'] as $roomData) {
					$roomId    = (int) $roomData['room_id'];
					$nExaminee = (int) $roomData['nexaminee'];

					// F4a. Tìm hoặc tạo examroom
					$erQuery = $db->getQuery(true)
						->select($db->quoteName('id'))
						->from($db->quoteName('#__eqa_examrooms'))
						->where($db->quoteName('examsession_id') . ' = ' . $examsessionId)
						->where($db->quoteName('room_id')        . ' = ' . $roomId);
					$db->setQuery($erQuery);
					$existingExamroomId = $db->loadResult();

					if ($existingExamroomId !== null) {
						// Phòng thi đã tồn tại trong ca thi này
						if ($createNewExamrooms) {
							throw new Exception(
								sprintf(
									'Ca thi đã sử dụng phòng vật lý (room_id=%d). ' .
									'Tắt tùy chọn "Tạo phòng thi mới" nếu muốn ghép vào phòng thi đã có.',
									$roomId
								)
							);
						}
						$examroomId = (int) $existingExamroomId;
					} else {
						// F4b. Tạo examroom mới
						$roomCode = \Kma\Component\Eqa\Administrator\Helper\DatabaseHelper::getRoomCode($roomId);
						$insQuery = $db->getQuery(true)
							->insert($db->quoteName('#__eqa_examrooms'))
							->columns($db->quoteName(['name', 'room_id', 'examsession_id',
								'created_at', 'created_by',
								'modified_at', 'modified_by']))
							->values(implode(',', [
								$db->quote($roomCode),
								$roomId,
								$examsessionId,
								$db->quote($now), $operatorId,
								$db->quote($now), $operatorId,
							]));
						$db->setQuery($insQuery);
						$db->execute();
						$examroomId = (int) $db->insertid();
					}

					// F4c. Slice thí sinh cho phòng này
					$roomLearners = array_slice($eligibleLearners, $startIndex, $nExaminee);
					$startIndex  += $nExaminee;

					// F4d. Sắp xếp trong phòng: Tên ASC, Họ đệm ASC (vi_VN)
					usort($roomLearners, $comparator);

					// F4e. Ghi SBD + examroom_id cho từng thí sinh
					foreach ($roomLearners as $learner) {
						$updQuery = $db->getQuery(true)
							->update($db->quoteName('#__eqa_assessment_learner'))
							->set($db->quoteName('code')        . ' = ' . $examineeCode)
							->set($db->quoteName('examroom_id') . ' = ' . $examroomId)
							->set($db->quoteName('modified_at')  . ' = ' . $db->quote($now))
							->set($db->quoteName('modified_by')  . ' = ' . $operatorId)
							->where($db->quoteName('id')        . ' = ' . (int) $learner->al_id);
						$db->setQuery($updQuery);
						$db->execute();
						$examineeCode++;
					}
				} // end foreach rooms
			} // end foreach examsessions

			$db->transactionCommit();

		} catch (\Exception $e) {
			$db->transactionRollback();
			throw $e;
		}
	}


	// =========================================================================
    // Cập nhật thông tin thanh toán
    // =========================================================================

    /**
     * Cập nhật payment_amount, payment_completed và note cho một bản ghi.
     *
     * @param  int         $id
     * @param  int         $paymentAmount
     * @param  bool        $paymentCompleted
     * @param  string|null $note
     * @param  int         $operatorId
     *
     * @return string  learner_code của bản ghi đã cập nhật (dùng cho thông báo).
     * @throws Exception
     * @since 2.0.5
     */
    public function savePaymentInfo(
        int $id,
        int $paymentAmount,
        bool $paymentCompleted,
        ?string $note,
        int $operatorId
    ): string {
        $db     = $this->getDatabase();
        $record = $this->getItemById($id);

        $now   = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__eqa_assessment_learner'))
            ->set($db->quoteName('payment_amount')    . ' = ' . $paymentAmount)
            ->set($db->quoteName('payment_completed') . ' = ' . ($paymentCompleted ? 1 : 0))
            ->set($db->quoteName('note')              . ' = ' . ($note !== null ? $db->quote($note) : 'NULL'))
            ->set($db->quoteName('modified_at')        . ' = ' . $db->quote($now))
            ->set($db->quoteName('modified_by')        . ' = ' . $operatorId)
            ->where($db->quoteName('id')              . ' = ' . $id);

        $db->setQuery($query);
        $db->execute();

        return $record->learner_code ?? ('id=' . $id);
    }

	// =========================================================================
	// Nhập sao kê ngân hàng
	// =========================================================================

	/**
	 * Đối chiếu sao kê ngân hàng với danh sách thí sinh sát hạch,
	 * cập nhật payment_completed cho các bản ghi hợp lệ.
	 *
	 * @param  string  $filePath      Đường dẫn tuyệt đối đến file .xlsx.
	 * @param  string  $napasCode     Mã NAPAS ngân hàng (dùng để chọn parser).
	 * @param  int     $assessmentId  ID kỳ sát hạch.
	 * @param  int     $operatorId    ID người dùng (modified_by).
	 *
	 * @return BankStatementImportResult
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function importBankStatement(
		string $filePath,
		string $napasCode,
		int $assessmentId,
		int $operatorId
	): BankStatementImportResult
	{
		// 1. Parse file theo ngân hàng
		$parser       = BankStatementHelper::getParser($napasCode);
		$transactions = $parser->parse($filePath);

		if (empty($transactions)) {
			throw new Exception(
				sprintf(
					'File sao kê %s không có giao dịch Credit nào hợp lệ. Vui lòng kiểm tra lại định dạng file.',
					$parser->getBankName()
				)
			);
		}

		// 2. Load bản ghi DB cần đối chiếu
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('al.id'),
				$db->quoteName('al.payment_code'),
				$db->quoteName('al.payment_amount'),
				$db->quoteName('al.payment_completed'),
				$db->quoteName('lr.code',      'learner_code'),
				$db->quoteName('lr.lastname',  'learner_lastname'),
				$db->quoteName('lr.firstname', 'learner_firstname'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
			)
			->where($db->quoteName('al.assessment_id')   . ' = ' . $assessmentId)
			->where($db->quoteName('al.payment_amount')  . ' > 0')
			->where($db->quoteName('al.payment_code')    . ' IS NOT NULL')
			->where($db->quoteName('al.cancelled')       . ' = 0');
		$db->setQuery($query);
		$dbRecords = $db->loadObjectList();

		// 3. Đối chiếu (thuật toán dùng chung)
		$reconciled = BankStatementHelper::reconcile($transactions, $dbRecords);

		// 4. Thực hiện UPDATE cho các bản ghi hợp lệ
		$now          = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
		$updatedCodes = [];

		foreach ($reconciled['matched'] as $pair) {
			$rec  = $pair['record'];
			$tx   = $pair['transaction'];
			$note = $tx['description'];

			$query = $db->getQuery(true)
				->update($db->quoteName('#__eqa_assessment_learner'))
				->set($db->quoteName('payment_completed') . ' = 1')
				->set($db->quoteName('note')       . ' = ' . $db->quote($note))
				->set($db->quoteName('modified_at') . ' = ' . $db->quote($now))
				->set($db->quoteName('modified_by') . ' = ' . $operatorId)
				->where($db->quoteName('id') . ' = ' . (int) $rec->id);
			$db->setQuery($query);
			$db->execute();

			$updatedCodes[] = $rec->learner_code ?? ('id=' . $rec->id);
		}

		$result                = new BankStatementImportResult();
		$result->updated        = count($reconciled['matched']);
		$result->alreadyPaid    = $reconciled['alreadyPaid'];
		$result->notFound       = $reconciled['notFound'];
		$result->amountMismatch = $reconciled['amountMismatch'];
		$result->duplicate      = $reconciled['duplicate'];
		$result->updatedCodes   = $updatedCodes;
		return $result;
	}

	// =========================================================================
	// Xuất ca iTest
	// =========================================================================

	/**
	 * Lấy dữ liệu thí sinh đã được đánh số báo danh để xuất ca iTest.
	 *
	 * Điều kiện lọc:
	 *   - Thuộc kỳ sát hạch có assessment_id = $assessmentId
	 *   - Chưa bị hủy (cancelled = 0)
	 *   - Đã được xếp phòng (examroom_id IS NOT NULL)
	 *   - Đã được cấp SBD (al.code IS NOT NULL)
	 *
	 * Sắp xếp: theo thời gian bắt đầu ca thi ASC, sau đó SBD ASC.
	 *
	 * @param  int  $assessmentId
	 *
	 * @return object[]  Mảng object, mỗi phần tử có fields:
	 *                     start        (datetime UTC),
	 *                     room         (tên phòng thi — er.name),
	 *                     learner_code (mã HVSV — lr.code),
	 *                     code         (SBD — al.code)
	 * @throws \Exception
	 * @since 2.0.5
	 */
	public function getITestData(int $assessmentId): array
	{
		if ($assessmentId <= 0) {
			throw new \Exception('assessment_id không hợp lệ.');
		}

		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('es.start',  'start'),
				$db->quoteName('er.name',   'room'),
				$db->quoteName('lr.code',   'learner_code'),
				$db->quoteName('al.code',   'code'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->join(
				'INNER',
				$db->quoteName('#__eqa_examrooms', 'er') .
				' ON ' . $db->quoteName('er.id') . ' = ' . $db->quoteName('al.examroom_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__eqa_examsessions', 'es') .
				' ON ' . $db->quoteName('es.id') . ' = ' . $db->quoteName('er.examsession_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
			)
			->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('al.cancelled')     . ' = 0')
			->where($db->quoteName('al.examroom_id')   . ' IS NOT NULL')
			->where($db->quoteName('al.code')          . ' IS NOT NULL')
			->order($db->quoteName('es.start') . ' ASC')
			->order($db->quoteName('al.code')  . ' ASC');

		$db->setQuery($query);

		return $db->loadObjectList();
	}

	// =========================================================================
	// getUnassignedIds — lấy danh sách id thí sinh chưa được chia phòng
	// =========================================================================

	/**
	 * Trả về danh sách al.id của các thí sinh thuộc kỳ sát hạch chưa được chia phòng thi
	 * (examroom_id IS NULL), không bị hủy (cancelled = 0).
	 *
	 * @param  int  $assessmentId  ID kỳ sát hạch.
	 *
	 * @return int[]
	 * @since 2.0.5
	 */
	public function getUnassignedIds(int $assessmentId): array
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('cancelled')     . ' = 0')
			->where($db->quoteName('examroom_id')   . ' IS NULL');

		$db->setQuery($query);

		return array_map('intval', $db->loadColumn());
	}

	// =========================================================================
	// clearRoomAssignments — xóa thông tin chia phòng thi
	// =========================================================================

	/**
	 * Xóa thông tin chia phòng thi (examroom_id, code) của thí sinh trong phạm vi chỉ định.
	 * Sau khi xóa, tự động dọn dẹp các phòng thi rỗng (không còn thí sinh nào).
	 *
	 * @param  int    $assessmentId  ID kỳ sát hạch.
	 * @param  int[]  $scopeIds      Danh sách al.id cần xóa; [] = toàn bộ kỳ sát hạch.
	 * @param  int    $operatorId    ID người thực hiện.
	 *
	 * @return array{cleared:int, roomsDeleted:int}
	 * @throws \Exception
	 * @since 2.0.5
	 */
	public function clearRoomAssignments(int $assessmentId, array $scopeIds, int $operatorId): array
	{
		$db  = $this->getDatabase();
		$now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

		// Kiểm tra không có thí sinh nào đã có kết quả thi
		$hasResultQuery = $db->getQuery(true)
			->select('COUNT(1)')
			->from($db->quoteName('#__eqa_assessment_learner'))
			->where($db->quoteName('assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('cancelled')     . ' = 0')
			->where('('. $db->quoteName('score') . ' IS NOT NULL OR ' . $db->quoteName('passed') . ' IS NOT NULL)');

		if (!empty($scopeIds)) {
			$hasResultQuery->where(
				$db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $scopeIds)) . ')'
			);
		}

		$db->setQuery($hasResultQuery);
		if ((int) $db->loadResult() > 0) {
			throw new Exception('Không thể xóa chia phòng thi: một hoặc nhiều thí sinh trong phạm vi đã có kết quả thi.');
		}

		$db->transactionStart();
		try {
			// B1. Lấy danh sách examroom_id bị ảnh hưởng (để kiểm tra phòng rỗng sau khi xóa)
			$affectedRoomQuery = $db->getQuery(true)
				->select('DISTINCT ' . $db->quoteName('examroom_id'))
				->from($db->quoteName('#__eqa_assessment_learner'))
				->where($db->quoteName('assessment_id') . ' = ' . $assessmentId)
				->where($db->quoteName('examroom_id')   . ' IS NOT NULL');

			if (!empty($scopeIds)) {
				$affectedRoomQuery->where(
					$db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $scopeIds)) . ')'
				);
			}

			$db->setQuery($affectedRoomQuery);
			$affectedRoomIds = array_map('intval', array_filter($db->loadColumn()));

			// B2. Reset examroom_id + code cho thí sinh trong phạm vi
			$clearQuery = $db->getQuery(true)
				->update($db->quoteName('#__eqa_assessment_learner'))
				->set($db->quoteName('examroom_id') . ' = NULL')
				->set($db->quoteName('code')        . ' = NULL')
				->set($db->quoteName('modified_at') . ' = ' . $db->quote($now))
				->set($db->quoteName('modified_by') . ' = ' . $operatorId)
				->where($db->quoteName('assessment_id') . ' = ' . $assessmentId);

			if (!empty($scopeIds)) {
				$clearQuery->where(
					$db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $scopeIds)) . ')'
				);
			}

			$db->setQuery($clearQuery);
			$db->execute();
			$cleared = $db->getAffectedRows();

			// B3. Xóa các phòng thi rỗng trong danh sách bị ảnh hưởng
			$roomsDeleted = 0;
			if (!empty($affectedRoomIds)) {
				// Kiểm tra phòng nào còn thí sinh
				$occupiedQuery = $db->getQuery(true)
					->select('DISTINCT ' . $db->quoteName('examroom_id'))
					->from($db->quoteName('#__eqa_assessment_learner'))
					->where(
						$db->quoteName('examroom_id') . ' IN (' . implode(',', $affectedRoomIds) . ')'
					)
					->where($db->quoteName('examroom_id') . ' IS NOT NULL');

				$db->setQuery($occupiedQuery);
				$occupiedRoomIds = array_map('intval', $db->loadColumn());

				$emptyRoomIds = array_diff($affectedRoomIds, $occupiedRoomIds);

				if (!empty($emptyRoomIds)) {
					$deleteRoomsQuery = $db->getQuery(true)
						->delete($db->quoteName('#__eqa_examrooms'))
						->where(
							$db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $emptyRoomIds)) . ')'
						);
					$db->setQuery($deleteRoomsQuery);
					$db->execute();
					$roomsDeleted = $db->getAffectedRows();
				}
			}

			$db->transactionCommit();

			return ['cleared' => $cleared, 'roomsDeleted' => $roomsDeleted];

		} catch (\Exception $e) {
			$db->transactionRollback();
			throw $e;
		}
	}

	// =========================================================================
	// importITestResult — Nhập điểm thi TOEIC iTest
	// =========================================================================

	/**
	 * Ngưỡng điểm TOEIC tối thiểu cần đạt theo năm nhập học.
	 *
	 * Quy định hiện hành:
	 *   - Nhập học trước năm 2025 : 450 điểm
	 *   - Nhập học từ năm 2025    : 500 điểm
	 *
	 * Khi quy định thay đổi, chỉ cần sửa method này.
	 *
	 * @param  int  $admissionYear  Năm nhập học (từ cột admissionyear của #__eqa_courses).
	 *
	 * @return int  Ngưỡng điểm tối thiểu (thang 990).
	 * @since  2.0.8
	 */
	protected function getToeicPassingScore(int $admissionYear): int
	{
		return $admissionYear < 2025 ? 450 : 500;
	}

	/**
	 * Nhập điểm thi iTest TOEIC cho một kỳ sát hạch.
	 *
	 * Quy trình:
	 *   1. Đọc & parse tất cả sheet điểm trong file Excel (bỏ qua 'Thống kê', 'Tất cả').
	 *   2. Kiểm tra tính toàn vẹn: duplicate, khớp danh sách DB↔file, khớp cặp SBD↔mã HVSV.
	 *   3. Tính kết quả từng thí sinh (dùng ToeicHelper).
	 *   4. Ghi toàn bộ vào DB trong một transaction.
	 *
	 * @param  string  $filePath      Đường dẫn đến file .xlsx đã được lưu tạm.
	 * @param  int     $assessmentId  ID kỳ sát hạch.
	 * @param  int     $operatorId    ID người thực hiện (ghi vào modified_by).
	 *
	 * @return array{total: int, passed: int, failed: int}
	 *
	 * @throws \Exception  Nếu file không hợp lệ, dữ liệu không toàn vẹn, hoặc lỗi DB.
	 * @since  2.0.8
	 */
	public function importITestResult(string $filePath, int $assessmentId, int $operatorId): array
	{
		// ----------------------------------------------------------------
		// BƯỚC 1: Đọc & parse file Excel
		// ----------------------------------------------------------------
		$fileRows = $this->parseITestResultFile($filePath);

		// ----------------------------------------------------------------
		// BƯỚC 2: Load dữ liệu thí sinh đủ điều kiện từ DB
		// ----------------------------------------------------------------
		$dbRows = $this->loadEligibleExaminees($assessmentId);

		// ----------------------------------------------------------------
		// BƯỚC 3: Kiểm tra tính toàn vẹn
		// ----------------------------------------------------------------
		$this->validateITestResultData($fileRows, $dbRows);

		// ----------------------------------------------------------------
		// BƯỚC 4 & 5: Tính kết quả và ghi DB trong transaction
		// ----------------------------------------------------------------
		return $this->saveITestResults($fileRows, $dbRows, $assessmentId, $operatorId);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Đọc và parse tất cả sheet điểm trong file Excel iTest.
	 *
	 * Bỏ qua các sheet có tên 'Thống kê' và 'Tất cả'.
	 * Mỗi dòng dữ liệu (từ dòng 2 trở đi) được đọc từ:
	 *   - Cột B (index 1) : examinee_code  (int)
	 *   - Cột C (index 2) : learner_code   (string)
	 *   - Cột K (index 10): anomaly_text   (string|null)
	 *   - Cột N (index 13): listening_raw  (int|null)
	 *   - Cột O (index 14): reading_raw    (int|null)
	 *
	 * @param  string  $filePath  Đường dẫn file .xlsx.
	 *
	 * @return array<int, array{
	 *     examinee_code: int,
	 *     learner_code: string,
	 *     anomaly: Anomaly,
	 *     listening_raw: int|null,
	 *     reading_raw: int|null,
	 *     sheet: string,
	 *     row: int
	 * }>  Mảng indexed bởi examinee_code.
	 *
	 * @throws \Exception  Nếu file không đọc được hoặc dữ liệu không hợp lệ.
	 * @since  2.0.8
	 */
	private function parseITestResultFile(string $filePath): array
	{
		require_once JPATH_ROOT . '/vendor/autoload.php';
		$spreadsheet = IOHelper::loadSpreadsheet($filePath);

		// Danh sách sheet bỏ qua
		$ignoredSheets = ['Thống kê', 'Tất cả'];

		$fileRows     = [];   // key = examinee_code
		$duplicates   = [];   // Ghi nhận SBD trùng để báo lỗi chi tiết
		$parseErrors  = [];   // Ghi nhận lỗi parse từng dòng

		foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
			$sheetName = $sheet->getTitle();

			// Bỏ qua sheet tổng hợp
			if (in_array($sheetName, $ignoredSheets, true)) {
				continue;
			}

			$highestRow = $sheet->getHighestDataRow();

			// Bỏ qua sheet rỗng (chỉ có header hoặc không có dữ liệu)
			if ($highestRow < 2) {
				continue;
			}

			for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
				// Đọc các ô theo vị trí cột (1-based)
				$rawExamineeCode = $sheet->getCell('B' . $rowIndex)->getValue(); // Cột B
				$rawLearnerCode  = $sheet->getCell('C' . $rowIndex)->getValue(); // Cột C
				$rawAnomalyText  = $sheet->getCell('K' . $rowIndex)->getValue(); // Cột K
				$rawListening    = $sheet->getCell('N' . $rowIndex)->getValue(); // Cột N
				$rawReading      = $sheet->getCell('O' . $rowIndex)->getValue(); // Cột O

				// Bỏ qua dòng trống hoàn toàn
				if ($rawExamineeCode === null && $rawLearnerCode === null) {
					continue;
				}

				$location = sprintf('Sheet "%s", dòng %d', $sheetName, $rowIndex);

				// Validate examinee_code
				if (!is_numeric($rawExamineeCode) || (int) $rawExamineeCode <= 0) {
					$parseErrors[] = $location . ': SBD không hợp lệ — "' . $rawExamineeCode . '".';
					continue;
				}
				$examineeCode = (int) $rawExamineeCode;

				// Validate learner_code
				$learnerCode = trim((string) $rawLearnerCode);
				if ($learnerCode === '') {
					$parseErrors[] = $location . ': Mã HVSV (cột C) trống.';
					continue;
				}

				// Parse anomaly
				$anomalyText = ($rawAnomalyText !== null) ? trim((string) $rawAnomalyText) : '';
				$anomaly     = Anomaly::tryFromLabel($anomalyText);

				if ($anomaly === null) {
					$parseErrors[] = sprintf(
						'%s (SBD %d — %s): Giá trị cột "Ghi chú" không hợp lệ — "%s". '
						. 'Chỉ chấp nhận: trống, "Vắng thi", "Đình chỉ".',
						$location,
						$examineeCode,
						$learnerCode,
						$anomalyText
					);
					continue;
				}

				// Validate điểm thành phần (bắt buộc khi không có bất thường)
				$listeningRaw = null;
				$readingRaw   = null;

				$isAbsentOrSuspended = in_array(
					$anomaly,
					[Anomaly::Absent,
						Anomaly::Suspended],
					true
				);

				if (!$isAbsentOrSuspended) {
					// Trường hợp bình thường: phải có điểm listening và reading
					if ($rawListening === null || $rawListening === '') {
						$parseErrors[] = sprintf(
							'%s (SBD %d — %s): Thiếu điểm Listening (cột N).',
							$location, $examineeCode, $learnerCode
						);
						continue;
					}
					if ($rawReading === null || $rawReading === '') {
						$parseErrors[] = sprintf(
							'%s (SBD %d — %s): Thiếu điểm Reading (cột O).',
							$location, $examineeCode, $learnerCode
						);
						continue;
					}

					// Cột N/O có dạng "55/100" — lấy phần trước dấu "/"
					$listeningRaw = $this->parseRawScoreCell($rawListening);
					$readingRaw   = $this->parseRawScoreCell($rawReading);

					if ($listeningRaw === null) {
						$parseErrors[] = sprintf(
							'%s (SBD %d — %s): Giá trị điểm Listening không hợp lệ — "%s".',
							$location, $examineeCode, $learnerCode, $rawListening
						);
						continue;
					}
					if ($readingRaw === null) {
						$parseErrors[] = sprintf(
							'%s (SBD %d — %s): Giá trị điểm Reading không hợp lệ — "%s".',
							$location, $examineeCode, $learnerCode, $rawReading
						);
						continue;
					}
				}

				// Kiểm tra SBD trùng
				if (isset($fileRows[$examineeCode])) {
					$existing    = $fileRows[$examineeCode];
					$duplicates[] = sprintf(
						'SBD %d xuất hiện nhiều lần: lần đầu tại sheet "%s" dòng %d, lần sau tại %s.',
						$examineeCode,
						$existing['sheet'],
						$existing['row'],
						$location
					);
					continue;
				}

				$fileRows[$examineeCode] = [
					'examinee_code' => $examineeCode,
					'learner_code'  => $learnerCode,
					'anomaly'       => $anomaly,
					'listening_raw' => $listeningRaw,
					'reading_raw'   => $readingRaw,
					'sheet'         => $sheetName,
					'row'           => $rowIndex,
				];
			}
		}

		// Tổng hợp lỗi parse và duplicate — dừng lại nếu có lỗi
		$allErrors = array_merge($parseErrors, $duplicates);
		if (!empty($allErrors)) {
			throw new \Exception(
				'File điểm có lỗi dữ liệu — không thể nhập. Chi tiết:<br>'
				. '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $allErrors)) . '</li></ul>'
			);
		}

		if (empty($fileRows)) {
			throw new \Exception('File điểm không có dữ liệu thí sinh nào (sau khi bỏ qua các sheet tổng hợp).');
		}

		return $fileRows;
	}

	/**
	 * Parse giá trị ô điểm có dạng "55/100" hoặc số nguyên thuần.
	 *
	 * @param  mixed  $cellValue  Giá trị ô từ PhpSpreadsheet.
	 *
	 * @return int|null  Số câu đúng, hoặc null nếu không parse được.
	 * @since  2.0.8
	 */
	private function parseRawScoreCell(mixed $cellValue): ?int
	{
		$str = trim((string) $cellValue);

		// Dạng "55/100"
		if (str_contains($str, '/')) {
			$parts = explode('/', $str, 2);
			$numerator = trim($parts[0]);
			if (is_numeric($numerator) && (int) $numerator >= 0) {
				return (int) $numerator;
			}
			return null;
		}

		// Dạng số nguyên thuần
		if (is_numeric($str) && (int) $str >= 0) {
			return (int) $str;
		}

		return null;
	}

	/**
	 * Load danh sách thí sinh đủ điều kiện dự thi của kỳ sát hạch từ DB.
	 *
	 * Điều kiện: cancelled = 0, examroom_id IS NOT NULL, code IS NOT NULL.
	 * Join thêm để lấy admission_year của từng thí sinh.
	 *
	 * @param  int  $assessmentId  ID kỳ sát hạch.
	 *
	 * @return array<int, array{
	 *     al_id: int,
	 *     examinee_code: int,
	 *     learner_id: int,
	 *     learner_code: string,
	 *     admission_year: int
	 * }>  Mảng indexed bởi examinee_code.
	 *
	 * @throws \Exception
	 * @since  2.0.8
	 */
	private function loadEligibleExaminees(int $assessmentId): array
	{
		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('al.id',              'al_id'),
				$db->quoteName('al.code',            'examinee_code'),
				$db->quoteName('al.learner_id',      'learner_id'),
				$db->quoteName('lr.code',            'learner_code'),
				$db->quoteName('co.admissionyear',   'admission_year'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->join(
				'INNER',
				$db->quoteName('#__eqa_learners', 'lr')
				. ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__eqa_groups', 'gr')
				. ' ON ' . $db->quoteName('gr.id') . ' = ' . $db->quoteName('lr.group_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__eqa_courses', 'co')
				. ' ON ' . $db->quoteName('co.id') . ' = ' . $db->quoteName('gr.course_id')
			)
			->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('al.cancelled')     . ' = 0')
			->where($db->quoteName('al.examroom_id')   . ' IS NOT NULL')
			->where($db->quoteName('al.code')          . ' IS NOT NULL');

		$db->setQuery($query);
		$rows = $db->loadObjectList();

		// Chuyển sang mảng indexed bởi examinee_code
		$dbRows = [];
		foreach ($rows as $row) {
			$dbRows[(int) $row->examinee_code] = [
				'al_id'          => (int) $row->al_id,
				'examinee_code'  => (int) $row->examinee_code,
				'learner_id'     => (int) $row->learner_id,
				'learner_code'   => (string) $row->learner_code,
				'admission_year' => (int) $row->admission_year,
			];
		}

		if (empty($dbRows)) {
			throw new \Exception(
				'Kỳ sát hạch này chưa có thí sinh nào được đánh số báo danh và xếp phòng thi. '
				. 'Vui lòng hoàn thành bước chia phòng trước khi nhập điểm.'
			);
		}

		return $dbRows;
	}

	/**
	 * Kiểm tra tính toàn vẹn giữa dữ liệu file và dữ liệu DB.
	 *
	 * Các kiểm tra:
	 *   (a) Thí sinh trong DB nhưng không có trong file (thiếu điểm).
	 *   (b) Thí sinh trong file nhưng không có trong DB (thừa, không thuộc danh sách).
	 *   (c) Cặp (examinee_code → learner_code) trong file khác với DB.
	 *
	 * Nếu có bất kỳ lỗi nào → throw Exception với thông báo chi tiết.
	 *
	 * @param  array<int, array>  $fileRows  Dữ liệu parse từ file.
	 * @param  array<int, array>  $dbRows    Dữ liệu load từ DB.
	 *
	 * @return void
	 * @throws \Exception
	 * @since  2.0.8
	 */
	private function validateITestResultData(array $fileRows, array $dbRows): void
	{
		$errors = [];

		// (a) Thí sinh trong DB nhưng thiếu trong file
		$missingInFile = array_diff_key($dbRows, $fileRows);
		foreach ($missingInFile as $code => $row) {
			$errors[] = sprintf(
				'SBD %d (mã HVSV: %s) có trong danh sách thi nhưng không tìm thấy trong file điểm.',
				$code,
				$row['learner_code']
			);
		}

		// (b) Thí sinh trong file nhưng không có trong DB
		$extraInFile = array_diff_key($fileRows, $dbRows);
		foreach ($extraInFile as $code => $row) {
			$errors[] = sprintf(
				'SBD %d (mã HVSV: %s, sheet "%s" dòng %d) có trong file điểm nhưng không thuộc danh sách thi của kỳ sát hạch này.',
				$code,
				$row['learner_code'],
				$row['sheet'],
				$row['row']
			);
		}

		// (c) Kiểm tra khớp cặp examinee_code ↔ learner_code
		foreach ($fileRows as $examineeCode => $fileRow) {
			if (!isset($dbRows[$examineeCode])) {
				continue; // Đã báo lỗi ở (b)
			}
			$dbLearnerCode   = $dbRows[$examineeCode]['learner_code'];
			$fileLearnerCode = $fileRow['learner_code'];
			if ($fileLearnerCode !== $dbLearnerCode) {
				$errors[] = sprintf(
					'SBD %d: mã HVSV trong file là "%s" nhưng trong CSDL là "%s" — dữ liệu không khớp (sheet "%s", dòng %d).',
					$examineeCode,
					$fileLearnerCode,
					$dbLearnerCode,
					$fileRow['sheet'],
					$fileRow['row']
				);
			}
		}

		if (!empty($errors)) {
			throw new \Exception(
				'Dữ liệu file điểm không khớp với danh sách thi — không thể nhập. Chi tiết:<br>'
				. '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $errors)) . '</li></ul>'
			);
		}
	}

	/**
	 * Tính kết quả từng thí sinh và ghi vào DB trong một transaction.
	 *
	 * @param  array<int, array>  $fileRows     Dữ liệu đã parse & validate từ file.
	 * @param  array<int, array>  $dbRows       Dữ liệu thí sinh từ DB (indexed bởi examinee_code).
	 * @param  int                $assessmentId  ID kỳ sát hạch.
	 * @param  int                $operatorId    ID người thực hiện.
	 *
	 * @return array{total: int, passed: int, failed: int}
	 * @throws \Exception
	 * @since  2.0.8
	 */
	private function saveITestResults(
		array $fileRows,
		array $dbRows,
		int $assessmentId,
		int $operatorId
	): array {
		$db  = $this->getDatabase();
		$now = DatetimeHelper::getCurrentUtcTime();

		$countPassed = 0;
		$countFailed = 0;

		$db->transactionStart();
		try {
			foreach ($fileRows as $examineeCode => $fileRow) {
				$dbRow        = $dbRows[$examineeCode];
				$alId         = $dbRow['al_id'];
				$admissionYear = $dbRow['admission_year'];
				$anomaly       = $fileRow['anomaly'];

				$isAbsentOrSuspended = in_array(
					$anomaly,
					[Anomaly::Absent, Anomaly::Suspended],
					true
				);

				if ($isAbsentOrSuspended) {
					// Vắng thi / Đình chỉ → điểm 0, không đạt, không có raw_result
					$score      = 0.0;
					$passed     = false;
					$rawResult  = null;
				} else {
					// Trường hợp bình thường — tính điểm TOEIC
					$breakdown = ToeicHelper::getScoreBreakdown(
						$fileRow['listening_raw'],
						$fileRow['reading_raw']
					);
					$score   = (float) $breakdown['total'];
					$passing = $this->getToeicPassingScore($admissionYear);
					$passed  = $score >= $passing;

					// Lưu thêm ngưỡng tối thiểu vào raw_result để sau này
					// có thể truy vết mà không cần tính lại theo admission_year.
					$breakdown['passing_score'] = $passing;

					$rawResult = json_encode($breakdown, JSON_UNESCAPED_UNICODE);
				}

				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_assessment_learner'))
					->set($db->quoteName('anomaly')      . ' = ' . (int) $anomaly->value)
					->set($db->quoteName('raw_result')   . ' = ' . ($rawResult !== null ? $db->quote($rawResult) : 'NULL'))
					->set($db->quoteName('score')        . ' = ' . $score)
					->set($db->quoteName('passed')       . ' = ' . ($passed ? 1 : 0))
					->set($db->quoteName('modified_at')  . ' = ' . $db->quote($now))
					->set($db->quoteName('modified_by')  . ' = ' . $operatorId)
					->where($db->quoteName('id')         . ' = ' . $alId);

				$db->setQuery($query);
				$db->execute();

				$passed ? $countPassed++ : $countFailed++;
			}

			$db->transactionCommit();

		} catch (\Exception $e) {
			$db->transactionRollback();
			throw $e;
		}

		return [
			'total'  => count($fileRows),
			'passed' => $countPassed,
			'failed' => $countFailed,
		];
	}

	// =========================================================================
	// getExportData — lấy dữ liệu xuất báo cáo kỳ sát hạch
	// =========================================================================

	/**
	 * Lấy toàn bộ dữ liệu cần thiết để xuất 2 loại báo cáo kết quả kỳ sát hạch
	 * (Báo cáo Hội đồng thi và Thông báo cho HVSV).
	 *
	 * Dữ liệu trả về gồm:
	 *   - assessment  : object  — thông tin kỳ sát hạch (id, title, type)
	 *   - examinees   : array   — danh sách thí sinh, mỗi phần tử là object với các trường:
	 *       learner_id, learner_code, learner_lastname, learner_firstname,
	 *       course_code, admission_year,
	 *       anomaly (int), score (float|null), passed (bool|null), raw_result (string|null),
	 *       attempt_number (int — lần sát hạch thứ mấy của thí sinh trong cùng loại kỳ sát hạch)
	 *   - stats_by_course : array — thống kê theo khóa, mỗi phần tử là object với:
	 *       course_code, admission_year, total, passed, failed, absent, pass_rate (float)
	 *       (sắp theo admission_year ASC, course_code ASC)
	 *
	 * Chỉ lấy thí sinh đã được xếp phòng thi (examroom_id IS NOT NULL, code IS NOT NULL,
	 * cancelled = 0) — tức là những thí sinh thực sự tham dự kỳ sát hạch.
	 *
	 * @param  int  $assessmentId  ID kỳ sát hạch.
	 *
	 * @return array{assessment: object, examinees: object[], stats_by_course: object[]}
	 * @throws \Exception
	 * @since  2.0.8
	 */
	public function getExportData(int $assessmentId): array
	{
		$db = $this->getDatabase();

		// ----------------------------------------------------------------
		// 1. Thông tin kỳ sát hạch
		// ----------------------------------------------------------------
		$query = $db->getQuery(true)
			->select(['id', 'title', 'type'])
			->from($db->quoteName('#__eqa_assessments'))
			->where($db->quoteName('id') . ' = ' . $assessmentId);
		$db->setQuery($query);
		$assessment = $db->loadObject();

		if ($assessment === null) {
			throw new \Exception('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId . '.');
		}

		// ----------------------------------------------------------------
		// 2. Danh sách thí sinh đã dự thi
		// ----------------------------------------------------------------
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('al.learner_id'),
				$db->quoteName('lr.code',            'learner_code'),
				$db->quoteName('lr.lastname',         'learner_lastname'),
				$db->quoteName('lr.firstname',        'learner_firstname'),
				$db->quoteName('co.code',             'course_code'),
				$db->quoteName('co.admissionyear',    'admission_year'),
				$db->quoteName('al.anomaly'),
				$db->quoteName('al.score'),
				$db->quoteName('al.passed'),
				$db->quoteName('al.raw_result'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->join('INNER',
				$db->quoteName('#__eqa_learners', 'lr')
				. ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
			)
			->join('INNER',
				$db->quoteName('#__eqa_groups', 'gr')
				. ' ON ' . $db->quoteName('gr.id') . ' = ' . $db->quoteName('lr.group_id')
			)
			->join('INNER',
				$db->quoteName('#__eqa_courses', 'co')
				. ' ON ' . $db->quoteName('co.id') . ' = ' . $db->quoteName('gr.course_id')
			)
			->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId)
			->where($db->quoteName('al.cancelled')     . ' = 0')
			->where($db->quoteName('al.examroom_id')   . ' IS NOT NULL')
			->where($db->quoteName('al.code')          . ' IS NOT NULL')
			->order($db->quoteName('co.admissionyear') . ' ASC')
			->order($db->quoteName('co.code')          . ' ASC')
			->order($db->quoteName('lr.firstname')     . ' ASC')
			->order($db->quoteName('lr.lastname')      . ' ASC');

		$db->setQuery($query);
		$examinees = $db->loadObjectList();

		if (empty($examinees)) {
			throw new \Exception(
				'Kỳ sát hạch này chưa có thí sinh nào được đánh số báo danh và xếp phòng thi.'
			);
		}

		// ----------------------------------------------------------------
		// 3. Tính attempt_number — lần sát hạch thứ mấy (cùng type)
		//    Đếm số kỳ sát hạch cùng type có id <= currentAssessmentId
		//    mà thí sinh đã thực sự dự thi (examroom_id IS NOT NULL, cancelled = 0).
		// ----------------------------------------------------------------
		$learnerIds = array_map(
			static fn(object $e): int => (int) $e->learner_id,
			$examinees
		);
		$learnerIdList = implode(',', array_map('intval', $learnerIds));

		$attemptQuery = $db->getQuery(true)
			->select([
				$db->quoteName('al.learner_id'),
				'COUNT(*) AS ' . $db->quoteName('attempt_number'),
			])
			->from($db->quoteName('#__eqa_assessment_learner', 'al'))
			->join('INNER',
				$db->quoteName('#__eqa_assessments', 'a')
				. ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('al.assessment_id')
			)
			->where($db->quoteName('a.type')         . ' = ' . (int) $assessment->type)
			->where($db->quoteName('al.learner_id')  . ' IN (' . $learnerIdList . ')')
			->where($db->quoteName('al.assessment_id') . ' <= ' . $assessmentId)
			->where($db->quoteName('al.cancelled')   . ' = 0')
			->where($db->quoteName('al.examroom_id') . ' IS NOT NULL')
			->group($db->quoteName('al.learner_id'));

		$db->setQuery($attemptQuery);
		// Map: learner_id (int) → attempt_number (int)
		$attemptMap = [];
		foreach ($db->loadObjectList() as $row) {
			$attemptMap[(int) $row->learner_id] = (int) $row->attempt_number;
		}

		// Gắn attempt_number vào từng thí sinh
		foreach ($examinees as $examinee) {
			$examinee->attempt_number = $attemptMap[(int) $examinee->learner_id] ?? 1;
		}

		// ----------------------------------------------------------------
		// 4. Thống kê theo khóa (PHP — không cần query thêm)
		// ----------------------------------------------------------------
		$courseGroups = [];
		foreach ($examinees as $examinee) {
			$key = $examinee->course_code;
			if (!isset($courseGroups[$key])) {
				$courseGroups[$key] = [
					'course_code'    => $examinee->course_code,
					'admission_year' => (int) $examinee->admission_year,
					'total'          => 0,
					'passed'         => 0,
					'failed'         => 0,   // không đạt, không tính vắng thi
					'absent'         => 0,
				];
			}

			$courseGroups[$key]['total']++;

			$isAbsent = ((int) $examinee->anomaly === Anomaly::Absent->value);

			if ($isAbsent) {
				$courseGroups[$key]['absent']++;
			} elseif ((bool) $examinee->passed) {
				$courseGroups[$key]['passed']++;
			} else {
				$courseGroups[$key]['failed']++;
			}
		}

		// Sắp xếp: admission_year ASC, course_code ASC
		uasort($courseGroups, static function (array $a, array $b): int {
			if ($a['admission_year'] !== $b['admission_year']) {
				return $a['admission_year'] <=> $b['admission_year'];
			}
			return strcmp($a['course_code'], $b['course_code']);
		});

		// Tính pass_rate và chuyển sang object
		$statsByCourse = [];
		foreach ($courseGroups as $group) {
			$passRate = $group['total'] > 0
				? round($group['passed'] / $group['total'] * 100, 1)
				: 0.0;

			$stat                = new \stdClass();
			$stat->course_code   = $group['course_code'];
			$stat->admission_year = $group['admission_year'];
			$stat->total         = $group['total'];
			$stat->passed        = $group['passed'];
			$stat->failed        = $group['failed'];
			$stat->absent        = $group['absent'];
			$stat->pass_rate     = $passRate;

			$statsByCourse[] = $stat;
		}

		return [
			'assessment'       => $assessment,
			'examinees'        => $examinees,
			'stats_by_course'  => $statsByCourse,
		];
	}

}
