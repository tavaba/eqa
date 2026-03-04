<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\ListModel;

/**
 * Model danh sách môn thi có trong bảng #__eqa_secondattempts,
 * kèm thống kê số thí sinh theo từng môn.
 *
 * Mỗi bản ghi trong kết quả tương ứng một môn thi (last_exam_id duy nhất),
 * với các cột: exam_id, exam_code, exam_name, total_examinees,
 * total_free, total_paid_required, total_paid, total_unpaid.
 *
 * @since 2.0.5
 */
class SecondAttemptSubjectsModel extends ListModel
{
    /**
     * @param array                   $config
     * @param MVCFactoryInterface|null $factory
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        // Khai báo các cột được phép sort để Joomla không bỏ qua giá trị ordering
        $config['filter_fields'] = [
            'exam_code',
            'exam_name',
            'total_examinees',
            'total_free',
            'total_paid_required',
            'total_paid',
            'total_unpaid',
        ];
        parent::__construct($config, $factory);
    }

    /**
     * Mặc định sắp xếp theo mã môn thi tăng dần.
     *
     * @param  string $ordering
     * @param  string $direction
     * @return void
     */
    protected function populateState($ordering = 'exam_code', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }

    // =========================================================================
    // Query
    // =========================================================================

    /**
     * Xây dựng câu truy vấn danh sách môn thi, GROUP BY last_exam_id,
     * kèm các cột thống kê được tính bằng COUNT/SUM…CASE WHEN.
     *
     * Các bảng tham gia:
     *   sa → #__eqa_secondattempts  (bảng chính)
     *   ex → #__eqa_exams           (môn thi)
     *   su → #__eqa_subjects        (môn học — lấy code và name)
     *
     * Cột total_examinees được tính trong SELECT (không phải subquery)
     * để Joomla có thể ORDER BY trực tiếp trên alias này.
     *
     * @return \Joomla\Database\QueryInterface
     * @since 2.0.5
     */
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('sa.last_exam_id', 'exam_id'),
                $db->quoteName('su.code',         'exam_code'),
                $db->quoteName('su.name',         'exam_name'),

                // Tổng số thí sinh — dùng làm cột sort
                'COUNT(' . $db->quoteName('sa.id') . ')'
                . ' AS ' . $db->quoteName('total_examinees'),

                // Thí sinh miễn phí (payment_amount = 0)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' = 0'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_free'),

                // Thí sinh phải đóng phí (payment_amount > 0)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_paid_required'),

                // Đã nộp phí (payment_amount > 0 AND payment_completed = 1)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' AND ' . $db->quoteName('sa.payment_completed') . ' = 1'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_paid'),

                // Chưa nộp phí (payment_amount > 0 AND payment_completed = 0)
                'SUM(CASE WHEN ' . $db->quoteName('sa.payment_amount') . ' > 0'
                . ' AND ' . $db->quoteName('sa.payment_completed') . ' = 0'
                . ' THEN 1 ELSE 0 END)'
                . ' AS ' . $db->quoteName('total_unpaid'),
            ])
            ->from($db->quoteName('#__eqa_secondattempts', 'sa'))
            ->leftJoin(
                $db->quoteName('#__eqa_exams', 'ex')
                . ' ON ' . $db->quoteName('ex.id')
                . ' = ' . $db->quoteName('sa.last_exam_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_subjects', 'su')
                . ' ON ' . $db->quoteName('su.id')
                . ' = ' . $db->quoteName('ex.subject_id')
            )
            ->group([
                $db->quoteName('sa.last_exam_id'),
                $db->quoteName('ex.id'),
                $db->quoteName('su.code'),
                $db->quoteName('su.name'),
            ]);

        // Ordering — sử dụng alias được khai báo trong SELECT
        $orderingCol = $db->escape($this->getState('list.ordering', 'exam_code'));
        $orderingDir = $db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

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
