<?php

use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Site\Helper\ViewHelper;

defined('_JEXEC') or die();

if(empty($this->learner))
{
	echo 'Cần đăng nhập bằng tài khoản HVSV để xem nội dung trang này';
	return;
}
?>

<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);

//Hiển thị thông tin tổng hợp
//Nếu không có môn nào thì bỏ qua
$items = $this->layoutData->items;
if (empty($items))
	return;
echo '<div>';
{
	$totalWorks = sizeof($items);
	$totalCredits = 0;
	$unpaidCredits = 0;
	$unpaidWorks = 0;
	foreach ($items as $item)
	{
		$totalCredits += $item->credits;
		if($item->status == ExamHelper::EXAM_PPAA_STATUS_INIT)
		{
			$unpaidCredits += $item->credits;
			$unpaidWorks++;
		}
	}

	echo 'Tổng số yêu cầu phúc khảo: ', $totalWorks, ' (môn)<br/>';
	$feeMode = ConfigHelper::getRegradingFeeMode();
	$feeRate = ConfigHelper::getRegradingFeeRate();

	//Hiển thị phí phúc khảo trong trường hợp tính phí theo môn
	if($feeMode == ExamHelper::REGRADING_FEE_MODE_BY_WORK)
	{
		echo 'Tổng phí phúc khảo: ', number_format($totalWorks*$feeRate, 0, ',', '.') , ' (vnđ)<br/>';
		echo 'Phí cần nộp (chưa nộp): ', number_format($unpaidWorks*$feeRate, 0, ',', '.') , ' (vnđ)<br/>';
	}

	//Hiển thị phí phúc khảo trong trường hợp tính phí theo môn
	else if($feeMode == ExamHelper::REGRADING_FEE_MODE_BY_CREDIT)
	{
		echo 'Tổng số tín chỉ: ', $totalCredits, '<br/>';
		echo 'Tổng phí phúc khảo: ', number_format($totalCredits*$feeRate, 0, ',', '.'), ' (vnđ)';
		echo 'Phí cần nộp (chưa nộp): ', number_format($unpaidCredits*$feeRate, 0, ',', '.'), ' (vnđ)';
	}
}
echo '</div>';
