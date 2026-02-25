<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ViewHelper;
$examseason = $this->examseason;
if(empty($examseason))
{
	echo 'Hãy chọn kỳ thi!';
	return;
}
else
{
	echo '<DIV>Kỳ thi: ', htmlspecialchars($examseason->name), '</DIV>';
}
?>
<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
