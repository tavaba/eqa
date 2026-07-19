<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use RuntimeException;

/**
 * Item model của 'cơ sở đào tạo' (campus).
 *
 * Ngoài nghiệp vụ CRUD thông thường, model này còn:
 *  - nạp/lưu danh sách tài khoản được gán vào cơ sở (#__eqa_campus_user);
 *  - chặn xóa cơ sở đang có dữ liệu tham chiếu và chặn xóa cơ sở gốc (id = 1).
 *
 * Quyền quản trị danh mục: 'core.admin' đối với com_eqa.
 *
 * @since 2.1.6
 */
class CampusModel extends AdminModel
{
    /**
     * Id của cơ sở gốc (Cơ sở chính Hà Nội) — không được phép xóa.
     *
     * @since 2.1.6
     */
    public const ROOT_CAMPUS_ID = 1;

    /**
     * Các bảng tham chiếu tới #__eqa_campuses qua cột `campus_id`,
     * dùng để kiểm tra trước khi xóa. Khóa là tên bảng, giá trị là nhãn hiển thị.
     *
     * @var array<string, string>
     * @since 2.1.6
     */
    private const REFERENCING_TABLES = [
        '#__eqa_units'       => 'đơn vị',
        '#__eqa_groups'      => 'lớp hành chính',
        '#__eqa_cohorts'     => 'nhóm người học',
        '#__eqa_classes'     => 'lớp học phần',
        '#__eqa_examseasons' => 'kỳ thi',
        '#__eqa_assessments' => 'kỳ sát hạch',
        '#__eqa_buildings'   => 'tòa nhà',
    ];

    /**
     * Nạp một cơ sở đào tạo, kèm danh sách tài khoản được gán.
     *
     * @param   int|null  $pk
     *
     * @return  mixed
     * @throws  Exception
     * @since   2.1.6
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (empty($item) || empty($item->id)) {
            return $item;
        }

        // Cột params đã được CampusTable decode sang mảng
        if (is_string($item->params)) {
            $item->params = json_decode($item->params, true) ?: [];
        }

        $item->assigned_users = $this->getAssignedUserIds((int) $item->id);

        return $item;
    }

    /**
     * Lưu cơ sở đào tạo và đồng bộ danh sách tài khoản được gán.
     *
     * Việc đồng bộ thực hiện theo phương án 'xóa rồi chèn lại' trong một
     * transaction: mapping cũ của cơ sở này bị xóa, mapping mới được chèn
     * theo đúng lựa chọn trên form.
     *
     * @param   array  $data
     *
     * @return  bool
     * @throws  Exception
     * @since   2.1.6
     */
    public function save($data): bool
    {
        if (!$this->isCampusManager()) {
            throw new RuntimeException('Bạn không có quyền quản trị danh mục cơ sở đào tạo.');
        }

        // Tách danh sách tài khoản ra khỏi dữ liệu ghi vào bảng #__eqa_campuses
        $assignedUsers = [];
        if (array_key_exists('assigned_users', $data)) {
            $assignedUsers = array_values(array_unique(array_map('intval', (array) $data['assigned_users'])));
            unset($data['assigned_users']);
        }

        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            if (!parent::save($data)) {
                throw new RuntimeException($this->getError() ?: 'Không lưu được cơ sở đào tạo.');
            }

            $campusId = (int) $this->getState($this->getName() . '.id');
            if ($campusId <= 0) {
                throw new RuntimeException('Không xác định được id của cơ sở đào tạo vừa lưu.');
            }

            $this->syncAssignedUsers($campusId, $assignedUsers);

            $db->transactionCommit();
        } catch (Exception $e) {
            $db->transactionRollback();
            $this->setError($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Xóa cơ sở đào tạo, sau khi kiểm tra các ràng buộc nghiệp vụ.
     *
     * @param   array|int  $pks
     *
     * @return  bool
     * @throws  Exception
     * @since   2.1.6
     */
    public function delete(&$pks): bool
    {
        if (!$this->isCampusManager()) {
            throw new RuntimeException('Bạn không có quyền quản trị danh mục cơ sở đào tạo.');
        }

        foreach ((array) $pks as $pk) {
            $this->assertDeletable((int) $pk);
        }

        return parent::delete($pks);
    }

    /**
     * Kiểm tra một cơ sở đào tạo có được phép xóa hay không.
     *
     * Ràng buộc khóa ngoại ở tầng CSDL là ON DELETE RESTRICT; kiểm tra ở đây
     * để trả về thông báo nghiệp vụ rõ ràng thay vì lỗi SQL.
     *
     * @param   int  $campusId
     *
     * @return  void
     * @throws  RuntimeException  Nếu không được phép xóa
     * @since   2.1.6
     */
    private function assertDeletable(int $campusId): void
    {
        if ($campusId === self::ROOT_CAMPUS_ID) {
            throw new RuntimeException('Không được phép xóa cơ sở đào tạo gốc (Cơ sở chính).');
        }

        $db = $this->getDatabase();

        foreach (self::REFERENCING_TABLES as $tableName => $label) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($tableName))
                ->where($db->quoteName('campus_id') . ' = :campusId')
                ->bind(':campusId', $campusId, \Joomla\Database\ParameterType::INTEGER);

            $count = (int) $db->setQuery($query)->loadResult();

            if ($count > 0) {
                throw new RuntimeException(sprintf(
                    'Không thể xóa: cơ sở đào tạo này đang có %d %s.',
                    $count,
                    $label
                ));
            }
        }
    }

    /**
     * Lấy danh sách id tài khoản được gán vào một cơ sở đào tạo.
     *
     * @param   int  $campusId
     *
     * @return  int[]
     * @since   2.1.6
     */
    private function getAssignedUserIds(int $campusId): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__eqa_campus_user'))
            ->where($db->quoteName('campus_id') . ' = :campusId')
            ->bind(':campusId', $campusId, \Joomla\Database\ParameterType::INTEGER);

        return array_map('intval', (array) $db->setQuery($query)->loadColumn());
    }

    /**
     * Đồng bộ mapping tài khoản — cơ sở đào tạo.
     *
     * Phải được gọi bên trong một transaction đang mở.
     *
     * @param   int    $campusId
     * @param   int[]  $userIds
     *
     * @return  void
     * @since   2.1.6
     */
    private function syncAssignedUsers(int $campusId, array $userIds): void
    {
        $db = $this->getDatabase();

        // 1. Xóa toàn bộ mapping hiện có của cơ sở này
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__eqa_campus_user'))
            ->where($db->quoteName('campus_id') . ' = :campusId')
            ->bind(':campusId', $campusId, \Joomla\Database\ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        $userIds = array_values(array_filter($userIds, static fn(int $id): bool => $id > 0));

        if (empty($userIds)) {
            return;
        }

        // 2. Chèn lại mapping theo lựa chọn mới
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__eqa_campus_user'))
            ->columns([$db->quoteName('user_id'), $db->quoteName('campus_id')]);

        foreach ($userIds as $userId) {
            $query->values((int) $userId . ', ' . $campusId);
        }

        $db->setQuery($query)->execute();
    }

    /**
     * @param   string|null  $specificAction  Không sử dụng ở model này.
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canCreate(?string $specificAction = null): bool
    {
        return $this->isCampusManager();
    }

    /**
     * @param   mixed  $record
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canEdit($record = null): bool
    {
        return $this->isCampusManager();
    }

    /**
     * @param   mixed  $record
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canDelete($record = null): bool
    {
        return $this->isCampusManager();
    }

    /**
     * Người dùng hiện tại có quyền quản trị danh mục cơ sở đào tạo hay không.
     *
     * @return  bool
     * @since   2.1.6
     */
    private function isCampusManager(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_eqa');
    }
}
