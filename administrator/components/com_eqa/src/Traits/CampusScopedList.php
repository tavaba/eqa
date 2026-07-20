<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Traits
 *
 * @copyright   (C) 2026 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Traits;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\Database\QueryInterface;
use Kma\Component\Eqa\Administrator\Service\CampusService;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * Trait áp điều kiện lọc theo 'cơ sở đào tạo' cho các List Model.
 *
 * Dùng cho mọi thực thể được quản lý riêng theo cơ sở, bất kể campus_id nằm
 * trực tiếp trên bảng hay được suy diễn qua JOIN.
 *
 * Quy tắc lọc:
 *  - Người dùng không thuộc cơ sở nào  → không thấy bản ghi nào (WHERE 1 = 0).
 *  - Người dùng thường                 → chỉ thấy dữ liệu của cơ sở đang làm việc.
 *  - Người dùng có quyền mọi cơ sở     → thấy tất cả, hoặc lọc theo bộ lọc
 *                                        'campus_id' nếu có chọn.
 *
 * CẢNH BÁO: đây là bộ lọc phục vụ hiển thị. Chốt chặn quyền thực sự nằm ở
 * tầng Item Model (xem trait CampusScopedItem).
 *
 * @since 2.1.6
 */
trait CampusScopedList
{
    /**
     * @var CampusService|null
     * @since 2.1.6
     */
    private ?CampusService $campusServiceInstance = null;

    /**
     * @return  CampusService
     * @since   2.1.6
     */
    protected function getCampusService(): CampusService
    {
        if ($this->campusServiceInstance === null) {
            $this->campusServiceInstance = ComponentHelper::getComponent()->getCampusService();
        }

        return $this->campusServiceInstance;
    }

    /**
     * Áp điều kiện lọc theo cơ sở đào tạo vào truy vấn danh sách.
     *
     * @param   QueryInterface  $query       Truy vấn đang xây dựng
     * @param   string          $columnName  Cột campus_id đủ điều kiện,
     *                                       ví dụ 'a.campus_id' hoặc 'b.campus_id'
     *
     * @return  void
     * @since   2.1.6
     */
    protected function applyCampusFilter(QueryInterface $query, string $columnName): void
    {
        $db            = $this->getDatabase();
        $campusService = $this->getCampusService();
        $column        = $db->quoteName($columnName);

        // Không thuộc cơ sở nào → không có quyền xem bất cứ dữ liệu nào.
        // Tuyệt đối không được hiểu là 'không lọc'.
        if (empty($campusService->getUserCampusIds())) {
            $query->where('1 = 0');

            return;
        }

        if ($campusService->canAccessAllCampuses()) {
            // Người dùng cấp Học viện: mặc định xem tất cả, có thể lọc theo cơ sở
            $filterCampusId = (int) $this->getState('filter.campus_id');

            if ($filterCampusId > 0) {
                $query->where($column . ' = ' . $filterCampusId);
            }

            return;
        }

        // Người dùng thường: chỉ dữ liệu của cơ sở đang làm việc
        $activeCampusId = $campusService->getActiveCampusId();

        if ($activeCampusId <= 0) {
            $query->where('1 = 0');

            return;
        }

        $query->where($column . ' = ' . $activeCampusId);
    }

    /**
     * Hậu tố cần thêm vào getStoreId() để cache danh sách không bị lẫn giữa
     * các cơ sở đào tạo.
     *
     * BẮT BUỘC dùng ở mọi List Model có áp dụng applyCampusFilter(): thiếu nó,
     * người dùng chuyển cơ sở nhưng vẫn nhận lại danh sách đã cache của cơ sở cũ.
     *
     * @return  string
     * @since   2.1.6
     */
    protected function getCampusStoreId(): string
    {
        $campusService = $this->getCampusService();

        return ':campus' . $campusService->getActiveCampusId()
            . ':filter' . (int) $this->getState('filter.campus_id');
    }

    /**
     * Gỡ bộ lọc cơ sở đào tạo khỏi filter form nếu người dùng không có quyền
     * làm việc với mọi cơ sở (với họ, bộ lọc này không có tác dụng).
     *
     * Gọi trong getFilterForm() của List Model.
     *
     * @param   Form|bool  $form
     *
     * @return  Form|bool
     * @since   2.1.6
     */
    protected function applyCampusFilterVisibility($form)
    {
        if ($form instanceof Form && !$this->getCampusService()->canAccessAllCampuses()) {
            $form->removeField('campus_id', 'filter');
        }

        return $form;
    }
}
