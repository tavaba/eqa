<?php

namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Model\BaseModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;

/**
 * Model front-end cho chức năng "Thi lại".
 *
 * Lấy danh sách môn thi lần hai của người học từ #__eqa_resit_learner,
 * kết hợp JOIN để lấy thông tin điểm số và môn học.
 *
 * Số tiền lệ phí (`payment_amount`) được đọc trực tiếp từ bảng
 * #__eqa_resit_learner — đã được tính và lưu khi tạo bản ghi (không tính lại).
 *
 * TỪ 2.1.8: bảng #__eqa_resit_learner lưu dữ liệu của NHIỀU đợt tổ chức thi
 * lần 2, mỗi đợt là một 'danh sách thi lần 2'. Người học chỉ được thấy các môn
 * thuộc DANH SÁCH ĐANG KÍCH HOẠT — đó là danh sách mà thí sinh đăng ký thi lại
 * vào ở thời điểm hiện tại. Mỗi cơ sở đào tạo có tối đa một danh sách kích hoạt
 * nên điều kiện `l.active = 1` là đủ.
 *
 * @since 2.0.2
 */
class LearnerresitModel extends BaseModel
{
    /**
     * Lấy danh sách môn thi lại của người học, kèm thông tin điểm và phí.
     *
     * Các bảng tham gia:
     *   sa   → #__eqa_resit_learner  (bảng chính)
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
	public function getResitList(string $learnerCode): array
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
				'cl.academicyear',   // INT trực tiếp từ classes, không cần join academicyears
				'cl.term',
				'l.name',            // Tên danh sách thi lần 2 đang kích hoạt (2.1.8)
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
				'resit_name',
			]
		);

		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_resit_learner', 'sa'))
			// Chỉ lấy các môn thuộc danh sách thi lần 2 ĐANG KÍCH HOẠT (2.1.8):
			// đó là danh sách mà thí sinh đăng ký thi lại vào ở thời điểm hiện tại.
			// Dữ liệu của các đợt tổ chức thi trước vẫn được lưu nhưng không hiển
			// thị ở đây để người học không nhầm lẫn về khoản phí cần nộp.
			->innerJoin(
				$db->quoteName('#__eqa_resits', 'l') .
				' ON ' . $db->quoteName('l.id') . ' = ' . $db->quoteName('sa.resit_id') .
				' AND ' . $db->quoteName('l.active') . ' = 1'
			)
			// Lấy learner_id theo learner code
			->innerJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('sa.learner_id') .
				' AND ' . $db->quoteName('lr.code') . ' = ' . $db->quote($learnerCode)
			)
			// Lớp học phần (có cột academicyear INT trực tiếp)
			->leftJoin(
				$db->quoteName('#__eqa_classes', 'cl') .
				' ON ' . $db->quoteName('cl.id') . ' = ' . $db->quoteName('sa.class_id')
			)
			// Điểm quá trình của người học trong lớp học phần
			->leftJoin(
				$db->quoteName('#__eqa_class_learner', 'cl_l') .
				' ON ' . $db->quoteName('cl_l.class_id') . ' = ' . $db->quoteName('sa.class_id') .
				' AND ' . $db->quoteName('cl_l.learner_id') . ' = ' . $db->quoteName('sa.learner_id')
			)
			// Môn thi → lấy subject_id
			->leftJoin(
				$db->quoteName('#__eqa_exams', 'ex') .
				' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('sa.last_exam_id')
			)
			// Môn học → mã, tên, số tín chỉ
			->leftJoin(
				$db->quoteName('#__eqa_subjects', 'su') .
				' ON ' . $db->quoteName('su.id') . ' = ' . $db->quoteName('ex.subject_id')
			)
			// Kết quả thi lần cuối của người học
			->leftJoin(
				$db->quoteName('#__eqa_exam_learner', 'el') .
				' ON ' . $db->quoteName('el.exam_id') . ' = ' . $db->quoteName('sa.last_exam_id') .
				' AND ' . $db->quoteName('el.learner_id') . ' = ' . $db->quoteName('sa.learner_id')
			);

		$db->setQuery($query);
		$items = $db->loadObjectList();

		// Decode academicyear INT → chuỗi hiển thị "YYYY-YYYY"
		foreach ($items as $item) {
			if (!empty($item->academicyear)) {
				$item->academicyear = DatetimeHelper::decodeAcademicYear((int) $item->academicyear);
			}
		}

		return $items;
	}

	/**
	 * Đợt thi lại ĐANG KÍCH HOẠT mà người học có tên trong đó.
	 *
	 * Bản ghi trả về mang toàn bộ tham số của cổng thu phí (tài khoản nhận,
	 * thời điểm mở/đóng, mốc đối chiếu sao kê gần nhất) mà view cần để hiển thị
	 * cho người học. Từ 2.1.8 các tham số này thuộc về đợt thi lại chứ không còn
	 * nằm ở params của menu item.
	 *
	 * Đợt được suy ra từ chính các bản ghi của người học: mỗi cơ sở đào tạo có
	 * tối đa một đợt đang kích hoạt, nên phép lọc `active = 1` là đủ và không
	 * cần biết người học thuộc cơ sở nào.
	 *
	 * @param  string  $learnerCode  Mã người học.
	 * @return object|null           Null nếu người học không có tên trong đợt nào
	 *                               đang kích hoạt.
	 * @throws Exception
	 * @since  2.1.8
	 */
	public function getActiveResit(string $learnerCode): ?object
	{
		$db    = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select($db->quoteName('r') . '.*')
			->from($db->quoteName('#__eqa_resits', 'r'))
			->innerJoin(
				$db->quoteName('#__eqa_resit_learner', 'rl') .
				' ON ' . $db->quoteName('rl.resit_id') . ' = ' . $db->quoteName('r.id')
			)
			->innerJoin(
				$db->quoteName('#__eqa_learners', 'lr') .
				' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('rl.learner_id') .
				' AND ' . $db->quoteName('lr.code') . ' = ' . $db->quote($learnerCode)
			)
			->where($db->quoteName('r.active') . ' = 1')
			->group($db->quoteName('r.id'));

		$db->setQuery($query, 0, 1);

		return $db->loadObject();
	}
}
