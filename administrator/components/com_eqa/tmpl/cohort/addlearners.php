<?php

use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$cohort = $this->item;
?>
    <form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="cohort_id" value="<?php echo $cohort->id;?>">
        <?php
        echo $this->form->renderFieldset('basic');
        ?>
        <?php echo JHtml::_('form.token');?>
    </form>
<?php
