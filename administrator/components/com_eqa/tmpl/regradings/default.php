<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamseasonInfo;
$examseasonName='';
$examseasonPpaaRequestDeadline='';
if(isset($this->examseason))
{
	$examseason = ExamseasonInfo::cast($this->examseason);
	$examseasonName=htmlspecialchars($examseason->name);
	if(!empty($examseason->ppaaRequestDeadline))
		$examseasonPpaaRequestDeadline=htmlspecialchars($examseason->ppaaRequestDeadline);
}
echo "<div>Kỳ thi: <b>$examseasonName</b></div>";
echo "<div>Thời hạn phúc khảo: <b>$examseasonPpaaRequestDeadline</b></div>";
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
