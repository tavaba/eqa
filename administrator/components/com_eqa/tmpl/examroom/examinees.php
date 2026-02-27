<?php

use Kma\Library\Kma\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamroomInfo;

defined('_JEXEC') or die();
$examroom = ExamroomInfo::cast($this->examroom);
?>
<div>
    <?php echo $examroom->getHtml(); ?>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);
