<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('behavior.formvalidator');
?>
	<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
		<input type="hidden" name="task" value="gradecorrection.reject"/>
		<input type="hidden" name="boxchecked" value="0"/>
		<?php
		echo $this->form->renderFieldset('gradecorrectionreject');
		?>
		<?php echo JHtml::_('form.token');?>
        <div class="control-group">
            <div class="control-label"></div>
            <div class="controls"> <input type="submit" class="btn btn-danger validate" value="Từ chối"></div>
        </div>

	</form>
<?php
