<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Site\View\EmployeeMarkings\HtmlView $this */

if (!empty($this->errorMessage))
{
	echo '<div class="alert alert-warning">', htmlspecialchars($this->errorMessage), '</div>';
	return;
}

if (!empty($this->employee))
{
	$fullName = trim($this->employee->lastname . ' ' . $this->employee->firstname);
	echo '<div>Cán bộ: <b>', htmlspecialchars($fullName), '</b>';
	if (!empty($this->employee->unit))
		echo ' (', htmlspecialchars($this->employee->unit), ')';
	echo '</div>';
}
if (!empty($this->examseason))
	echo '<div>Kỳ thi: <b>', htmlspecialchars($this->examseason->name), '</b></div>';
?>
<div class="text-muted small mb-2">
	Bấm vào tên môn thi để xem chi tiết kết quả chấm.
	Chỉ có thể xem chi tiết khi môn thi đã ở trạng thái <b>Đã có đủ điểm thi</b> trở lên.
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
