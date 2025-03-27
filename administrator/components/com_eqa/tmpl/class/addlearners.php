<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$class = $this->class;
echo '<div>';
{
    echo Text::_('COM_EQA_CLASS_CODE'), ': ', $class->code, '<br/>';
    echo Text::_('COM_EQA_CLASS_NAME'), ': ', htmlentities($class->name), '<br/>';
    echo Text::_('COM_EQA_CLASS_SIZE'), ': ', $class->size, '<br/>';
    echo Text::_('COM_EQA_LECTURER'), ': ';
    if(is_numeric($class->lecturer_id))
        echo EmployeeHelper::getFullName($class->lecturer_id);
    echo '<br/>';
}
echo '</div>';
?>
    <form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="class_id" value="<?php echo $class->id;?>">
        <input type="hidden" name="phase" value="getdata">
        <?php
        echo $this->form->renderFieldset('addlearners');
        ?>
        <?php echo JHtml::_('form.token');?>
    </form>
<?php
