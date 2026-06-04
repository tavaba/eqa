<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\ParameterType;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Enum\ExamType;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Library\Kma\Controller\FormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Service\LogService;

defined('_JEXEC') or die();
class FixerController extends FormController
{
	public function fix(): void
	{
		die('Ha ha ha');
	}
	public function test(): void
	{
		$app        = $this->app;
		$academicyear = $app->input->getInt('year');
		$term         = $app->input->getInt('term');

		if (empty($academicyear) || empty($term)) {
			echo 'Thiếu tham số: year, term';
			return;
		}

		$db = DatabaseHelper::getDatabaseDriver();

		// Lấy danh sách người học chưa có kết quả thi (conclusion IS NULL)
		// trong các lớp học phần thuộc năm học và học kỳ cho trước
		$query = $db->getQuery(true)
			->select('DISTINCT ' . implode(', ', [
					$db->quoteName('l.id'),
					$db->quoteName('l.code',      'learner_code'),
					$db->quoteName('l.lastname'),
					$db->quoteName('l.firstname'),
					$db->quoteName('c.name',      'class_name'),
				]))
			->from($db->quoteName('#__eqa_class_learner', 'cl'))
			->leftJoin(
				$db->quoteName('#__eqa_classes',  'c')  . ' ON c.id  = cl.class_id'
			)
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'l')  . ' ON l.id  = cl.learner_id'
			)
			->where($db->quoteName('c.academicyear') . ' = ' . (int) $academicyear)
			->where($db->quoteName('c.term')         . ' = ' . (int) $term)
			->where($db->quoteName('cl.expired') . ' = 0')
			->where(
				'NOT EXISTS (' .
				$db->getQuery(true)
					->select('1')
					->from($db->quoteName('#__eqa_exam_learner', 'el'))
					->where($db->quoteName('el.learner_id') . ' = ' . $db->quoteName('cl.learner_id'))
					->where($db->quoteName('el.class_id')   . ' = ' . $db->quoteName('cl.class_id'))
					->where($db->quoteName('el.conclusion') . ' IS NOT NULL')
				. ')'
			)
			->order($db->quoteName('c.name')    . ' ASC')
			->order($db->quoteName('l.lastname') . ' ASC')
			->order($db->quoteName('l.firstname') . ' ASC');

		$db->setQuery($query);
		$rows = $db->loadObjectList();

		// Hiển thị kết quả dạng bảng HTML
		$title = sprintf('Người học chưa có kết quả thi — Năm học %d-%d, Học kỳ %d',
			$academicyear, $academicyear + 1, $term);

		echo '<style>table{border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px 8px}th{background:#f0f0f0}</style>';
		echo '<h2>' . htmlspecialchars($title) . '</h2>';

		if (empty($rows)) {
			echo '<p>Không có người học nào thỏa điều kiện.</p>';
			return;
		}

		echo '<table>';
		echo '<thead><tr><th>STT</th><th>Mã HVSV</th><th>Họ đệm</th><th>Tên</th><th>Lớp học phần</th></tr></thead>';
		echo '<tbody>';
		foreach ($rows as $i => $row) {
			echo '<tr>';
			echo '<td>' . ($i + 1) . '</td>';
			echo '<td>' . htmlspecialchars($row->learner_code) . '</td>';
			echo '<td>' . htmlspecialchars($row->lastname)     . '</td>';
			echo '<td>' . htmlspecialchars($row->firstname)    . '</td>';
			echo '<td>' . htmlspecialchars($row->class_name)   . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p>Tổng: <b>' . count($rows) . '</b> bản ghi.</p>';

		$app->close();
	}
	/**
	 * Kiểm tra tác động của việc hoán đổi pam1/pam2 đến điều kiện dự thi (allowed)
	 * của người học thuộc các lớp có class_id IN (1937, 1938, 1939, 1940).
	 *
	 * Hàm chỉ đọc dữ liệu, không thay đổi bất kỳ giá trị nào trong CSDL.
	 * Chạy hàm này trước khi thực hiện sửa sai để đánh giá mức độ ảnh hưởng.
	 *
	 * @return void
	 * @since 1.x.x
	 */
	public function checkSwapPamImpactOnAllowed(): void
	{
		if (!$this->app->getIdentity()->authorise('core.admin')) {
			die('Ha ha ha');
		}

		$targetClassIds = [1937, 1938, 1939, 1940];
		$classIdsStr    = implode(',', $targetClassIds);
		$db             = DatabaseHelper::getDatabaseDriver();

		// Đọc toàn bộ bản ghi có PAM của các lớp cần kiểm tra,
		// kèm mã HVSV và tên để dễ nhận diện trong báo cáo.
		$query = $db->getQuery(true)
			->select($db->quoteName([
				'cl.class_id', 'cl.learner_id',
				'cl.pam1', 'cl.pam2', 'cl.pam',
				'cl.allowed',
				'l.code',
			]))
			->select('CONCAT(' . $db->quoteName('l.lastname') . ', \' \', ' . $db->quoteName('l.firstname') . ') AS fullname')
			->from($db->quoteName('#__eqa_class_learner', 'cl'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'l'),
				$db->quoteName('l.id') . ' = ' . $db->quoteName('cl.learner_id')
			)
			->where($db->quoteName('cl.class_id') . ' IN (' . $classIdsStr . ')')
			->where($db->quoteName('cl.pam') . ' IS NOT NULL')
			->order($db->quoteName('cl.class_id') . ' ASC, ' . $db->quoteName('l.code') . ' ASC');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		if (empty($rows)) {
			echo '<pre>Không tìm thấy bản ghi nào có PAM trong các lớp đã chỉ định.</pre>';
			return;
		}

		// Phân tích từng bản ghi
		$impactedRows  = [];   // Các bản ghi mà allowed thay đổi sau khi hoán đổi
		$unchangedRows = [];   // Các bản ghi mà allowed không thay đổi
		$skippedRows   = [];   // pam1 == pam2, hoán đổi không có tác dụng

		foreach ($rows as $row) {
			$oldPam1    = (float) $row->pam1;
			$oldPam2    = (float) $row->pam2;
			$oldAllowed = (bool)  $row->allowed;

			// Trường hợp pam1 == pam2: hoán đổi không thay đổi gì
			if ($oldPam1 === $oldPam2) {
				$skippedRows[] = $row;
				continue;
			}

			// Tính pam mới sau khi hoán đổi
			$newPam1 = $oldPam2;
			$newPam2 = $oldPam1;

			if ($newPam1 < 0) {
				$newPam = $newPam1;
			} elseif ($newPam2 < 0) {
				$newPam = $newPam2;
			} else {
				$newPam = ExamHelper::calculatePamForDefaultFormular($newPam1, $newPam2);
			}

			$newAllowed = ExamHelper::isAllowedToFinalExam($newPam1, $newPam2, $newPam);

			// Phân loại kết quả
			$entry = [
				'class_id'   => $row->class_id,
				'learner_id' => $row->learner_id,
				'code'       => $row->code,
				'fullname'   => $row->fullname,
				'old_pam1'   => $oldPam1,
				'old_pam2'   => $oldPam2,
				'old_pam'    => (float) $row->pam,
				'old_allowed'=> $oldAllowed,
				'new_pam1'   => $newPam1,
				'new_pam2'   => $newPam2,
				'new_pam'    => $newPam,
				'new_allowed'=> $newAllowed,
			];

			if ($oldAllowed !== $newAllowed) {
				$impactedRows[] = $entry;
			} else {
				$unchangedRows[] = $entry;
			}
		}

		// ----------------------------------------------------------------
		// In báo cáo
		// ----------------------------------------------------------------
		$total    = count($rows);
		$skipped  = count($skippedRows);
		$impacted = count($impactedRows);
		$safe     = count($unchangedRows);

		echo '<pre>';
		echo "=== KIỂM TRA TÁC ĐỘNG CỦA VIỆC HOÁN ĐỔI PAM1/PAM2 ===\n";
		echo "Lớp học phần      : " . implode(', ', $targetClassIds) . "\n";
		echo "Tổng bản ghi có PAM: {$total}\n";
		echo "Bỏ qua (pam1==pam2): {$skipped}\n";
		echo "Không đổi allowed  : {$safe}\n";
		echo "CÓ ĐỔI ALLOWED     : {$impacted}";

		if ($impacted > 0) {
			echo " ⚠️  CẦN XỬ LÝ THỦ CÔNG!\n";
			echo "\n--- DANH SÁCH CÁC TRƯỜNG HỢP CÓ THAY ĐỔI ALLOWED ---\n";
			echo sprintf(
				"%-10s %-12s %-30s %-6s %-6s %-6s %-8s %-6s %-6s %-6s %-8s\n",
				'class_id', 'Mã HVSV', 'Họ tên',
				'pam1', 'pam2', 'pam', 'allowed',
				'pam1*', 'pam2*', 'pam*', 'allowed*'
			);
			echo str_repeat('-', 110) . "\n";
			foreach ($impactedRows as $e) {
				echo sprintf(
					"%-10d %-12s %-30s %-6.2f %-6.2f %-6.2f %-8s %-6.2f %-6.2f %-6.2f %-8s\n",
					$e['class_id'],
					$e['code'],
					mb_substr($e['fullname'], 0, 30),
					$e['old_pam1'], $e['old_pam2'], $e['old_pam'],
					$e['old_allowed'] ? 'Có' : 'Không',
					$e['new_pam1'], $e['new_pam2'], $e['new_pam'],
					$e['new_allowed'] ? 'Có' : 'Không'
				);
			}
			echo "\n(Cột có dấu * là giá trị sau khi hoán đổi)\n";
		} else {
			echo " ✅  AN TOÀN, có thể tiến hành sửa sai.\n";
		}

		echo '</pre>';
	}
	/**
	 * Kiểm tra tác động của việc hoán đổi pam1/pam2 đến kết luận (conclusion)
	 * trong bảng #__eqa_exam_learner, đối với người học thuộc các lớp
	 * có class_id IN (1937, 1938, 1939, 1940).
	 *
	 * Hàm chỉ đọc dữ liệu, không thay đổi bất kỳ giá trị nào trong CSDL.
	 * Chạy hàm này sau checkSwapPamImpactOnAllowed() để đánh giá toàn diện
	 * mức độ ảnh hưởng trước khi thực hiện sửa sai.
	 *
	 * @return void
	 * @since 1.x.x
	 */
	public function checkSwapPamImpactOnConclusion(): void
	{
		if (!$this->app->getIdentity()->authorise('core.admin')) {
			die('Ha ha ha');
		}

		$targetClassIds = [1937, 1938, 1939, 1940];
		$classIdsStr    = implode(',', $targetClassIds);
		$db             = DatabaseHelper::getDatabaseDriver();

		// Đọc tất cả bản ghi exam_learner của các lớp cần kiểm tra
		// có kết luận (conclusion IS NOT NULL) — tức là đã có điểm học phần.
		// JOIN thêm các bảng để lấy đủ dữ liệu phục vụ tính toán lại.
		$query = $db->getQuery(true)
			->select($db->quoteName([
				'el.exam_id', 'el.learner_id', 'el.class_id',
				'el.attempt', 'el.anomaly',
				'el.mark_ppaa', 'el.mark_orig', 'el.mark_final',
				'el.module_mark', 'el.conclusion',
				'cl.pam1', 'cl.pam2', 'cl.pam',
				'l.code',
				'ex.subject_id',
			]))
			->select('CONCAT(' . $db->quoteName('l.lastname') . ', \' \', ' . $db->quoteName('l.firstname') . ') AS fullname')
			->select($db->quoteName('st.type', 'stimul_type'))
			->select($db->quoteName('st.value', 'stimul_value'))
			->select($db->quoteName('es.type', 'exam_type'))
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->leftJoin(
				$db->quoteName('#__eqa_class_learner', 'cl'),
				$db->quoteName('cl.class_id') . ' = ' . $db->quoteName('el.class_id')
				. ' AND ' . $db->quoteName('cl.learner_id') . ' = ' . $db->quoteName('el.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'l'),
				$db->quoteName('l.id') . ' = ' . $db->quoteName('el.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_exams', 'ex'),
				$db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_examseasons', 'es'),
				$db->quoteName('es.id') . ' = ' . $db->quoteName('ex.examseason_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_stimulations', 'st'),
				$db->quoteName('st.id') . ' = ' . $db->quoteName('el.stimulation_id')
			)
			->where($db->quoteName('el.class_id') . ' IN (' . $classIdsStr . ')')
			->where($db->quoteName('el.conclusion') . ' IS NOT NULL')
			->order($db->quoteName('el.class_id') . ' ASC, ' . $db->quoteName('l.code') . ' ASC');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		if (empty($rows)) {
			echo '<pre>Không tìm thấy thí sinh nào đã có kết luận trong các lớp đã chỉ định.</pre>';
			return;
		}

		$impactedRows  = [];
		$unchangedRows = [];
		$skippedRows   = [];

		foreach ($rows as $row) {
			$oldPam1 = (float) $row->pam1;
			$oldPam2 = (float) $row->pam2;

			// Hoán đổi sẽ không có tác dụng nếu pam1 == pam2
			if ($oldPam1 === $oldPam2) {
				$skippedRows[] = $row;
				continue;
			}

			// Tính pam mới sau khi hoán đổi
			$newPam1 = $oldPam2;
			$newPam2 = $oldPam1;

			if ($newPam1 < 0) {
				$newPam = $newPam1;
			} elseif ($newPam2 < 0) {
				$newPam = $newPam2;
			} else {
				$newPam = ExamHelper::calculatePamForDefaultFormular($newPam1, $newPam2);
			}

			// Xác định ExamType từ dữ liệu đọc được
			$examType = ExamType::tryFrom((int) $row->exam_type) ?? ExamType::SubjectFinalTest;

			// Lấy admissionYear (chỉ cần tra DB khi attempt > 1)
			$attempt = (int) $row->attempt;
			$admissionYear = $attempt > 1
				? (int) DatabaseHelper::getLearnerAdmissionYear((int) $row->learner_id)
				: 0;

			// Xác định điểm khuyến khích cộng thêm (nếu có)
			$addValue = 0.0;
			if (!is_null($row->stimul_type) && (int) $row->stimul_type === \Kma\Component\Eqa\Administrator\Helper\StimulationHelper::TYPE_ADD) {
				$addValue = (float) $row->stimul_value;
			}

			// Tính lại module_mark với pam mới
			// mark_final không đổi (điểm thi không thay đổi),
			// chỉ thay đổi pam trong công thức: module_mark = pamWeight*pam + finalTestWeight*markFinal
			$markFinal   = (float) $row->mark_final;
			$subjectId   = (int) $row->subject_id;
			$anomaly     = (int) $row->anomaly;

			$newModuleMark = ExamHelper::calculateModuleMark($subjectId, $newPam, $markFinal, $attempt, $admissionYear);

			// Tính lại conclusion với module_mark và mark_final mới
			$newConclusion = ExamHelper::calculateConclusion($newModuleMark, $markFinal, $anomaly, $attempt, $examType);

			$oldConclusionValue = (int) $row->conclusion;
			$newConclusionValue = $newConclusion->value;

			$entry = [
				'class_id'          => $row->class_id,
				'exam_id'           => $row->exam_id,
				'learner_id'        => $row->learner_id,
				'code'              => $row->code,
				'fullname'          => $row->fullname,
				'old_pam1'          => $oldPam1,
				'old_pam2'          => $oldPam2,
				'old_pam'           => (float) $row->pam,
				'new_pam'           => $newPam,
				'mark_final'        => $markFinal,
				'old_module_mark'   => (float) $row->module_mark,
				'new_module_mark'   => $newModuleMark,
				'old_conclusion'    => $oldConclusionValue,
				'new_conclusion'    => $newConclusionValue,
				'old_conclusion_lbl'=> Conclusion::from($oldConclusionValue)->getLabel(),
				'new_conclusion_lbl'=> $newConclusion->getLabel(),
			];

			if ($oldConclusionValue !== $newConclusionValue) {
				$impactedRows[] = $entry;
			} else {
				$unchangedRows[] = $entry;
			}
		}

		// ----------------------------------------------------------------
		// In báo cáo
		// ----------------------------------------------------------------
		$total    = count($rows);
		$skipped  = count($skippedRows);
		$impacted = count($impactedRows);
		$safe     = count($unchangedRows);

		echo '<pre>';
		echo "=== KIỂM TRA TÁC ĐỘNG CỦA VIỆC HOÁN ĐỔI PAM1/PAM2 ĐẾN CONCLUSION ===\n";
		echo "Lớp học phần          : " . implode(', ', $targetClassIds) . "\n";
		echo "Tổng thí sinh có kết luận: {$total}\n";
		echo "Bỏ qua (pam1 == pam2)    : {$skipped}\n";
		echo "Kết luận không đổi       : {$safe}\n";
		echo "CÓ ĐỔI CONCLUSION        : {$impacted}";

		if ($impacted > 0) {
			echo " ⚠️  CẦN XEM XÉT!\n";
			echo "\n--- DANH SÁCH CÁC TRƯỜNG HỢP CÓ THAY ĐỔI CONCLUSION ---\n";
			// Header: 12 cột → 12 specifier
			echo sprintf(
				"%-10s %-8s %-12s %-25s %-6s %-6s %-7s %-8s %-7s %-16s %-16s\n",
				'class_id', 'exam_id', 'Mã HVSV', 'Họ tên',
				'pam', 'pam*', 'Đ.thi',
				'ĐHP', 'ĐHP*',
				'KL cũ', 'KL mới'
			);
			echo str_repeat('-', 130) . "\n";
			foreach ($impactedRows as $e) {
				// Data: 11 giá trị → 11 specifier
				echo sprintf(
					"%-10d %-8d %-12s %-25s %-6.2f %-6.2f %-7.2f %-8.2f %-7.2f %-16s %-16s\n",
					$e['class_id'],
					$e['exam_id'],
					$e['code'],
					mb_substr($e['fullname'], 0, 25),
					$e['old_pam'],
					$e['new_pam'],
					$e['mark_final'],
					$e['old_module_mark'],
					$e['new_module_mark'],
					$e['old_conclusion_lbl'],
					$e['new_conclusion_lbl']
				);
			}
			echo "\n(Cột có dấu * là giá trị sau khi hoán đổi | ĐHP = Điểm học phần | KL = Kết luận)\n";
		} else {
			echo " ✅  AN TOÀN, conclusion không thay đổi.\n";
		}
		echo '</pre>';
	}
	/**
	 * Sửa sai: hoán đổi pam1/pam2 cho tất cả người học thuộc các lớp
	 * có class_id IN (1937, 1938, 1939, 1940), đồng thời tính lại các
	 * giá trị phái sinh (pam, module_mark, module_base4_mark, module_grade,
	 * conclusion, expired).
	 *
	 * Logic:
	 *   - Bước 1: Cập nhật #__eqa_class_learner: chỉ pam1, pam2, pam.
	 *   - Bước 2: Nếu đã có kết quả thi (conclusion IS NOT NULL):
	 *             tính lại module_mark, module_base4_mark, module_grade, conclusion
	 *             → cập nhật #__eqa_exam_learner;
	 *             → cập nhật expired vào #__eqa_class_learner.
	 *   - ExamType xác định một lần duy nhất, áp dụng cho tất cả.
	 *   - Toàn bộ được bọc trong một transaction.
	 *
	 * Chỉ thực thi một lần. Sau khi chạy xong hãy vô hiệu hóa hàm này.
	 *
	 * @return void
	 * @since 1.x.x
	 */
	public function swapPam1AndPam2ForClasses(): void
	{
		if (!$this->app->getIdentity()->authorise('core.admin')) {
			die('Ha ha ha');
		}

		$targetClassIds = [1937, 1938, 1939, 1940];
		$classIdsStr    = implode(',', $targetClassIds);
		$db             = DatabaseHelper::getDatabaseDriver();

		// ----------------------------------------------------------------
		// TIỀN XỬ LÝ: Xác định ExamType một lần duy nhất
		// ----------------------------------------------------------------
		$query = $db->getQuery(true)
			->select($db->quoteName('es.type'))
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->leftJoin(
				$db->quoteName('#__eqa_exams', 'ex'),
				$db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_examseasons', 'es'),
				$db->quoteName('es.id') . ' = ' . $db->quoteName('ex.examseason_id')
			)
			->where($db->quoteName('el.class_id') . ' IN (' . $classIdsStr . ')')
			->setLimit(1);
		$db->setQuery($query);
		$examTypeValue = $db->loadResult();

		$examType = ($examTypeValue !== null)
			? (ExamType::tryFrom((int) $examTypeValue) ?? ExamType::SubjectFinalTest)
			: ExamType::SubjectFinalTest;

		// ----------------------------------------------------------------
		// ĐỌC DỮ LIỆU: Tất cả bản ghi cần xử lý
		// Điều kiện: pam IS NOT NULL AND pam1 != pam2
		// LEFT JOIN exam_learner để lấy thông tin thi (nếu có)
		// ----------------------------------------------------------------
		$query = $db->getQuery(true)
			->select($db->quoteName([
				'cl.class_id', 'cl.learner_id',
				'cl.pam1', 'cl.pam2', 'cl.pam',
			]))
			->select('CONCAT(' . $db->quoteName('l.lastname') . ', \' \', ' . $db->quoteName('l.firstname') . ') AS fullname')
			->select($db->quoteName('l.code', 'learner_code'))
			->select($db->quoteName([
				'el.exam_id', 'el.attempt', 'el.anomaly',
				'el.mark_final', 'el.module_mark', 'el.conclusion',
				'ex.subject_id',
			]))
			->from($db->quoteName('#__eqa_class_learner', 'cl'))
			->leftJoin(
				$db->quoteName('#__eqa_learners', 'l'),
				$db->quoteName('l.id') . ' = ' . $db->quoteName('cl.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_exam_learner', 'el'),
				$db->quoteName('el.class_id')    . ' = ' . $db->quoteName('cl.class_id')
				. ' AND ' . $db->quoteName('el.learner_id') . ' = ' . $db->quoteName('cl.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_exams', 'ex'),
				$db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
			)
			->where($db->quoteName('cl.class_id') . ' IN (' . $classIdsStr . ')')
			->where($db->quoteName('cl.pam') . ' IS NOT NULL')
			->where($db->quoteName('cl.pam1') . ' != ' . $db->quoteName('cl.pam2'))
			->order($db->quoteName('cl.class_id') . ' ASC, ' . $db->quoteName('l.code') . ' ASC');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		if (empty($rows)) {
			echo '<pre>Không tìm thấy bản ghi nào thỏa điều kiện (pam IS NOT NULL AND pam1 != pam2).</pre>';
			return;
		}

		$updatedCl = 0;
		$updatedEl = 0;
		$details   = [];

		$db->transactionStart();
		try {
			foreach ($rows as $row) {
				$classId   = (int) $row->class_id;
				$learnerId = (int) $row->learner_id;

				// ------------------------------------------------------------
				// Tính pam1, pam2, pam mới
				// ------------------------------------------------------------
				$newPam1 = (float) $row->pam2;  // Hoán đổi
				$newPam2 = (float) $row->pam1;  // Hoán đổi

				if ($newPam1 < 0) {
					$newPam = $newPam1;         // Điểm đặc biệt từ pam1
				} elseif ($newPam2 < 0) {
					$newPam = $newPam2;         // Điểm đặc biệt từ pam2
				} else {
					$newPam = ExamHelper::calculatePamForDefaultFormular($newPam1, $newPam2);
				}

				// ------------------------------------------------------------
				// BƯỚC 1: Cập nhật #__eqa_class_learner — chỉ pam1, pam2, pam
				// ------------------------------------------------------------
				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_class_learner'))
					->set([
						$db->quoteName('pam1') . ' = ' . $newPam1,
						$db->quoteName('pam2') . ' = ' . $newPam2,
						$db->quoteName('pam')  . ' = ' . $newPam,
					])
					->where($db->quoteName('class_id')   . ' = ' . $classId)
					->where($db->quoteName('learner_id') . ' = ' . $learnerId);
				$db->setQuery($query);
				$db->execute();
				$updatedCl++;

				// ------------------------------------------------------------
				// BƯỚC 2: Chỉ thực hiện nếu đã có kết quả thi
				// ------------------------------------------------------------
				if (is_null($row->conclusion)) {
					$details[] = sprintf(
						'class_id=%d learner=%s: pam1 %.2f↔%.2f | pam %.2f→%.2f (chưa thi)',
						$classId, $row->learner_code,
						(float) $row->pam1, (float) $row->pam2,
						(float) $row->pam, $newPam
					);
					continue;
				}

				$attempt   = (int) $row->attempt;
				$anomaly   = (int) $row->anomaly;
				$markFinal = (float) $row->mark_final;
				$subjectId = (int) $row->subject_id;

				$admissionYear = $attempt > 1
					? (int) DatabaseHelper::getLearnerAdmissionYear($learnerId)
					: 0;

				$newModuleMark      = ExamHelper::calculateModuleMark($subjectId, $newPam, $markFinal, $attempt, $admissionYear);
				$newModuleBase4Mark = ExamHelper::calculateBase4Mark($newModuleMark);
				$newConclusion      = ExamHelper::calculateConclusion($newModuleMark, $markFinal, $anomaly, $attempt, $examType);
				$newModuleGrade     = ExamHelper::calculateModuleGrade($newModuleMark, $newConclusion);
				$newExpired         = in_array($newConclusion, [Conclusion::Passed, Conclusion::RetakeCourse], true) ? 1 : 0;

				// 2a. Cập nhật #__eqa_exam_learner
				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_exam_learner'))
					->set([
						$db->quoteName('module_mark')       . ' = ' . $newModuleMark,
						$db->quoteName('module_base4_mark') . ' = ' . $newModuleBase4Mark,
						$db->quoteName('module_grade')      . ' = ' . $db->quote($newModuleGrade),
						$db->quoteName('conclusion')        . ' = ' . $newConclusion->value,
					])
					->where($db->quoteName('exam_id')    . ' = ' . (int) $row->exam_id)
					->where($db->quoteName('learner_id') . ' = ' . $learnerId);
				$db->setQuery($query);
				$db->execute();
				$updatedEl++;

				// 2b. Cập nhật expired vào #__eqa_class_learner
				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_class_learner'))
					->set($db->quoteName('expired') . ' = ' . $newExpired)
					->where($db->quoteName('class_id')   . ' = ' . $classId)
					->where($db->quoteName('learner_id') . ' = ' . $learnerId);
				$db->setQuery($query);
				$db->execute();

				$details[] = sprintf(
					'class_id=%d learner=%s: pam1 %.2f↔%.2f | pam %.2f→%.2f | ĐHP %.2f→%.2f | KL: %s→%s | expired→%d',
					$classId, $row->learner_code,
					(float) $row->pam1, (float) $row->pam2,
					(float) $row->pam, $newPam,
					(float) $row->module_mark, $newModuleMark,
					Conclusion::from((int) $row->conclusion)->getLabel(),
					$newConclusion->getLabel(),
					$newExpired
				);
			}

			$db->transactionCommit();

		} catch (\Exception $e) {
			$db->transactionRollback();
			echo '<pre>TRANSACTION BỊ ROLLBACK: ' . htmlspecialchars($e->getMessage()) . '</pre>';
			return;
		}

		// ----------------------------------------------------------------
		// Báo cáo kết quả
		// ----------------------------------------------------------------
		echo '<pre>';
		echo "=== KẾT QUẢ SỬA SAI PAM1/PAM2 ===\n";
		echo "Lớp học phần              : " . implode(', ', $targetClassIds) . "\n";
		echo "ExamType áp dụng          : " . $examType->getLabel() . "\n";
		echo "Tổng bản ghi xử lý        : " . count($rows) . "\n";
		echo "Đã cập nhật class_learner : {$updatedCl}\n";
		echo "Đã cập nhật exam_learner  : {$updatedEl}\n";
		echo "\n--- CHI TIẾT ---\n";
		echo implode("\n", $details);
		echo "\n";
		echo '</pre>';
	}
}
