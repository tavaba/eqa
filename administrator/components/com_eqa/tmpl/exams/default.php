<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
if(!empty($this->examseason)){
    echo 'Kỳ thi: <b>'. htmlentities($this->examseason->name) . '</b><br/>';
    echo '(Học kỳ '.$this->examseason->term.'. Năm học '. $this->examseason->academicyear.')';
}
else{
    echo 'Đang hiển thị danh sách các môn thi của <b>tất cả</b> các kỳ thi.';
}
ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
