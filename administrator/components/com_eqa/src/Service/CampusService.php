<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Service
 *
 * @copyright   (C) 2026 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * Service quản lý 'cơ sở đào tạo' (campus).
 *
 * Chịu trách nhiệm:
 *  - cung cấp danh mục cơ sở đào tạo và tham số cấu hình riêng của từng cơ sở;
 *  - xác định các cơ sở mà người dùng hiện tại được phép thao tác;
 *  - quản lý 'cơ sở đang làm việc' (active campus) trong phiên làm việc;
 *  - cung cấp chốt chặn quyền cho tầng Model.
 *
 * Cách dùng:
 *   $campusService = ComponentHelper::getComponent()->getCampusService();
 *   $campusId      = $campusService->getActiveCampusId();
 *
 * Toàn bộ dữ liệu được cache trong phạm vi một request.
 *
 * @since 2.1.6
 */
class CampusService
{
    /**
     * Khóa lưu active campus trong user state.
     *
     * @since 2.1.6
     */
    public const STATE_KEY = 'com_eqa.activecampus';

    /**
     * Action trong ACL cho phép thao tác trên dữ liệu của mọi cơ sở đào tạo.
     *
     * @since 2.1.6
     */
    public const ACTION_ALL_CAMPUSES = 'com.manage.all_campuses';

    /**
     * @var DatabaseInterface
     * @since 2.1.6
     */
    private DatabaseInterface $db;

    /**
     * Cache danh mục cơ sở đào tạo: id => object{id, code, name, state}.
     *
     * @var array<int, object>|null
     * @since 2.1.6
     */
    private ?array $campuses = null;

    /**
     * Cache tham số cấu hình theo từng cơ sở: campusId => Registry.
     *
     * @var array<int, Registry>
     * @since 2.1.6
     */
    private array $campusParams = [];

    /**
     * Cache danh sách cơ sở được phép theo user: userId => int[].
     *
     * @var array<int, int[]>
     * @since 2.1.6
     */
    private array $userCampusIds = [];

    /**
     * Cache active campus của request hiện tại.
     *
     * @var int|null
     * @since 2.1.6
     */
    private ?int $activeCampusId = null;

    /**
     * @param   DatabaseInterface  $db  Database driver, được inject qua DI.
     *
     * @since 2.1.6
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // Danh mục cơ sở đào tạo
    // =========================================================================

    /**
     * Danh mục các cơ sở đào tạo đang được kích hoạt.
     *
     * @return  array<int, object>  Mảng id => object{id, code, name}
     * @since   2.1.6
     */
    public function getAllCampuses(): array
    {
        if ($this->campuses !== null) {
            return $this->campuses;
        }

        $query = $this->db->getQuery(true)
            ->select(
                [
                    $this->db->quoteName('id'),
                    $this->db->quoteName('code'),
                    $this->db->quoteName('name'),
                    $this->db->quoteName('params'),
                ]
            )
            ->from($this->db->quoteName('#__eqa_campuses'))
            ->where($this->db->quoteName('state') . ' = ' . StateHelper::STATE_PUBLISHED)
            ->order($this->db->quoteName('ordering') . ' ASC');

        $rows = $this->db->setQuery($query)->loadObjectList('id');

        $this->campuses = [];
        foreach ($rows as $id => $row) {
            // Tách params ra khỏi danh mục để cache riêng, tránh nạp lại từ CSDL
            $this->campusParams[(int) $id] = new Registry($row->params ?: '{}');
            unset($row->params);

            $this->campuses[(int) $id] = $row;
        }

        return $this->campuses;
    }

    /**
     * Thông tin một cơ sở đào tạo.
     *
     * @param   int  $campusId
     *
     * @return  object|null
     * @since   2.1.6
     */
    public function getCampus(int $campusId): ?object
    {
        return $this->getAllCampuses()[$campusId] ?? null;
    }

    /**
     * Tên một cơ sở đào tạo (chuỗi rỗng nếu không tồn tại).
     *
     * @param   int  $campusId
     *
     * @return  string
     * @since   2.1.6
     */
    public function getCampusName(int $campusId): string
    {
        return $this->getCampus($campusId)->name ?? '';
    }

    /**
     * Tham số cấu hình riêng của một cơ sở đào tạo.
     *
     * Trả về Registry rỗng nếu cơ sở chưa được cấu hình — khi đó phía gọi
     * (ConfigService) sẽ dùng giá trị mặc định.
     *
     * @param   int  $campusId
     *
     * @return  Registry
     * @since   2.1.6
     */
    public function getCampusParams(int $campusId): Registry
    {
        // getAllCampuses() nạp luôn params vào cache
        $this->getAllCampuses();

        return $this->campusParams[$campusId] ?? new Registry('{}');
    }

    // =========================================================================
    // Quyền theo cơ sở đào tạo
    // =========================================================================

    /**
     * Người dùng có quyền thao tác trên dữ liệu của mọi cơ sở đào tạo hay không.
     *
     * @param   int|null  $userId  Mặc định: người dùng hiện tại.
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canAccessAllCampuses(?int $userId = null): bool
    {
        $user = $userId === null
            ? Factory::getApplication()->getIdentity()
            : Factory::getUser($userId);

        if (empty($user)) {
            return false;
        }

        // Người có quyền quản trị component đương nhiên thao tác được mọi cơ sở
        return $user->authorise('core.admin', 'com_eqa')
            || $user->authorise(self::ACTION_ALL_CAMPUSES, 'com_eqa');
    }

    /**
     * Danh sách id các cơ sở đào tạo mà người dùng được phép thao tác.
     *
     * @param   int|null  $userId  Mặc định: người dùng hiện tại.
     *
     * @return  int[]
     * @since   2.1.6
     */
    public function getUserCampusIds(?int $userId = null): array
    {
        if ($userId === null) {
            $userId = (int) Factory::getApplication()->getIdentity()->id;
        }

        if (isset($this->userCampusIds[$userId])) {
            return $this->userCampusIds[$userId];
        }

        if ($this->canAccessAllCampuses($userId)) {
            $this->userCampusIds[$userId] = array_keys($this->getAllCampuses());

            return $this->userCampusIds[$userId];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('campus_id'))
            ->from($this->db->quoteName('#__eqa_campus_user'))
            ->where($this->db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER);

        $ids = array_map('intval', (array) $this->db->setQuery($query)->loadColumn());

        // Loại các cơ sở đã bị ngừng kích hoạt
        $activeCampusIds = array_keys($this->getAllCampuses());

        $this->userCampusIds[$userId] = array_values(array_intersect($ids, $activeCampusIds));

        return $this->userCampusIds[$userId];
    }

    /**
     * Người dùng hiện tại có được phép thao tác trên dữ liệu của cơ sở này không.
     *
     * Đây là chốt chặn dùng trong các thao tác ghi của tầng Model.
     *
     * @param   int       $campusId
     * @param   int|null  $userId    Mặc định: người dùng hiện tại.
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canManageCampus(int $campusId, ?int $userId = null): bool
    {
        return in_array($campusId, $this->getUserCampusIds($userId), true);
    }

    // =========================================================================
    // Cơ sở đang làm việc (active campus)
    // =========================================================================

    /**
     * Id của cơ sở đào tạo mà người dùng đang làm việc.
     *
     * Giá trị đọc từ user state luôn được kiểm tra lại với danh sách cơ sở
     * được phép tại thời điểm sử dụng; nếu không hợp lệ thì lấy cơ sở đầu tiên
     * trong danh sách. Trả về 0 nếu người dùng không thuộc cơ sở nào.
     *
     * @return  int
     * @since   2.1.6
     */
    public function getActiveCampusId(): int
    {
        if ($this->activeCampusId !== null) {
            return $this->activeCampusId;
        }

        $allowed = $this->getUserCampusIds();

        if (empty($allowed)) {
            $this->activeCampusId = 0;

            return 0;
        }

        $stored = (int) Factory::getApplication()->getUserState(self::STATE_KEY, 0);

        $this->activeCampusId = in_array($stored, $allowed, true)
            ? $stored
            : (int) reset($allowed);

        return $this->activeCampusId;
    }

    /**
     * Đặt cơ sở đào tạo đang làm việc.
     *
     * @param   int  $campusId
     *
     * @return  bool  true nếu đặt thành công; false nếu không được phép.
     * @since   2.1.6
     */
    public function setActiveCampusId(int $campusId): bool
    {
        if (!$this->canManageCampus($campusId)) {
            return false;
        }

        Factory::getApplication()->setUserState(self::STATE_KEY, $campusId);
        $this->activeCampusId = $campusId;

        return true;
    }

    /**
     * Người dùng có nhiều hơn một cơ sở để lựa chọn hay không.
     *
     * Dùng để quyết định hiển thị dropdown chuyển cơ sở trên giao diện.
     *
     * @return  bool
     * @since   2.1.6
     */
    public function hasMultipleCampuses(): bool
    {
        return count($this->getUserCampusIds()) > 1;
    }
}
