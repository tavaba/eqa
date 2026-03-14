<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Model\ListModel;

/**
 * Model danh sách thí sinh (người học) của một kỳ sát hạch.
 *
 * assessment_id PHẢI được set vào state trước khi gọi getItems()/getListQuery():
 *   $model->setState('filter.assessment_id', $assessmentId);
 *
 * @since 2.0.5
 */
class AssessmentLearnersModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'al.id', 'al.code', 'lr.code', 'lr.lastname', 'lr.firstname',
            'al.payment_completed', 'al.anomaly', 'al.passed', 'al.score', 'al.level',
        ];
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'lr.lastname', $direction = 'ASC'): void
    {
        parent::populateState($ordering, $direction);
    }

    // =========================================================================
    // Query
    // =========================================================================

    /**
     * Xây dựng câu truy vấn danh sách thí sinh của một kỳ sát hạch.
     *
     * @return \Joomla\Database\QueryInterface
     * @throws Exception  Nếu assessment_id chưa được set.
     * @since 2.0.5
     */
    public function getListQuery()
    {
        $assessmentId = (int) $this->getState('filter.assessment_id');
        if ($assessmentId <= 0) {
            throw new Exception('Không xác định được kỳ sát hạch (assessment_id chưa được set).');
        }

        $db = $this->getDatabase();

        $query = parent::getListQuery();
        $query
            ->from($db->quoteName('#__eqa_assessment_learner', 'al'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('al.learner_id')
            )
            ->select([
                $db->quoteName('al.id'),
                $db->quoteName('al.assessment_id'),
                $db->quoteName('al.learner_id'),
                $db->quoteName('al.code',               'examinee_code'),
                $db->quoteName('al.payment_amount'),
                $db->quoteName('al.payment_code'),
                $db->quoteName('al.payment_completed'),
                $db->quoteName('al.anomaly'),
                $db->quoteName('al.score'),
                $db->quoteName('al.level'),
                $db->quoteName('al.passed'),
                $db->quoteName('al.note'),
                $db->quoteName('lr.code',               'learner_code'),
                $db->quoteName('lr.lastname',            'learner_lastname'),
                $db->quoteName('lr.firstname',           'learner_firstname'),
            ])
            ->where($db->quoteName('al.assessment_id') . ' = ' . $assessmentId);

        // ----- Filtering -----

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . trim($search) . '%');
            $query->where(
                '(' .
                $db->quoteName('lr.code') . ' LIKE ' . $like .
                ' OR CONCAT(' .
                    $db->quoteName('lr.lastname') . ', \' \', ' .
                    $db->quoteName('lr.firstname') .
                ') LIKE ' . $like .
                ')'
            );
        }

        $paymentCompleted = $this->getState('filter.payment_completed');
        if (is_numeric($paymentCompleted)) {
            // Chỉ lọc "đã/chưa nộp" với các bản ghi có yêu cầu đóng phí
            $query->where($db->quoteName('al.payment_amount') . ' > 0');
            $query->where($db->quoteName('al.payment_completed') . ' = ' . (int) $paymentCompleted);
        }

        $anomaly = $this->getState('filter.anomaly');
        if (is_numeric($anomaly)) {
            $query->where($db->quoteName('al.anomaly') . ' = ' . (int) $anomaly);
        }

        $passed = $this->getState('filter.passed');
        if (is_numeric($passed)) {
            $query->where($db->quoteName('al.passed') . ' = ' . (int) $passed);
        }

        // ----- Ordering -----
        $orderingCol = $db->escape($this->getState('list.ordering', 'lr.lastname'));
        $orderingDir = $db->escape($this->getState('list.direction', 'ASC'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        return $query;
    }

    /**
     * @inheritDoc
     * @since 2.0.5
     */
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.assessment_id');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.payment_completed');
        $id .= ':' . $this->getState('filter.anomaly');
        $id .= ':' . $this->getState('filter.passed');
        return parent::getStoreId($id);
    }

    // =========================================================================
    // Thống kê tổng hợp cho header
    // =========================================================================

    /**
     * Trả về số liệu thống kê tổng hợp của một kỳ sát hạch.
     * Tính trên toàn bộ bản ghi của assessment đó (không bị ảnh hưởng bởi filter).
     *
     * @return object{
     *     total: int,
     *     hasFee: int,
     *     paid: int,
     *     unpaid: int,
     *     totalFeeAmount: int,
     *     collectedAmount: int,
     *     passed: int,
     *     failed: int,
     *     notYet: int
     * }
     * @since 2.0.5
     */
    public function getStatistics(): object
    {
        $assessmentId = (int) $this->getState('filter.assessment_id');
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'COUNT(1)                                                               AS ' . $db->quoteName('total'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0 THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('hasFee'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 1 THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('paid'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_amount') . ' > 0
                          AND '  . $db->quoteName('payment_completed') . ' = 0 THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('unpaid'),
                'SUM(' . $db->quoteName('payment_amount') . ')                          AS ' . $db->quoteName('totalFeeAmount'),
                'SUM(CASE WHEN ' . $db->quoteName('payment_completed') . ' = 1
                          THEN ' . $db->quoteName('payment_amount') . ' ELSE 0 END)    AS ' . $db->quoteName('collectedAmount'),
                'SUM(CASE WHEN ' . $db->quoteName('passed') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('passed'),
                'SUM(CASE WHEN ' . $db->quoteName('passed') . ' = 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('failed'),
                'SUM(CASE WHEN ' . $db->quoteName('passed') . ' IS NULL THEN 1 ELSE 0 END)
                                                                                        AS ' . $db->quoteName('notYet'),
            ])
            ->from($db->quoteName('#__eqa_assessment_learner'))
            ->where($db->quoteName('assessment_id') . ' = ' . $assessmentId);

        $db->setQuery($query);
        return $db->loadObject();
    }
}
