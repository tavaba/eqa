<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Kma\Component\Eqa\Administrator\Base\ListModel;

/**
 * List model của danh mục 'cơ sở đào tạo' (campus).
 *
 * Danh mục này chỉ được quản lý bởi người dùng có quyền 'core.admin' đối với
 * com_eqa (tương đương quyền vào màn hình Options của component).
 *
 * @since 2.1.6
 */
class CampusesModel extends ListModel
{
    /**
     * @param   array                      $config
     * @param   MVCFactoryInterface|null   $factory
     *
     * @since 2.1.6
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = ['code', 'name', 'published', 'ordering'];
        parent::__construct($config, $factory);
    }

    /**
     * Truy vấn danh sách cơ sở đào tạo, kèm số lượng tài khoản được gán.
     *
     * @return  DatabaseQuery
     * @since   2.1.6
     */
    public function getListQuery()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('a') . '.*',
                    '(SELECT COUNT(*) FROM ' . $db->quoteName('#__eqa_campus_user', 'cu')
                        . ' WHERE ' . $db->quoteName('cu.campus_id') . ' = ' . $db->quoteName('a.id')
                        . ') AS ' . $db->quoteName('userCount'),
                ]
            )
            ->from($db->quoteName('#__eqa_campuses', 'a'));

        // Lọc theo từ khóa tìm kiếm (ký hiệu hoặc tên)
        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $searchValue = $db->quote('%' . str_replace(' ', '%', $search) . '%');
            $query->where(
                '(' . $db->quoteName('a.code') . ' LIKE ' . $searchValue
                . ' OR ' . $db->quoteName('a.name') . ' LIKE ' . $searchValue . ')'
            );
        }

        // Lọc theo trạng thái
        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        $orderingCol = $db->escape($this->getState('list.ordering', 'ordering'));
        $orderingDir = $db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        return $query;
    }

    /**
     * Chỉ người dùng có quyền 'core.admin' được tạo mới cơ sở đào tạo.
     *
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
     * @param   array  $items
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canEditAny(array $items): bool
    {
        return !empty($items) && $this->isCampusManager();
    }

    /**
     * @param   array  $items
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canEditStateAny(array $items): bool
    {
        return !empty($items) && $this->isCampusManager();
    }

    /**
     * @param   array  $items
     *
     * @return  bool
     * @since   2.1.6
     */
    public function canDeleteAny(array $items): bool
    {
        return !empty($items) && $this->isCampusManager();
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
