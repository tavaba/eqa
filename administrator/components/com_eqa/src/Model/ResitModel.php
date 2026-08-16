<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

require_once JPATH_ROOT . '/vendor/autoload.php';

use Exception;
use Joomla\Database\DatabaseDriver;
use Kma\Component\Eqa\Administrator\Base\CampusAdminModel;
use Kma\Component\Eqa\Administrator\Enum\Action;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Enum\FeeMode;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Library\Kma\BankStatement\BankStatementImportResult;
use Kma\Library\Kma\DataObject\LogEntry;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * Item model của 'đợt thi lại' (resit) — ĐỒNG THỜI là nơi chứa toàn bộ nghiệp vụ
 * trên bảng thí sinh của đợt (#__eqa_resit_learner).
 *
 * Cách tổ chức này theo đúng khuôn mẫu đã có của com_eqa: bảng junction
 * #__eqa_exam_learner không có Table/Model riêng mà được ExamModel quản lý, còn
 * ExamExamineesModel chỉ lo phần hiển thị danh sách.
 *
 * Mỗi kỳ thi lần 2 làm việc với một đợt riêng. Mỗi cơ sở đào tạo có tối đa MỘT
 * đợt đang kích hoạt (active = 1) tại một thời điểm; đợt đó là nơi thí sinh đăng
 * ký thi lại và là nguồn dữ liệu cho việc sinh môn thi lần 2 cũng như rà soát
 * nộp phí. CHỈ đợt đang kích hoạt mới được phép thay đổi danh sách thí sinh.
 *
 * Toàn bộ logic chốt chặn quyền theo cơ sở đào tạo nằm ở CampusAdminModel.
 *
 * @since 2.1.8
 */
class ResitModel extends CampusAdminModel
{
	/**
	 * @inheritDoc
	 * Override để xử lý 2 trường ngày giờ
	 */
	protected function prepareTable($table)
	{
		if(empty($table->payment_open_from))
			$table->payment_open_from = null;
		if(empty($table->payment_open_to))
			$table->payment_open_to = null;
		if(empty($table->last_statement_update))
			$table->last_statement_update = null;
		parent::prepareTable($table);
	}


	/**
     * Cơ sở đào tạo của một đợt, đọc trực tiếp từ CSDL.
     *
     * @param   int  $recordId
     *
     * @return  int
     * @since   2.1.8
     */
    protected function getCampusIdOfRecord(int $recordId): int
    {
        return $this->getStoredCampusId('#__eqa_resits', $recordId);
    }

    // =========================================================================
    // Chốt chặn truy cập
    // =========================================================================

    /**
     * Khẳng định người dùng được phép LÀM VIỆC với đợt này, và trả về bản ghi đợt.
     *
     * Bản ghi có thể được truy cập bằng URL trực tiếp nên không thể chỉ dựa vào
     * bộ lọc hiển thị của danh sách: mọi thao tác đọc/ghi đều phải đi qua đây.
     *
     * @param   int  $resitId
     *
     * @return  object  Bản ghi đợt thi lại.
     * @throws  Exception  Nếu không tìm thấy hoặc không đủ quyền.
     * @since   2.1.8
     */
    public function assertAccessible(int $resitId): object
    {
        $resit = $this->getResitRecord($resitId);

        if (!$this->getCampusService()->canManageCampus((int) $resit->campus_id)) {
            throw new Exception(
                'Đợt thi lại này thuộc cơ sở đào tạo khác. Bạn không có quyền truy cập.'
            );
        }

        return $resit;
    }

    /**
     * Khẳng định đợt này được phép THAY ĐỔI danh sách thí sinh.
     *
     * Chỉ đợt đang kích hoạt mới cho phép bổ sung/làm mới thí sinh, nhập sao kê
     * hay đổi trạng thái nộp phí — dữ liệu của các đợt đã qua phải được giữ
     * nguyên để tra cứu. Chốt chặn này là bắt buộc ở tầng model vì các nút trên
     * giao diện chỉ được ẩn đi, request vẫn có thể được gửi trực tiếp.
     *
     * @param   int  $resitId
     *
     * @return  object  Bản ghi đợt thi lại.
     * @throws  Exception  Nếu đợt không tồn tại, không đủ quyền, hoặc không kích hoạt.
     * @since   2.1.8
     */
    public function assertModifiable(int $resitId): object
    {
        $resit = $this->assertAccessible($resitId);

        if (empty($resit->active)) {
            throw new Exception(sprintf(
                'Đợt thi lại "%s" không phải là đợt đang kích hoạt của cơ sở đào tạo nên'
                . ' không thể thay đổi danh sách thí sinh. Hãy kích hoạt đợt này trước.',
                $resit->name
            ));
        }

        if ((int) $resit->state !== StateHelper::STATE_PUBLISHED) {
            throw new Exception(sprintf(
                'Đợt thi lại "%s" đang ở trạng thái tạm ngừng nên không thể thay đổi danh sách'
                . ' thí sinh. Hãy chuyển đợt về trạng thái "Đang dùng" trước.',
                $resit->name
            ));
        }

        return $resit;
    }

    // =========================================================================
    // Tạo mới / cập nhật
    // =========================================================================

    /**
     * Lưu danh sách.
     *
     * Danh sách VỪA ĐƯỢC TẠO luôn trở thành danh sách đang kích hoạt của cơ sở
     * đào tạo tương ứng (quy trình nghiệp vụ: trước mỗi kỳ thi lần 2 lập một
     * danh sách mới rồi làm việc trên đó); cờ kích hoạt của các danh sách khác
     * cùng cơ sở được gỡ bỏ.
     *
     * Cờ 'active' KHÔNG có trên form: nó do hệ thống quản lý, đổi bằng tác vụ
     * 'Kích hoạt' ở danh sách các danh sách.
     *
     * @param   array  $data
     *
     * @return  bool
     * @since   2.1.8
     */
    public function save($data): bool
    {
        $isNew = empty($data['id']);

        if ($isNew) {
            $data['active'] = 1;
        } else {
            // Giữ nguyên giá trị đang có trong CSDL, không cho form ghi đè
            unset($data['active']);
        }

        $result = parent::save($data);

        if ($result && $isNew) {
            $newId = $this->getInsertId();

            if ($newId > 0) {
                $this->clearActiveFlagOfOtherResits($newId, $this->getCampusIdOfRecord($newId));
            }
        }

        return $result;
    }

    // =========================================================================
    // Kích hoạt
    // =========================================================================

    /**
     * Kích hoạt một danh sách: đặt active = 1 cho danh sách này và gỡ cờ kích
     * hoạt của mọi danh sách khác CÙNG CƠ SỞ ĐÀO TẠO.
     *
     * @param   int  $id  Mã danh sách.
     *
     * @return  string  Tên danh sách đã được kích hoạt (dùng cho thông báo).
     * @throws  Exception  Nếu không tìm thấy danh sách hoặc không đủ quyền.
     * @since   2.1.8
     */
    public function activate(int $id): string
    {
        $record = $this->getResitRecord($id);

        // Chốt chặn quyền theo cơ sở đào tạo: bản ghi có thể được truy cập bằng
        // URL trực tiếp nên không thể dựa vào bộ lọc của danh sách.
        $this->assertCanManageCampus((int) $record->campus_id);

        // Đợt tạm ngừng không được kích hoạt: kích hoạt xong vẫn không thay đổi
        // được danh sách thí sinh (xem assertModifiable) nên chỉ tạo ra một
        // trạng thái bế tắc khó hiểu cho người dùng.
        if ((int) $record->state !== StateHelper::STATE_PUBLISHED) {
            throw new Exception(sprintf(
                'Không thể kích hoạt đợt thi lại "%s" vì đợt đang ở trạng thái tạm ngừng.'
                . ' Hãy chuyển đợt về trạng thái "Đang dùng" trước.',
                $record->name
            ));
        }

        if ((int) $record->active === 1) {
            return $record->name;
        }

        $db = DatabaseHelper::getDatabaseDriver();
        $db->transactionStart();

        try {
            $this->clearActiveFlagOfOtherResits($id, (int) $record->campus_id);

            $query = $db->getQuery(true)
                ->update('#__eqa_resits')
                ->set('active = 1')
                ->where('id = ' . $id);
            $db->setQuery($query);
            $db->execute();

            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();

            throw $e;
        }

        return $record->name;
    }

    /**
     * Gỡ cờ kích hoạt của mọi danh sách thuộc cùng cơ sở đào tạo, trừ danh sách
     * được chỉ định.
     *
     * @param   int  $keepId    Mã danh sách được giữ nguyên.
     * @param   int  $campusId  Mã cơ sở đào tạo.
     *
     * @return  void
     * @since   2.1.8
     */
    private function clearActiveFlagOfOtherResits(int $keepId, int $campusId): void
    {
        if ($campusId <= 0) {
            return;
        }

        $db    = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->update('#__eqa_resits')
            ->set('active = 0')
            ->where('campus_id = ' . $campusId)
            ->where('id <> ' . $keepId)
            ->where('active = 1');

        $db->setQuery($query);
        $db->execute();
    }

    // =========================================================================
    // Xóa
    // =========================================================================

    /**
     * Xóa danh sách kèm toàn bộ thí sinh thuộc danh sách đó.
     *
     * Quy tắc nghiệp vụ:
     *   - KHÔNG cho xóa danh sách đang kích hoạt (phải kích hoạt danh sách khác
     *     trước), vì đó là danh sách mà thí sinh đang đăng ký thi lại vào.
     *   - Bản ghi thí sinh (#__eqa_resit_learner) có khóa ngoại RESTRICT trỏ
     *     tới danh sách nên phải được xóa trước, trong cùng một transaction.
     *
     * Việc ghi log do lớp cha đảm nhiệm (một bản ghi cho mỗi danh sách bị xóa,
     * kèm snapshot dữ liệu cũ). Trường hợp bị từ chối được ghi log tại đây.
     *
     * @param   array|int  $pks
     *
     * @return  bool
     * @since   2.1.8
     */
    public function delete(&$pks): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $pks))));

        if (empty($ids)) {
            $this->setError('Không có danh sách nào được chọn.');

            return false;
        }

        // 1. Không cho xóa danh sách đang kích hoạt
        $activeNames = $this->getActiveResitNames($ids);

        if (!empty($activeNames)) {
            $message = sprintf(
                'Không thể xóa danh sách đang kích hoạt: <b>%s</b>.'
                . ' Hãy kích hoạt một danh sách khác trước khi xóa.',
                implode('</b>, <b>', array_map('htmlspecialchars', $activeNames))
            );
            $this->setError($message);

            $this->writeLog(new LogEntry(
                action: Action::DELETE,
                objectType: ObjectType::Resit->value,
                isSuccess: false,
                errorMessage: $message,
                extraData: ['resit_ids' => $ids],
            ));

            return false;
        }

        // 2. Xóa thí sinh của các danh sách rồi xóa chính các danh sách đó
        $db = DatabaseHelper::getDatabaseDriver();
        $db->transactionStart();

        try {
            $query = $db->getQuery(true)
                ->delete('#__eqa_resit_learner')
                ->where('resit_id IN (' . implode(',', $ids) . ')');
            $db->setQuery($query);
            $db->execute();

            if (!parent::delete($pks)) {
                throw new Exception($this->getError() ?: 'Không xóa được danh sách thi lần 2.');
            }

            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            $this->setError($e->getMessage());

            return false;
        }

        return true;
    }

    // =========================================================================
    // Truy vấn hỗ trợ
    // =========================================================================

    /**
     * Đọc toàn bộ thông tin của một đợt thi lại, kể cả các tham số thu phí dùng
     * cho cổng thông tin người học.
     *
     * @param   int  $id
     *
     * @return  object
     * @throws  Exception  Nếu không tìm thấy.
     * @since   2.1.8
     */
    public function getResitRecord(int $id): object
    {
        $db    = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__eqa_resits')
            ->where('id = ' . $id);

        $db->setQuery($query);
        $record = $db->loadObject();

        if ($record === null) {
            throw new Exception('Không tìm thấy đợt thi lại có id = ' . $id);
        }

        return $record;
    }

    /**
     * Tên của những danh sách ĐANG KÍCH HOẠT trong tập id được chỉ định.
     *
     * @param   int[]  $ids
     *
     * @return  string[]
     * @since   2.1.8
     */
    private function getActiveResitNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $db    = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('name')
            ->from('#__eqa_resits')
            ->where('id IN (' . implode(',', array_map('intval', $ids)) . ')')
            ->where('active = 1');

        $db->setQuery($query);

        return $db->loadColumn() ?: [];
    }

    // =========================================================================
    // Thống kê
    // =========================================================================

    /**
     * Trả về số liệu thống kê tổng hợp của DANH SÁCH đang mở.
     *
     * Không phụ thuộc các bộ lọc khác trên giao diện, nhưng TỪ 2.1.8 chỉ tính
     * trong phạm vi một danh sách thi lần 2 — nếu không, số liệu sẽ gộp mọi
     * danh sách và không khớp với danh sách hiển thị bên dưới.
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
    public function getStatistics(int $resitId): object
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $query = $db->getQuery(true)
            ->select([
	            'COUNT(DISTINCT ' . $db->quoteName('sa.last_exam_id') . ')' .
	            ' AS ' . $db->quoteName('totalExams'),
	            'COUNT(DISTINCT ' . $db->quoteName('sa.learner_id') . ')' .
	            ' AS ' . $db->quoteName('totalLearners'),
                'COUNT(1)' .
                ' AS ' . $db->quoteName('totalAttempts'),
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' = 0 THEN 1 ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalFree'),
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0 THEN 1 ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalRequired'),
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0' .
                ' AND ' . $db->quoteName('sa.payment_completed') . ' = 1 THEN 1 ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalPaid'),
                // Tổng phí cần thu: cộng toàn bộ payment_amount > 0
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0' .
                ' THEN ' . $db->quoteName('sa.payment_amount') . ' ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalFeeAmount'),
                // Tổng đã thu: cộng payment_amount của các trường hợp đã thanh toán
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0' .
                ' AND ' . $db->quoteName('sa.payment_completed') . ' = 1' .
                ' THEN ' . $db->quoteName('sa.payment_amount') . ' ELSE 0 END)' .
                ' AS ' . $db->quoteName('totalCollectedAmount'),
            ])
            ->from($db->quoteName('#__eqa_resit_learner', 'sa'))
            ->innerJoin(
                $db->quoteName('#__eqa_classes', 'cl') .
                ' ON ' . $db->quoteName('cl.id') . ' = ' . $db->quoteName('sa.class_id')
            )
            // Giới hạn theo đợt thi lại đang mở (2.1.8). Phạm vi cơ sở đào tạo đã
            // được bảo đảm bởi assertAccessible() mà caller gọi trước đó.
            ->where($db->quoteName('sa.resit_id') . ' = ' . $resitId);

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
    // Chức năng "Thêm thí sinh"
    // =========================================================================

	/**
	 * Bổ sung các trường hợp đủ điều kiện thi lần 2 vào ĐỢT đang mở; KHÔNG xóa
	 * bất cứ bản ghi nào đang có.
	 *
	 * Thuật toán:
	 *   1. Xây dựng $newList các trường hợp đủ điều kiện từ dữ liệu hiện tại.
	 *   2. Load các triple key (class_id:learner_id:last_exam_id) đang có TRONG
	 *      ĐỢT NÀY.
	 *   3. Loại khỏi $newList những entry đã có → chỉ còn các entry thực sự mới.
	 *   4. Insert phần còn lại.
	 *
	 * LƯU Ý (2.1.8): đây là chức năng DUY NHẤT sinh dữ liệu tự động cho đợt.
	 * Chức năng "Làm mới" trước đây (dựng lại toàn bộ đợt và xóa các bản ghi bị
	 * coi là lỗi thời) đã bị BỎ HẲN vì nó xóa mất cả những thí sinh đã dự thi
	 * khỏi đợt. Muốn loại bỏ thí sinh, quản trị viên chọn từng trường hợp rồi
	 * dùng chức năng "Xóa" — xem deleteExaminees().
	 *
	 * @return array{added: int, resitId: int, resitName: string}
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function addNew(int $resitId): array
	{
		// Toàn bộ thao tác chỉ diễn ra trong phạm vi MỘT danh sách, và do đó
		// trong phạm vi MỘT cơ sở đào tạo (2.1.8)
		$resit = $this->assertModifiable($resitId);
		$campusId = (int) $resit->campus_id;

		$db = DatabaseHelper::getDatabaseDriver();
		$db->transactionStart();

		try {
			$newList = $this->buildNewList($db, $campusId);

			// Load các triple key đang tồn tại TRONG DANH SÁCH NÀY
			$db->setQuery(
				$db->getQuery(true)
					->select([
						$db->quoteName('sa.class_id'),
						$db->quoteName('sa.learner_id'),
						$db->quoteName('sa.last_exam_id'),
					])
					->from($db->quoteName('#__eqa_resit_learner', 'sa'))
					->where($db->quoteName('sa.resit_id') . ' = ' . $resitId)
			);
			foreach ($db->loadObjectList() as $existing) {
				$tripleKey = $existing->class_id . ':' . $existing->learner_id . ':' . $existing->last_exam_id;
				unset($newList[$tripleKey]);
			}

			$addedCount = $this->insertNewRecords($db, $newList, $resitId);
			$db->transactionCommit();

		} catch (Exception $e) {
			$db->transactionRollback();
			throw $e;
		}

		return ['added' => $addedCount, 'resitId' => $resitId, 'resitName' => $resit->name];
	}

    /**
     * Xây dựng danh sách các thí sinh đủ điều kiện thi lần hai từ dữ liệu hiện tại.
     *
     * Điều kiện lọc:
     *   - Thí sinh được phép dự thi (cl.allowed = 1).
     *   - Thí sinh chưa hết quyền dự thi (cl.expired = 0).
     *   - Kết luận của lần thi gần nhất là Failed (20) hoặc Deferred (30).
     *
     * Từ 2.1.6, chỉ xét các lớp học phần thuộc cơ sở đào tạo $campusId.
     *
     * @param DatabaseDriver $db
     * @param int            $campusId  Cơ sở đào tạo cần xử lý
     * @return array<string, object> Map theo key "class_id:learner_id:last_exam_id".
     *                               Mỗi giá trị là object có các thuộc tính:
     *                               class_id, learner_id, last_exam_id, last_attempt, last_conclusion.
     * @throws Exception
     * @since 2.0.2
     */
    private function buildNewList(DatabaseDriver $db, int $campusId): array
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
            // Giới hạn phạm vi theo cơ sở đào tạo (2.1.6)
            ->innerJoin(
                $db->quoteName('#__eqa_classes', 'c') .
                ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('el.class_id')
            )
            ->where([
                $db->quoteName('c.campus_id') . ' = ' . (int) $campusId,
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
     * Thêm vào MỘT DANH SÁCH các bản ghi trong $newList chưa tồn tại trong đó.
     *
     * Quy tắc gán thông tin thanh toán:
     *   - last_conclusion = Deferred  → payment_amount = 0.0 (bảo lưu, không cần đóng phí)
     *   - last_conclusion = Failed    → payment_amount = calculateFee(...),
     *                                   payment_completed = FALSE,
     *                                   payment_code = chuỗi ngẫu nhiên 8 ký tự [A-Z0-9] (duy nhất)
     *
     * LƯU Ý (2.1.8): mỗi danh sách được tính phí ĐỘC LẬP. Thí sinh đã nộp phí ở
     * một danh sách trước đó nhưng chưa sử dụng quyền dự thi vẫn được sinh mã
     * nộp phí mới ở danh sách này; trường hợp đó do quản trị viên xử lý thủ công
     * bằng chức năng 'Đổi trạng thái nộp phí'.
     *
     * @param DatabaseDriver        $db
     * @param array<string, object> $newList  Danh sách bản ghi cần thêm.
     * @param int                   $resitId   Danh sách thi lần 2 nhận các bản ghi.
     * @return int Số bản ghi đã thêm.
     * @throws Exception
     * @since 2.0.2
     */
    private function insertNewRecords(DatabaseDriver $db, array $newList, int $resitId): int
    {
        if (empty($newList)) {
            return 0;
        }

        // Đọc cấu hình phí thi lần 2
        $feeMode = ConfigHelper::getResitFeeMode();
        $feeRate = ConfigHelper::getResitFeeRate();
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
            ' FROM ' . $db->quoteName('#__eqa_resit_learner') .
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
                    $resitId . ', ' .
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
                    $resitId . ', ' .
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
            'resit_id', 'class_id', 'learner_id', 'last_exam_id', 'last_attempt',
            'last_conclusion', 'payment_amount', 'payment_completed', 'payment_code',
        ]);
        $sql = 'INSERT INTO ' . $db->quoteName('#__eqa_resit_learner') .
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
    // Chức năng "Xóa" thí sinh khỏi đợt
    // =========================================================================

    /**
     * Xóa một số thí sinh khỏi đợt thi lại.
     *
     * Quy tắc nghiệp vụ:
     *   - Chỉ thao tác được trên đợt đang kích hoạt và đang dùng
     *     (xem assertModifiable()).
     *   - KHÔNG xóa thí sinh ĐÃ ĐÓNG PHÍ: xóa bản ghi đồng nghĩa với việc mất
     *     dấu vết khoản tiền đã thu, và mã nộp phí (payment_code) đã đối chiếu
     *     với sao kê ngân hàng cũng biến mất. Muốn loại một thí sinh như vậy,
     *     quản trị viên phải chuyển trạng thái nộp phí về "Chưa nộp" trước —
     *     một thao tác có ghi log riêng.
     *   - Tất cả bản ghi được chọn phải thuộc CÙNG đợt đang mở; bản ghi lạc sẽ
     *     bị bỏ qua thay vì xóa nhầm sang đợt khác.
     *
     * @param   int    $resitId  Đợt đang mở.
     * @param   int[]  $ids      Mã các bản ghi trong #__eqa_resit_learner.
     *
     * @return  array{deleted: int, deletedCodes: string[], paidCodes: string[], foreignCount: int,
     *                resitId: int, resitName: string}
     * @throws  Exception
     * @since   2.1.8
     */
    public function deleteExaminees(int $resitId, array $ids): array
    {
        $resit = $this->assertModifiable($resitId);

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (empty($ids)) {
            throw new Exception('Không có thí sinh nào được chọn.');
        }

        $db = DatabaseHelper::getDatabaseDriver();

        // Nạp thông tin các bản ghi được chọn để phân loại trước khi xóa
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('rl.id',                'id'),
                $db->quoteName('rl.resit_id',          'resit_id'),
                $db->quoteName('rl.payment_amount',    'payment_amount'),
                $db->quoteName('rl.payment_completed', 'payment_completed'),
                $db->quoteName('lr.code',              'learner_code'),
            ])
            ->from($db->quoteName('#__eqa_resit_learner', 'rl'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('rl.learner_id')
            )
            ->where($db->quoteName('rl.id') . ' IN (' . implode(',', $ids) . ')');

        $db->setQuery($query);
        $records = $db->loadObjectList();

        $idsToDelete  = [];
        $deletedCodes = [];
        $paidCodes    = [];
        $foreignCount = 0;

        foreach ($records as $record) {
            $label = $record->learner_code ?? ('id=' . $record->id);

            // Bản ghi của đợt khác (URL bị sửa tay) → bỏ qua
            if ((int) $record->resit_id !== $resitId) {
                $foreignCount++;

                continue;
            }

            // Đã đóng phí → không xóa
            if ((float) $record->payment_amount > 0 && !empty($record->payment_completed)) {
                $paidCodes[] = $label;

                continue;
            }

            $idsToDelete[]  = (int) $record->id;
            $deletedCodes[] = $label;
        }

        if (!empty($idsToDelete)) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete('#__eqa_resit_learner')
                    ->where('id IN (' . implode(',', $idsToDelete) . ')')
            );
            $db->execute();
        }

        return [
            'deleted'      => count($idsToDelete),
            'deletedCodes' => $deletedCodes,
            'paidCodes'    => $paidCodes,
            'foreignCount' => $foreignCount,
            'resitId'      => $resitId,
            'resitName'    => $resit->name,
        ];
    }

    // =========================================================================
    // Chức năng cập nhật trạng thái thanh toán
    // =========================================================================
	/**
	 * Lấy thông tin một bản ghi thi lần hai theo id, kèm thông tin người học
	 * và môn thi. Dùng cho layout 'setpayment'.
	 *
	 * @param  int  $id  ID bản ghi trong #__eqa_resit_learner.
	 * @return object
	 * @throws Exception  Nếu không tìm thấy bản ghi.
	 * @since 2.0.4
	 */
	public function getExamineeById(int $id): object
	{
		$db    = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('sa.id'),
				//Cần cho việc quay lại đúng danh sách sau khi cập nhật (2.1.8)
				$db->quoteName('sa.resit_id'),
				$db->quoteName('sa.payment_amount'),
				$db->quoteName('sa.payment_completed'),
				$db->quoteName('sa.description'),
				$db->quoteName('lr.code', 'learner_code'),
				$db->quoteName('lr.lastname', 'learner_lastname'),
				$db->quoteName('lr.firstname', 'learner_firstname'),
				$db->quoteName('su.code', 'subject_code'),
				//Màn hình xác nhận nộp phí ở backend → dùng tên phân biệt (2.1.7)
				DatabaseHelper::displayNameExpr('su') . ' AS ' . $db->quoteName('subject_name'),
			])
			->from($db->quoteName('#__eqa_resit_learner', 'sa'))
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
	 * @param  int          $id                ID bản ghi trong #__eqa_resit_learner.
	 * @param  bool         $paymentCompleted  TRUE = Đã nộp phí; FALSE = Chưa nộp phí.
	 * @param  string|null  $description       Mô tả/ghi chú; NULL để xóa mô tả cũ.
	 * @return array{learnerCode: string, paymentCompleted: bool, resitId: int}
	 * @throws Exception  Nếu bản ghi không tồn tại hoặc không có yêu cầu đóng phí.
	 * @since 2.0.4
	 */
	public function savePaymentStatus(int $id, bool $paymentCompleted, ?string $description): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// Đọc bản ghi để validate và lấy learner_code cho thông báo
		$record = $this->getExamineeById($id);

		// Chốt chặn quyền theo cơ sở đào tạo và trạng thái kích hoạt của đợt
		// (2.1.8): bản ghi có thể được truy cập bằng URL trực tiếp nên không thể
		// dựa vào bộ lọc hiển thị của danh sách.
		$this->assertModifiable((int) $record->resit_id);

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
			->update($db->quoteName('#__eqa_resit_learner'))
			->set($db->quoteName('payment_completed') . ' = ' . ($paymentCompleted ? 1 : 0))
			->set($db->quoteName('description') . ' = ' . ($description !== null ? $db->quote($description) : 'NULL'))
			->where($db->quoteName('id') . ' = ' . (int) $id);

		$db->setQuery($query);
		$db->execute();

		return [
			'learnerCode'      => $record->learner_code ?? ('id=' . $id),
			'paymentCompleted' => $paymentCompleted,
			'resitId'           => (int) $record->resit_id,
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
	 * Từ 2.1.8, việc đối chiếu chỉ diễn ra TRONG PHẠM VI ĐỢT ĐANG MỞ: giao dịch
	 * ứng với mã nộp phí của đợt khác sẽ được báo là 'không tìm thấy' thay vì âm
	 * thầm cập nhật sang đợt đó. Sau khi đối chiếu xong, thời điểm cập nhật sao kê
	 * gần nhất của đợt được ghi lại để hiển thị cho người học ở cổng thông tin.
	 *
	 * @param  int     $resitId    Đợt thi lại cần đối chiếu.
	 * @param  string  $filePath   Đường dẫn tuyệt đối đến file .xlsx đã upload.
	 * @param  string  $napasCode  Mã NAPAS của ngân hàng phát hành sao kê.
	 * @return BankStatementImportResult
	 * @throws Exception  Nếu file không đọc được hoặc format không đúng.
	 * @since 2.0.3
	 */
	public function importBankStatement(int $resitId, string $filePath, string $napasCode): BankStatementImportResult
	{
		//Phạm vi đối chiếu: đợt đang mở; chỉ đợt đang kích hoạt mới được cập nhật
		$this->assertModifiable($resitId);

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
			->from($db->quoteName('#__eqa_resit_learner', 'sa'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id')
			)
			->innerJoin(
				$db->quoteName('#__eqa_classes', 'cl') .
				' ON ' . $db->quoteName('cl.id') . ' = ' . $db->quoteName('sa.class_id')
			)
			// Chỉ đối soát trong phạm vi DANH SÁCH đang mở (2.1.8). Điều này đồng
			// thời bảo đảm phạm vi cơ sở đào tạo (2.1.6): tránh việc cán bộ một
			// cơ sở vô tình cập nhật trạng thái thanh toán của cơ sở kia. Giao
			// dịch ngoài phạm vi sẽ được báo là 'không tìm thấy'.
			->where($db->quoteName('sa.resit_id') . ' = ' . $resitId)
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
			$updateQuery = $db->getQuery(true)
				->update($db->quoteName('#__eqa_resit_learner'))
				->set($db->quoteName('payment_completed') . ' = 1')
				->set($db->quoteName('description') . ' = ' . $db->quote($tx['description']))
				->where($db->quoteName('id') . ' = ' . (int) $rec->id);
			$db->setQuery($updateQuery);
			$db->execute();
			$updatedCodes[] = $rec->learner_code ?? ('id=' . $rec->id);
		}

		// 5. Ghi nhận thời điểm đối chiếu sao kê gần nhất của đợt (UTC).
		//    Người học nhìn thấy mốc thời gian này ở cổng thông tin để biết dữ liệu
		//    thu phí đã được cập nhật đến lúc nào.
		$db->setQuery(
			$db->getQuery(true)
				->update('#__eqa_resits')
				->set('last_statement_update = ' . $db->quote(DatetimeHelper::getCurrentUtcTime()))
				->where('id = ' . $resitId)
		);
		$db->execute();

		$result                = new BankStatementImportResult();
		$result->updated        = count($reconciled['matched']);
		$result->alreadyPaid    = $reconciled['alreadyPaid'];
		$result->notFound       = $reconciled['notFound'];
		$result->amountMismatch = $reconciled['amountMismatch'];
		$result->duplicate      = $reconciled['duplicate'];
		$result->updatedCodes   = $updatedCodes;
		return $result;
	}

	/**
	 * Nạp thí sinh của MỘT đợt thi lại để xuất dữ liệu / sinh môn thi lại.
	 *
	 * @param int  $resitId                     Đợt cần lấy dữ liệu.
	 * @param bool $onlyFreeOrPaymentCompleted  Chỉ lấy trường hợp miễn phí hoặc đã đóng phí
	 *
	 * @return array
	 * @throws Exception
	 * @since 2.0.5
	 */
	public function loadExamineesForExport(int $resitId, bool $onlyFreeOrPaymentCompleted): array
	{
		$this->assertAccessible($resitId);

		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.learner_id')          . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('c.code')                . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('c.lastname')            . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('c.firstname')           . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('e.id')                  . ' AS ' . $db->quoteName('subjectId'),
			$db->quoteName('e.code')                . ' AS ' . $db->quoteName('subjectCode'),
			//CỐ Ý dùng tên chính thức: dữ liệu này được xuất ra hồ sơ thi (IOHelper),
			//không phải để hiển thị trên giao diện quản trị. (2.1.7)
			$db->quoteName('e.name')                . ' AS ' . $db->quoteName('subjectName'),
			$db->quoteName('e.finaltesttype')       . ' AS ' . $db->quoteName('testType'),
			$db->quoteName('e.finaltestduration')   . ' AS ' . $db->quoteName('testDuration'),
			$db->quoteName('d.term')                . ' AS ' . $db->quoteName('term'),
			$db->quoteName('d.academicyear')        . ' AS ' . $db->quoteName('academicyear'),
			$db->quoteName('a.last_exam_id')        . ' AS ' . $db->quoteName('examId'),
			$db->quoteName('a.class_id')            . ' AS ' . $db->quoteName('classId'),
			$db->quoteName('b.ntaken')              . ' AS ' . $db->quoteName('ntaken'),
			$db->quoteName('a.last_conclusion')     . ' AS ' . $db->quoteName('conclusion'),
		];

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_resit_learner AS a')
			->leftJoin('#__eqa_class_learner AS b', 'b.class_id=a.class_id AND b.learner_id=a.learner_id')
			->leftJoin('#__eqa_learners AS c', 'c.id=a.learner_id')
			->leftJoin('#__eqa_classes AS d', 'd.id=a.class_id')
			->leftJoin('#__eqa_subjects AS e', 'e.id=d.subject_id')
			// Giới hạn phạm vi theo danh sách thi lần 2 (2.1.8). Vì mọi bản ghi
			// của một danh sách đều thuộc cơ sở đào tạo của danh sách đó, điều
			// kiện này đồng thời giữ nguyên phạm vi cơ sở đào tạo (2.1.6).
			->where($db->quoteName('a.resit_id') . ' = ' . (int) $resitId);
		if($onlyFreeOrPaymentCompleted) {
			$query->where('(a.payment_amount = 0 OR a.payment_completed = 1)');
		}

		$db->setQuery($query);
		return $db->loadObjectList();
	}

}
