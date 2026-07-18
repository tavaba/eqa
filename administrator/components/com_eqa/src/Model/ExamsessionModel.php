<?php

namespace Kma\Component\Eqa\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Kma\Component\Eqa\Administrator\Base\AdminModel;

defined('_JEXEC') or die();

class ExamsessionModel extends AdminModel
{
    // =========================================================================
    // getItem
    // =========================================================================

    public function getItem($pk = null): CMSObject|bool
    {
        $item = parent::getItem($pk);

        if (!empty($item->monitor_ids)) {
            $item->monitor_ids = array_map('intval', explode(',', $item->monitor_ids));
        }

        if (!empty($item->examiner_ids)) {
            $item->examiner_ids = array_map('intval', explode(',', $item->examiner_ids));
        }

	    if (!empty($item->supervisor_ids)) {
		    $item->supervisor_ids = array_map('intval', explode(',', $item->supervisor_ids));
	    }

        // Populate trường UI session_type dựa trên dữ liệu đã lưu,
        // để form hiển thị đúng trạng thái khi edit.
        // Nếu assessment_id có giá trị → loại 2 (Sát hạch); ngược lại → loại 1.
        if (!empty($item->assessment_id)) {
            $item->session_type = 2;
        } else {
            $item->session_type = 1;
        }

        return $item;
    }

    // =========================================================================
    // prepareTable
    // =========================================================================

    /**
     * Chuẩn hóa dữ liệu trước khi bind vào Table object.
     *
     * Đảm bảo đúng một trong hai cột examseason_id / assessment_id có giá trị,
     * cột còn lại được ép về NULL để tránh vi phạm ràng buộc business.
     *
     * @param  \Joomla\CMS\Table\Table $table
     * @return void
     */
    public function prepareTable($table): void
    {
        parent::prepareTable($table);

        // Ép cột không được dùng về NULL
        if (!empty($table->assessment_id)) {
            $table->examseason_id = null;
        } else {
            $table->assessment_id = null;
        }
    }

    // =========================================================================
    // save (single record)
    // =========================================================================

    /**
     * @param  array $data  Dữ liệu form đã qua filter của Joomla.
     * @return bool
     */
    public function save($data): bool
    {
        // --- Validate: đúng 1 trong 2 trường phải có giá trị ---
        if (!$this->validateSessionContext($data)) {
            return false;
        }

        // --- Serialize multi-select fields ---
        if (isset($data['monitor_ids']) && is_array($data['monitor_ids'])) {
            $data['monitor_ids'] = implode(',', $data['monitor_ids']);
        }

        if (isset($data['examiner_ids']) && is_array($data['examiner_ids'])) {
            $data['examiner_ids'] = implode(',', $data['examiner_ids']);
        }

	    if (isset($data['supervisor_ids']) && is_array($data['supervisor_ids'])) {
		    $data['supervisor_ids'] = implode(',', $data['supervisor_ids']);
	    }

        // --- Ép cột không được dùng về NULL trước khi lưu ---
        $hasAssessment = !empty($data['assessment_id']);
        if ($hasAssessment) {
            $data['examseason_id'] = null;
        } else {
            $data['assessment_id'] = null;
        }

        return parent::save($data);
    }

    // =========================================================================
    // getAddbatchForm / saveBatch (batch add)
    // =========================================================================

    public function getAddbatchForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_eqa.examsessions',
            'examsessions',
            ['control' => 'jform', 'load_data' => false]
        );

        return $form ?: false;
    }

	/**
	 * Lưu nhiều ca thi cùng lúc (batch add).
	 *
	 * Dữ liệu đầu vào là dữ liệu form ĐÃ QUA validate()/filter() của Joomla
	 * (xem ExamsessionController::saveBatch). Nhờ subform khai báo
	 * filter="user_utc", trường `start` của mỗi dòng đã được chuyển sang UTC
	 * TRƯỚC khi tới đây — tuyệt đối không convert lại trong hàm này.
	 *
	 * Mỗi dòng được lưu qua Table::save() (không INSERT thô) để tự động điền
	 * các trường timestamp (created_at, created_by, modified_at, modified_by)
	 * và đi đúng pipeline chung. Toàn bộ được bọc trong một transaction:
	 * hoặc thêm được tất cả, hoặc không thêm dòng nào.
	 *
	 * @param   array  $data  Dữ liệu form đã validate:
	 *                        - session_type  : '1' | '2'
	 *                        - examseason_id : int|'' (loại 1)
	 *                        - assessment_id : int|'' (loại 2)
	 *                        - examsessions  : array of {start (UTC), name, flexible}
	 *
	 * @return  bool   true nếu thêm thành công toàn bộ.
	 *
	 * @since   1.0.0
	 */
	public function saveBatch(array $data): bool
	{
		$app = Factory::getApplication();

		// --- Validate context: đúng 1 trong 2 (examseason_id / assessment_id) phải có ---
		if (!$this->validateSessionContext($data)) {
			return false;
		}

		$rows = $data['examsessions'] ?? [];
		if (empty($rows)) {
			$app->enqueueMessage('Không có ca thi nào để thêm.', 'warning');
			return false;
		}

		// --- Xác định cột ngữ cảnh (chỉ một trong hai có giá trị) ---
		$hasAssessment = !empty($data['assessment_id']);
		$examseasonId  = $hasAssessment ? null : (int) $data['examseason_id'];
		$assessmentId  = $hasAssessment ? (int) $data['assessment_id'] : null;

		$db = $this->getDatabase();
		$db->transactionStart();

		try {
			foreach ($rows as $row) {
				// Lấy instance Table mới cho mỗi dòng để tránh dính state của dòng trước.
				$table = $this->getTable();

				$record = [
					'id'            => 0,
					'examseason_id' => $examseasonId,
					'assessment_id' => $assessmentId,
					// `start` đã là UTC (subform filter="user_utc" đã convert ở validate()).
					'start'         => $row['start'] ?? '',
					'name'          => $row['name'] ?? '',
					'flexible'      => (int) ($row['flexible'] ?? 0),
				];

				// Table::save() = bind + check + store; store() tự điền các trường timestamp.
				if (!$table->save($record)) {
					throw new \RuntimeException($table->getError() ?: 'Không lưu được một ca thi.');
				}
			}

			$db->transactionCommit();
			$app->enqueueMessage(sprintf('Đã thêm %d ca thi.', count($rows)), 'success');

			return true;
		} catch (\Throwable $e) {
			$db->transactionRollback();
			$app->enqueueMessage($e->getMessage(), 'error');

			return false;
		}
	}

    // =========================================================================
    // Validation helper
    // =========================================================================

    /**
     * Kiểm tra ràng buộc business: đúng 1 trong 2 trường examseason_id /
     * assessment_id phải có giá trị khác NULL / rỗng.
     *
     * Các trường hợp không hợp lệ:
     *   - Cả hai cùng NULL/rỗng  → không biết ca thi thuộc kỳ thi nào.
     *   - Cả hai cùng có giá trị → mâu thuẫn (một ca thi không thể vừa là
     *     KTHP vừa là sát hạch).
     *
     * @param  array $data  Mảng dữ liệu form.
     * @return bool  true nếu hợp lệ, false nếu không.
     */
    private function validateSessionContext(array $data): bool
    {
        $hasExamseason = !empty($data['examseason_id']);
        $hasAssessment = !empty($data['assessment_id']);

        if ($hasExamseason === $hasAssessment) {
            // XOR thất bại: cả hai NULL hoặc cả hai có giá trị
            if (!$hasExamseason) {
                Factory::getApplication()->enqueueMessage(
                    'Vui lòng chọn kỳ thi hoặc kỳ sát hạch cho ca thi.',
                    'error'
                );
            } else {
                Factory::getApplication()->enqueueMessage(
                    'Ca thi chỉ được thuộc một kỳ thi hoặc một kỳ sát hạch, không thể chọn cả hai.',
                    'error'
                );
            }
            return false;
        }

        return true;
    }
}
