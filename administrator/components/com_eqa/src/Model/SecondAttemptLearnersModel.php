<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\ListModel;

/**
 * Model danh sách người học có trong bảng #__eqa_secondattempts,
 * kèm thống kê số môn thi theo từng người học.
 *
 * Mỗi bản ghi trong kết quả tương ứng một người học (learner_id duy nhất),
 * với các cột: learner_id, learner_code, learner_lastname, learner_firstname,
 * total_subjects, total_free, total_paid_required, total_paid, total_unpaid.
 *
 * @since 2.0.5
 */
class SecondAttemptLearnersModel extends ListModel
{
    /**
     * @param array                    $config
     * @param MVCFactoryInterface|null $factory
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        // Khai báo các cột được phép sort
        $config['filter_fields'] = [
            'learner_code',
            'learner_firstname',
            'total_subjects',
            'total_free',
            'total_paid_required',
            'total_paid',
            'total_unpaid',
        ];
        parent::__construct($config, $factory);
    }

    /**
     * Mặc định sắp xếp theo tên người học tăng dần.
     *
     * @param  string $ordering
     * @param  string $direction
     * @return void
     */
    protected function populateState($ordering = 'learner_firstname', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }

    // =========================================================================
    // Query
    // =========================================================================

    /**
     * Xây dựng câu truy vấn danh sách người học, GROUP BY learner_id,
     * kèm các cột thống kê số môn thi được tính bằng COUNT/SUM…CASE WHEN.
     *
     * Các bảng tham gia:
     *   sa → #__eqa_secondattempts  (bảng chính)
     *   lr → #__eqa_learners        (thông tin người học)
     *
     * Cột total_subjects được tính trong SELECT để Joomla có thể
     * ORDER BY trực tiếp trên alias này.
     *
     * @return \Joomla\Database\QueryInterface
     * @since 2.0.5
     */
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('sa.learner_id',    'learner_id'),
                $db->quoteName('lr.code',          'learner_code'),
                $db->quoteName('lr.lastname',      'learner_lastname'),
                $db->quoteName('lr.firstname',     'learner_firstname'),

                // Tổng số môn thi — dùng làm cột sort
                'COUNT(' . $db->quoteName('sa.id') . ')'
                . ' AS ' . $db->quoteName('total_subjects'),

                // Số môn miễn phí (payment_amount = 0)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' = 0'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_free'),

                // Số môn phải đóng phí (payment_amount > 0)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_paid_required'),

                // Số môn đã nộp phí (payment_amount > 0 AND payment_completed = 1)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' AND ' . $db->quoteName('sa.payment_completed') . ' = 1'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_paid'),

                // Số môn chưa nộp phí (payment_amount > 0 AND payment_completed = 0)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' AND ' . $db->quoteName('sa.payment_completed') . ' = 0'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_unpaid'),
            ])
            ->from($db->quoteName('#__eqa_secondattempts', 'sa'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr')
                . ' ON ' . $db->quoteName('lr.id')
                . ' = '  . $db->quoteName('sa.learner_id')
            )
            ->group([
                $db->quoteName('sa.learner_id'),
                $db->quoteName('lr.code'),
                $db->quoteName('lr.lastname'),
                $db->quoteName('lr.firstname'),
            ]);

        // Ordering — sử dụng alias được khai báo trong SELECT
        $orderingCol = $db->escape($this->getState('list.ordering', 'learner_firstname'));
        $orderingDir = $db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        // Sắp xếp phụ theo họ và mã HVSV để kết quả ổn định
        if ($orderingCol !== 'learner_code') {
            $query->order($db->quoteName('lr.lastname') . ' ' . $orderingDir);
        }
        if (!in_array($orderingCol, ['learner_code', 'learner_firstname'], true)) {
            $query->order($db->quoteName('learner_firstname') . ' asc');
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('list.ordering');
        $id .= ':' . $this->getState('list.direction');
        return parent::getStoreId($id);
    }
}
