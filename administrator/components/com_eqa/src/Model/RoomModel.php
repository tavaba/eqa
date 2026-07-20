<?php
namespace Kma\Component\Eqa\Administrator\Model;

use Kma\Component\Eqa\Administrator\Base\CampusAdminModel;

defined('_JEXEC') or die();

/**
 * Item model của 'phòng' (room).
 *
 * Phòng KHÔNG có cột campus_id: cơ sở đào tạo được suy diễn qua building_id.
 * Nhờ getCampusIdOfIncomingData(), lớp cơ sở tự động kiểm tra cả cơ sở của bản
 * ghi hiện có lẫn cơ sở của tòa nhà được chọn trên form — chặn việc chuyển
 * phòng sang tòa nhà thuộc cơ sở khác theo cả hai chiều.
 *
 * @since 1.0.0
 */
class RoomModel extends CampusAdminModel
{
    /**
     * Bảng #__eqa_rooms không có cột campus_id.
     *
     * @var   bool
     * @since 2.1.6
     */
    protected bool $hasCampusColumn = false;

    /**
     * @param  int $recordId
     * @return int
     * @since  2.1.6
     */
    protected function getCampusIdOfRecord(int $recordId): int
    {
        $buildingId = $this->getStoredCampusId('#__eqa_rooms', $recordId, 'building_id');

        return $this->resolveCampusIdByForeignKey('#__eqa_buildings', $buildingId);
    }

    /**
     * Cơ sở đào tạo tương ứng với tòa nhà được chọn trên form.
     *
     * @param  array $data
     * @return int
     * @since  2.1.6
     */
    protected function getCampusIdOfIncomingData(array $data): int
    {
        return $this->resolveCampusIdByForeignKey(
            '#__eqa_buildings',
            (int) ($data['building_id'] ?? 0)
        );
    }
}
