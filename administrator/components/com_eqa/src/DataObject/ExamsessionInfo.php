<?php

namespace Kma\Component\Eqa\Administrator\DataObject;

defined('_JEXEC') or die();

/**
 * Thông tin tóm tắt của một ca thi, dùng để hiển thị header trong các view
 * (ví dụ: Examsessionemployees).
 *
 * Một ca thi thuộc đúng một trong hai loại, xác định bởi $isAssessmentSession:
 *   - KTHP  (false): liên kết với kỳ thi (examseason). Hiển thị danh sách môn thi.
 *   - Sát hạch (true): liên kết với kỳ sát hạch (assessment). Hiển thị tên kỳ sát hạch.
 *
 * @since 2.0.6
 */
class ExamsessionInfo extends ObjectInfo
{
	// =========================================================================
	// Properties dùng chung (cả KTHP và sát hạch)
	// =========================================================================

	/** ID ca thi. */
	public int $id;

	/** Tên ca thi (ví dụ: "Ca thi 07:00 - 09:00"). */
	public string $name;

	/**
	 * Thời điểm bắt đầu ca thi (UTC string, dạng "Y-m-d H:i:s").
	 * Cần chuyển về Local Time khi hiển thị.
	 */
	public string $start;

	/** Số phòng thi trong ca thi. */
	public int $countExamroom = 0;

	/**
	 * Tổng số thí sinh đã được xếp phòng trong ca thi.
	 * - KTHP: đếm từ #__eqa_exam_learner.
	 * - Sát hạch: đếm từ #__eqa_assessment_learner.
	 */
	public int $countExaminee = 0;

	/**
	 * Ca thi này thuộc kỳ sát hạch (true) hay KTHP (false).
	 * Được xác định bởi DatabaseHelper::getExamsessionInfo().
	 */
	public bool $isAssessmentSession = false;

	/**
	 * Danh sách tên môn thi / môn sát hạch trong ca thi.
	 *
	 * - KTHP      : mảng tên môn thi lấy từ #__eqa_exams (qua getExamNames()).
	 * - Sát hạch  : mảng một phần tử duy nhất là tên kỳ sát hạch ($assessmentTitle).
	 *
	 * Luôn là mảng string (có thể rỗng), không bao giờ null.
	 *
	 * @var string[]
	 */
	public array $exams = [];

	// =========================================================================
	// Properties riêng cho KTHP (isAssessmentSession = false)
	// =========================================================================

	/**
	 * Danh sách exam_id (unique) của các môn thi trong ca thi.
	 * Dùng để hiển thị danh sách môn thi qua DatabaseHelper::getExamNames().
	 * NULL khi là ca thi sát hạch.
	 *
	 * @var int[]|null
	 */
	public ?array $examIds = null;

	// =========================================================================
	// Properties riêng cho sát hạch (isAssessmentSession = true)
	// =========================================================================

	/** ID kỳ sát hạch. NULL khi là ca thi KTHP. */
	public ?int $assessmentId = null;

	/** Tiêu đề kỳ sát hạch. NULL khi là ca thi KTHP. */
	public ?string $assessmentTitle = null;
}