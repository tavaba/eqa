<?php
namespace Kma\Component\Eqa\Administrator\DataObject;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

defined('_JEXEC') or die();
class ExamInfo extends ObjectInfo
{
	public int $id;
	public int $campusId;

	/**
	 * Tên chính thức của môn thi (cột #__eqa_exams.name).
	 * Dùng cho mọi hồ sơ/biểu mẫu thi xuất ra và giao diện frontend.
	 */
	public string $name;

	/**
	 * Tên phân biệt của môn thi: COALESCE(display_name, name).
	 * Chỉ dùng cho giao diện quản trị, giúp phân biệt các môn thi trùng tên
	 * chính thức nhưng khác mã, khác nội dung.
	 *
	 * @since 2.1.7
	 */
	public string $displayName;

	public string|null $code;
	public int|null $credits;
	public string $academicyear;
	public int $term;
	public int $examseasonId;
	public string $examseason;
	public int $testtype;
	public int|null $duration;
	public int $attempt;
	public bool $useTestBank;
	public int $status;
	public int $countTotal;         //Tổng số thí sinh
	public int $countAllowed;       //Tổng số thí sinh được thi (ở lớp học phần)
	public int $countDebtors;       //Tổng số thí sinh nợ phí
	public int $countExempted;      //Tổng số thí sinh không phải thi
	public int $countToTake;        //Tổng số thí sinh sẽ dự thi
	public int $countHavePaperInfo; //Có thông tin về bài thi viết (trong số thí sinh được thi)
	public int $countConcluded;     //Đã có kết quả thi (điểm thi, không được thi,...)

	public function getHtml(array $options=[]): string
	{
		$basicInfomationOnly = false;
		if(is_array($options) && isset($options['basic_info_only']))
			$basicInfomationOnly = (bool)$options['basic_info_only'];

		$html = '';
		//getHtml() chỉ phục vụ giao diện quản trị nên dùng tên phân biệt (2.1.7)
        $html .= 'Môn thi: <b>' .  htmlentities($this->displayName) . '</b>';
		$html .= '&nbsp;&nbsp;&nbsp;&nbsp; Hình thức thi: ' . TestType::from($this->testtype)->getLabel() . '<br/>';
		$html .= 'Kỳ thi: ' . $this->examseason .'<br/>';
		$html .= '(Học kỳ ' . $this->term . 'Năm học ' . $this->academicyear;
		if($basicInfomationOnly)
			return $html;

		$html .= '<br/>';
		$html .= 'Tổng số thí sinh: ' . $this->countTotal . '<br/>';
		$html .= 'Đủ điều kiện dự thi: ' . $this->countToTake;
		$html .= '&nbsp;&nbsp;&nbsp;&nbsp; Miễn thi: ' . $this->countExempted;
		$html .= '&nbsp;&nbsp;&nbsp;&nbsp; Trượt quá trình: ' . $this->countTotal - $this->countAllowed;
		$html .= '&nbsp;&nbsp;&nbsp;&nbsp; Nợ học phí: ' . $this->countDebtors;
		return $html;
	}
}