<?php
namespace Kma\Component\Eqa\Administrator\DataObject;
defined('_JEXEC') or die();
class PackageInfo extends ObjectInfo
{
	public int $id;
	public int $number;
	public int $firstMask;
	public int $paperCount;
	public int $sheetCount;
	public int $firstExaminerId;
	public string $firstExaminerFullname;
	public int $secondExaminerId;
	public string $secondExaminerFullname;
	public int $examId;
	public string $examName;
	public string $examseasonName;
	public int $term;
	public string $academicyearCode;

	public function getHtml(array $options=[]): string
	{
		$html = 'Kỳ thi: ' . $this->examseasonName . '<br/>'
			. 'Môn thi: <b>' . $this->examName . '</b><br/>'
			. 'Túi số: ' . $this->number
			. '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Số bài: ' . $this->paperCount
			. '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Số tờ: ' . $this->sheetCount;
		return $html;
	}
}