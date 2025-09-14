<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$examseason = $this->examseason;
?>
<div>
    Thêm môn thi vào kỳ thi <b><?php echo htmlentities($examseason->name);?></b><br/>
    (Học kỳ <?php echo $examseason->term;?>, Năm học <?php echo htmlentities(DatabaseHelper::getAcademicyearCode($examseason->academicyear_id));?>)<br/>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);
