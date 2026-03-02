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

class SecondAttemptsModel extends ListModel{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = [
			'id', 'learner_code', 'academicyear', 'term',
			'payment_required', 'payment_completed',
		];
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'id', $direction = 'desc'): void
	{
		parent::populateState($ordering, $direction);
	}

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
	 * @since 2.0.1
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
				'sa.payment_required',
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
				'payment_required',
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

		$paymentRequired = $this->getState('filter.payment_required');
		if (is_numeric($paymentRequired)) {
			$query->where($db->quoteName('sa.payment_required') . ' = ' . (int) $paymentRequired);
		}

		$paymentCompleted = $this->getState('filter.payment_completed');
		if (is_numeric($paymentCompleted)) {
			// filter này chỉ có nghĩa với các bản ghi có payment_required = TRUE
			$query->where($db->quoteName('sa.payment_required') . ' = 1');
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
		$id .= ':' . $this->getState('filter.payment_required');
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
	 * @return object{totalLearners:int, totalAttempts:int, totalFree:int, totalRequired:int, totalPaid:int}
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
				'SUM(CASE WHEN ' . $db->quoteName('payment_required') . ' = 0 THEN 1 ELSE 0 END)' .
				' AS ' . $db->quoteName('totalFree'),
				'SUM(CASE WHEN ' . $db->quoteName('payment_required') . ' = 1 THEN 1 ELSE 0 END)' .
				' AS ' . $db->quoteName('totalRequired'),
				'SUM(CASE WHEN ' . $db->quoteName('payment_required') . ' = 1' .
				' AND ' . $db->quoteName('payment_completed') . ' = 1 THEN 1 ELSE 0 END)' .
				' AS ' . $db->quoteName('totalPaid'),
			])
			->from($db->quoteName('#__eqa_secondattempts'));

		$db->setQuery($query);
		$result = $db->loadObject();

		// Đảm bảo luôn là int (bảng rỗng → loadObject() trả về null trong từng cột)
		$result->totalLearners = (int) $result->totalLearners;
		$result->totalAttempts = (int) $result->totalAttempts;
		$result->totalFree     = (int) $result->totalFree;
		$result->totalRequired = (int) $result->totalRequired;
		$result->totalPaid     = (int) $result->totalPaid;

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
	 *   3. Loại khỏi $newList những trường hợp đã tồn tại hợp lệ trong DB (sau Bước 2).
	 *   4. Ghi bổ sung các trường hợp còn lại vào DB, kèm thông tin thanh toán.
	 *
	 * @return array{removed: int, added: int} Số bản ghi đã xóa và đã thêm.
	 * @throws Exception Khi có lỗi truy vấn CSDL.
	 * @since 2.0.2
	 */
	public function refresh(): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$db->transactionStart();

		try {
			// -----------------------------------------------------------------
			// Bước 1: Xây dựng $newList
			// -----------------------------------------------------------------
			$newList = $this->buildNewList($db);

			// -----------------------------------------------------------------
			// Bước 2: Xóa bản ghi lỗi thời khỏi DB; đồng thời xác định tập
			//         bản ghi còn lại (dùng cho Bước 3) ngay trên bộ nhớ,
			//         tránh phải query lại DB.
			// -----------------------------------------------------------------
			[$removedCount, $survivingKeys] = $this->removeStaleRecords($db, $newList);

			// -----------------------------------------------------------------
			// Bước 3: Loại khỏi $newList những trường hợp đã tồn tại hợp lệ
			// -----------------------------------------------------------------
			foreach ($survivingKeys as $tripleKey => $_) {
				// $tripleKey có dạng "class_id:learner_id:last_exam_id"
				unset($newList[$tripleKey]);
			}

			// -----------------------------------------------------------------
			// Bước 4: Ghi bổ sung các trường hợp còn lại
			// -----------------------------------------------------------------
			$addedCount = $this->insertNewRecords($db, $newList);

			$db->transactionCommit();

		} catch (Exception $e) {
			$db->transactionRollback();
			throw $e;
		}

		return ['removed' => $removedCount, 'added' => $addedCount];
	}

	// =========================================================================
	// Các phương thức private hỗ trợ
	// =========================================================================

	/**
	 * Xây dựng danh sách mới (newList) từ dữ liệu trong các bảng class_learner
	 * và exam_learner.
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
		// Đọc toàn bộ bảng hiện tại (chỉ cần 4 cột)
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('class_id'),
				$db->quoteName('learner_id'),
				$db->quoteName('last_exam_id'),
			])
			->from($db->quoteName('#__eqa_secondattempts'));
		$db->setQuery($query);
		$existing = $db->loadObjectList();

		$idsToDelete  = [];
		$survivingKeys = [];

		foreach ($existing as $record) {
			// Xây dựng lookup key theo (class_id, learner_id) để kiểm tra sự tồn tại
			$pairKey  = $record->class_id . ':' . $record->learner_id;
			$tripleKey = $pairKey . ':' . $record->last_exam_id;

			// Tìm bất kỳ entry nào trong $newList khớp với (class_id, learner_id)
			$matchingNewEntry = $this->findByPairKey($newList, $pairKey);

			if ($matchingNewEntry === null) {
				// Trường hợp (a): cặp (class_id, learner_id) không còn trong newList
				$idsToDelete[] = (int) $record->id;
			} elseif ($record->last_exam_id < $matchingNewEntry->last_exam_id) {
				// Trường hợp (b): đã có kỳ thi gần hơn trong newList
				$idsToDelete[] = (int) $record->id;
			} else {
				// Bản ghi hợp lệ, giữ lại
				$survivingKeys[$tripleKey] = true;
			}
		}

		// Thực hiện xóa một lần duy nhất
		if (!empty($idsToDelete)) {
			$idList = implode(',', $idsToDelete);
			$db->setQuery(
				'DELETE FROM ' . $db->quoteName('#__eqa_secondattempts') .
				' WHERE ' . $db->quoteName('id') . ' IN (' . $idList . ')'
			);
			$db->execute();
		}

		return [count($idsToDelete), $survivingKeys];
	}

	/**
	 * Tìm entry trong $newList theo cặp (class_id, learner_id).
	 *
	 * Vì $newList được đánh index theo key "class_id:learner_id:last_exam_id",
	 * không thể tra cứu trực tiếp bằng pairKey; phương thức này duyệt qua $newList
	 * và trả về entry đầu tiên khớp. Trong thực tế, mỗi cặp (class_id, learner_id)
	 * chỉ xuất hiện một lần trong $newList (do điều kiện exam_id = MAX).
	 *
	 * @param array<string, object> $newList
	 * @param string                $pairKey  Dạng "class_id:learner_id"
	 * @return object|null
	 * @since 2.0.2
	 */
	private function findByPairKey(array $newList, string $pairKey): ?object
	{
		foreach ($newList as $key => $entry) {
			// Key có dạng "class_id:learner_id:last_exam_id"
			// Lấy phần "class_id:learner_id" bằng cách bỏ phần sau dấu ':' cuối
			$entryPairKey = substr($key, 0, strrpos($key, ':'));
			if ($entryPairKey === $pairKey) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Ghi bổ sung các bản ghi trong $newList vào #__eqa_secondattempts.
	 *
	 * Quy tắc gán thông tin thanh toán:
	 *   - last_conclusion = Deferred  → payment_required = FALSE (không cần đóng phí)
	 *   - last_conclusion = Failed    → payment_required = TRUE, payment_completed = FALSE,
	 *                                   payment_code = chuỗi ngẫu nhiên 8 ký tự [A-Z0-9] (duy nhất)
	 *
	 * @param DatabaseDriver $db
	 * @param array<string, object>           $newList  Danh sách bản ghi cần thêm.
	 * @return int Số bản ghi đã thêm.
	 * @throws Exception
	 * @since 2.0.2
	 */
	private function insertNewRecords(DatabaseDriver $db, array $newList): int
	{
		if (empty($newList)) {
			return 0;
		}

		//Kiểm tra chế độ tính phí
		$feeMode = ConfigHelper::getSecondAttemptFeeMode();
		$isFree = $feeMode == FeeMode::Free;

		// Load tập payment_code đang tồn tại để đảm bảo unique khi sinh mới
		$db->setQuery(
			'SELECT ' . $db->quoteName('payment_code') .
			' FROM ' . $db->quoteName('#__eqa_secondattempts') .
			' WHERE ' . $db->quoteName('payment_code') . ' IS NOT NULL'
		);
		$existingCodes = array_flip($db->loadColumn()); // dùng như Set để tra O(1)

		$rows = [];
		foreach ($newList as $entry) {
			$classId      = (int) $entry->class_id;
			$learnerId    = (int) $entry->learner_id;
			$lastExamId   = (int) $entry->last_exam_id;
			$lastAttempt  = (int) $entry->last_attempt;
			$lastConclusion = (int) $entry->last_conclusion;

			if ($lastConclusion === Conclusion::Deferred->value || $isFree) {
				// Thí sinh bảo lưu: không cần đóng phí
				$rows[] = '(' .
					$classId . ', ' .
					$learnerId . ', ' .
					$lastExamId . ', ' .
					$lastAttempt . ', ' .
					$lastConclusion . ', ' .
					'0, ' .         // payment_required = FALSE
					'NULL, ' .      // payment_completed = NULL
					'NULL' .        // payment_code = NULL
					')';
			} else {
				// Thí sinh không đạt: phải đóng phí, sinh payment_code duy nhất
				$paymentCode = $this->generateUniquePaymentCode($existingCodes);
				$existingCodes[$paymentCode] = true; // Thêm vào Set ngay để tránh trùng lặp nội bộ

				$rows[] = '(' .
					$classId . ', ' .
					$learnerId . ', ' .
					$lastExamId . ', ' .
					$lastAttempt . ', ' .
					$lastConclusion . ', ' .
					'1, ' .                           // payment_required = TRUE
					'0, ' .                           // payment_completed = FALSE
					$db->quote($paymentCode) .        // payment_code
					')';
			}
		}

		if (empty($rows)) {
			return 0;
		}

		$columns = $db->quoteName([
			'class_id', 'learner_id', 'last_exam_id', 'last_attempt',
			'last_conclusion', 'payment_required', 'payment_completed', 'payment_code',
		]);
		$sql = 'INSERT INTO ' . $db->quoteName('#__eqa_secondattempts') .
			' (' . implode(', ', $columns) . ') VALUES ' .
			implode(', ', $rows);
		$db->setQuery($sql);
		$db->execute();

		return $db->getAffectedRows();
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
	 * Chỉ áp dụng với các bản ghi có payment_required = TRUE và payment_completed = FALSE.
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
	 * Chỉ áp dụng với các bản ghi có payment_required = TRUE và payment_completed = TRUE.
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
	 *       payment_required = TRUE  VÀ  payment_completed ≠ $targetValue
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
				$db->quoteName('sa.payment_required'),
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

		$eligibleIds        = [];
		$changedLearnerCodes = [];
		$skipped            = 0;

		foreach ($records as $record) {
			// Điều kiện áp dụng: phải đóng phí VÀ trạng thái chưa ở giá trị đích
			if ($record->payment_required && (bool) $record->payment_completed !== $targetValue) {
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