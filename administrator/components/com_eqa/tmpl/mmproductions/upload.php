<?php

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\ViewHelper;

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
<?php
ViewHelper::printUploadForm($this->form);
