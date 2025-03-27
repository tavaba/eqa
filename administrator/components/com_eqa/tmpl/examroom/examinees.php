<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamroomInfo;

defined('_JEXEC') or die();
$examroom = ExamroomInfo::cast($this->examroom);
?>
<div>
    <?php echo $examroom->getHtml(); ?>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);
