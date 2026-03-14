<?php

use Kma\Library\Kma\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\DataObject\ExamroomInfo;

defined('_JEXEC') or die();
/**
 * @var \Kma\Component\Eqa\Administrator\View\Examroom\HtmlView $this
 */
$examroom = $this->examroom;
?>
<div>
    <?php echo $examroom->getHtml(); ?>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);
