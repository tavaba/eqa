<?php

namespace Kma\Component\Eqa\Administrator\DataObject;

use Kma\Library\Kma\Helper\DatetimeHelper;

defined('_JEXEC') or die();

/**
 * Data object chứa thông tin hiển thị của một phòng thi.
 *
 * Phòng thi có thể thuộc một trong hai loại:
 *   - KTHP (isAssessmentRoom = false): liên kết với kỳ thi (examseason)
 *     thông qua ca thi (examsession). Header hiển thị năm học, học kỳ,
 *     kỳ thi, môn thi.
 *   - Sát hạch (isAssessmentRoom = true): liên kết với kỳ sát hạch
 *     (assessment). Header hiển thị tên kỳ sát hạch.
 *
 * @since 1.0
 */
class ExamroomInfo extends ObjectInfo
{
	// =========================================================================
	// Properties dùng chung (cả KTHP và sát hạch)
	// =========================================================================

	public int $id;

	/** Tên phòng thi (mã phòng vật lý, ví dụ: "A101"). */
	public string $name;

	/** Tên/mã tòa nhà. */
	public string $building;

	/** Thời điểm bắt đầu ca thi (UTC, dạng string). */
	public string|null $examTime;

	/** Tên ca thi (ví dụ: "Ca thi 07:00 - 09:00"). */
	public string|null $examsession;

	/** ID ca thi. */
	public int|null $examsessionId;

	/** Số thí sinh trong phòng thi. */
	public int|null $examineeCount;

	/** ID cán bộ coi thi 1, 2, 3. */
	public int|null $monitor1Id;
	public int|null $monitor2Id;
	public int|null $monitor3Id;

	/** ID cán bộ chấm thi 1, 2. */
	public int|null $examiner1Id;
	public int|null $examiner2Id;

	/** Hình thức thi (TestType enum value). */
	public int|null $testtype;

	/** Thời gian làm bài (phút). */
	public int|null $testDuration;

	/**
	 * Phòng thi này thuộc kỳ sát hạch (true) hay KTHP (false).
	 * Được xác định bởi DatabaseHelper::getExamroomInfo().
	 */
	public bool $isAssessmentRoom = false;

	// =========================================================================
	// Properties riêng cho KTHP (isAssessmentRoom = false)
	// =========================================================================

	/** Năm học dạng chuỗi "YYYY-YYYY". */
	public string|null $academicyear;

	/** Học kỳ. */
	public int|null $term;

	/** Tên kỳ thi (examseason). */
	public string|null $examseason;

	/** Lần thi (attempt). */
	public int|null $attempt;

	/** Danh sách ID môn thi. */
	public array|null $examIds;

	/** Danh sách tên môn thi. */
	public array|null $exams;

	// =========================================================================
	// Properties riêng cho sát hạch (isAssessmentRoom = true)
	// =========================================================================

	/** ID kỳ sát hạch. */
	public int|null $assessmentId;

	/** Tiêu đề kỳ sát hạch. */
	public string|null $assessmentTitle;

	// =========================================================================
	// getHtml()
	// =========================================================================

	/**
	 * Trả về HTML mô tả thông tin phòng thi để hiển thị làm header.
	 *
	 * Tự động chọn template phù hợp dựa trên $this->isAssessmentRoom.
	 *
	 * @param  array  $options  Tùy chọn mở rộng (hiện chưa dùng).
	 *
	 * @return string  HTML string.
	 * @since 1.0
	 */
	public function getHtml(array $options = []): string
	{
		return $this->isAssessmentRoom
			? $this->getHtmlForAssessment()
			: $this->getHtmlForExam();
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * HTML header cho phòng thi KTHP.
	 *
	 * @return string
	 * @since 1.0
	 */
	private function getHtmlForExam(): string
	{
		$dayOfWeek = DatetimeHelper::getDayOfWeek($this->examTime);
		$examTime  = DatetimeHelper::getDayAndTime($this->examTime);

		$html  = "Năm học {$this->academicyear} - Học kỳ {$this->term}<br/>";
		$html .= "Kỳ thi: {$this->examseason}<br/>";
		$html .= "Ca thi: {$this->examsession} ({$dayOfWeek}, {$examTime})<br/>";
		$html .= "Phòng thi: <b>{$this->name}</b> (Tòa nhà: {$this->building})"
			. "&nbsp;&nbsp;&nbsp;&nbsp; Số thí sinh: {$this->examineeCount}<br/>";
		$html .= 'Môn thi: ';

		if (empty($this->exams)) {
			$html .= '<br/>';
		} elseif (count($this->exams) === 1) {
			$html .= '<b>' . $this->exams[0] . '</b><br/>';
		} else {
			$html .= '<br/><ol>';
			foreach ($this->exams as $exam) {
				$html .= "<li>{$exam}</li>";
			}
			$html .= '</ol>';
		}

		return $html;
	}

	/**
	 * HTML header cho phòng thi sát hạch.
	 *
	 * @return string
	 * @since 2.0.6
	 */
	private function getHtmlForAssessment(): string
	{
		$dayOfWeek = DatetimeHelper::getDayOfWeek($this->examTime);
		$examTime  = DatetimeHelper::getDayAndTime($this->examTime);

		$html  = "Kỳ sát hạch: <b>{$this->assessmentTitle}</b><br/>";
		$html .= "Ca thi: {$this->examsession} ({$dayOfWeek}, {$examTime})<br/>";
		$html .= "Phòng thi: <b>{$this->name}</b> (Tòa nhà: {$this->building})"
			. "&nbsp;&nbsp;&nbsp;&nbsp; Số thí sinh: {$this->examineeCount}<br/>";

		return $html;
	}
}