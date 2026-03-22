<?php

namespace Kma\Component\Eqa\Administrator\Model;

use Kma\Component\Eqa\Administrator\Base\AdminModel;

defined('_JEXEC') or die();

/**
 * Model cho một môn học (subject).
 *
 * Ngoài logic chuẩn của AdminModel, model này xử lý thêm:
 *   - Cột `allowed_rooms` (TEXT/JSON) ↔ array of int khi load/save form.
 *   - Các cột số có thể NULL: credits, finaltestduration, finaltestweight, testbankyear.
 *
 * @since 1.0.0
 */
class SubjectModel extends AdminModel
{
    // =========================================================================
    // Load
    // =========================================================================

    /**
     * Lấy thông tin một môn học.
     *
     * Override để deserialize cột `allowed_rooms`:
     *   - JSON string → array of int  (để Joomla ListField nhận diện đúng selection)
     *   - NULL / chuỗi rỗng          → [] (mảng rỗng, nghĩa là không giới hạn)
     *
     * @param  int|null $pk Primary key. Null = lấy từ state.
     *
     * @return \stdClass|bool
     * @since 2.0.1
     */
    public function getItem($pk = null): bool|\stdClass
    {
        $item = parent::getItem($pk);

        if ($item === false) {
            return false;
        }

        // Deserialize allowed_rooms: JSON → int[]
        if (!empty($item->allowed_rooms)) {
            $decoded = json_decode($item->allowed_rooms, true);
            // Đảm bảo kết quả là mảng int hợp lệ; nếu JSON lỗi thì fallback về []
            $item->allowed_rooms = is_array($decoded)
                ? array_values(array_map('intval', $decoded))
                : [];
        } else {
            $item->allowed_rooms = [];
        }

        return $item;
    }

    // =========================================================================
    // Save
    // =========================================================================

    /**
     * Lưu dữ liệu môn học.
     *
     * Override để serialize `allowed_rooms` trước khi ghi xuống database:
     *   - array không rỗng → JSON string, ví dụ: "[1,3,7]"
     *   - array rỗng / null / không tồn tại → NULL (không giới hạn phòng)
     *
     * @param  array $data Dữ liệu từ form (jform).
     * @return bool
     */
    public function save($data): bool
    {
        // Serialize allowed_rooms: int[] → JSON hoặc NULL
        if (isset($data['allowed_rooms']) && is_array($data['allowed_rooms'])) {
            // Lọc bỏ giá trị rỗng/0 có thể xuất hiện khi form gửi mảng rỗng
            $roomIds = array_values(
                array_filter(
                    array_map('intval', $data['allowed_rooms']),
                    static fn(int $id): bool => $id > 0
                )
            );

            $data['allowed_rooms'] = !empty($roomIds)
                ? json_encode($roomIds, JSON_THROW_ON_ERROR)
                : null;
        } else {
            // Không có key hoặc giá trị không phải array → NULL
            $data['allowed_rooms'] = null;
        }

        return parent::save($data);
    }

    // =========================================================================
    // prepareTable
    // =========================================================================

    /**
     * Chuẩn hóa dữ liệu trước khi bind vào Table object.
     *
     * Chuyển chuỗi rỗng thành NULL cho các cột số tùy chọn,
     * tránh lỗi kiểu dữ liệu khi INSERT/UPDATE.
     *
     * @param  \Joomla\CMS\Table\Table $table
     * @return void
     */
    public function prepareTable($table): void
    {
        parent::prepareTable($table);

        if (empty($table->credits)) {
            $table->credits = null;
        }

        if (empty($table->finaltestduration)) {
            $table->finaltestduration = null;
        }

        if (empty($table->finaltestweight)) {
            $table->finaltestweight = null;
        }

        if (empty($table->testbankyear)) {
            $table->testbankyear = null;
        }

        // allowed_rooms đã được serialize thành JSON string hoặc null trong save()
        // trước khi bind vào table, nên không cần xử lý thêm ở đây.
    }
}
