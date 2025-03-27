<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamroomInfo;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');

$examroom = ExamroomInfo::cast($this->examroom);
$form = $this->form;
?>
<div>
    <?php echo $examroom->getHtml(); ?>
    <hr/>
</div>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="examroom_id" value="<?php echo $examroom->id;?>">
    <input type="hidden" name="phase" value="getdata">
    <input type="hidden" name="task" value="">
    <?php
    echo HTMLHelper::_('form.token');
    echo $this->form->renderFieldset('addexamroomexaminees');
    ?>
</form>
