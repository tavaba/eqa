<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Library\Kma\Model\ListModel;

/**
 * Model danh sách thí sinh (người học) của một kỳ sát hạch.
 *
 * assessment_id PHẢI được set vào state trước khi gọi getItems()/getListQuery():
 *   $model->setState('filter.assessment_id', $assessmentId);
 *
 * @since 2.1.0
 */
class AssessmentLearnersModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'al.id', 'al.code', 'lr.code', 'lr.lastname', 'lr.firstname',
            'al.payment_completed', 'al.anomaly', 'al.passed', 'al.score', 'al.level',
	        'al.cancelled'
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
     * @since 2.1.0
     */
    public function getListQuery()
    {
        $assessmentId = (int) $this->getState('filter.assessment_id');
        if ($assessmentId <= 0) {
            throw new Exception('Không xác định được kỳ sát hạch (assessment_id chưa được set).');
        }

        $db = $this->getDatabase();

        $query = parent::getListQuery();
        $query
            ->from($db->quoteName('#__eqa_assessment_learner', 'al'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
            )
            ->select([
                $db->quoteName('al.id'),
                $db->quoteName('al.assessment_id'),
                $db->quoteName('al.learner_id'),
                $db->quoteName('al.code',               'examinee_code'),
                $db->quoteName('al.payment_amount'),
                $db->quoteName('al.payment_code'),
                $db->quoteName('al.payment_completed'),
                $db->quoteName('al.anomaly'),
                $db->quoteName('al.score'),
                $db->quoteName('al.level'),
                $db->quoteName('al.passed'),
                $db->quoteName('al.note'),
                $db->quoteName('lr.code',               'learner_code'),
                $db->quoteName('lr.lastname',            'learner_lastname'),
	            $db->quoteName('lr.firstname',           'learner_firstname'),
	            $db->quoteName('al.cancelled'),
            ])
            ->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId);

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
            $query->where($db->quoteName('al.passed') . ' = ' . (int) $passed);
        }

		$cancelled = $this->getState('filter.cancelled');
		if (is_numeric($cancelled)) {
			$query->where($db->quoteName('al.cancelled') . ' = ' . (int) $cancelled);
		}

        // ----- Ordering -----
        $orderingCol = $db->escape($this->getState('list.ordering', 'lr.lastname'));
        $orderingDir = $db->escape($this->getState('list.direction', 'ASC'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        return $query;
    }

    /**
     * @inheritDoc
     * @since 2.1.0
     */
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.assessment_id');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.payment_completed');
        $id .= ':' . $this->getState('filter.anomaly');
        $id .= ':' . $this->getState('filter.passed');
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
     *     hasFee: int,
     *     paid: int,
     *     unpaid: int,
     *     totalFeeAmount: int,
     *     collectedAmount: int,
     *     passed: int,
     *     failed: int,
     *     notYet: int
     * }
     * @since 2.1.0
     */
    public function getStatistics(): object
    {
        $assessmentId = (int) $this->getState('filter.assessment_id');
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'COUNT(1)                                                               AS ' . $db->quoteName('total'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0 THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('hasFee'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 1 THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('paid'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 0 THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('unpaid'),
                'SUM(' . $db->quoteName('payment_amount') . ')                          AS ' . $db->quoteName('totalFeeAmount'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_completed') . ' = 1
                          THEN ' . $db->quoteName('payment_amount') . ' ELSE 0 END)    AS ' . $db->quoteName('collectedAmount'),
                'SUM(CASE WHEN ' . $db->quoteName('passed') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('passed'),
                'SUM(CASE WHEN ' . $db->quoteName('passed') . ' = 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('failed'),
                'SUM(CASE WHEN ' . $db->quoteName('passed') . ' IS NULL THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('notYet'),
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
     * @since 2.1.0
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

        // end_date là DATE (không có timezone) → so sánh với ngày hôm nay theo OS timezone
        $today = \Kma\Library\Kma\Helper\DatetimeHelper::getSystemCurrentClockTime('Y-m-d');
        if ($a->end_date < $today) {
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
     * @since 2.1.0
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
     * @param  int     $operatorId   ID người dùng (created_by / updated_by).
     *
     * @return array{added: string[], skipped: string[], notFound: string[]}
     * @throws Exception
     * @since 2.1.0
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
                    ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('updated_by') . ' = ' . $operatorId)
                    ->where($db->quoteName('id') . ' = ' . (int) $existing->id);
                $db->setQuery($query);
                $db->execute();
                $added[] = $code;
                continue;
            }

            // INSERT mới
            $paymentCode = null;
            if (!$isFree) {
                $paymentCode             = $this->generateUniqueCode($existingCodes);
                $existingCodes[$paymentCode] = true;
            }

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__eqa_assessment_learner'))
                ->columns($db->quoteName([
                    'assessment_id', 'learner_id',
                    'payment_amount', 'payment_code', 'payment_completed',
                    'cancelled', 'created_at', 'created_by', 'updated_at', 'updated_by',
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
     * @since 2.1.0
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
            ->set($db->quoteName('updated_at')        . ' = ' . $db->quote($now))
            ->set($db->quoteName('updated_by')        . ' = ' . $operatorId)
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
	 * @param  int     $operatorId    ID người dùng (updated_by).
	 *
	 * @return array{
	 *     updated:       int,
	 *     alreadyPaid:   int,
	 *     notFound:      int,
	 *     amountMismatch: array,
	 *     duplicate:     array,
	 *     updatedCodes:  string[]
	 * }
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function importBankStatement(
		string $filePath,
		string $napasCode,
		int $assessmentId,
		int $operatorId
	): array {
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
				->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
				->set($db->quoteName('updated_by') . ' = ' . $operatorId)
				->where($db->quoteName('id') . ' = ' . (int) $rec->id);
			$db->setQuery($query);
			$db->execute();

			$updatedCodes[] = $rec->learner_code ?? ('id=' . $rec->id);
		}

		return [
			'updated'        => count($reconciled['matched']),
			'alreadyPaid'    => $reconciled['alreadyPaid'],
			'notFound'       => $reconciled['notFound'],
			'amountMismatch' => $reconciled['amountMismatch'],
			'duplicate'      => $reconciled['duplicate'],
			'updatedCodes'   => $updatedCodes,
		];
	}

	// =========================================================================
    // Private helper
    // =========================================================================

    /**
     * Sinh payment_code ngẫu nhiên 8 ký tự [A-Z0-9] không trùng tập đã có.
     *
     * @param  array<string, mixed>  $existingCodes  Map (flip) các code đã dùng.
     *
     * @throws Exception
     */
    private function generateUniqueCode(array $existingCodes): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (isset($existingCodes[$code]));
        return $code;
    }
}
