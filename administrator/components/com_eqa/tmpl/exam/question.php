<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$form = $this->form;
?>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <input type="hidden" name="task" value="">
    <?php
    echo HTMLHelper::_('form.token');
    echo $this->form->renderFieldset('examquestion');
    ?>
</form>
