<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
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
                'sa.last_conclusion',
                'sa.payment_amount',
                'sa.payment_completed',
                'sa.payment_code',
                'lr.code',
                'lr.lastname',
                'lr.firstname',
                'su.code',
                'su.name',
                'ay.code',
                'cl.term',
            ],
            [
                'id',
                'class_id',
                'learner_id',
                'last_exam_id',
                'last_attempt',
                'last_conclusion',
                'payment_amount',
                'payment_completed',
                'payment_code',
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
                $db->quoteName('#__eqa_academicyears', 'ay') .
                ' ON ' . $db->quoteName('ay.id') . ' = ' . $db->quoteName('cl.academicyear_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_exams', 'ex') .
                ' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('sa.last_exam_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_subjects', 'su') .
                ' ON ' . $db->quoteName('su.id') . ' = ' . $db->quoteName('ex.subject_id')
            );

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

        $academicyearId = $this->getState('filter.academicyear_id');
        if (is_numeric($academicyearId)) {
            $query->where($db->quoteName('cl.academicyear_id') . ' = ' . (int) $academicyearId);
        }

        $term = $this->getState('filter.term');
        if (is_numeric($term)) {
            $query->where($db->quoteName('cl.term') . ' = ' . (int) $term);
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
            Conclusion::Failed->value,
            Conclusion::Deferred->value,
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
     * Xóa khỏi #__eqa_secondattempts các bản ghi lỗi thời.
     *
     * Một bản ghi bị coi là lỗi thời khi:
     *   (a) Cặp (class_id, learner_id) không còn trong $newList, HOẶC
     *   (b) Cặp (class_id, learner_id) vẫn còn trong $newList nhưng last_exam_id
     *       trong DB nhỏ hơn last_exam_id trong $newList (tức là đã có kỳ thi mới hơn).
     *
     * @param DatabaseDriver $db
     * @param array<string, object>           $newList  Kết quả từ buildNewList().
     * @return array{0: int, 1: array<string, true>}
     *         Phần tử 0: số bản ghi đã xóa.
     *         Phần tử 1: map các key "class_id:learner_id:last_exam_id" còn tồn tại
     *                    sau khi xóa (dùng cho Bước 3).
     * @throws Exception
     * @since 2.0.2
     */
    private function removeStaleRecords(DatabaseDriver $db, array $newList): array
    {
        // Đọc toàn bộ bảng hiện tại (chỉ 3 cột cần thiết để so sánh)
        $db->setQuery(
            'SELECT ' . $db->quoteName('id') . ', ' .
            $db->quoteName('class_id') . ', ' .
            $db->quoteName('learner_id') . ', ' .
            $db->quoteName('last_exam_id') .
            ' FROM ' . $db->quoteName('#__eqa_secondattempts')
        );
        $currentRecords = $db->loadObjectList();

        $staleIds     = [];
        $survivingKeys = [];

        foreach ($currentRecords as $record) {
            $key = $record->class_id . ':' . $record->learner_id . ':' . $record->last_exam_id;

            if (!isset($newList[$key])) {
                // Không còn trong danh sách mới → lỗi thời
                $staleIds[] = (int) $record->id;
            } else {
                // Vẫn còn hợp lệ → ghi lại để bước 3 bỏ qua
                $survivingKeys[$key] = true;
            }
        }

        if (!empty($staleIds)) {
            $idList = implode(',', $staleIds);
            $db->setQuery(
                'DELETE FROM ' . $db->quoteName('#__eqa_secondattempts') .
                ' WHERE ' . $db->quoteName('id') . ' IN (' . $idList . ')'
            );
            $db->execute();
        }

        return [count($staleIds), $survivingKeys];
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

            if ($lastConclusion === Conclusion::Deferred->value || $isFree) {
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
     * Đánh dấu "Đã nộp phí" cho các bản ghi được chọn.
     *
     * Chỉ áp dụng với các bản ghi có payment_amount > 0 và payment_completed = FALSE.
     * Các bản ghi khác trong danh sách $ids bị bỏ qua (không phát sinh lỗi).
     *
     * @param  int[]  $ids  Danh sách id bản ghi trong #__eqa_secondattempts.
     * @return array{
     *     changed: int,
     *     skipped: int,
     *     changedLearnerCodes: string[]
     * }
     * @throws Exception
     * @since 2.0.2
     */
    public function setPaymentCompleted(array $ids): array
    {
        return $this->updatePaymentStatus($ids, true);
    }

    /**
     * Đánh dấu "Chưa nộp phí" cho các bản ghi được chọn.
     *
     * Chỉ áp dụng với các bản ghi có payment_amount > 0 và payment_completed = TRUE.
     * Các bản ghi khác trong danh sách $ids bị bỏ qua (không phát sinh lỗi).
     *
     * @param  int[]  $ids  Danh sách id bản ghi trong #__eqa_secondattempts.
     * @return array{
     *     changed: int,
     *     skipped: int,
     *     changedLearnerCodes: string[]
     * }
     * @throws Exception
     * @since 2.0.2
     */
    public function setPaymentIncomplete(array $ids): array
    {
        return $this->updatePaymentStatus($ids, false);
    }

    /**
     * Cập nhật trạng thái payment_completed cho danh sách bản ghi.
     *
     * Logic:
     *   - Đọc toàn bộ các bản ghi có id trong $ids kèm learner code.
     *   - Với mỗi bản ghi, kiểm tra điều kiện áp dụng:
     *       payment_amount > 0  VÀ  payment_completed ≠ $targetValue
     *   - Những bản ghi đủ điều kiện → UPDATE, thu thập learner_code.
     *   - Những bản ghi không đủ điều kiện → đếm vào $skipped.
     *
     * @param  int[]  $ids          Danh sách id.
     * @param  bool   $targetValue  TRUE = Đã nộp phí; FALSE = Chưa nộp phí.
     * @return array{changed: int, skipped: int, changedLearnerCodes: string[]}
     * @throws Exception
     * @since 2.0.2
     */
    private function updatePaymentStatus(array $ids, bool $targetValue): array
    {
        if (empty($ids)) {
            return ['changed' => 0, 'skipped' => 0, 'changedLearnerCodes' => []];
        }

        $db = DatabaseHelper::getDatabaseDriver();

        // Đọc thông tin cần thiết của các bản ghi được chọn, kèm learner code
        $idList = implode(',', array_map('intval', $ids));
        $query  = $db->getQuery(true)
            ->select([
                $db->quoteName('sa.id'),
                $db->quoteName('sa.payment_amount'),
                $db->quoteName('sa.payment_completed'),
                $db->quoteName('lr.code', 'learner_code'),
            ])
            ->from($db->quoteName('#__eqa_secondattempts', 'sa'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id')
            )
            ->where($db->quoteName('sa.id') . ' IN (' . $idList . ')');
        $db->setQuery($query);
        $records = $db->loadObjectList();

        $eligibleIds         = [];
        $changedLearnerCodes = [];
        $skipped             = 0;

        foreach ($records as $record) {
            // Điều kiện áp dụng: có phí VÀ trạng thái chưa ở giá trị đích
            if ((float) $record->payment_amount > 0 && (bool) $record->payment_completed !== $targetValue) {
                $eligibleIds[]         = (int) $record->id;
                $changedLearnerCodes[] = $record->learner_code ?? '';
            } else {
                $skipped++;
            }
        }

        if (!empty($eligibleIds)) {
            $eligibleIdList = implode(',', $eligibleIds);
            $newValue       = $targetValue ? 1 : 0;
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__eqa_secondattempts') .
                ' SET ' . $db->quoteName('payment_completed') . ' = ' . $newValue .
                ' WHERE ' . $db->quoteName('id') . ' IN (' . $eligibleIdList . ')'
            );
            $db->execute();
        }

        return [
            'changed'             => count($eligibleIds),
            'skipped'             => $skipped,
            'changedLearnerCodes' => $changedLearnerCodes,
        ];
    }
}
