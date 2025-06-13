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
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
