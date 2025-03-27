<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$examseason = $this->examseason;
if(empty($examseason)){
    echo 'Đang hiển thị danh sách ca thi của mọi kỳ thi<br/>';
}
else{
    echo 'Kỳ thi: <b>' . $examseason->name . '</b><br/>';
    echo '(Học kỳ ' . $examseason->term . ', Năm học ' . $examseason->academicyear . ')<br/>';
}
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
