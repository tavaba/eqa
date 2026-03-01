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
        $config['filter_fields']=array('id');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true)
            ->from('#__eqa_secondattempts')
            ->select('*');
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
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
}