<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\CampusListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

/**
 * List model của 'danh sách thi lần hai'.
 *
 * Mỗi bản ghi là một danh sách thí sinh thi lần 2, kèm số liệu thống kê tổng
 * hợp của các thí sinh thuộc danh sách đó (tổng số lượt, số miễn phí, số phải
 * nộp phí, số đã nộp, số chưa nộp).
 *
 * @since 2.1.8
 */
class ResitsModel extends CampusListModel
{
    /**
     * @param   array                     $config
     * @param   MVCFactoryInterface|null  $factory
     *
     * @since 2.1.8
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'id', 'name', 'active', 'created_at', 'campus_id', 'campus_name',
            'total_attempts', 'total_learners', 'total_free',
            'total_paid_required', 'total_paid', 'total_unpaid',
        ];

        parent::__construct($config, $factory);
    }

    /**
     * Mặc định sắp xếp theo thời điểm tạo, mới nhất lên trước.
     *
     * @param   string  $ordering
     * @param   string  $direction
     *
     * @return  void
     * @since   2.1.8
     */
    protected function populateState($ordering = 'created_at', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * @return  string
     * @since   2.1.8
     */
    protected function getCampusColumn(): string
    {
        return 'a.campus_id';
    }

    /**
     * Xây dựng truy vấn danh sách.
     *
     * Số liệu thống kê được lấy qua một truy vấn con GROUP BY resit_id (một lần
     * quét bảng thí sinh cho toàn bộ danh sách) thay vì truy vấn tương quan cho
     * từng dòng.
     *
     * @return  \Joomla\Database\QueryInterface
     * @since   2.1.8
     */
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        // Truy vấn con: thống kê thí sinh theo từng danh sách
        $statQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('sa.resit_id',    'resit_id'),
                'COUNT(1) AS ' . $db->quoteName('total_attempts'),
                'COUNT(DISTINCT ' . $db->quoteName('sa.learner_id') . ')'
                . ' AS ' . $db->quoteName('total_learners'),
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' = 0'
                . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('total_free'),
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('total_paid_required'),
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' AND ' . $db->quoteName('sa.payment_completed') . ' = 1'
                . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('total_paid'),
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' AND ' . $db->quoteName('sa.payment_completed') . ' = 0'
                . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('total_unpaid'),
            ])
            ->from($db->quoteName('#__eqa_resit_learner', 'sa'))
            ->group($db->quoteName('sa.resit_id'));

        $columns = [
            $db->quoteName('a.id',          'id'),
            $db->quoteName('a.name',        'name'),
            $db->quoteName('a.active',      'active'),
            $db->quoteName('a.description', 'description'),
            $db->quoteName('a.state',       'state'),
            $db->quoteName('a.created_at',  'created_at'),
            $db->quoteName('a.campus_id',   'campus_id'),
            $db->quoteName('c.name',        'campus_name'),
        ];

        $query = $db->getQuery(true)
            ->select($columns)
            ->select([
                'COALESCE(' . $db->quoteName('s.total_attempts') . ', 0)'
                . ' AS ' . $db->quoteName('total_attempts'),
                'COALESCE(' . $db->quoteName('s.total_learners') . ', 0)'
                . ' AS ' . $db->quoteName('total_learners'),
                'COALESCE(' . $db->quoteName('s.total_free') . ', 0)'
                . ' AS ' . $db->quoteName('total_free'),
                'COALESCE(' . $db->quoteName('s.total_paid_required') . ', 0)'
                . ' AS ' . $db->quoteName('total_paid_required'),
                'COALESCE(' . $db->quoteName('s.total_paid') . ', 0)'
                . ' AS ' . $db->quoteName('total_paid'),
                'COALESCE(' . $db->quoteName('s.total_unpaid') . ', 0)'
                . ' AS ' . $db->quoteName('total_unpaid'),
            ])
            ->from($db->quoteName('#__eqa_resits', 'a'))
            ->leftJoin(
                $db->quoteName('#__eqa_campuses', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.campus_id')
            )
            ->leftJoin(
                '(' . $statQuery . ') AS ' . $db->quoteName('s')
                . ' ON ' . $db->quoteName('s.resit_id') . ' = ' . $db->quoteName('a.id')
            );

        // Lọc theo cơ sở đào tạo
        $this->applyCampusScope($query);

        // Lọc theo trạng thái
        $this->applyStateFilter($query);

        // --- Filtering ---
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            $like = $db->quote('%' . trim($search) . '%');
            $query->where($db->quoteName('a.name') . ' LIKE ' . $like);
        }

        $active = $this->getState('filter.active');

        if (is_numeric($active)) {
            $query->where($db->quoteName('a.active') . ' = ' . (int) $active);
        }

        // --- Ordering ---
        $orderingCol = $db->escape($this->getState('list.ordering', 'created_at'));
        $orderingDir = $db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        return $query;
    }
}
