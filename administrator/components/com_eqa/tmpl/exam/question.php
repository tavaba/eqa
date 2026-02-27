<?php

use Joomla\CMS\HTML\HTMLHelper;

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
