<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

require_once JPATH_ROOT . '/vendor/autoload.php';

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Library\Kma\Helper\IOHelper;
use Joomla\Database\DatabaseDriver;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Enum\FeeMode;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Library\Kma\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

/**
 * Model quản lý danh sách thí sinh thi lần hai.
 *
 * @since 2.0.0
 */
class SecondAttemptsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'id', 'learner_code', 'academicyear', 'term',
            'has_fee', 'payment_completed',
        ];
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }

    // =========================================================================
    // Query danh sách
    // =========================================================================

    /**
     * Xây dựng câu truy vấn danh sách thí sinh thi lần hai, kết hợp JOIN để lấy
     * thông tin người học, lớp học phần, môn học và năm học.
     *
     * Các bảng tham gia:
     *   sa → #__eqa_secondattempts  (bảng chính)
     *   lr → #__eqa_learners        (thông tin người học)
     *   cl → #__eqa_classes         (lớp học phần: academicyear_id, term)
     *   ay → #__eqa_academicyears   (mã năm học)
     *   ex → #__eqa_exams           (môn thi, để xác định subject_id)
     *   su → #__eqa_subjects        (mã và tên môn học)
     *
     * @return \Joomla\Database\QueryInterface
     * @since 2.0.2
     */
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $columns = $db->quoteName(
            [
                'sa.id',
                'sa.class_id',
                'sa.learner_id',
                'sa.last_exam_id',
	            'sa.last_attempt',
				'el.debtor',
	            'el.anomaly',
                'sa.last_conclusion',
                'sa.payment_amount',
                'sa.payment_completed',
                'sa.payment_code',
                'sa.description',
                'lr.code',
                'lr.lastname',
                'lr.firstname',
                'su.code',
                'su.name',
                'cl.academicyear',
                'cl.term',
            ],
            [
                'id',
                'class_id',
                'learner_id',
                'last_exam_id',
                'last_attempt',
	            'is_debtor',
	            'last_anomaly',
                'last_conclusion',
                'payment_amount',
                'payment_completed',
                'payment_code',
	            'description',
                'learner_code',
                'learner_lastname',
                'learner_firstname',
                'subject_code',
                'subject_name',
                'academicyear',
                'term',
            ]
        );

        $query = $db->getQuery(true)
            ->select($columns)
            ->from($db->quoteName('#__eqa_secondattempts', 'sa'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_classes', 'cl') .
                ' ON ' . $db->quoteName('cl.id') . ' = ' . $db->quoteName('sa.class_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_exams', 'ex') .
                ' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('sa.last_exam_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_subjects', 'su') .
                ' ON ' . $db->quoteName('su.id') . ' = ' . $db->quoteName('ex.subject_id')
            )
	        ->leftJoin($db->quoteName('#__eqa_exam_learner','el'),
		        'el.exam_id = sa.last_exam_id AND el.learner_id = sa.learner_id');

        // --- Filtering ---

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . trim($search) . '%');
            $query->where(
                '(' .
                $db->quoteName('lr.code') . ' LIKE ' . $like .
                ' OR CONCAT(' . $db->quoteName('lr.lastname') . ', \' \', ' . $db->quoteName('lr.firstname') . ') LIKE ' . $like .
                ' OR ' . $db->quoteName('su.code') . ' LIKE ' . $like .
                ' OR ' . $db->quoteName('su.name') . ' LIKE ' . $like .
                ')'
            );
        }

        $subjectId = $this->getState('filter.subject_id');
        if (is_numeric($subjectId)) {
            $query->where($db->quoteName('su.id') . ' = ' . (int) $subjectId);
        }

        $academicyearCode = $this->getState('filter.academicyear');
        if (is_numeric($academicyearCode)) {
            $query->where($db->quoteName('cl.academicyear') . ' = ' . (int) $academicyearCode);
        }

        $term = $this->getState('filter.term');
        if (is_numeric($term)) {
            $query->where($db->quoteName('cl.term') . ' = ' . (int) $term);
        }

		$isDebtor = $this->getState('filter.is_debtor');
		if(is_numeric($isDebtor)){
			$query->where($db->quoteName('el.debtor') . ' = ' . (int)$isDebtor);
		}

		$anomaly = $this->getState('filter.anomaly');
		if(is_numeric($anomaly)){
			$query->where($db->quoteName('el.anomaly') . ' = ' . (int)$anomaly);
		}

        // Filter "Có phí": 1 = payment_amount > 0; 0 = payment_amount = 0
        $hasFee = $this->getState('filter.has_fee');
        if ($hasFee === '1') {
            $query->where($db->quoteName('sa.payment_amount') . ' > 0');
        } elseif ($hasFee === '0') {
            $query->where($db->quoteName('sa.payment_amount') . ' = 0');
        }

        $paymentCompleted = $this->getState('filter.payment_completed');
        if (is_numeric($paymentCompleted)) {
            // Filter này chỉ có nghĩa với các bản ghi có phí (payment_amount > 0)
            $query->where($db->quoteName('sa.payment_amount') . ' > 0');
            $query->where($db->quoteName('sa.payment_completed') . ' = ' . (int) $paymentCompleted);
        }

        // --- Ordering ---
        $orderingCol = $db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.academicyear_id');
        $id .= ':' . $this->getState('filter.term');
        $id .= ':' . $this->getState('filter.has_fee');
        $id .= ':' . $this->getState('filter.payment_completed');
        return parent::getStoreId($id);
    }

    // =========================================================================
    // Thống kê
    // =========================================================================

    /**
     * Trả về số liệu thống kê tổng hợp của bảng #__eqa_secondattempts.
     *
     * Luôn tính trên toàn bộ bảng (không bị ảnh hưởng bởi bộ lọc hiện tại)
     * để phản ánh đúng tổng quan tình hình.
     *
     * @return object{
     *     totalExams: int,
     *     totalLearners: int,
     *     totalAttempts: int,
     *     totalFree: int,
     *     totalRequired: int,
     *     totalPaid: int,
     *     totalFeeAmount: float,
     *     totalCollectedAmount: float
     * }
     * @throws Exception
     * @since 2.0.2
     */
    public function getStatistics(): object
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $query = $db->getQuery(true)
            ->select([
	            'COUNT(DISTINCT ' . $db->quoteName('last_exam_id') . ')' .
	            ' AS ' . $db->quoteName('totalExams'),
	            'COUNT(DISTINCT ' . $db->quoteName('learner_id') . ')' .
	            ' AS ' . $db->quoteName('totalLearners'),
                'COUNT(1)' .
                ' AS ' . $db->quoteName('totalAttempts'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' = 0 THEN 1 ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalFree'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0 THEN 1 ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalRequired'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0' .
                ' AND ' . $db->quoteName('payment_completed') . ' = 1 THEN 1 ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalPaid'),
                // Tổng phí cần thu: cộng toàn bộ payment_amount > 0
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0' .
                ' THEN ' . $db->quoteName('payment_amount') . ' ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalFeeAmount'),
                // Tổng đã thu: cộng payment_amount của các trường hợp đã thanh toán
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0' .
                ' AND ' . $db->quoteName('payment_completed') . ' = 1' .
                ' THEN ' . $db->quoteName('payment_amount') . ' ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalCollectedAmount'),
            ])
            ->from($db->quoteName('#__eqa_secondattempts'));

        $db->setQuery($query);
        $result = $db->loadObject();

        // Đảm bảo kiểu dữ liệu đúng (bảng rỗng → loadObject() trả về null trong từng cột)
        $result->totalLearners       = (int)   ($result->totalLearners       ?? 0);
        $result->totalAttempts       = (int)   ($result->totalAttempts       ?? 0);
        $result->totalFree           = (int)   ($result->totalFree           ?? 0);
        $result->totalRequired       = (int)   ($result->totalRequired       ?? 0);
        $result->totalPaid           = (int)   ($result->totalPaid           ?? 0);
        $result->totalFeeAmount      = (float) ($result->totalFeeAmount      ?? 0.0);
        $result->totalCollectedAmount = (float) ($result->totalCollectedAmount ?? 0.0);

        return $result;
    }

    // =========================================================================
    // Chức năng "Làm mới"
    // =========================================================================

	/**
	 * Chỉ bổ sung các trường hợp mới vào #__eqa_secondattempts, không xóa bất cứ
	 * bản ghi nào đang có.
	 *
	 * Thuật toán:
	 *   1. Xây dựng $newList từ dữ liệu hiện tại (giống refresh).
	 *   2. Load các triple key (class_id:learner_id:last_exam_id) đang tồn tại.
	 *   3. Loại khỏi $newList những entry đã có → chỉ còn các entry thực sự mới.
	 *   4. Insert phần còn lại.
	 *
	 * @return array{added: int}
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function addNew(): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$db->transactionStart();

		try {
			$newList = $this->buildNewList($db);

			// Load các triple key đang tồn tại trong bảng
			$db->setQuery(
				$db->getQuery(true)
					->select([
						$db->quoteName('class_id'),
						$db->quoteName('learner_id'),
						$db->quoteName('last_exam_id'),
					])
					->from($db->quoteName('#__eqa_secondattempts'))
			);
			foreach ($db->loadObjectList() as $existing) {
				$tripleKey = $existing->class_id . ':' . $existing->learner_id . ':' . $existing->last_exam_id;
				unset($newList[$tripleKey]);
			}

			$addedCount = $this->insertNewRecords($db, $newList);
			$db->transactionCommit();

		} catch (Exception $e) {
			$db->transactionRollback();
			throw $e;
		}

		return ['added' => $addedCount];
	}

    /**
     * Làm mới bảng #__eqa_secondattempts theo thuật toán an toàn, bảo toàn thông
     * tin đóng phí của những thí sinh đã thanh toán.
     *
     * Quy trình gồm 4 bước:
     *   1. Xây dựng $newList từ dữ liệu hiện tại của hai bảng class_learner và exam_learner.
     *   2. Xóa khỏi DB các bản ghi lỗi thời (không còn trong $newList hoặc có exam_id cũ hơn).
     *   3. Thêm mới các bản ghi có trong $newList nhưng chưa có trong DB (sau khi đã xóa).
     *
     * @return array{removed: int, added: int}
     * @throws Exception
     * @since 2.0.2
     */
    public function refresh(): array
    {
        $db = DatabaseHelper::getDatabaseDriver();

        // Bước 1: Xây dựng danh sách mới
        $newList = $this->buildNewList($db);

        // Bước 2: Xóa bản ghi lỗi thời, thu về tập key còn tồn tại
        [$removed, $survivingKeys] = $this->removeStaleRecords($db, $newList);

        // Bước 3: Thêm bản ghi mới (những key trong $newList nhưng không có trong $survivingKeys)
        $toInsert = array_diff_key($newList, $survivingKeys);
        $added    = $this->insertNewRecords($db, $toInsert);

        return ['removed' => $removed, 'added' => $added];
    }

    /**
     * Xây dựng danh sách các thí sinh đủ điều kiện thi lần hai từ dữ liệu hiện tại.
     *
     * Điều kiện lọc:
     *   - Thí sinh được phép dự thi (cl.allowed = 1).
     *   - Thí sinh chưa hết quyền dự thi (cl.expired = 0).
     *   - Kết luận của lần thi gần nhất là Failed (20) hoặc Deferred (30).
     *
     * @param DatabaseDriver $db
     * @return array<string, object> Map theo key "class_id:learner_id:last_exam_id".
     *                               Mỗi giá trị là object có các thuộc tính:
     *                               class_id, learner_id, last_exam_id, last_attempt, last_conclusion.
     * @throws Exception
     * @since 2.0.2
     */
    private function buildNewList(DatabaseDriver $db): array
    {
        // Subquery: lấy exam_id lớn nhất của mỗi cặp (class_id, learner_id)
        $subQuery = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('el2.exam_id') . ')')
            ->from($db->quoteName('#__eqa_exam_learner', 'el2'))
            ->where([
                $db->quoteName('el2.class_id') . ' = ' . $db->quoteName('el.class_id'),
                $db->quoteName('el2.learner_id') . ' = ' . $db->quoteName('el.learner_id'),
            ]);

        $validConclusions = implode(',', [
            Conclusion::RetakeExam->value,
            Conclusion::Postponed->value,
        ]);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('el.class_id'),
                $db->quoteName('el.learner_id'),
                $db->quoteName('el.exam_id', 'last_exam_id'),
                $db->quoteName('el.attempt', 'last_attempt'),
                $db->quoteName('el.conclusion', 'last_conclusion'),
            ])
            ->from($db->quoteName('#__eqa_exam_learner', 'el'))
            ->innerJoin(
                $db->quoteName('#__eqa_class_learner', 'cl') .
                ' ON ' . $db->quoteName('cl.class_id') . ' = ' . $db->quoteName('el.class_id') .
                ' AND ' . $db->quoteName('cl.learner_id') . ' = ' . $db->quoteName('el.learner_id')
            )
            ->where([
                $db->quoteName('cl.allowed') . ' = 1',
                $db->quoteName('cl.expired') . ' = 0',
                $db->quoteName('el.conclusion') . ' IN (' . $validConclusions . ')',
                $db->quoteName('el.exam_id') . ' = (' . $subQuery . ')',
            ]);

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        // Đánh index theo key "class_id:learner_id:last_exam_id" để tra cứu O(1)
        $newList = [];
        foreach ($rows as $row) {
            $key           = $row->class_id . ':' . $row->learner_id . ':' . $row->last_exam_id;
            $newList[$key] = $row;
        }

        return $newList;
    }

 	/**
	 * Xóa các bản ghi lỗi thời khỏi #__eqa_secondattempts.
	 *
	 * Quy tắc quyết định cho từng record R(class_id, learner_id, last_exam_id):
	 *
	 * 1. Nếu cặp (class_id, learner_id) CÓ trong $newList:
	 *    - last_exam_id khớp với $newList  → KEEP
	 *    - last_exam_id khác $newList      → DELETE
	 *
	 * 2. Nếu cặp (class_id, learner_id) KHÔNG có trong $newList:
	 *    Tra cứu MAX(exam_id) và conclusion tương ứng trong #__eqa_exam_learner:
	 *    - maxExamId > last_exam_id VÀ conclusion IS NULL → KEEP
	 *      (thí sinh đang trong kỳ thi mới, chưa có kết quả)
	 *    - Tất cả trường hợp còn lại                     → DELETE
	 *
	 * Toàn bộ dữ liệu cần thiết được load bằng 2 query (không có N+1 query).
	 *
	 * @since 2.0.2
	 */
	private function removeStaleRecords(object $db, array $newList): array
	{
		// --- Bước 1: Xây dựng lookup map từ $newList ---
		// "class_id:learner_id" → last_exam_id, tra cứu O(1)
		$newPairMap = [];
		foreach ($newList as $entry) {
			$pairKey              = $entry->class_id . ':' . $entry->learner_id;
			$newPairMap[$pairKey] = (int) $entry->last_exam_id;
		}

		// --- Bước 2: Load toàn bộ secondattempts ---
		$db->setQuery(
			$db->getQuery(true)
				->select([
					$db->quoteName('id'),
					$db->quoteName('class_id'),
					$db->quoteName('learner_id'),
					$db->quoteName('last_exam_id'),
				])
				->from($db->quoteName('#__eqa_secondattempts'))
		);
		$saRecords = $db->loadObjectList();

		// --- Bước 3: Load MAX(exam_id) và conclusion tương ứng trong exam_learner ---
		// Chỉ cần cho các cặp (class_id, learner_id) KHÔNG có trong $newList,
		// nhưng để đơn giản và tránh N+1 query, ta load tất cả trong 1 query.
		//
		// Kỹ thuật: JOIN #__eqa_exam_learner với subquery lấy MAX(exam_id)
		// theo từng (class_id, learner_id), rồi lấy conclusion của bản ghi MAX đó.
		$maxExamQuery = $db->getQuery(true)
			->select([
				$db->quoteName('el.class_id'),
				$db->quoteName('el.learner_id'),
				'MAX(' . $db->quoteName('el.exam_id') . ') AS ' . $db->quoteName('max_exam_id'),
			])
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->group([$db->quoteName('el.class_id'), $db->quoteName('el.learner_id')]);

		$latestExamQuery = $db->getQuery(true)
			->select([
				$db->quoteName('el2.class_id'),
				$db->quoteName('el2.learner_id'),
				$db->quoteName('mx.max_exam_id'),
				$db->quoteName('el2.conclusion'),
			])
			->from($db->quoteName('#__eqa_exam_learner', 'el2'))
			->innerJoin(
				'(' . $maxExamQuery . ') AS ' . $db->quoteName('mx') .
				' ON ' . $db->quoteName('mx.class_id') . ' = ' . $db->quoteName('el2.class_id') .
				' AND ' . $db->quoteName('mx.learner_id') . ' = ' . $db->quoteName('el2.learner_id') .
				' AND ' . $db->quoteName('mx.max_exam_id') . ' = ' . $db->quoteName('el2.exam_id')
			);

		$db->setQuery($latestExamQuery);

		// $latestExamMap: "class_id:learner_id" → {max_exam_id, conclusion}
		$latestExamMap = [];
		foreach ($db->loadObjectList() as $row) {
			$pairKey                = $row->class_id . ':' . $row->learner_id;
			$latestExamMap[$pairKey] = $row;
		}

		// --- Bước 4: Phân loại từng record ---
		$idsToDelete   = [];
		$survivingKeys = [];

		foreach ($saRecords as $record) {
			$pairKey   = $record->class_id . ':' . $record->learner_id;
			$tripleKey = $pairKey . ':' . $record->last_exam_id;

			if (isset($newPairMap[$pairKey])) {
				// Trường hợp 1: cặp CÓ trong $newList
				if ((int) $record->last_exam_id === $newPairMap[$pairKey]) {
					$survivingKeys[$tripleKey] = true;  // last_exam_id khớp → KEEP
				} else {
					$idsToDelete[] = (int) $record->id; // last_exam_id lệch → DELETE
				}
			} else {
				// Trường hợp 2: cặp KHÔNG có trong $newList
				// KEEP nếu: đang có kỳ thi mới (maxExamId > last_exam_id)
				//           VÀ kết quả kỳ thi đó chưa có (conclusion IS NULL)
				$latest = $latestExamMap[$pairKey] ?? null;
				if (
					$latest !== null &&
					(int) $latest->max_exam_id > (int) $record->last_exam_id &&
					$latest->conclusion === null
				) {
					$survivingKeys[$tripleKey] = true;  // đang thi → KEEP
				} else {
					$idsToDelete[] = (int) $record->id; // không còn hợp lệ → DELETE
				}
			}
		}

		// --- Bước 5: Thực hiện xóa ---
		if (!empty($idsToDelete)) {
			$db->setQuery(
				'DELETE FROM ' . $db->quoteName('#__eqa_secondattempts') .
				' WHERE ' . $db->quoteName('id') . ' IN (' . implode(',', $idsToDelete) . ')'
			);
			$db->execute();
		}

		return [count($idsToDelete), $survivingKeys];
	}

    /**
     * Thêm mới vào #__eqa_secondattempts các bản ghi trong $newList chưa tồn tại trong DB.
     *
     * Quy tắc gán thông tin thanh toán:
     *   - last_conclusion = Deferred  → payment_amount = 0.0 (bảo lưu, không cần đóng phí)
     *   - last_conclusion = Failed    → payment_amount = calculateFee(...),
     *                                   payment_completed = FALSE,
     *                                   payment_code = chuỗi ngẫu nhiên 8 ký tự [A-Z0-9] (duy nhất)
     *
     * @param DatabaseDriver        $db
     * @param array<string, object> $newList  Danh sách bản ghi cần thêm.
     * @return int Số bản ghi đã thêm.
     * @throws Exception
     * @since 2.0.2
     */
    private function insertNewRecords(DatabaseDriver $db, array $newList): int
    {
        if (empty($newList)) {
            return 0;
        }

        // Đọc cấu hình phí thi lần 2
        $feeMode = ConfigHelper::getSecondAttemptFeeMode();
        $feeRate = ConfigHelper::getSecondAttemptFeeRate();
        $isFree  = $feeMode === FeeMode::Free;

        // Đọc số tín chỉ của các môn thi liên quan (để tính phí theo PerCredit)
        $examIds   = array_unique(array_map(
            static fn(object $entry): int => (int) $entry->last_exam_id,
            $newList
        ));
        $creditMap = $this->loadCreditsByExamIds($db, $examIds);

        // Load tập payment_code đang tồn tại để đảm bảo unique khi sinh mới
        $db->setQuery(
            'SELECT ' . $db->quoteName('payment_code') .
            ' FROM ' . $db->quoteName('#__eqa_secondattempts') .
            ' WHERE ' . $db->quoteName('payment_code') . ' IS NOT NULL'
        );
        $existingCodes = array_flip($db->loadColumn()); // dùng như Set để tra O(1)

        $rows = [];
        foreach ($newList as $entry) {
            $classId        = (int) $entry->class_id;
            $learnerId      = (int) $entry->learner_id;
            $lastExamId     = (int) $entry->last_exam_id;
            $lastAttempt    = (int) $entry->last_attempt;
            $lastConclusion = (int) $entry->last_conclusion;

            if ($lastConclusion === Conclusion::Postponed->value || $isFree) {
                // Thí sinh bảo lưu hoặc chế độ miễn phí: payment_amount = 0
                $rows[] = '(' .
                    $classId . ', ' .
                    $learnerId . ', ' .
                    $lastExamId . ', ' .
                    $lastAttempt . ', ' .
                    $lastConclusion . ', ' .
                    '0, ' .      // payment_amount = 0
                    'NULL, ' .   // payment_completed = NULL
                    'NULL' .     // payment_code = NULL
                    ')';
            } else {
                // Thí sinh không đạt: tính phí, sinh payment_code duy nhất
                $credits       = $creditMap[$lastExamId] ?? 0;
                $paymentAmount = $this->calculateFee($feeMode, $feeRate, $credits);

                $paymentCode  = $this->generateUniquePaymentCode($existingCodes);
                $existingCodes[$paymentCode] = true; // Thêm vào Set ngay để tránh trùng lặp nội bộ

                $rows[] = '(' .
                    $classId . ', ' .
                    $learnerId . ', ' .
                    $lastExamId . ', ' .
                    $lastAttempt . ', ' .
                    $lastConclusion . ', ' .
                    $paymentAmount . ', ' .               // payment_amount
                    '0, ' .                               // payment_completed = FALSE
                    $db->quote($paymentCode) .            // payment_code
                    ')';
            }
        }

        if (empty($rows)) {
            return 0;
        }

        $columns = $db->quoteName([
            'class_id', 'learner_id', 'last_exam_id', 'last_attempt',
            'last_conclusion', 'payment_amount', 'payment_completed', 'payment_code',
        ]);
        $sql = 'INSERT INTO ' . $db->quoteName('#__eqa_secondattempts') .
            ' (' . implode(', ', $columns) . ') VALUES ' .
            implode(', ', $rows);
        $db->setQuery($sql);
        $db->execute();

        return $db->getAffectedRows();
    }

    /**
     * Tải số tín chỉ của các môn học tương ứng với danh sách exam ID.
     *
     * Trả về map: exam_id → credits (int, 0 nếu không xác định được).
     *
     * @param  DatabaseDriver $db
     * @param  int[]          $examIds
     * @return array<int, int>
     */
    private function loadCreditsByExamIds(DatabaseDriver $db, array $examIds): array
    {
        if (empty($examIds)) {
            return [];
        }

        $idList = implode(',', array_map('intval', $examIds));

        $rows = $db->setQuery(
            'SELECT ex.' . $db->quoteName('id') . ' AS exam_id,' .
            ' COALESCE(su.' . $db->quoteName('credits') . ', 0) AS credits' .
            ' FROM ' . $db->quoteName('#__eqa_exams', 'ex') .
            ' LEFT JOIN ' . $db->quoteName('#__eqa_subjects', 'su') .
            ' ON su.id = ex.subject_id' .
            ' WHERE ex.id IN (' . $idList . ')'
        )->loadObjectList();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->exam_id] = (int) $row->credits;
        }

        return $map;
    }

    /**
     * Tính lệ phí thi lần hai theo fee mode và fee rate.
     *
     * @param  FeeMode $feeMode   Chế độ tính phí.
     * @param  float   $feeRate   Mức phí cơ bản (VNĐ/môn hoặc VNĐ/tín chỉ).
     * @param  int     $credits   Số tín chỉ của môn học.
     * @return float               Số tiền lệ phí (VNĐ).
     * @since  2.0.2
     */
    private function calculateFee(FeeMode $feeMode, float $feeRate, int $credits): float
    {
        return match ($feeMode) {
            FeeMode::Free      => 0.0,
            FeeMode::PerExam   => $feeRate,
            FeeMode::PerCredit => $feeRate * max(1, $credits),
        };
    }

    /**
     * Sinh một chuỗi ngẫu nhiên 8 ký tự [A-Z0-9] không trùng với các code đã tồn tại.
     *
     * @param array<string, mixed> $existingCodes  Map (flip) các code đã dùng.
     * @return string
     * @since 2.0.2
     */
    private function generateUniquePaymentCode(array $existingCodes): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length     = 8;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (isset($existingCodes[$code]));

        return $code;
    }

    // =========================================================================
    // Chức năng cập nhật trạng thái thanh toán
    // =========================================================================
	/**
	 * Lấy thông tin một bản ghi thi lần hai theo id, kèm thông tin người học
	 * và môn thi. Dùng cho layout 'setpayment'.
	 *
	 * @param  int  $id  ID bản ghi trong #__eqa_secondattempts.
	 * @return object
	 * @throws Exception  Nếu không tìm thấy bản ghi.
	 * @since 2.0.4
	 */
	public function getItemById(int $id): object
	{
		$db    = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('sa.id'),
				$db->quoteName('sa.payment_amount'),
				$db->quoteName('sa.payment_completed'),
				$db->quoteName('sa.description'),
				$db->quoteName('lr.code', 'learner_code'),
				$db->quoteName('lr.lastname', 'learner_lastname'),
				$db->quoteName('lr.firstname', 'learner_firstname'),
				$db->quoteName('su.code', 'subject_code'),
				$db->quoteName('su.name', 'subject_name'),
			])
			->from($db->quoteName('#__eqa_secondattempts', 'sa'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_exams', 'ex') .
				' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('sa.last_exam_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_subjects', 'su') .
				' ON ' . $db->quoteName('su.id') . ' = ' . $db->quoteName('ex.subject_id')
			)
			->where($db->quoteName('sa.id') . ' = ' . (int) $id);

		$db->setQuery($query);
		$record = $db->loadObject();

		if ($record === null) {
			throw new Exception('Không tìm thấy bản ghi thi lần hai có id = ' . $id);
		}

		return $record;
	}

	/**
	 * Cập nhật trạng thái nộp phí và mô tả cho một bản ghi thi lần hai.
	 *
	 * Logic:
	 *   - Kiểm tra bản ghi tồn tại và có yêu cầu đóng phí (payment_amount > 0).
	 *   - UPDATE payment_completed và description theo giá trị truyền vào.
	 *   - description = NULL nếu $description là null (đã được xử lý tại Controller).
	 *
	 * @param  int          $id                ID bản ghi trong #__eqa_secondattempts.
	 * @param  bool         $paymentCompleted  TRUE = Đã nộp phí; FALSE = Chưa nộp phí.
	 * @param  string|null  $description       Mô tả/ghi chú; NULL để xóa mô tả cũ.
	 * @return array{learnerCode: string, paymentCompleted: bool}
	 * @throws Exception  Nếu bản ghi không tồn tại hoặc không có yêu cầu đóng phí.
	 * @since 2.0.4
	 */
	public function savePaymentStatus(int $id, bool $paymentCompleted, ?string $description): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// Đọc bản ghi để validate và lấy learner_code cho thông báo
		$record = $this->getItemById($id);

		if ((float) $record->payment_amount <= 0) {
			throw new Exception(
				sprintf(
					'Trường hợp HVSV %s không yêu cầu đóng phí — không thể cập nhật trạng thái nộp phí.',
					$record->learner_code ?? ('id=' . $id)
				)
			);
		}

		// Thực hiện UPDATE
		$query = $db->getQuery(true)
			->update($db->quoteName('#__eqa_secondattempts'))
			->set($db->quoteName('payment_completed') . ' = ' . ($paymentCompleted ? 1 : 0))
			->set($db->quoteName('description') . ' = ' . ($description !== null ? $db->quote($description) : 'NULL'))
			->where($db->quoteName('id') . ' = ' . (int) $id);

		$db->setQuery($query);
		$db->execute();

		return [
			'learnerCode'      => $record->learner_code ?? ('id=' . $id),
			'paymentCompleted' => $paymentCompleted,
		];
	}

	// =========================================================================
	// Chức năng nhập bản sao kê ngân hàng
	// =========================================================================

	/**
	 * Đối chiếu bản sao kê ngân hàng MB Bank (file .xlsx) với dữ liệu thanh toán
	 * thi lần hai, tự động ghi nhận trạng thái "Đã nộp phí" cho những trường hợp
	 * hợp lệ.
	 *
	 * Thuật toán đối chiếu:
	 *   1. Parse toàn bộ dòng Credit > 0 từ file Excel (bỏ qua dòng Debit và tổng kết).
	 *   2. Load tất cả bản ghi DB có payment_amount > 0 và payment_code IS NOT NULL,
	 *      xây dựng map: payment_code → record.
	 *   3. Với mỗi dòng sao kê, tìm payment_code nào có trong chuỗi nội dung (INSTR).
	 *   4. Phân loại kết quả:
	 *      - Trùng code >1 lần trong toàn bộ sao kê → cảnh báo "thanh toán 2 lần".
	 *      - Số tiền credit ≠ payment_amount trong DB → lỗi sai số tiền, không cập nhật.
	 *      - Đã payment_completed = TRUE từ trước → bỏ qua (đã ghi nhận).
	 *      - Hợp lệ → UPDATE payment_completed = 1, description = nội dung CK.
	 *
	 * @param  string  $filePath  Đường dẫn tuyệt đối đến file .xlsx đã upload.
	 * @return array{
	 *     updated:          int,
	 *     alreadyPaid:      int,
	 *     notFound:         int,
	 *     amountMismatch:   array<array{payment_code: string, description: string, expected: float, actual: float}>,
	 *     duplicate:        array<array{payment_code: string, count: int, descriptions: string[]}>,
	 *     updatedCodes:     string[]
	 * }
	 * @throws Exception  Nếu file không đọc được hoặc format không đúng.
	 * @since 2.0.3
	 */
	public function importBankStatement(string $filePath, string $napasCode): array
	{
		// 1. Parse file theo ngân hàng được chọn
		$parser       = BankStatementHelper::getParser($napasCode);
		$transactions = $parser->parse($filePath);

		if (empty($transactions)) {
			throw new Exception(
				sprintf(
					'File sao kê %s không có giao dịch Credit nào hợp lệ. ' .
					'Vui lòng kiểm tra lại định dạng file.',
					$parser->getBankName()
				)
			);
		}

		// 2. Load bản ghi DB cần đối chiếu
		$db    = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('sa.id'),
				$db->quoteName('sa.payment_code'),
				$db->quoteName('sa.payment_amount'),
				$db->quoteName('sa.payment_completed'),
				$db->quoteName('lr.code',      'learner_code'),
				$db->quoteName('lr.lastname',  'learner_lastname'),
				$db->quoteName('lr.firstname', 'learner_firstname'),
			])
			->from($db->quoteName('#__eqa_secondattempts', 'sa'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id')
			)
			->where($db->quoteName('sa.payment_amount') . ' > 0')
			->where($db->quoteName('sa.payment_code')   . ' IS NOT NULL');
		$db->setQuery($query);
		$dbRecords = $db->loadObjectList();

		// 3. Đối chiếu — thuật toán dùng chung từ BankStatementHelper
		$reconciled = BankStatementHelper::reconcile($transactions, $dbRecords);

		// 4. UPDATE bản ghi hợp lệ
		$updatedCodes = [];
		foreach ($reconciled['matched'] as $pair) {
			$rec  = $pair['record'];
			$tx   = $pair['transaction'];
			$db->setQuery(
				'UPDATE ' . $db->quoteName('#__eqa_secondattempts') .
				' SET ' .
				$db->quoteName('payment_completed') . ' = 1, ' .
				$db->quoteName('description') . ' = ' . $db->quote($tx['description']) .
				' WHERE ' . $db->quoteName('id') . ' = ' . (int) $rec->id
			);
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

	public function loadListForExport(bool $onlyFreeOrPaymentCompleted): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.learner_id')          . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('c.code')                . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('c.lastname')            . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('c.firstname')           . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('e.id')                  . ' AS ' . $db->quoteName('subjectId'),
			$db->quoteName('e.code')                . ' AS ' . $db->quoteName('subjectCode'),
			$db->quoteName('e.name')                . ' AS ' . $db->quoteName('subjectName'),
			$db->quoteName('e.finaltesttype')       . ' AS ' . $db->quoteName('testType'),
			$db->quoteName('e.finaltestduration')   . ' AS ' . $db->quoteName('testDuration'),
			$db->quoteName('d.term')                . ' AS ' . $db->quoteName('term'),
			$db->quoteName('d.academicyear')                . ' AS ' . $db->quoteName('academicyear'),
			$db->quoteName('a.last_exam_id')             . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('a.class_id')            . ' AS ' . $db->quoteName('classId'),
			$db->quoteName('b.ntaken')              . ' AS ' . $db->quoteName('ntaken'),
			$db->quoteName('a.last_conclusion')          . ' AS ' . $db->quoteName('conclusion'),
		];

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_secondattempts AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->leftJoin('#__eqa_classes AS d', 'd.id=a.class_id')
			->leftJoin('#__eqa_subjects AS e', 'e.id=d.subject_id');
		if($onlyFreeOrPaymentCompleted) {
			$query->where('(a.payment_amount = 0 OR a.payment_completed = 1)');
		}

		$db->setQuery($query);
		return $db->loadObjectList();
	}

}
