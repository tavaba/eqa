<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('behavior.formvalidator');
?>
	<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="POST" name="adminForm" id="adminForm" class="form-validate" >
		<input type="hidden" name="task" value=""/>
		<?php
		echo $this->form->renderFieldset('masking');
		echo JHtml::_('form.token');
		?>
	</form>
<?php
