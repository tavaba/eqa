<?php

namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Model\BaseModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

/**
 * Model front-end cho chức năng "Thi lại".
 *
 * Lấy danh sách môn thi lần hai của người học từ #__eqa_secondattempts,
 * kết hợp JOIN để lấy thông tin điểm số và môn học.
 *
 * Số tiền lệ phí (`payment_amount`) được đọc trực tiếp từ bảng
 * #__eqa_secondattempts — đã được tính và lưu khi tạo bản ghi (không tính lại).
 *
 * @since 2.0.2
 */
class LearnerretakeModel extends BaseModel
{
    /**
     * Lấy danh sách môn thi lại của người học, kèm thông tin điểm và phí.
     *
     * Các bảng tham gia:
     *   sa   → #__eqa_secondattempts  (bảng chính)
     *   lr   → #__eqa_learners        (để tra learner_id từ code)
     *   cl   → #__eqa_classes         (lớp học phần)
     *   cl_l → #__eqa_class_learner   (pam1, pam2, pam)
     *   ex   → #__eqa_exams           (môn thi → subject_id)
     *   su   → #__eqa_subjects        (mã, tên, số tín chỉ môn học)
     *   el   → #__eqa_exam_learner    (điểm thi, điểm HP, kết luận)
     *   ay   → #__eqa_academicyears   (mã năm học, để hiển thị)
     *
     * @param  string   $learnerCode  Mã người học.
     * @return object[]               Danh sách bản ghi, mỗi bản ghi đã có thêm trường:
     *                                feeAmount (float), feeLabel (string).
     * @throws Exception
     * @since  2.0.2
     */
    public function getRetakeList(string $learnerCode): array
    {
        $db = DatabaseHelper::getDatabaseDriver();

        $columns = $db->quoteName(
            [
                'sa.id',
                'sa.class_id',
                'sa.learner_id',
                'sa.last_exam_id',
                'sa.last_attempt',
                'sa.last_conclusion',
                'sa.payment_amount',
                'sa.payment_completed',
                'sa.payment_code',
                'su.code',
                'su.name',
                'su.credits',
                'cl_l.pam1',
                'cl_l.pam2',
                'cl_l.pam',
                'el.mark_orig',
                'el.module_mark',
                'el.conclusion',
                'ay.code',
                'cl.term',
            ],
            [
                'id',
                'class_id',
                'learner_id',
                'last_exam_id',
                'last_attempt',
                'last_conclusion',
                'payment_amount',
                'payment_completed',
                'payment_code',
                'subject_code',
                'subject_name',
                'credits',
                'pam1',
                'pam2',
                'pam',
                'mark_orig',
                'module_mark',
                'conclusion',
                'academicyear',
                'term',
            ]
        );

        $query = $db->getQuery(true)
            ->select($columns)
            ->from($db->quoteName('#__eqa_secondattempts', 'sa'))
            // Lấy learner_id theo learner code
            ->innerJoin(
                $db->quoteName('#__eqa_learners', 'lr') .
                ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id') .
                ' AND ' . $db->quoteName('lr.code') . ' = ' . $db->quote($learnerCode)
            )
            // Lớp học phần
            ->leftJoin(
                $db->quoteName('#__eqa_classes', 'cl') .
                ' ON ' . $db->quoteName('cl.id') . ' = ' . $db->quoteName('sa.class_id')
            )
            // Năm học
            ->leftJoin(
                $db->quoteName('#__eqa_academicyears', 'ay') .
                ' ON ' . $db->quoteName('ay.id') . ' = ' . $db->quoteName('cl.academicyear_id')
            )
            // Điểm quá trình của người học trong lớp học phần
            ->leftJoin(
                $db->quoteName('#__eqa_class_learner', 'cl_l') .
                ' ON ' . $db->quoteName('cl_l.class_id') . ' = ' . $db->quoteName('sa.class_id') .
                ' AND ' . $db->quoteName('cl_l.learner_id') . ' = ' . $db->quoteName('sa.learner_id')
            )
            // Môn thi → subject_id
            ->leftJoin(
                $db->quoteName('#__eqa_exams', 'ex') .
                ' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('sa.last_exam_id')
            )
            // Môn học
            ->leftJoin(
                $db->quoteName('#__eqa_subjects', 'su') .
                ' ON ' . $db->quoteName('su.id') . ' = ' . $db->quoteName('ex.subject_id')
            )
            // Điểm thi, điểm học phần, kết luận của lần thi gần nhất
            ->leftJoin(
                $db->quoteName('#__eqa_exam_learner', 'el') .
                ' ON ' . $db->quoteName('el.exam_id') . ' = ' . $db->quoteName('sa.last_exam_id') .
                ' AND ' . $db->quoteName('el.learner_id') . ' = ' . $db->quoteName('sa.learner_id')
            )
            ->order($db->quoteName('su.code') . ' ASC');

        $db->setQuery($query);
        $items = $db->loadObjectList();

        if (empty($items)) {
            return [];
        }

        // Gán feeAmount và feeLabel từ payment_amount đã lưu trong DB.
        // Không tính lại — giá trị đã được xác định khi tạo bản ghi và
        // khi migration 2.0.2 chạy, đảm bảo nhất quán với dữ liệu thanh toán.
        foreach ($items as $item) {
            $amount          = (float) ($item->payment_amount ?? 0.0);
            $item->feeAmount = $amount;
            $item->feeLabel  = $amount > 0
                ? number_format($amount, 0, ',', '.') . ' đ'
                : 'Miễn phí';
        }

        return $items;
    }
}
