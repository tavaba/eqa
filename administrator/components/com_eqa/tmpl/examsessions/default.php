<?php
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\ViewHelper;
if(empty($this->examseason)){
    echo 'Đang hiển thị danh sách ca thi của mọi kỳ thi<br/>';
}
else{
	$examseason = $this->examseason;
    echo 'Kỳ thi: <b>' . $examseason->name . '</b><br/>';
    echo '(Học kỳ ' . $examseason->term . ', Năm học ' . $examseason->academicyear . ')<br/>';
}
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
