<?php
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Library\Kma\Helper\ViewHelper;

$class = $this->class;
if(empty($class))
    return;
echo '<div>';
{
    echo Text::_('COM_EQA_CLASS_CODE'), ': ', $class->code, '<br/>';
    echo Text::_('COM_EQA_CLASS_NAME'), ': ', htmlentities($class->name), '<br/>';
    echo Text::_('COM_EQA_CLASS_SIZE'), ': ', $class->size, '<br/>';
    echo Text::_('COM_EQA_LECTURER'), ': ';
    if(is_numeric($class->lecturer_id))
        echo EmployeeHelper::getFullName($class->lecturer_id);
    echo '<br/>';
}
echo '</div>';
$hiddenFields = [
        'class_id' => $class->id,
        'phase'=> 'getdata'
];
ViewHelper::printForm($this->form,'addlearners', $hiddenFields);
