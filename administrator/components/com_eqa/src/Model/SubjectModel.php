<?php

namespace Kma\Component\Eqa\Administrator\Model;

use Joomla\CMS\Table\Table;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;

defined('_JEXEC') or die();

/**
 * Model cho một môn học (subject).
 *
 * Ngoài logic chuẩn của AdminModel, model này xử lý thêm:
 *   - Cột `allowed_rooms` (TEXT/JSON) ↔ array of int khi load/save form.
 *   - Các cột số có thể NULL: credits, finaltestduration, finaltestweight, testbankyear.
 *
 * @since 1.0.0
 */
class SubjectModel extends AdminModel
{
    // =========================================================================
    // Load
    // =========================================================================

    /**
     * Lấy thông tin một môn học.
     *
     * Override để deserialize cột `allowed_rooms`:
     *   - JSON string → array of int  (để Joomla ListField nhận diện đúng selection)
     *   - NULL / chuỗi rỗng          → [] (mảng rỗng, nghĩa là không giới hạn)
     *
     * @param  int|null $pk Primary key. Null = lấy từ state.
     *
     * @return \stdClass|bool
     * @since 2.0.1
     */
    public function getItem($pk = null): bool|\stdClass
    {
        $item = parent::getItem($pk);

        if ($item === false) {
            return false;
        }

        // Deserialize allowed_rooms: JSON → int[]
        if (!empty($item->allowed_rooms)) {
            $decoded = json_decode($item->allowed_rooms, true);
            // Đảm bảo kết quả là mảng int hợp lệ; nếu JSON lỗi thì fallback về []
            $item->allowed_rooms = is_array($decoded)
                ? array_values(array_map('intval', $decoded))
                : [];
        } else {
            $item->allowed_rooms = [];
        }

        return $item;
    }

    // =========================================================================
    // Save
    // =========================================================================

    /**
     * Lưu dữ liệu môn học.
     *
     * Override để serialize `allowed_rooms` trước khi ghi xuống database:
     *   - array không rỗng → JSON string, ví dụ: "[1,3,7]"
     *   - array rỗng / null / không tồn tại → NULL (không giới hạn phòng)
     *
     * @param  array $data Dữ liệu từ form (jform).
     * @return bool
     */
    public function save($data): bool
    {
        // Serialize allowed_rooms: int[] → JSON hoặc NULL
        if (isset($data['allowed_rooms']) && is_array($data['allowed_rooms'])) {
            // Lọc bỏ giá trị rỗng/0 có thể xuất hiện khi form gửi mảng rỗng
            $roomIds = array_values(
                array_filter(
                    array_map('intval', $data['allowed_rooms']),
                    static fn(int $id): bool => $id > 0
                )
            );

            $data['allowed_rooms'] = !empty($roomIds)
                ? json_encode($roomIds, JSON_THROW_ON_ERROR)
                : null;
        } else {
            // Không có key hoặc giá trị không phải array → NULL
            $data['allowed_rooms'] = null;
        }

        return parent::save($data);
    }

    // =========================================================================
    // prepareTable
    // =========================================================================

    /**
     * Chuẩn hóa dữ liệu trước khi bind vào Table object.
     *
     * Chuyển chuỗi rỗng thành NULL cho các cột số tùy chọn,
     * tránh lỗi kiểu dữ liệu khi INSERT/UPDATE.
     *
     * @param  Table $table
     * @return void
     */
    public function prepareTable($table): void
    {
        parent::prepareTable($table);

        if (empty($table->credits)) {
            $table->credits = null;
        }

        if (empty($table->finaltestduration)) {
            $table->finaltestduration = null;
        }

        if (empty($table->finaltestweight)) {
            $table->finaltestweight = null;
        }

        if (empty($table->testbankyear)) {
            $table->testbankyear = null;
        }

        // allowed_rooms đã được serialize thành JSON string hoặc null trong save()
        // trước khi bind vào table, nên không cần xử lý thêm ở đây.
    }


	/**
	 * Lấy dữ liệu thống kê tổng hợp cho một môn học.
	 *
	 * Trả về object gồm:
	 *   - subject          : thông tin cơ bản môn học (code, name, credits, degree)
	 *   - class_count      : số lớp học phần
	 *   - enrollment_count : số lượt người học (tổng bản ghi class_learner)
	 *   - concluded_count  : số lượt thi đã có kết luận
	 *   - passed_count     : số lượt thi đạt
	 *   - pass_rate        : tỉ lệ đạt (%) tính trên concluded_count
	 *   - avg_final_mark   : điểm thi trung bình (NULL nếu chưa có)
	 *   - avg_pam          : điểm quá trình trung bình (NULL nếu chưa có)
	 *   - avg_module_mark  : điểm học phần trung bình (NULL nếu chưa có)
	 *   - by_course        : mảng tối đa 10 object thống kê theo khóa đào tạo,
	 *                        mỗi object gồm: course_code, admission_year,
	 *                        enrollment_count, concluded_count, passed_count,
	 *                        pass_rate, avg_final_mark, avg_pam, avg_module_mark
	 *
	 * @param  int  $subjectId  ID môn học.
	 * @return object
	 * @since  2.0.8
	 */
	public function getStatistics(int $subjectId): object
	{
		$db          = $this->getDatabase();
		$passedValue = Conclusion::Passed->value;

		// =========================================================================
		// 1. Thông tin cơ bản môn học
		// =========================================================================
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('s.code'),
				$db->quoteName('s.name'),
				$db->quoteName('s.credits'),
				$db->quoteName('s.degree'),
			])
			->from($db->quoteName('#__eqa_subjects', 's'))
			->where($db->quoteName('s.id') . ' = ' . $subjectId);
		$db->setQuery($query);
		$subject = $db->loadObject() ?? new \stdClass();

		// =========================================================================
		// 2. Số lớp học phần
		// =========================================================================
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__eqa_classes', 'cl'))
			->where($db->quoteName('cl.subject_id') . ' = ' . $subjectId);
		$db->setQuery($query);
		$classCount = (int) $db->loadResult();

		// =========================================================================
		// 3. Thống kê tổng hợp (lượt ghi danh + điểm trung bình + tỉ lệ đạt)
		//
		// Nguồn: #__eqa_exam_learner (el) JOIN #__eqa_exams (ex) WHERE subject_id
		//        JOIN #__eqa_class_learner (cll) để lấy pam
		//
		// - enrollment_count : COUNT(*) trên el → tổng lượt thi (kể cả thi lại)
		// - concluded_count  : số bản ghi el.conclusion IS NOT NULL
		// - passed_count     : số bản ghi el.conclusion = Passed
		// - avg_final_mark   : AVG(el.mark_final) khi IS NOT NULL
		// - avg_pam          : AVG(cll.pam) khi IS NOT NULL
		// - avg_module_mark  : AVG(el.module_mark) khi IS NOT NULL
		// =========================================================================
		$query = $db->getQuery(true)
			->select([
				'COUNT(*) AS ' . $db->quoteName('enrollment_count'),
				'SUM(CASE WHEN ' . $db->quoteName('el.conclusion') . ' IS NOT NULL THEN 1 ELSE 0 END)'
				. ' AS ' . $db->quoteName('concluded_count'),
				'SUM(CASE WHEN ' . $db->quoteName('el.conclusion') . ' = ' . $passedValue . ' THEN 1 ELSE 0 END)'
				. ' AS ' . $db->quoteName('passed_count'),
				'AVG(' . $db->quoteName('el.mark_final') . ') AS ' . $db->quoteName('avg_final_mark'),
				'AVG(' . $db->quoteName('cll.pam') . ') AS ' . $db->quoteName('avg_pam'),
				'AVG(' . $db->quoteName('el.module_mark') . ') AS ' . $db->quoteName('avg_module_mark'),
			])
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->innerJoin(
				$db->quoteName('#__eqa_exams', 'ex')
				. ' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
				. ' AND ' . $db->quoteName('ex.subject_id') . ' = ' . $subjectId
			)
			->leftJoin(
				$db->quoteName('#__eqa_class_learner', 'cll')
				. ' ON ' . $db->quoteName('cll.class_id') . ' = ' . $db->quoteName('el.class_id')
				. ' AND ' . $db->quoteName('cll.learner_id') . ' = ' . $db->quoteName('el.learner_id')
			);
		$db->setQuery($query);
		$summary = $db->loadObject();

		$enrollmentCount = (int) ($summary->enrollment_count ?? 0);
		$concludedCount  = (int) ($summary->concluded_count  ?? 0);
		$passedCount     = (int) ($summary->passed_count     ?? 0);
		$passRate        = $concludedCount > 0
			? round($passedCount / $concludedCount * 100, 1)
			: null;
		$avgFinalMark  = $summary->avg_final_mark  !== null ? round((float) $summary->avg_final_mark,  2) : null;
		$avgPam        = $summary->avg_pam         !== null ? round((float) $summary->avg_pam,         2) : null;
		$avgModuleMark = $summary->avg_module_mark !== null ? round((float) $summary->avg_module_mark, 2) : null;

		// =========================================================================
		// 4. Thống kê theo khóa đào tạo (tối đa 10 khóa gần nhất theo admissionyear)
		// =========================================================================
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('co.code',          'course_code'),
				$db->quoteName('co.admissionyear', 'admission_year'),
				'COUNT(*) AS ' . $db->quoteName('enrollment_count'),
				'SUM(CASE WHEN ' . $db->quoteName('el.conclusion') . ' IS NOT NULL THEN 1 ELSE 0 END)'
				. ' AS ' . $db->quoteName('concluded_count'),
				'SUM(CASE WHEN ' . $db->quoteName('el.conclusion') . ' = ' . $passedValue . ' THEN 1 ELSE 0 END)'
				. ' AS ' . $db->quoteName('passed_count'),
				'AVG(' . $db->quoteName('el.mark_final') . ') AS '  . $db->quoteName('avg_final_mark'),
				'AVG(' . $db->quoteName('cll.pam') . ') AS '         . $db->quoteName('avg_pam'),
				'AVG(' . $db->quoteName('el.module_mark') . ') AS ' . $db->quoteName('avg_module_mark'),
			])
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->innerJoin(
				$db->quoteName('#__eqa_exams', 'ex')
				. ' ON ' . $db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
				. ' AND ' . $db->quoteName('ex.subject_id') . ' = ' . $subjectId
			)
			->leftJoin(
				$db->quoteName('#__eqa_class_learner', 'cll')
				. ' ON ' . $db->quoteName('cll.class_id') . ' = ' . $db->quoteName('el.class_id')
				. ' AND ' . $db->quoteName('cll.learner_id') . ' = ' . $db->quoteName('el.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'lr')
				. ' ON ' . $db->quoteName('lr.id') . ' = ' . $db->quoteName('el.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_groups', 'gr')
				. ' ON ' . $db->quoteName('gr.id') . ' = ' . $db->quoteName('lr.group_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_courses', 'co')
				. ' ON ' . $db->quoteName('co.id') . ' = ' . $db->quoteName('gr.course_id')
			)
			->group($db->quoteName('co.id'))
			->order($db->quoteName('co.admissionyear') . ' DESC')
			->setLimit(10);
		$db->setQuery($query);
		$byCourseRaw = $db->loadObjectList() ?: [];

		// Tính pass_rate cho từng khóa và làm tròn điểm trung bình
		$byCourse = [];
		foreach ($byCourseRaw as $row) {
			$rowConcluded = (int) $row->concluded_count;
			$rowPassed    = (int) $row->passed_count;

			$row->enrollment_count = (int)  $row->enrollment_count;
			$row->concluded_count  = $rowConcluded;
			$row->passed_count     = $rowPassed;
			$row->pass_rate        = $rowConcluded > 0
				? round($rowPassed / $rowConcluded * 100, 1)
				: null;
			$row->avg_final_mark   = $row->avg_final_mark  !== null ? round((float) $row->avg_final_mark,  2) : null;
			$row->avg_pam          = $row->avg_pam         !== null ? round((float) $row->avg_pam,         2) : null;
			$row->avg_module_mark  = $row->avg_module_mark !== null ? round((float) $row->avg_module_mark, 2) : null;

			$byCourse[] = $row;
		}

		// =========================================================================
		// 5. Ghép và trả kết quả
		// =========================================================================
		return (object) [
			'subject'          => $subject,
			'class_count'      => $classCount,
			'enrollment_count' => $enrollmentCount,
			'concluded_count'  => $concludedCount,
			'passed_count'     => $passedCount,
			'pass_rate'        => $passRate,
			'avg_final_mark'   => $avgFinalMark,
			'avg_pam'          => $avgPam,
			'avg_module_mark'  => $avgModuleMark,
			'by_course'        => $byCourse,
		];
	}
}
