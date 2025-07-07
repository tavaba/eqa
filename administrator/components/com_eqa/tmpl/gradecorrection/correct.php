<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('behavior.formvalidator');
?>
	<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
		<input type="hidden" name="task" value="gradecorrection.correct"/>
		<input type="hidden" name="boxchecked" value="0"/>
		<?php
		echo $this->form->renderFieldset('gradecorrection');
		?>
		<?php echo JHtml::_('form.token');?>
        <div class="control-group">
            <div class="control-label"></div>
            <div class="controls"> <input type="submit" class="btn btn-success validate" value="Lưu"></div>
        </div>

	</form>
<?php
