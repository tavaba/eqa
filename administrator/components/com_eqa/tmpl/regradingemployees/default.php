<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$examseason = $this->examseason;
?>
<div>
    <h3>Kỳ thi: <?php echo $examseason->name; ?></h3>
</div>
<div>&nbsp;</div>
<?php
if(empty($this->layoutData->items)) {
	echo '<p>Không có bài thi nào để phân công chấm. Hãy kiểm tra và đảm bảo 
          rằng yêu cầu phúc khảo của thí sinh đã được chấp nhận.</p>';
}
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
