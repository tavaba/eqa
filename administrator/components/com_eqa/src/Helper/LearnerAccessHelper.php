<?php

/**
 * @package     Kma.Component.Eqa
 * @subpackage  Administrator.Helper
 *
 * @copyright   (C) 2026 KMA. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Component\Eqa\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\Database\ParameterType;
use Kma\Library\Kma\Helper\ComponentHelper;
use RuntimeException;

/**
 * Kiểm soát quyền xem thông tin của MỘT người học cụ thể.
 *
 * Dùng chung cho các màn hình xem thông tin cá nhân người học (bảng điểm thi,
 * danh sách lớp học phần...), vốn chạy ở HAI ngữ cảnh:
 *
 *  1. Front-end — người học tự xem thông tin của chính mình.
 *  2. Back-end  — quản trị viên xem thông tin của một thí sinh.
 *
 * Hai ngữ cảnh có hai cơ chế bảo mật khác nhau; áp nhầm sẽ chặn nhầm chính chủ.
 *
 * @since 2.1.6
 */
abstract class LearnerAccessHelper
{
    /**
     * Khẳng định người dùng hiện tại được phép xem thông tin của người học này.
     *
     * Thứ tự xét:
     *  - Quản trị viên (core.manage / eqa.supervise):
     *      + có quyền mọi cơ sở  → cho phép;
     *      + ngược lại           → chỉ khi người học thuộc cơ sở đào tạo mà
     *                              quản trị viên được phép (learner → group →
     *                              group.campus_id).
     *  - Không phải quản trị viên (front-end self-service):
     *      + chỉ khi mã người học trùng với mã của tài khoản đang đăng nhập.
     *
     * @param   int  $learnerId
     *
     * @return  void
     * @throws  RuntimeException  Nếu không được phép
     * @since   2.1.6
     */
    public static function assertCanViewLearner(int $learnerId): void
    {
        if ($learnerId <= 0) {
            throw new RuntimeException('Không xác định được người học cần xem.');
        }

        // ----- Nhánh quản trị viên -----
        if (GeneralHelper::checkPermissions(['core.manage', 'eqa.supervise'])) {
            $campusService = ComponentHelper::getComponent()->getCampusService();

            // Quyền toàn Học viện → xem được mọi người học
            if ($campusService->canAccessAllCampuses()) {
                return;
            }

            $learnerCampusId = self::getLearnerCampusId($learnerId);

            if ($learnerCampusId <= 0 || !$campusService->canManageCampus($learnerCampusId)) {
                throw new RuntimeException(
                    'Người học này không thuộc cơ sở đào tạo mà bạn quản lý.'
                    . ' Bạn không có quyền xem thông tin của người học này.'
                );
            }

            return;
        }

        // ----- Nhánh front-end (người học tự xem) -----
        $signedInCode = GeneralHelper::getSignedInLearnerCode();

        if (empty($signedInCode) || $signedInCode !== self::getLearnerCode($learnerId)) {
            throw new RuntimeException('Bạn chỉ có thể xem thông tin của chính mình.');
        }
    }

    /**
     * Cơ sở đào tạo của một người học, suy qua lớp hành chính.
     *
     * learner → group → group.campus_id.
     *
     * @param   int  $learnerId
     *
     * @return  int  0 nếu không xác định được
     * @since   2.1.6
     */
    private static function getLearnerCampusId(int $learnerId): int
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $query = $db->getQuery(true)
            ->select($db->quoteName('g.campus_id'))
            ->from($db->quoteName('#__eqa_learners', 'l'))
            ->leftJoin($db->quoteName('#__eqa_groups', 'g') . ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('l.group_id'))
            ->where($db->quoteName('l.id') . ' = :lid')
            ->bind(':lid', $learnerId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Mã của một người học.
     *
     * @param   int  $learnerId
     *
     * @return  string
     * @since   2.1.6
     */
    private static function getLearnerCode(int $learnerId): string
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $query = $db->getQuery(true)
            ->select($db->quoteName('code'))
            ->from($db->quoteName('#__eqa_learners'))
            ->where($db->quoteName('id') . ' = :lid')
            ->bind(':lid', $learnerId, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }
}
