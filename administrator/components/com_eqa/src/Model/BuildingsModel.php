<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\CampusListModel;

/**
 * List model của 'tòa nhà' (building).
 *
 * Từ 2.1.6, tòa nhà được quản lý riêng theo từng cơ sở đào tạo.
 *
 * @since 1.0.0
 */
class BuildingsModel extends CampusListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = array('code', 'published', 'ordering', 'campus_id', 'campus_name');
        parent::__construct($config, $factory);
    }

    /**
     * @since 2.1.6
     */
    protected function getCampusColumn(): string
    {
        return 'a.campus_id';
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__eqa_buildings', 'a'))
            ->select($db->quoteName('a') . '.*')
            ->select($db->quoteName('c.name', 'campus_name'))
            ->leftJoin(
                $db->quoteName('#__eqa_campuses', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.campus_id')
            );

        // Lọc theo cơ sở đào tạo (2.1.6)
        $this->applyCampusScope($query);

        $orderingCol = $db->escape($this->getState('list.ordering', 'code'));
        $orderingDir = $db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        return $query;
    }
}
