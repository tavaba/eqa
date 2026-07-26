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
 * Trait định nghĩa HỢP ĐỒNG: lớp sử dụng phải cho biết cách suy ra cơ sở đào
 * tạo của một bản ghi, qua phương thức trừu tượng getCampusIdOfRecord().
 * Phần lặp lại (kiểm tra khi lưu/xóa) đã được đóng gói thành template method.
 *
 * Thông thường nên kế thừa Base\CampusAdminModel (đã cài sẵn save/delete/
 * prepareTable) thay vì dùng trực tiếp trait này; chỉ dùng trực tiếp khi model
 * lệch khuôn mẫu chung.
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
     * Suy ra id cơ sở đào tạo của một bản ghi đang lưu trong CSDL.
     *
     * Bắt buộc đọc từ CSDL, KHÔNG lấy từ dữ liệu form (có thể bị giả mạo).
     *
     * Ví dụ với thực thể có cột campus_id:
     *     return $this->getStoredCampusId('#__eqa_buildings', $recordId);
     *
     * Ví dụ với thực thể suy diễn qua khóa ngoại:
     *     $buildingId = $this->getStoredCampusId('#__eqa_rooms', $recordId, 'building_id');
     *     return $this->resolveCampusIdByForeignKey('#__eqa_buildings', $buildingId);
     *
     * @param   int  $recordId
     *
     * @return  int  0 nếu không xác định được
     * @since   2.1.6
     */
    abstract protected function getCampusIdOfRecord(int $recordId): int;

    /**
     * Suy ra id cơ sở đào tạo tương ứng với dữ liệu người dùng gửi lên.
     *
     * Dùng để chặn việc CHUYỂN một bản ghi sang cơ sở khác. Mặc định lấy từ
     * cột campus_id trên form; model suy diễn qua khóa ngoại cần override
     * (ví dụ RoomModel suy từ building_id).
     *
     * @param   array  $data  Dữ liệu form
     *
     * @return  int  0 nghĩa là không cần kiểm tra
     * @since   2.1.6
     */
    protected function getCampusIdOfIncomingData(array $data): int
    {
        return (int) ($data['campus_id'] ?? 0);
    }

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

    // =========================================================================
    // Template method — phần lặp lại của mọi Item Model campus-scoped
    // =========================================================================

    /**
     * Kiểm tra quyền trước khi lưu.
     *
     * Kiểm tra hai chiều:
     *   1. Cơ sở của bản ghi hiện có trong CSDL (nếu là cập nhật);
     *   2. Cơ sở tương ứng với dữ liệu gửi lên (nếu người dùng đổi cơ sở).
     *
     * @param   array  $data
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function guardCampusOnSave(array $data): void
    {
        $recordId = (int) ($data['id'] ?? 0);

        if ($recordId > 0) {
            $this->assertCanManageCampus($this->getCampusIdOfRecord($recordId));
        }

        $incomingCampusId = $this->getCampusIdOfIncomingData($data);

        if ($incomingCampusId > 0) {
            $this->assertCanManageCampus($incomingCampusId);
        }
    }

    /**
     * Kiểm tra quyền trước khi xóa.
     *
     * @param   array|int  $pks
     *
     * @return  void
     * @throws  RuntimeException
     * @since   2.1.6
     */
    protected function guardCampusOnDelete($pks): void
    {
        foreach ((array) $pks as $pk) {
            $this->assertCanManageCampus($this->getCampusIdOfRecord((int) $pk));
        }
    }

    // =========================================================================
    // Các phương thức hạ tầng
    // =========================================================================

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
     * Gán/ép cơ sở đào tạo cho bản ghi trước khi ghi xuống CSDL.
     *
     * Chỉ dùng cho thực thể CÓ cột campus_id.
     *
     * Bản ghi mới: gán cơ sở đang làm việc.
     * Người dùng thường: luôn ép về cơ sở đang làm việc, bỏ qua giá trị gửi lên
     * từ form (chống giả mạo campus_id trong request).
     * Người dùng có quyền mọi cơ sở: tôn trọng lựa chọn trên form.
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

        // Bản ghi đã tồn tại: giữ nguyên cơ sở, không cho chuyển sang cơ sở khác
        if (!empty($table->id) && !empty($table->campus_id)) {
            $this->assertCanManageCampus((int) $table->campus_id);

            return;
        }

        $table->campus_id = $activeCampusId;
    }

    /**
     * Đọc một giá trị cột của bản ghi trực tiếp từ CSDL.
     *
     * @param   string  $tableName  Ví dụ '#__eqa_buildings'
     * @param   int     $recordId
     * @param   string  $column     Tên cột cần đọc
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
     * Suy diễn campus_id qua khóa ngoại trỏ tới một bảng có cột campus_id.
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
     * Chuẩn bị field campus_id trên form theo quyền của người dùng.
     *
     * Hai việc, cả hai đều cần thiết:
     *
     * 1. GÁN SẴN GIÁ TRỊ cho bản ghi mới. Joomla validate form TRƯỚC khi gọi
     *    prepareTable(), nên nếu để trống, field 'required' sẽ báo lỗi
     *    "Field required" và không bao giờ chạy tới chỗ gán cơ sở đào tạo.
     *
     * 2. CHUYỂN SANG READONLY với người dùng không có quyền mọi cơ sở, đồng
     *    thời bỏ ràng buộc 'required' ở tầng form: giá trị đằng nào cũng bị
     *    prepareTable() ép lại theo cơ sở đang làm việc, và khi tài khoản chưa
     *    được gán cơ sở nào thì nên để server sinh thông báo nghiệp vụ rõ ràng
     *    thay vì lỗi validate khó hiểu.
     *
     * @param   Form|bool  $form
     *
     * @return  Form|bool
     * @since   2.1.6
     */
    protected function applyCampusFieldVisibility($form)
    {
        if (!($form instanceof Form) || $form->getField('campus_id') === false) {
            return $form;
        }

        $campusService = $this->getCampusService();

        // 1. Bản ghi mới: gán sẵn cơ sở đang làm việc để vượt qua form validation
        if (empty($form->getValue('campus_id'))) {
            $activeCampusId = $campusService->getActiveCampusId();

            if ($activeCampusId > 0) {
                $form->setValue('campus_id', null, $activeCampusId);
            }
        }

        // 2. Người dùng thường: không được đổi cơ sở
        if (!$campusService->canAccessAllCampuses()) {
            $form->setFieldAttribute('campus_id', 'readonly', 'true');
            $form->setFieldAttribute('campus_id', 'required', 'false');
        }

        return $form;
    }
}
