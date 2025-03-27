<?php

use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$urlSample = JUri::root().'media/com_eqa/xlsx/sample_mmp.xlsx';
?>
<div>
	Trên hệ thống iTest, truy cập chức năng "Phân công chấm tự luận", sử dụng chuột để chọn
	và sao chép nội dung phân công chấm; dán vào một tập tin Excel. Kết quả như tập tin mẫu
	sau đây: <a href="<?php echo $urlSample;?>">Phân công chấm tự luận</a>.
	<hr/>
</div>
<form action="" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">
	<?php echo JHtml::_('form.token'); ?>
	<input type="hidden" name="task">
	<?php
	if(!empty($this->form))
		echo $this->form->renderFieldset('upload');
	?>

</form>
