<?php
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\DataObject\ExamseasonInfo;

/**
 * @var \Kma\Component\Eqa\Administrator\View\Gradecorrections\HtmlView $this
 */

$examseasonName='';
$examseasonPpaaRequestDeadline='';
if(isset($this->examseason))
{
	$examseason = $this->examseason;
	$examseasonName=htmlspecialchars($examseason->name);
	if(!empty($examseason->ppaaRequestDeadline))
		$examseasonPpaaRequestDeadline=htmlspecialchars($examseason->ppaaRequestDeadline);
}
echo "<div>Kỳ thi: <b>$examseasonName</b></div>";
echo "<div>Thời hạn phúc khảo: <b>$examseasonPpaaRequestDeadline</b></div>";
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
