<?php
namespace Kma\Component\Eqa\Administrator\Interface;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

defined('_JEXEC') or die();
class ExamseasonInfo extends ObjectInfo
{
	public int $id;
	public string $name;
	public int $term;
	public string $academicyear;
	public int $countTotal;
	public int $countAllowedButDebtor;
	public int $countDebtors;
	public int $countExempted;
	public int $countToTake;


	static public function cast($obj): ExamseasonInfo
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
		if($basicInfomationOnly)
			return $html;

		$html .= '<br/>';
		$html .= 'Tổng số thí sinh: ' . $this->countTotal . '<br/>';
		return $html;
	}
}