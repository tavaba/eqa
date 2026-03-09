<?php

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$examseason = $this->examseason;
?>
<div>
    Thêm môn thi vào kỳ thi <b><?php echo htmlentities($examseason->name);?></b><br/>
    (Học kỳ <?php echo $examseason->term;?>, Năm học <?php echo DatetimeHelper::decodeAcademicYear($examseason->academicyear).')'; ?><br/>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);
