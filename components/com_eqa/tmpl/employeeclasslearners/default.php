<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Site\View\EmployeeClassLearners\HtmlView $this */

if (!empty($this->errorMessage))
{
	echo '<div class="alert alert-warning">', htmlspecialchars($this->errorMessage), '</div>';
	return;
}

if (!empty($this->class))
{
	echo '<div>Lớp học phần: <b>', htmlspecialchars($this->class->code), '</b></div>';
	if (!empty($this->class->academicyear))
	{
		echo '<div class="text-muted small mb-2">Học kỳ ', (int) $this->class->term,
			', năm học ', htmlspecialchars(DatetimeHelper::decodeAcademicYear($this->class->academicyear)),
			'</div>';
	}
}
?>
<div class="text-muted small mb-2">
	Kết quả thi hiển thị theo <b>lần thi cuối cùng</b> đã có kết luận của mỗi HVSV.
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
