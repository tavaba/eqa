<?php
namespace Kma\Component\Eqa\Administrator\Interface;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

defined('_JEXEC') or die();
class ExamInfo extends ObjectInfo
{
	public int $id;
	public string $name;
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
	public int $countTotal;
	public int $countAllowed;
	public int $countDebtors;
	public int $countExempted;
	public int $countToTake;
	public int $countHavePaperInfo; //Có thông tin về bài thi viết (trong số thí sinh được thi)
	public int $countConcluded;     //Đã có kết quả thi (điểm thi, không được thi,...)


	static public function cast($obj): ExamInfo
	{
		return $obj;
	}
	public function getHtml(array $options=[]): string
	{
		$basicInfomationOnly = false;
		if(is_array($options) && isset($options['basic_info_only']))
			$basicInfomationOnly = (bool)$options['basic_info_only'];

		$html = '';
        $html .= 'Môn thi: <b>' .  htmlentities($this->name) . '</b>';
		$html .= '&nbsp;&nbsp;&nbsp;&nbsp; Hình thức thi: ' . ExamHelper::getTestType($this->testtype) . '<br/>';
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