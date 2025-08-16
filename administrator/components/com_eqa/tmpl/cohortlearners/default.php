<?php
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$cohort = $this->cohort;
echo "<div>Đang hiển thị danh sách của nhóm: <b>{$cohort->code} ({$cohort->name})</b></div>";
$view = ViewHelper::castToEqaItemsHtmlView($this);
ViewHelper::printItemsDefaultLayout($view->getLayoutData(), $view->getListLayoutItemFields());
