<?php
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
$this->wam->useScript('com_eqa.fixpam');

//Preprocessing
$form = $this->layoutData->form;
?>
<div>
    Lưu ý: Chỉ có thể sửa điểm quá trình trước khi có kết quả thi.
    Mặt khác, việc sửa điểm quá trình có thể làm thay đổi quyền dự thi KTHP
    của HVSV. Cán bộ sửa điểm phải tự cân nhắc việc này, phần mềm không kiểm tra nội dung đó.
</div>
<hr/>
<form action="<?php echo JRoute::_('index.php?option=com_eqa');?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data" class="form-validate">
    <input type="hidden" name="task" value=""/>
	<?php echo JHtml::_('form.token'); ?>
	<?php echo $form->renderFieldset('fixpam'); ?>
</form>
