<?php
namespace Kma\Component\Eqa\Administrator\Interface;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;

defined('_JEXEC') or die();
class ExamroomInfo extends ObjectInfo
{
	public int $id;
	public string $name;
	public string $building;
	public string|null $academicyear;
	public int|null $term;
	public string|null $examseason;
	public string|null $examsession;
	public int|null $examsessionId;
	public int|null $testtype;
	public int|null $testDuration;
	public string|null $examTime;
	public int|null $examineeCount;
	public array|null $examIds;
	public array|null $exams;
	public int|null $attempt;
	public int|null $monitor1Id;
	public int|null $monitor2Id;
	public int|null $monitor3Id;
	public int|null $examiner1Id;
	public int|null $examiner2Id;

	static public function cast($obj): ExamroomInfo
	{
		return $obj;
	}
	public function getHtml(array $options=[]): string
	{
		$html = '';
		$html .= "Năm học $this->academicyear - Học kỳ $this->term<br/>";
		$html .= "Kỳ thi: $this->examseason<br/>";
		$dayOfWeek = DatetimeHelper::getDayOfWeek($this->examTime);
		$html .= "Ca thi: $this->examsession ($dayOfWeek, $this->examTime)<br/>";
		$html .= "Phòng thi: <b>$this->name</b> (Tòa nhà: $this->building) &nbsp;&nbsp;&nbsp;&nbsp; Số thí sinh: $this->examineeCount <br/>";
		$html .= "Môn thi: ";
		if(empty($this->exams))
			$html .= '</br>';
		elseif(sizeof($this->exams)==1)
			$html .= '<b>' . $this->exams[0] . '</b><br/>';
		else
		{
			$html .= '<br/>';
			$html .= '<ol>';
			foreach ($this->exams as $exam)
				$html .= "<li>$exam</li>";
			$html .= '</ol>';
		}



		return $html;
	}
}