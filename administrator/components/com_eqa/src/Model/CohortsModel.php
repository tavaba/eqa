<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Traits\CampusScopedList;

/**
 * List model của 'nhóm người học' (cohort).
 *
 * Nhóm là dữ liệu DÙNG CHUNG, quản lý tương tự lớp hành chính (group): cột
 * campus_id chỉ là thuộc tính thông tin cho biết nhóm thuộc cơ sở đào tạo nào,
 * KHÔNG dùng để chặn quyền và KHÔNG lọc mặc định theo cơ sở đang làm việc.
 *
 * @since 1.0.0
 */
class CohortsModel extends ListModel
{
    use CampusScopedList;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = array('id','code','name','size','campus_id','campus_name');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $subQuerySize = $db->getQuery(true)
            ->select('COUNT(1)')
            ->from('#__eqa_cohort_learner AS z')
            ->where('z.cohort_id = a.id');
        $columns = $db->quoteName(
            array('a.id', 'a.code', 'a.name', 'a.published', 'c.name'),
            array('id',   'code',   'name',   'published',   'campus_name')
        );
        $query =  $db->getQuery(true)
            ->from('#__eqa_cohorts AS a')
            ->leftJoin('#__eqa_campuses AS c', 'a.campus_id = c.id')
            ->select($columns)
            ->select('(' . $subQuerySize . ') AS ' . $db->quoteName('size'));

        // Bộ lọc cơ sở đào tạo dạng tùy chọn (2.1.6)
        $this->applyOptionalCampusFilter($query, 'a.campus_id');

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('(a.code LIKE ' . $like . ' OR a.name LIKE ' . $like . ')');
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
}
