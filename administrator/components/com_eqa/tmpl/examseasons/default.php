<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
?>
<div>
	Một số lưu ý:
	<ol>
		<li>Trước khi <span style="font-weight: bold; color: red">xuất thống kê sản lượng</span>
			cần kiểm tra lại <b>Cấu hình tham số</b>, trong đó có Hệ số coi thi cuối tuần (ở mục
			cấu hình hệ thống), hệ số coi thi, hệ số chấm thi ở các môn thi.</li>
		<li>Việc phúc khảo chỉ có hiệu lực khi thỏa mãn đồng thời 2 điều kiện: 1) Quyền gửi yêu cầu
			phúc khảo được mở và 2) Chưa quá thời hạn phúc khảo hoặc thời hạn phúc khảo không xác định</li>
	</ol>
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
