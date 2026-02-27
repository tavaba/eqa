<?php
defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\ViewHelper;
$cohort = $this->cohort;
echo "<div>Đang hiển thị danh sách của nhóm: <b>{$cohort->code} ({$cohort->name})</b></div>";
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
