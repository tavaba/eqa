<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');

$exam = ExamInfo::cast($this->exam);
$form = $this->form;
?>
<div>
    <?php echo $exam->getHtml(['basic_info_only'=>true]);?>
    <br/>
</div>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="exam_id" value="<?php echo $exam->id;?>">
    <input type="hidden" name="phase" value="getdata">
    <input type="hidden" name="task" value="">
    <?php
    echo HTMLHelper::_('form.token');
    echo $this->form->renderFieldset('addexamexaminees');
    ?>
</form>
