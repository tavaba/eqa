<?php

namespace Kma\Component\Eqa\Administrator\Table;

defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\Table;

/**
 * Table class cho bảng `#__eqa_assessments`.
 *
 * Tên class theo quy tắc: tên bảng (singular) + "Table"
 * → AssessmentTable → bảng #__eqa_assessments (lib Table tự suy ra tên bảng).
 *
 * @since 2.0.5
 */
class AssessmentTable extends Table
{
    /**
     * Kiểm tra tính hợp lệ của dữ liệu trước khi lưu.
     *
     * @return bool
     * @since 2.0.5
     */
    public function check(): bool
    {
        if (!parent::check()) {
            return false;
        }

        // Tiêu đề không được rỗng
        if (empty(trim((string) $this->title))) {
            $this->setError('Tiêu đề kỳ sát hạch không được để trống.');
            return false;
        }

        // end_date không được trước start_date
        if (!empty($this->start_date) && !empty($this->end_date)) {
            if ($this->end_date < $this->start_date) {
                $this->setError('Ngày kết thúc không được trước ngày bắt đầu.');
                return false;
            }
        }

        // registration_end không được trước registration_start
        if (!empty($this->registration_start) && !empty($this->registration_end)) {
            if ($this->registration_end < $this->registration_start) {
                $this->setError('Thời hạn kết thúc đăng ký không được trước thời điểm bắt đầu đăng ký.');
                return false;
            }
        }

        // fee không được âm
        if (isset($this->fee) && (int) $this->fee < 0) {
            $this->setError('Phí sát hạch không được âm.');
            return false;
        }

        return true;
    }
}
