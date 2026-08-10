<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Component\Eqa\Administrator\Base\CampusAdminModel;

defined('_JEXEC') or die();

/**
 * Item model của một bản ghi 'thi lần hai' (secondattempt).
 *
 * Secondattempt gắn với một lớp học phần (class_id); cơ sở đào tạo được suy
 * diễn qua lớp học phần đó. Toàn bộ logic chốt chặn nằm ở CampusAdminModel;
 * model này chỉ khai báo cách suy ra cơ sở của bản ghi.
 *
 * @since 1.0.0
 */
class SecondAttemptModel extends CampusAdminModel {
    /**
     * Secondattempt không có cột campus_id — suy diễn qua class_id.
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
        $classId = $this->getStoredCampusId('#__eqa_secondattempts', $recordId, 'class_id');

        return $this->resolveCampusIdByForeignKey('#__eqa_classes', $classId);
    }

    /**
     * Cơ sở đào tạo tương ứng với lớp học phần được chọn trên form.
     *
     * @param  array $data
     * @return int
     * @since  2.1.6
     */
    protected function getCampusIdOfIncomingData(array $data): int
    {
        return $this->resolveCampusIdByForeignKey(
            '#__eqa_classes',
            (int) ($data['class_id'] ?? 0)
        );
    }
}
