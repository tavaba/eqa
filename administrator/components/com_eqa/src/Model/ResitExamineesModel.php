<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Traits\CampusScopedList;

/**
 * List model: thí sinh thi lần hai của MỘT đợt thi lại.
 *
 * Model này CHỈ phục vụ việc hiển thị danh sách (view ResitExaminees), theo đúng
 * khuôn mẫu ExamExamineesModel đối với bảng #__eqa_exam_learner. Toàn bộ nghiệp
 * vụ đọc/ghi trên #__eqa_resit_learner (làm mới, bổ sung, thu phí, nhập sao kê,
 * xuất dữ liệu) nằm ở ResitModel — model của chính đợt thi lại.
 *
 * Đợt đang xem được xác định bởi state 'filter.resit_id' do View đặt từ tham số
 * 'resit_id' của request (cùng khuôn mẫu với ExamseasonExamsModel).
 *
 * @since 2.0.0
 */
class ResitExamineesModel extends ListModel
{
    use CampusScopedList;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'id', 'learner_code', 'academicyear', 'term',
            'has_fee', 'payment_completed', 'campus_id',
        ];
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }

    // =========================================================================
    // Phạm vi đợt thi lại
    // =========================================================================

    /**
     * Mã đợt thi lại đang xem, đọc từ state.
     *
     * Chốt chặn quyền theo cơ sở đào tạo do ResitModel::assertAccessible() đảm
     * nhiệm (View gọi trước khi nạp danh sách); ở đây chỉ cần bảo đảm truy vấn
     * luôn bị giới hạn trong đúng một đợt.
     *
     * @return  int
     * @throws  Exception  Nếu không xác định được đợt.
     * @since   2.1.8
     */
    public function getRequiredResitId(): int
    {
        $resitId = $this->getState('filter.resit_id');

        if (!is_numeric($resitId) || (int) $resitId <= 0) {
            throw new Exception('Không xác định được đợt thi lại.');
        }

        return (int) $resitId;
    }

    // =========================================================================
    // Query danh sách
    // =========================================================================

    /**
     * Xây dựng câu truy vấn danh sách thí sinh thi lần hai, kết hợp JOIN để lấy
     * thông tin người học, lớp học phần, môn học và năm học.
     *
     * Các bảng tham gia:
     *   sa → #__eqa_resit_learner  (bảng chính)
     *   lr → #__eqa_learners        (thông tin người học)
     *   cl → #__eqa_classes         (lớp học phần: academicyear_id, term)
     *   ay → #__eqa_academicyears   (mã năm học)
     *   ex → #__eqa_exams           (môn thi, để xác định subject_id)
     *   su → #__eqa_subjects        (mã và tên môn học)
     *
     * @return \Joomla\Database\QueryInterface
     * @since 2.0.2
     */
    public function getListQuery()
    {
        $db = DatabaseHelper::getDatabaseDriver();

        // Toàn bộ danh sách chỉ gồm thí sinh của MỘT danh sách thi lần 2 (2.1.8)
        $resitId = $this->getRequiredResitId();

        $columns = $db->quoteName(
            [
                'sa.id',
                'sa.class_id',
                'sa.learner_id',
                'sa.last_exam_id',
	            'sa.last_attempt',
				'el.debtor',
	            'el.anomaly',
                'sa.last_conclusion',
                'sa.payment_amount',
                'sa.payment_completed',
                'sa.payment_code',
                'sa.description',
                'lr.code',
                'lr.lastname',
                'lr.firstname',
                'su.code',
                'cl.academicyear',
                'cl.term',
            ],
            [
                'id',
                'class_id',
                'learner_id',
                'last_exam_id',
                'last_attempt',
	            'is_debtor',
	            'last_anomaly',
                'last_conclusion',
                'payment_amount',
                'payment_completed',
                'payment_code',
	            'description',
                'learner_code',
                'learner_lastname',
                'learner_firstname',
                'subject_code',
                'academicyear',
                'term',
            ]
        );

        $query = $db->getQuery(true)
            ->select($columns)
            //Tên môn học hiển thị cho quản trị viên là TÊN PHÂN BIỆT (2.1.7)
            ->select(DatabaseHelper::displayNameExpr('su') . ' AS ' . $db->quoteName('subject_name'))
            ->from($db->quoteName('#__eqa_resit_learner', 'sa'))
            ->leftJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_classes', 'cl') .
                ' ON ' . $db->quoteName('cl.id') . ' = ' . $db->quoteName('sa.class_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_exams', 'ex') .
                ' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('sa.last_exam_id')
            )
            ->leftJoin(
                $db->quoteName('#__eqa_subjects', 'su') .
                ' ON ' . $db->quoteName('su.id') . ' = ' . $db->quoteName('ex.subject_id')
            )
	        ->leftJoin($db->quoteName('#__eqa_exam_learner','el'),
		        'el.exam_id = sa.last_exam_id AND el.learner_id = sa.learner_id')
            // Giới hạn theo danh sách thi lần 2 đang mở (2.1.8)
            ->where($db->quoteName('sa.resit_id') . ' = ' . $resitId);

        // Lọc theo cơ sở đào tạo, suy diễn qua lớp học phần (2.1.6).
        // Giữ lại như chốt chặn thứ hai, dù phạm vi danh sách đã bao hàm cơ sở.
        $this->applyCampusFilter($query, 'cl.campus_id');

        // --- Filtering ---

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . trim($search) . '%');
            $query->where(
                '(' .
                $db->quoteName('lr.code') . ' LIKE ' . $like .
                ' OR CONCAT(' . $db->quoteName('lr.lastname') . ', \' \', ' . $db->quoteName('lr.firstname') . ') LIKE ' . $like .
                ' OR ' . $db->quoteName('su.code') . ' LIKE ' . $like .
                //Tìm trên cả tên chính thức lẫn tên phân biệt (2.1.7)
                ' OR ' . DatabaseHelper::displayNameSearchExpr('su', $like) .
                ' OR ' . $db->quoteName('sa.payment_code') . ' LIKE ' . $like .
                ')'
            );
        }

        $subjectId = $this->getState('filter.subject_id');
        if (is_numeric($subjectId)) {
            $query->where($db->quoteName('su.id') . ' = ' . (int) $subjectId);
        }

        $academicyearCode = $this->getState('filter.academicyear');
        if (is_numeric($academicyearCode)) {
            $query->where($db->quoteName('cl.academicyear') . ' = ' . (int) $academicyearCode);
        }

        $term = $this->getState('filter.term');
        if (is_numeric($term)) {
            $query->where($db->quoteName('cl.term') . ' = ' . (int) $term);
        }

		$isDebtor = $this->getState('filter.is_debtor');
		if(is_numeric($isDebtor)){
			$query->where($db->quoteName('el.debtor') . ' = ' . (int)$isDebtor);
		}

		$anomaly = $this->getState('filter.anomaly');
		if(is_numeric($anomaly)){
			$query->where($db->quoteName('el.anomaly') . ' = ' . (int)$anomaly);
		}

        // Filter "Có phí": 1 = payment_amount > 0; 0 = payment_amount = 0
        $hasFee = $this->getState('filter.has_fee');
        if ($hasFee === '1') {
            $query->where($db->quoteName('sa.payment_amount') . ' > 0');
        } elseif ($hasFee === '0') {
            $query->where($db->quoteName('sa.payment_amount') . ' = 0');
        }

        $paymentCompleted = $this->getState('filter.payment_completed');
        if (is_numeric($paymentCompleted)) {
            // Filter này chỉ có nghĩa với các bản ghi có phí (payment_amount > 0)
            $query->where($db->quoteName('sa.payment_amount') . ' > 0');
            $query->where($db->quoteName('sa.payment_completed') . ' = ' . (int) $paymentCompleted);
        }

        // --- Ordering ---
        $orderingCol = $db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

        return $query;
    }

    /**
     * Ẩn bộ lọc cơ sở đào tạo với người dùng không có quyền làm việc mọi cơ sở.
     *
     * @param   array  $data
     * @param   bool   $loadData
     *
     * @return  mixed
     * @since   2.1.6
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->applyCampusFilterVisibility(parent::getFilterForm($data, $loadData));
    }

}
