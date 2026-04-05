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
     * Dữ liệu đầu vào từ form examsessions.xml:
     *   $data['session_type']  = '1' | '2'
     *   $data['examseason_id'] = int | ''    (loại 1)
     *   $data['assessment_id'] = int | ''    (loại 2)
     *   $data['examsessions']  = array of {start, name, flexible}
     *
     * @param  array $data
     * @return bool
     */
    public function saveBatch(array $data): bool
    {
        $app = Factory::getApplication();
        $db  = $this->getDatabase();

        // --- Validate context ---
        if (!$this->validateSessionContext($data)) {
            return false;
        }

        $hasAssessment  = !empty($data['assessment_id']);
        $examseasonId   = $hasAssessment ? null : (int) $data['examseason_id'];
        $assessmentId   = $hasAssessment ? (int) $data['assessment_id'] : null;

        $examsessions = $data['examsessions'] ?? [];
        if (empty($examsessions)) {
            $app->enqueueMessage('Không có ca thi nào để thêm.', 'warning');
            return false;
        }

        // --- Build INSERT ---
        $columns = $db->quoteName(['examseason_id', 'assessment_id', 'start', 'name', 'flexible']);

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__eqa_examsessions'))
            ->columns($columns);

        foreach ($examsessions as $item) {
            $values = [
                $examseasonId !== null ? (int) $examseasonId : 'NULL',
                $assessmentId !== null ? (int) $assessmentId : 'NULL',
                $db->quote($item['start']),
                $db->quote($item['name']),
                (int) $item['flexible'],
            ];
            $query->values(implode(',', $values));
        }

        $db->transactionStart();
        try {
            $db->setQuery($query)->execute();
            $db->transactionCommit();

            $app->enqueueMessage(
	            Text::sprintf('COM_EQA_MSG_N_ITEMS_INSERTED', count($examsessions)),
                'success'
            );
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
