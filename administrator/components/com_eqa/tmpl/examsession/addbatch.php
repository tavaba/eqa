<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
?>
    <form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
        <input type="hidden" name="task" value=""/>
        <?php
        echo $this->form->renderFieldset('examsessions');
        ?>
        <?php echo JHtml::_('form.token');?>
    </form>
<?php
