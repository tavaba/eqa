<?php
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;
$class = $this->class;
if(!empty($class)){
    echo Text::_('COM_EQA_CLASS_CODE') . ': ' . $class->code . '<br/>';
    echo Text::_('COM_EQA_CLASS_NAME') . ': <b>' . htmlspecialchars($class->name) . '</b><br/>';
    echo Text::_('COM_EQA_CLASS_SIZE') . ': ' . $class->size . '<br/>';
    echo Text::_('COM_EQA_LECTURER')  .  ': ';
    if(is_numeric($class->lecturerId))
        echo EmployeeHelper::getFullName($class->lecturerId);
    echo '<br/>';
}

$view = ViewHelper::castToEqaItemsHtmlView($this);
ViewHelper::printItemsDefaultLayout($view->getLayoutData(), $view->getListLayoutItemFields());
