<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Base
 *
 * @copyright   (C) 2026 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Base;

defined('_JEXEC') or die;

use Kma\Component\Eqa\Administrator\Traits\CampusScopedItem;
use RuntimeException;

/**
 * Lớp cơ sở cho các Item Model của thực thể được quản lý riêng theo cơ sở đào tạo.
 *
 * Cài sẵn toàn bộ phần lặp lại: kiểm tra quyền khi lưu/xóa, gán cơ sở cho bản
 * ghi mới, và điều chỉnh field campus_id trên form. Lớp con thường chỉ cần
 * khai báo một phương thức:
 *
 *     protected function getCampusIdOfRecord(int $recordId): int
 *     {
 *         return $this->getStoredCampusId('#__eqa_buildings', $recordId);
 *     }
 *
 * Với thực thể KHÔNG có cột campus_id (suy diễn qua khóa ngoại), đặt thêm:
 *
 *     protected bool $hasCampusColumn = false;
 *
 * Model nào lệch khuôn mẫu này thì kế thừa AdminModel như cũ và `use` trực tiếp
 * trait CampusScopedItem — lớp cơ sở là tiện ích, không phải ràng buộc.
 *
 * @since 2.1.6
 */
abstract class CampusAdminModel extends AdminModel
{
    use CampusScopedItem;

    /**
     * Bảng của thực thể này có cột campus_id hay không.
     *
     * false → cơ sở đào tạo được suy diễn qua khóa ngoại; khi đó tuyệt đối
     * không được gán thuộc tính campus_id lên Table object, vì Table sẽ đưa
     * thuộc tính lạ đó vào câu lệnh INSERT/UPDATE và gây lỗi SQL.
     *
     * @var    bool
     * @since  2.1.6
     */
    protected bool $hasCampusColumn = true;

    /**
     * Gán/ép cơ sở đào tạo trước khi ghi xuống CSDL.
     *
     * Chữ ký giữ nguyên dạng của lớp cha (protected, không khai báo kiểu trả về)
     * để các model hiện có override được mà không vi phạm quy tắc kế thừa của PHP.
     *
     * @param   object  $table
     *
     * @return  void
     * @since   2.1.6
     */
    protected function prepareTable($table)
    {
        parent::prepareTable($table);

        if ($this->hasCampusColumn) {
            $this->enforceCampusOnNewRecord($table);
        }
    }

    /**
     * Lưu bản ghi sau khi kiểm tra quyền theo cơ sở đào tạo.
     *
     * @param   array  $data
     *
     * @return  bool
     * @since   2.1.6
     */
    public function save($data): bool
    {
        try {
            $this->guardCampusOnSave((array) $data);
        } catch (RuntimeException $e) {
            $this->setError($e->getMessage());

            return false;
        }

        return parent::save($data);
    }

    /**
     * Xóa bản ghi sau khi kiểm tra quyền theo cơ sở đào tạo.
     *
     * @param   array|int  $pks
     *
     * @return  bool
     * @since   2.1.6
     */
    public function delete(&$pks): bool
    {
        try {
            $this->guardCampusOnDelete($pks);
        } catch (RuntimeException $e) {
            $this->setError($e->getMessage());

            return false;
        }

        return parent::delete($pks);
    }

    /**
     * @param   array  $data
     * @param   bool   $loadData
     *
     * @return  mixed
     * @since   2.1.6
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = parent::getForm($data, $loadData);

        return $this->hasCampusColumn
            ? $this->applyCampusFieldVisibility($form)
            : $form;
    }
}
