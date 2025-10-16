<?php
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$learner = $this->item;
$fullname = implode(' ', [$learner->lastname,$learner->firstname]);
$ecscapedFullname = htmlspecialchars($fullname);
$classInfo = DatabaseHelper::getClassInfo($this->item->classId);
$ecscapedClassName = htmlspecialchars($classInfo->name);
echo "Học viên/sinh viên: <b>{$learner->learnerCode} - {$ecscapedFullname}</b><br/>";
echo "Lớp học phần: <b>{$ecscapedClassName}</b><br/>";
echo "<hr/>";
ViewHelper::printForm($this->form, 'basic','index.php?option=com_eqa',[]);