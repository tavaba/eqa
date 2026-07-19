<?php

namespace Kma\Component\Eqa\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Access\Access;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Danh sách chọn tài khoản người dùng để gán vào một 'cơ sở đào tạo'.
 *
 * Chỉ liệt kê các tài khoản CÓ QUYỀN ĐĂNG NHẬP TRANG QUẢN TRỊ ('core.login.admin'),
 * vì hệ thống còn có tài khoản của người học — liệt kê toàn bộ #__users sẽ khiến
 * danh sách không sử dụng được.
 *
 * Cách xác định: duyệt các nhóm người dùng của Joomla, giữ lại nhóm được cấp
 * quyền 'core.login.admin', rồi lấy các tài khoản (chưa bị khóa) thuộc các nhóm đó.
 *
 * @since 2.1.6
 */
class CampusUsersField extends ListField
{
    /**
     * @var string
     * @since 2.1.6
     */
    protected $type = 'campusUsers';

    /**
     * @return  array  Mảng các option của HTMLHelper
     * @since   2.1.6
     */
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $db      = $this->getDatabase();

        // 1. Các nhóm người dùng được phép đăng nhập trang quản trị
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__usergroups'));

        $allGroupIds  = array_map('intval', (array) $db->setQuery($query)->loadColumn());
        $adminGroupIds = [];

        foreach ($allGroupIds as $groupId) {
            if (Access::checkGroup($groupId, 'core.login.admin')) {
                $adminGroupIds[] = $groupId;
            }
        }

        if (empty($adminGroupIds)) {
            return $options;
        }

        // 2. Các tài khoản thuộc những nhóm đó
        $query = $db->getQuery(true)
            ->select(
                [
                    'DISTINCT ' . $db->quoteName('u.id'),
                    $db->quoteName('u.name'),
                    $db->quoteName('u.username'),
                ]
            )
            ->from($db->quoteName('#__users', 'u'))
            ->innerJoin(
                $db->quoteName('#__user_usergroup_map', 'm')
                . ' ON ' . $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id')
            )
            ->where($db->quoteName('m.group_id') . ' IN (' . implode(',', $adminGroupIds) . ')')
            ->where($db->quoteName('u.block') . ' = 0')
            ->order($db->quoteName('u.name') . ' ASC');

        $users = $db->setQuery($query)->loadObjectList();

        foreach ($users as $user) {
            $options[] = HTMLHelper::_(
                'select.option',
                $user->id,
                sprintf('%s (%s)', $user->name, $user->username)
            );
        }

        return $options;
    }
}
