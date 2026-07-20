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
use Joomla\Database\ParameterType;
use Kma\Component\Eqa\Administrator\Service\CampusService;
use Kma\Library\Kma\Helper\ComponentHelper;
use RuntimeException;

/**
 * Trait chốt chặn quyền theo 'cơ sở đào tạo' cho các Item Model.
 *
 * ĐÂY MỚI LÀ CHỐT CHẶN BẢO MẬT THỰC SỰ của cơ chế multi-campus. Bộ lọc ở
 * List Model chỉ phục vụ hiển thị và có thể bị vượt qua bằng cách gọi thẳng
 * URL với id của bản ghi thuộc cơ sở khác.
 *
 * Quy ước sử dụng:
 *  - Thực thể có cột campus_id: gọi assertCanManageCampus() trong save()/delete(),
 *    và gọi enforceCampusOnNewRecord() trong prepareTable().
 *  - Thực thể suy diễn campus qua FK: dùng resolveCampusIdByQuery() để lấy
 *    campus của bản ghi cha rồi assert.
 *
 * @since 2.1.6
 */
trait CampusScopedItem
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
     * Khẳng định người dùng hiện tại được phép thao tác trên dữ liệu của cơ sở này.
     *
     * @param   int  $campusId
     *
     * @return  void
     * @throws  RuntimeException  Nếu không được phép
     * @since   2.1.6
     */
    protected function assertCanManageCampus(int $campusId): void
    {
        if ($campusId <= 0) {
            throw new RuntimeException(
                'Không xác định được cơ sở đào tạo của bản ghi. Thao tác bị từ chối.'
            );
        }

        if (!$this->getCampusService()->canManageCampus($campusId)) {
            throw new RuntimeException(sprintf(
                'Bạn không có quyền thao tác trên dữ liệu của cơ sở đào tạo "%s".',
                $this->getCampusService()->getCampusName($campusId) ?: ('#' . $campusId)
            ));
        }
    }

    /**
     * Gán cơ sở đào tạo cho bản ghi trước khi ghi xuống CSDL.
     *
     * Bản ghi mới: gán cơ sở đang làm việc.
     * Người dùng thường: luôn ép về cơ sở đang làm việc, bỏ qua giá trị gửi lên
     * từ form (chống giả mạo campus_id trong request).
     * Người dùng có quyền mọi cơ sở: tôn trọng lựa chọn trên form.
     *
     * Gọi trong prepareTable() của Item Model.
     *
     * @param   object  $table
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function enforceCampusOnNewRecord($table): void
    {
        $campusService = $this->getCampusService();

        if ($campusService->canAccessAllCampuses()) {
            if (empty($table->campus_id)) {
                $table->campus_id = $campusService->getActiveCampusId();
            }

            $this->assertCanManageCampus((int) $table->campus_id);

            return;
        }

        $activeCampusId = $campusService->getActiveCampusId();

        if ($activeCampusId <= 0) {
            throw new RuntimeException(
                'Tài khoản của bạn chưa được gán vào cơ sở đào tạo nào nên không thể tạo'
                . ' hoặc sửa dữ liệu. Vui lòng liên hệ quản trị viên.'
            );
        }

        // Bản ghi đã tồn tại: quyền trên bản ghi đã được kiểm tra ở save()/delete();
        // giữ nguyên campus của bản ghi, không cho chuyển sang cơ sở khác.
        if (!empty($table->id) && !empty($table->campus_id)) {
            $this->assertCanManageCampus((int) $table->campus_id);

            return;
        }

        $table->campus_id = $activeCampusId;
    }

    /**
     * Đọc campus_id hiện đang lưu trong CSDL của một bản ghi.
     *
     * Dùng để kiểm tra quyền TRƯỚC khi ghi đè bản ghi bằng dữ liệu từ form
     * (dữ liệu form có thể bị giả mạo).
     *
     * @param   string  $tableName  Ví dụ '#__eqa_buildings'
     * @param   int     $recordId
     * @param   string  $column     Tên cột chứa campus_id
     *
     * @return  int  0 nếu không tìm thấy bản ghi
     * @since   2.1.6
     */
    protected function getStoredCampusId(string $tableName, int $recordId, string $column = 'campus_id'): int
    {
        if ($recordId <= 0) {
            return 0;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName($column))
            ->from($db->quoteName($tableName))
            ->where($db->quoteName('id') . ' = :recordId')
            ->bind(':recordId', $recordId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Suy diễn campus_id của một bản ghi qua khóa ngoại.
     *
     * Ví dụ với phòng học (rooms), campus được suy qua building_id:
     *   $campusId = $this->resolveCampusIdByForeignKey(
     *       '#__eqa_buildings', (int) $data['building_id']
     *   );
     *
     * @param   string  $parentTable  Bảng cha có cột campus_id
     * @param   int     $parentId     Giá trị khóa ngoại
     *
     * @return  int  0 nếu không tìm thấy
     * @since   2.1.6
     */
    protected function resolveCampusIdByForeignKey(string $parentTable, int $parentId): int
    {
        return $this->getStoredCampusId($parentTable, $parentId);
    }

    /**
     * Điều chỉnh field campus_id trên form theo quyền của người dùng.
     *
     * Người dùng thường không được chọn cơ sở (giá trị bị ép trong prepareTable),
     * nên field được chuyển thành readonly để tránh hiểu nhầm.
     *
     * Gọi trong getForm() của Item Model.
     *
     * @param   Form|bool  $form
     *
     * @return  Form|bool
     * @since   2.1.6
     */
    protected function applyCampusFieldVisibility($form)
    {
        if ($form instanceof Form && !$this->getCampusService()->canAccessAllCampuses()) {
            $form->setFieldAttribute('campus_id', 'readonly', 'true');
        }

        return $form;
    }
}
