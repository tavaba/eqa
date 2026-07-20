<?php
namespace Kma\Component\Eqa\Administrator\Model;

use Kma\Component\Eqa\Administrator\Base\CampusAdminModel;

defined('_JEXEC') or die();

/**
 * Item model của 'tòa nhà' (building).
 *
 * Toàn bộ logic kiểm tra quyền theo cơ sở đào tạo nằm ở CampusAdminModel;
 * model này chỉ khai báo cách suy ra cơ sở của một bản ghi.
 *
 * @since 1.0.0
 */
class BuildingModel extends CampusAdminModel
{
    /**
     * @param  int $recordId
     * @return int
     * @since  2.1.6
     */
    protected function getCampusIdOfRecord(int $recordId): int
    {
        return $this->getStoredCampusId('#__eqa_buildings', $recordId);
    }
}
