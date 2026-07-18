<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Site\View\EmployeeClasses\HtmlView $this */

if (!empty($this->errorMessage))
{
	echo '<div class="alert alert-warning">', htmlspecialchars($this->errorMessage), '</div>';
	return;
}

if (!empty($this->employee))
{
	$fullName = trim($this->employee->lastname . ' ' . $this->employee->firstname);
	echo '<div>Giảng viên: <b>', htmlspecialchars($fullName), '</b>';
	if (!empty($this->employee->unit))
		echo ' (', htmlspecialchars($this->employee->unit), ')';
	echo '</div>';
	echo '<div class="text-muted small mb-2">Bấm vào mã lớp để xem danh sách HVSV và kết quả học tập.</div>';
}

ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
