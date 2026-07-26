<?php
namespace Kma\Component\Eqa\Administrator\Base;

defined('_JEXEC') or die;

use Joomla\Database\QueryInterface;
use Kma\Component\Eqa\Administrator\Traits\CampusScopedList;

/**
 * Lớp cơ sở cho các List Model của thực thể được quản lý riêng theo cơ sở đào tạo.
 *
 * Cài sẵn phần lặp lại: khóa cache theo cơ sở (getStoreId) và ẩn bộ lọc cơ sở
 * với người dùng không có quyền mọi cơ sở (getFilterForm).
 *
 * Lớp con khai báo cột campus và gọi applyCampusScope() trong getListQuery():
 *
 *     protected function getCampusColumn(): string
 *     {
 *         return 'a.campus_id';       // hoặc 'b.campus_id' nếu suy qua JOIN
 *     }
 *
 *     public function getListQuery()
 *     {
 *         ...
 *         $this->applyCampusScope($query);
 *         ...
 *     }
 *
 * LƯU Ý: nếu lớp con override getStoreId(), bắt buộc gọi parent::getStoreId()
 * để khóa cache vẫn chứa cơ sở đào tạo.
 *
 * @since 2.1.6
 */
abstract class CampusListModel extends ListModel
{
    use CampusScopedList;

    /**
     * Tên cột campus_id đủ điều kiện, dùng trong truy vấn danh sách.
     *
     * @return  string  Ví dụ 'a.campus_id' hoặc 'b.campus_id'
     * @since   2.1.6
     */
    abstract protected function getCampusColumn(): string;

    /**
     * Áp điều kiện lọc theo cơ sở đào tạo vào truy vấn danh sách.
     *
     * @param   QueryInterface  $query
     *
     * @return  void
     * @since   2.1.6
     */
    protected function applyCampusScope(QueryInterface $query): void
    {
        $this->applyCampusFilter($query, $this->getCampusColumn());
    }

    /**
     * Khóa cache danh sách, có tính đến cơ sở đào tạo.
     *
     * Thiếu phần này, người dùng chuyển cơ sở nhưng vẫn nhận lại danh sách đã
     * cache của cơ sở cũ.
     *
     * @param   string  $id
     *
     * @return  string
     * @since   2.1.6
     */
    protected function getStoreId($id = '')
    {
        $id .= $this->getCampusStoreId();

        return parent::getStoreId($id);
    }

    /**
     * @param   array  $data
     * @param   bool   $loadData
     *
     * @return  mixed
     * @since   2.1.6
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->applyCampusFilterVisibility(parent::getFilterForm($data, $loadData));
    }
}
